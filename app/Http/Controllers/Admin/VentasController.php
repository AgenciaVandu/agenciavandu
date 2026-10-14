<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\EmbudoEtapa;
use App\Models\Prospecto;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Ventas: prospectos en un embudo con etapas propias, lo que vale cada uno, y qué se gana y qué se pierde */
class VentasController extends Controller
{
    public const VISTAS = ['embudo' => 'Embudo', 'ganados' => 'Ganados', 'perdidos' => 'Perdidos', 'resultados' => 'Resultados'];
    public const PERIODOS = ['mes' => 'Este mes', 'trimestre' => 'Últimos 3 meses', 'anio' => 'Este año', 'todo' => 'Todo'];

    public function index(Request $request)
    {
        $vista = array_key_exists($request->query('ver'), self::VISTAS) ? $request->query('ver') : 'embudo';
        $periodo = array_key_exists($request->query('periodo'), self::PERIODOS) ? $request->query('periodo') : 'trimestre';
        $desde = $this->desde($periodo);
        $q = trim((string) $request->query('q'));

        $etapas = EmbudoEtapa::todas();
        $abiertas = $etapas->where('tipo', 'abierta')->values();
        $todos = Prospecto::with(['etapa', 'responsable', 'tareas' => fn ($t) => $t->abiertas()->orderByRaw('fecha_limite is null')->orderBy('fecha_limite')->orderBy('hora')])
            ->when($q, fn ($w) => $w->where(fn ($x) => $x->where('nombre', 'like', "%$q%")->orWhere('empresa', 'like', "%$q%")->orWhere('interes', 'like', "%$q%")))
            ->latest('etapa_at')->get();

        $enEmbudo = $todos->filter(fn ($p) => $p->etapa?->tipo === 'abierta');
        $cerradosPeriodo = $todos->filter(fn ($p) => $p->cerrado_at && (! $desde || $p->cerrado_at->gte($desde)));
        $ganados = $cerradosPeriodo->filter(fn ($p) => $p->etapa?->tipo === 'ganada')->sortByDesc('cerrado_at')->values();
        $perdidos = $cerradosPeriodo->filter(fn ($p) => $p->etapa?->tipo === 'perdida')->sortByDesc('cerrado_at')->values();

        $kpi = [
            'embudo'     => round($enEmbudo->sum('valor'), 2),
            'nEmbudo'    => $enEmbudo->count(),
            'pronostico' => round($enEmbudo->sum(fn ($p) => $p->ponderado), 2),
            'ganado'     => round($ganados->sum('valor'), 2),
            'nGanados'   => $ganados->count(),
            'perdido'    => round($perdidos->sum('valor'), 2),
            'nPerdidos'  => $perdidos->count(),
            'conversion' => ($ganados->count() + $perdidos->count()) ? round($ganados->count() / ($ganados->count() + $perdidos->count()) * 100) : null,
            'nuevos'     => $enEmbudo->where('nuevo', true)->count(),
            'sinValor'   => $enEmbudo->filter(fn ($p) => ! $p->valor)->count(),
        ];

        return view('admin.ventas.index', [
            'vista'     => $vista,
            'periodo'   => $periodo,
            'q'         => $q,
            'etapas'    => $etapas,
            'abiertas'  => $abiertas,
            'columnas'  => $abiertas->map(fn ($e) => ['etapa' => $e, 'prospectos' => $enEmbudo->where('etapa_id', $e->id)->values()]),
            'ganados'   => $ganados,
            'perdidos'  => $perdidos,
            'kpi'       => $kpi,
            'resultados' => $vista === 'resultados' ? $this->resultados($desde, $abiertas) : null,
        ]);
    }

    public function create(Request $request)
    {
        return $this->formulario(new Prospecto([
            'origen'         => 'manual',
            'etapa_id'       => $request->integer('etapa') ?: EmbudoEtapa::primera()->id,
            'responsable_id' => $request->user()->id,
        ]));
    }

    public function store(Request $request)
    {
        $p = Prospecto::create($this->validar($request));
        $p->eventos()->create(['tipo' => 'alta', 'a_etapa_id' => $p->etapa_id, 'user_id' => $request->user()->id, 'texto' => 'Lo dio de alta ' . $request->user()->primer_nombre]);
        return redirect()->route('admin.ventas.show', $p)->with('ok', 'Prospecto guardado.');
    }

