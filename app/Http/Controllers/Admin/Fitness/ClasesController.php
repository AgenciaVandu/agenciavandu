<?php

namespace App\Http\Controllers\Admin\Fitness;

use App\Models\Clase;
use App\Models\ClaseSerie;
use App\Models\ClaseTipo;
use App\Models\Cliente;
use App\Models\Ejercicio;
use App\Models\Sucursal;
use App\Models\User;
use App\Support\Cuentas;
use App\Support\Fitness\Agenda;
use App\Support\Fitness\Avisos;
use App\Support\Fitness\Calendario;
use App\Support\Fitness\Reservas;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Agenda del gimnasio: la semana, cada clase con su lista, y programar clases sueltas o fijas */
class ClasesController extends FitnessController
{
    public function index(Request $request)
    {
        $tz = config('vandu.zona_horaria');
        $hoy = now($tz);
        // ?fecha=2026-10-21 lleva a la semana de ese día (así regresa después de programar)
        $fecha = $request->query('fecha') && strtotime((string) $request->query('fecha')) ? Carbon::parse($request->query('fecha'), $tz) : null;
        $semana = $fecha
            ? (int) round($hoy->copy()->startOfWeek(Carbon::MONDAY)->diffInDays($fecha->copy()->startOfWeek(Carbon::MONDAY), false) / 7)
            : max(-52, min(52, (int) $request->query('semana', 0)));
        $lunes = $hoy->copy()->startOfWeek(Carbon::MONDAY)->addWeeks($semana);
        $domingo = $lunes->copy()->addDays(6)->endOfDay();
        $u = $request->user();
        $soloMias = ! $u->puede('clases');   // el entrenador ve sus clases
        $sucursal = (int) $request->query('sucursal') ?: null;
        $coach = $soloMias ? $u->id : ((int) $request->query('coach') ?: null);

        Agenda::completarTodas();

        $clases = Clase::with(['tipo', 'coach', 'sucursal'])->withCount('ocupadas')
            ->entre($lunes, $domingo)
            ->when($sucursal, fn ($q) => $q->where('sucursal_id', $sucursal))
            ->when($coach, fn ($q) => $q->where('coach_id', $coach))
            ->orderBy('inicia_at')->get();

        [$horaIni, $horaFin] = Calendario::horas($clases);
        $dias = collect(range(0, 6))->map(fn ($i) => $lunes->copy()->addDays($i))
            ->mapWithKeys(function ($d) use ($clases, $horaIni) {
                $delDia = $clases->filter(fn ($c) => $c->inicio->toDateString() === $d->toDateString())->values();
                return [$d->toDateString() => ['fecha' => $d, 'clases' => $delDia, 'bloques' => Calendario::acomodar($delDia, $horaIni)]];
            });

        // La clase abierta en el panel lateral (?clase=ID)
        $abierta = null;
        if ($id = (int) $request->query('clase')) {
            $c = Clase::find($id);
            if ($c && ($u->puede('clases') || $c->coach_id === $u->id)) $abierta = $this->datosClase($c, $u);
        }

        return view('admin.fitness.agenda', [
            'dias' => $dias, 'semana' => $semana, 'lunes' => $lunes, 'domingo' => $domingo, 'soloMias' => $soloMias,
            'sucursales' => Sucursal::orderBy('nombre')->get(), 'coaches' => $this->coaches(), 'sucursal' => $sucursal, 'coach' => $coach,
            'horaIni' => $horaIni, 'horaFin' => $horaFin, 'abierta' => $abierta,
            'tipos' => $u->puede('clases') ? ClaseTipo::activos()->ordenados()->get(['id', 'nombre', 'duracion', 'cupo', 'color']) : collect(),
            'resumen' => [
                'clases' => $clases->where('estado', 'programada')->count(),
                'lugares' => $clases->where('estado', 'programada')->sum('cupo'),
                'ocupados' => $clases->where('estado', 'programada')->sum('ocupadas_count'),
            ],
        ]);
    }

    /** Todo lo de una clase: su lista, a quién se puede inscribir y su horario fijo (página y panel de la agenda) */
    private function datosClase(Clase $clase, User $u): array
    {
        $clase->load(['tipo', 'coach', 'sucursal', 'serie.tipo', 'reservaciones' => fn ($q) => $q->with(['cliente', 'membresia', 'registro'])->orderByRaw("estado = 'cancelada'")->orderBy('id')]);
        $inscritos = $clase->reservaciones->pluck('cliente_id');
        $atletas = $u->puede('clases') || $u->puede('membresias') || $u->puede('asistencia')
            ? Cliente::where(fn ($w) => $w->whereNull('activo')->orWhere('activo', true))->whereNotIn('id', $inscritos)
                ->with(['membresias' => fn ($m) => $m->reorder()->vigentes($clase->inicio->toDateString())])
                ->whereHas('membresias', fn ($q) => $q->vigentes($clase->inicio->toDateString()))->orderBy('nombre')->get(['id', 'nombre', 'telefono'])
                ->filter(fn ($a) => $a->membresias->contains(fn ($m) => $m->con_creditos && $m->permiteTipo($clase->clase_tipo_id)))->values()   // con clases disponibles para este tipo
            : collect();
        return [
            'c' => $clase,
            'atletas' => $atletas,
            'puedeEditar' => $u->puede('clases'),
        ];
    }