    public function show(Request $request, Prospecto $prospecto)
    {
        if ($prospecto->nuevo) $prospecto->forceFill(['nuevo' => false])->saveQuietly();
        $prospecto->load(['etapa', 'responsable', 'cliente', 'presupuesto.conceptos', 'eventos.user', 'eventos.de', 'eventos.a',
            'tareas' => fn ($t) => $t->with('responsable')->orderByRaw("estado = 'terminada'")->orderByRaw('fecha_limite is null')->orderBy('fecha_limite')->orderBy('hora')]);
        return view('admin.ventas.show', [
            'p'      => $prospecto,
            'etapas' => EmbudoEtapa::todas(),
            'equipo' => User::where('activo', true)->orderBy('name')->get(['id', 'name']),
            'cotizaciones' => \App\Models\Presupuesto::with('conceptos')
                ->where(fn ($q) => $q->where('prospecto_id', $prospecto->id)->when($prospecto->cliente_id, fn ($q) => $q->orWhere('cliente_id', $prospecto->cliente_id)))
                ->latest('id')->get(),
        ]);
    }

    public function edit(Prospecto $prospecto)
    {
        return $this->formulario($prospecto);
    }

    public function update(Request $request, Prospecto $prospecto)
    {
        $d = $this->validar($request, $prospecto);
        $etapa = EmbudoEtapa::find($d['etapa_id']);
        unset($d['etapa_id']);
        $prospecto->update($d);
        if ($etapa && $etapa->id !== $prospecto->etapa_id) $prospecto->moverA($etapa, $request->user()->id);
        return redirect()->route('admin.ventas.show', $prospecto)->with('ok', 'Cambios guardados.');
    }

    public function destroy(Prospecto $prospecto)
    {
        $prospecto->delete();
        return redirect()->route('admin.ventas.index')->with('ok', 'Prospecto eliminado.');
    }

    /** Cambia de etapa (desde el tablero arrastrando, o desde su ficha). Al perder pide el motivo */
    public function etapa(Request $request, Prospecto $prospecto)
    {
        $d = $request->validate([
            'etapa_id' => ['required', Rule::exists('embudo_etapas', 'id')->where('cuenta_id', \App\Support\Cuentas::id())],
            'motivo'   => ['nullable', 'string', 'max:60'],
            'nota'     => ['nullable', 'string', 'max:500'],
            'valor'    => ['nullable', 'numeric', 'min:0'],
        ]);
        $etapa = EmbudoEtapa::findOrFail($d['etapa_id']);
        if ($etapa->tipo === 'ganada' && isset($d['valor']) && $d['valor'] !== '') $prospecto->update(['valor' => $d['valor']]);
        $prospecto->moverA($etapa, $request->user()->id, $d['motivo'] ?? null, $d['nota'] ?? null);

        $msg = match ($etapa->tipo) {
            'ganada'  => '¡Ganado! ' . $prospecto->quien . ' se cerró' . ($prospecto->valor ? ' por $' . number_format($prospecto->valor, 0) : '') . '.',
            'perdida' => $prospecto->quien . ' quedó como perdido (' . $prospecto->motivo_perdida . ').',
            default   => $prospecto->quien . ' pasó a ' . $etapa->nombre . '.',
        };
        if ($request->wantsJson()) return response()->json(['ok' => true, 'mensaje' => $msg]);
        return back()->with('ok', $msg);
    }

    public function nota(Request $request, Prospecto $prospecto)
    {
        $d = $request->validate(['texto' => 'required|string|max:3000'], ['texto.required' => 'Escribe la nota.']);
        $prospecto->eventos()->create(['tipo' => 'nota', 'user_id' => $request->user()->id, 'texto' => $d['texto']]);
        return back()->with('ok', 'Nota guardada.');
    }

    /** Lo vuelve cliente (sus cotizaciones de prospecto pasan a su ficha de cliente) */
    public function convertir(Request $request, Prospecto $prospecto)
    {
        $yaEra = (bool) $prospecto->cliente_id;
        $cliente = $prospecto->volverCliente($request->user()->id);
        return redirect()->route('admin.ventas.show', $prospecto)
            ->with('ok', ($cliente->empresa ?: $cliente->nombre) . ($yaEra ? ' ya era cliente.' : ' ya es cliente.'));
    }

    /** Agenda una tarea de seguimiento (llamar, mandar propuesta, reunión) con fecha y hora */
    public function seguimiento(Request $request, Prospecto $prospecto)
    {
        $d = $request->validate([
            'titulo'       => 'required|string|max:160',
            'fecha_limite' => 'nullable|date',
            'hora'         => 'nullable|date_format:H:i',
            'asignada_a'   => ['nullable', Rule::exists('users', 'id')->where('activo', true)->where('cuenta_id', \App\Support\Cuentas::id())],
        ], ['titulo.required' => 'Escribe qué hay que hacer.']);
        $yo = $request->user();
        $t = Tarea::create($d + [
            'prospecto_id' => $prospecto->id, 'cliente_id' => $prospecto->cliente_id,
            'asignada_a'   => $d['asignada_a'] ?? $yo->id, 'creada_por' => $yo->id, 'estado' => 'pendiente',
        ]);
        if ($t->asignada_a !== $yo->id) {
            \App\Support\Push\Notificar::aUsuarios([$t->asignada_a], 'tarea_asignada', "{$yo->primer_nombre} te asignó: {$t->titulo}", $prospecto->quien . ($t->cuando ? ' · ' . $t->cuando : ''), route('admin.tareas.show', $t), 'tarea-' . $t->id);
        }
        return back()->with('ok', 'Seguimiento agendado' . ($t->cuando ? ' para ' . mb_strtolower($t->cuando) : '') . '.');
    }

    /* ---------------- Etapas del embudo ---------------- */

    public function etapas()
    {
        return view('admin.ventas.etapas', ['etapas' => EmbudoEtapa::todas()->loadCount('prospectos')]);
    }

    public function guardarEtapas(Request $request)
    {
        $d = $request->validate([
            'etapas'                => 'required|array|min:1|max:15',
            'etapas.*.id'           => 'nullable|integer',
            'etapas.*.nombre'       => 'required|string|max:60',
            'etapas.*.probabilidad' => 'required|integer|min:0|max:100',
            'ganada'                => 'required|string|max:60',
            'perdida'               => 'required|string|max:60',
        ], ['etapas.*.nombre.required' => 'Cada etapa necesita un nombre.', 'etapas.min' => 'Deja al menos una etapa en proceso.']);

        $actuales = EmbudoEtapa::todas();
        DB::transaction(function () use ($d, $actuales) {
            $quedan = [];
            foreach (array_values($d['etapas']) as $i => $e) {
                $m = ! empty($e['id']) ? $actuales->where('tipo', 'abierta')->firstWhere('id', (int) $e['id']) : null;
                $m ??= new EmbudoEtapa(['tipo' => 'abierta']);
                $m->fill(['nombre' => trim($e['nombre']), 'probabilidad' => (int) $e['probabilidad'], 'orden' => $i])->save();
                $quedan[] = $m->id;
            }
            $n = count($quedan);
            $actuales->firstWhere('tipo', 'ganada')->update(['nombre' => trim($d['ganada']), 'orden' => $n, 'probabilidad' => 100]);
            $actuales->firstWhere('tipo', 'perdida')->update(['nombre' => trim($d['perdida']), 'orden' => $n + 1, 'probabilidad' => 0]);

            // Las etapas quitadas: sus prospectos pasan a la primera etapa
            $primera = $quedan[0];
            foreach ($actuales->where('tipo', 'abierta')->whereNotIn('id', $quedan) as $vieja) {
                Prospecto::where('etapa_id', $vieja->id)->update(['etapa_id' => $primera]);
                $vieja->delete();
            }
        });
        return redirect()->route('admin.ventas.etapas')->with('ok', 'Embudo guardado.');
    }

    /* ---------------- Apoyo ---------------- */

    private function formulario(Prospecto $p)
    {
        return view('admin.ventas.form', [
            'p'       => $p,
            'etapas'  => EmbudoEtapa::todas(),
            'equipo'  => User::where('activo', true)->orderBy('name')->get(['id', 'name']),
            'servicios' => collect(config('vandu.proyectos'))->pluck('nombre')->values(),
        ]);
    }

    private function validar(Request $request, ?Prospecto $p = null): array
    {
        $d = $request->validate([
            'nombre'         => 'required|string|max:160',
            'empresa'        => 'nullable|string|max:160',
            'email'          => 'nullable|email|max:160',
            'telefono'       => 'nullable|string|max:40',
            'origen'         => ['required', Rule::in(array_keys(Prospecto::ORIGENES))],
            'interes'        => 'nullable|string|max:160',
            'valor'          => 'nullable|numeric|min:0|max:999999999',
            'etapa_id'       => ['required', Rule::exists('embudo_etapas', 'id')->where('cuenta_id', \App\Support\Cuentas::id())],
            'responsable_id' => ['nullable', Rule::exists('users', 'id')->where('cuenta_id', \App\Support\Cuentas::id())],
            'notas'          => 'nullable|string|max:5000',
        ], ['nombre.required' => 'Escribe el nombre del prospecto.', 'email.email' => 'Revisa el correo.']);
        $d['valor'] = ($d['valor'] ?? '') === '' ? null : (float) $d['valor'];
        return $d;
    }