    public function create(Request $request)
    {
        $tipo = ClaseTipo::activos()->ordenados()->first();
        $tz = config('vandu.zona_horaria');
        $fecha = $request->query('fecha') && strtotime($request->query('fecha')) ? $request->query('fecha') : now($tz)->toDateString();
        $c = new Clase(['clase_tipo_id' => $tipo?->id, 'cupo' => $tipo?->cupo ?? 12, 'coach_id' => null, 'sucursal_id' => $request->user()->sucursal_id ?? Sucursal::value('id')]);
        $c->inicia_at = Carbon::parse($fecha . ' 07:00', $tz)->utc();
        $c->termina_at = $c->inicia_at->copy()->addMinutes($tipo?->duracion ?? 60);
        return view('admin.fitness.clase-form', $this->datosForm($c));
    }

    public function store(Request $request)
    {
        $d = $this->validar($request, true);
        $tz = config('vandu.zona_horaria');
        if ($request->boolean('repetir')) {
            $s = ClaseSerie::create([
                'clase_tipo_id' => $d['clase_tipo_id'], 'sucursal_id' => $d['sucursal_id'] ?? null, 'coach_id' => $d['coach_id'] ?? null,
                'dias' => array_values(array_map('intval', $d['dias'])), 'hora' => $d['hora'], 'duracion' => $d['duracion'], 'cupo' => $d['cupo'],
                'desde' => $d['fecha'], 'hasta' => $d['hasta'] ?? null, 'activa' => true,
            ]);
            $n = Agenda::generar($s);
            if (! empty($d['entrenamiento'])) $s->clases()->orderBy('inicia_at')->first()?->update(['entrenamiento' => $d['entrenamiento']]);
            $msg = "Horario fijo creado: {$s->tipo->nombre}, {$s->dias_texto} a las {$s->hora}. Se programaron $n clases de las próximas " . Agenda::SEMANAS . ' semanas.';
            if ($request->input('volver') === 'agenda') return redirect()->route('admin.clases.index', ['fecha' => $d['fecha']])->with('ok', $msg);
            return redirect()->route('admin.clases.series')->with('ok', "Horario fijo creado: {$s->tipo->nombre}, {$s->dias_texto} a las {$s->hora}. Se programaron $n clases de las próximas " . Agenda::SEMANAS . ' semanas.');
        }
        $inicio = Carbon::parse($d['fecha'] . ' ' . $d['hora'], $tz);
        $c = Clase::create([
            'clase_tipo_id' => $d['clase_tipo_id'], 'sucursal_id' => $d['sucursal_id'] ?? null, 'coach_id' => $d['coach_id'] ?? null,
            'inicia_at' => $inicio->copy()->utc(), 'termina_at' => $inicio->copy()->addMinutes($d['duracion'])->utc(), 'cupo' => $d['cupo'],
            'entrenamiento' => $d['entrenamiento'] ?: null, 'notas' => $d['notas'] ?? null,
        ]);
        if ($request->input('volver') === 'agenda') return redirect()->route('admin.clases.index', ['fecha' => $d['fecha'], 'clase' => $c->id])->with('ok', 'Clase programada.');
        return redirect()->route('admin.clases.show', $c)->with('ok', 'Clase programada.');
    }

    public function show(Request $request, Clase $clase)
    {
        $u = $request->user();
        abort_if(! $u->puede('clases') && $clase->coach_id !== $u->id, 403, 'Esta clase no es tuya.');
        return view('admin.fitness.clase', $this->datosClase($clase, $u));
    }

    public function edit(Clase $clase)
    {
        return view('admin.fitness.clase-form', $this->datosForm($clase));
    }

    public function update(Request $request, Clase $clase)
    {
        $d = $this->validar($request, false, $clase);
        $inicio = Carbon::parse($d['fecha'] . ' ' . $d['hora'], config('vandu.zona_horaria'));
        $clase->update([
            'clase_tipo_id' => $d['clase_tipo_id'], 'sucursal_id' => $d['sucursal_id'] ?? null, 'coach_id' => $d['coach_id'] ?? null,
            'inicia_at' => $inicio->copy()->utc(), 'termina_at' => $inicio->copy()->addMinutes($d['duracion'])->utc(), 'cupo' => $d['cupo'],
            'entrenamiento' => $d['entrenamiento'] ?: null, 'notas' => $d['notas'] ?? null,
        ]);
        return redirect()->route('admin.clases.index', ['fecha' => $inicio->toDateString(), 'clase' => $clase->id])->with('ok', 'Clase guardada.');
    }

    /** El gimnasio cancela la clase: se devuelve el crédito a todos y se les avisa */
    public function cancelar(Request $request, Clase $clase)
    {
        $d = $request->validate(['motivo' => 'required|string|max:500'], ['motivo.required' => 'Escribe el motivo; se lo avisamos a los inscritos.']);
        abort_if($clase->estado === 'cancelada', 422, 'La clase ya está cancelada.');
        $afectados = Reservas::cancelarClase($clase, trim($d['motivo']));
        foreach ($afectados as $r) {
            Avisos::atleta($r->cliente, 'Se canceló tu clase de ' . $clase->nombre,
                $clase->fecha_texto . ', ' . $clase->horario . '. ' . trim($d['motivo']) . ' Te regresamos tu clase.', 'clase-' . $clase->id);
        }
        return back()->with('ok', 'Clase cancelada.' . ($afectados->count() ? " A {$afectados->count()} " . ($afectados->count() === 1 ? 'atleta se le regresó su clase' : 'atletas se les regresó su clase') . ' y se les avisó.' : ''));
    }

    public function destroy(Clase $clase)
    {
        if ($clase->reservaciones()->exists()) return back()->with('error', 'Esta clase ya tiene reservaciones. Mejor cancélala para devolverles su clase.');
        $fecha = $clase->inicio->toDateString();
        $clase->delete();
        return redirect()->route('admin.clases.index', ['fecha' => $fecha])->with('ok', 'Clase eliminada.');
    }

    private function datosForm(Clase $c): array
    {
        return [
            'c' => $c,
            'tipos' => ClaseTipo::where(fn ($q) => $q->where('activo', true)->orWhere('id', $c->clase_tipo_id))->ordenados()->get(),
            'coaches' => $this->coaches(),
            'sucursales' => Sucursal::orderBy('nombre')->get(),
            'ejercicios' => Ejercicio::activos()->orderBy('nombre')->get(['id', 'nombre', 'categoria', 'unidad']),
        ];
    }

    /** Quien puede dar clases: el equipo con permiso de asistencia o de agenda */
    private function coaches()
    {
        return User::with('rol')->where('activo', true)->orderBy('name')->get()->filter(fn ($u) => $u->puede('asistencia') || $u->puede('clases'))->values();
    }

    private function validar(Request $request, bool $nueva, ?Clase $clase = null): array
    {
        $cuenta = Cuentas::id();
        $d = $request->validate([
            'clase_tipo_id' => ['required', Rule::exists('clase_tipos', 'id')->where('cuenta_id', $cuenta)],
            'fecha'         => 'required|date',
            'hora'          => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'duracion'      => 'required|integer|min:10|max:480',
            'cupo'          => 'required|integer|min:1|max:500',
            'coach_id'      => ['nullable', Rule::exists('users', 'id')->where('cuenta_id', $cuenta)],
            'sucursal_id'   => ['nullable', Rule::exists('sucursales', 'id')->where('cuenta_id', $cuenta)],
            'notas'         => 'nullable|string|max:2000',
            'repetir'       => 'nullable|boolean',
            'dias'          => 'nullable|required_if:repetir,1|array|min:1',
            'dias.*'        => 'integer|between:1,7',
            'hasta'         => 'nullable|date|after_or_equal:fecha',
            'entrenamiento' => 'nullable|string|max:20000',
        ], [
            'dias.required_if' => 'Elige qué días se repite.',
            'hora.regex'       => 'Escribe la hora como 07:00.',
        ]);
        // No se puede bajar el cupo por debajo de los que ya reservaron
        if ($clase && $d['cupo'] < $clase->ocupados) {
            throw \Illuminate\Validation\ValidationException::withMessages(['cupo' => "Ya hay {$clase->ocupados} reservados; el cupo no puede ser menor."]);
        }
        $d['entrenamiento'] = self::entrenamiento($d['entrenamiento'] ?? null);
        return $d;
    }

    /** Limpia los bloques que manda el armador de entrenamientos */
    public static function entrenamiento(?string $json): array
    {
        $bloques = json_decode((string) $json, true);
        if (! is_array($bloques)) return [];
        return collect($bloques)->take(20)->map(fn ($b) => [
            'nombre' => mb_substr(trim((string) ($b['nombre'] ?? '')), 0, 80),
            'formato' => mb_substr(trim((string) ($b['formato'] ?? '')), 0, 80),
            'ejercicios' => collect($b['ejercicios'] ?? [])->take(30)->map(fn ($e) => [
                'ejercicio_id' => isset($e['ejercicio_id']) ? (int) $e['ejercicio_id'] : null,
                'nombre' => mb_substr(trim((string) ($e['nombre'] ?? '')), 0, 100),
                'cantidad' => mb_substr(trim((string) ($e['cantidad'] ?? '')), 0, 40),
                'nota' => mb_substr(trim((string) ($e['nota'] ?? '')), 0, 200),
            ])->filter(fn ($e) => $e['nombre'] !== '')->values()->all(),
        ])->filter(fn ($b) => $b['nombre'] !== '' || $b['ejercicios'])->values()->all();
    }
}