    private function desde(string $periodo): ?Carbon
    {
        $hoy = now(config('vandu.zona_horaria'));
        return match ($periodo) {
            'mes'       => $hoy->copy()->startOfMonth(),
            'trimestre' => $hoy->copy()->subMonthsNoOverflow(2)->startOfMonth(),
            'anio'      => $hoy->copy()->startOfYear(),
            default     => null,
        };
    }

    /**
     * Cómo avanza el embudo: de los prospectos que entraron en el periodo, cuántos llegaron a cada etapa,
     * en qué etapa se murieron los perdidos, y por qué.
     */
    private function resultados(?Carbon $desde, Collection $abiertas): array
    {
        $prospectos = Prospecto::with(['etapa', 'eventos'])->when($desde, fn ($q) => $q->where('created_at', '>=', $desde))->get();
        $orden = $abiertas->pluck('orden', 'id');
        $ultima = $abiertas->count();

        // Hasta qué paso llegó cada uno (por su historial); los ganados cuentan como que pasaron todo
        $alcance = $prospectos->mapWithKeys(function ($p) use ($orden, $abiertas, $ultima) {
            if ($p->etapa?->tipo === 'ganada') return [$p->id => $ultima];
            $pasos = $p->eventos->pluck('a_etapa_id')->push($p->etapa_id)->filter(fn ($id) => $orden->has($id))
                ->map(fn ($id) => $abiertas->search(fn ($e) => $e->id === $id));
            return [$p->id => $pasos->isEmpty() ? 0 : $pasos->max()];
        });

        $pasos = $abiertas->values()->map(fn ($e, $i) => [
            'etapa' => $e,
            'n'     => $alcance->filter(fn ($a) => $a >= $i)->count(),
            'valor' => round($prospectos->filter(fn ($p) => $alcance[$p->id] >= $i)->sum('valor'), 2),
        ]);
        $ganados = $prospectos->filter(fn ($p) => $p->etapa?->tipo === 'ganada');
        $perdidos = $prospectos->filter(fn ($p) => $p->etapa?->tipo === 'perdida');

        // Etapa donde se murió cada perdido: de dónde venía al perderse
        $murio = $perdidos->groupBy(function ($p) use ($abiertas) {
            $ev = $p->eventos->first(fn ($e) => $e->tipo === 'etapa' && $e->a_etapa_id === $p->etapa_id);
            return $abiertas->firstWhere('id', $ev?->de_etapa_id)?->nombre ?? 'Sin registro';
        })->map(fn ($g, $n) => ['etapa' => $n, 'n' => $g->count(), 'valor' => round($g->sum('valor'), 2)])->sortByDesc('n')->values();

        $motivos = $perdidos->groupBy(fn ($p) => $p->motivo_perdida ?: 'Otro')
            ->map(fn ($g, $m) => ['motivo' => $m, 'n' => $g->count(), 'valor' => round($g->sum('valor'), 2)])->sortByDesc('n')->values();

        $origenes = $prospectos->groupBy('origen')->map(fn ($g, $o) => [
            'origen'  => Prospecto::ORIGENES[$o]['texto'] ?? $o, 'icono' => Prospecto::ORIGENES[$o]['icono'] ?? 'bi-dot',
            'n'       => $g->count(), 'ganados' => $g->filter(fn ($p) => $p->etapa?->tipo === 'ganada')->count(),
            'valor'   => round($g->filter(fn ($p) => $p->etapa?->tipo === 'ganada')->sum('valor'), 2),
        ])->sortByDesc('n')->values();

        $dias = $ganados->filter(fn ($p) => $p->cerrado_at)->map(fn ($p) => $p->created_at->diffInDays($p->cerrado_at));

        return [
            'total'    => $prospectos->count(),
            'pasos'    => $pasos,
            'ganados'  => ['n' => $ganados->count(), 'valor' => round($ganados->sum('valor'), 2)],
            'perdidos' => ['n' => $perdidos->count(), 'valor' => round($perdidos->sum('valor'), 2)],
            'abiertos' => ['n' => $prospectos->filter(fn ($p) => $p->etapa?->tipo === 'abierta')->count(), 'valor' => round($prospectos->filter(fn ($p) => $p->etapa?->tipo === 'abierta')->sum('valor'), 2)],
            'murio'    => $murio,
            'motivos'  => $motivos,
            'origenes' => $origenes,
            'dias'     => $dias->isEmpty() ? null : (int) round($dias->avg()),
        ];
    }
}
