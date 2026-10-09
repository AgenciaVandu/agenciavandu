<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Presupuesto;
use App\Models\Proyecto;
use App\Models\ProyectoArchivo;
use App\Models\ProyectoEtapa;
use App\Models\ProyectoPago;
use App\Support\ArchivosProyecto;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProyectoController extends Controller
{
    public function index(Request $request)
    {
        $filtro = $request->query('filtro', 'activo');
        $tipo = $request->query('tipo');

        $proyectos = Proyecto::with(['cliente', 'etapas', 'pagos'])
            ->when(array_key_exists($filtro, Proyecto::ESTADOS), fn ($q) => $q->where('estado', $filtro))
            ->when($tipo && config("vandu.proyectos.$tipo"), fn ($q) => $q->where('tipo', $tipo))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $conteos = collect(Proyecto::ESTADOS)->map(fn ($l, $k) => Proyecto::where('estado', $k)->count())->put('todos', Proyecto::count());

        return view('admin.proyectos.index', compact('proyectos', 'filtro', 'tipo', 'conteos'));
    }

    /** Pantalla para revisar etapas, pagos y fechas antes de crear el proyecto */
    public function crear(Request $request, Presupuesto $presupuesto)
    {
        if ($presupuesto->proyecto) {
            return redirect()->route('admin.proyectos.show', $presupuesto->proyecto);
        }
        $presupuesto->load('conceptos', 'cliente');
        $tipo = config("vandu.proyectos.{$request->query('tipo')}") ? $request->query('tipo') : Proyecto::tipoSugerido($presupuesto);
        $metodo = config("vandu.proyectos.$tipo");
        $total = $presupuesto->total;

        $acum = 0;
        $pagos = collect($metodo['pagos'])->values()->map(function ($pg, $i) use ($metodo, $total, &$acum) {
            $ultimo = $i === count($metodo['pagos']) - 1;
            $monto = $ultimo ? round($total - $acum, 2) : round($total * $pg['porcentaje'] / 100, 2);
            $acum += $monto;
            return $pg + ['id' => null, 'monto' => $monto, 'pagado_el' => '', 'referencia' => ''];
        });

        return view('admin.proyectos.fechas', [
            'modo'        => 'crear',
            'presupuesto' => $presupuesto,
            'proyecto'    => null,
            'tipo'        => $tipo,
            'nombre'      => (string) \Illuminate\Support\Str::of($presupuesto->conceptos->first()?->descripcion ?? $metodo['nombre'])->before("\n")->limit(70, '…'),
            'monto'       => $total,
            'inicio'      => ($presupuesto->aceptada_el ?? now(config('vandu.zona_horaria')))->toDateString(),
            'etapas'      => collect($metodo['etapas'])->values()->map(fn ($e) => [
                'id' => null, 'clave' => $e['clave'], 'nombre' => $e['nombre'], 'descripcion' => $e['descripcion'] ?? null,
                'es_fecha' => ! empty($e['fecha']), 'dias' => $e['dias'] ?? 1,
                'fecha_inicio' => '', 'fecha_fin' => '', 'estado' => 'pendiente', 'completada_el' => '',
            ]),
            'pagos'       => $pagos,
        ]);
    }

    /** Crea el proyecto con exactamente las fechas capturadas */
    public function store(Request $request, Presupuesto $presupuesto)
    {
        if ($presupuesto->proyecto) {
            return redirect()->route('admin.proyectos.show', $presupuesto->proyecto);
        }
        $request->validate([
            'tipo'   => ['required', Rule::in(array_keys(config('vandu.proyectos')))],
            'nombre' => 'required|string|max:255',
            'monto_total' => 'required|numeric|min:0',
        ]);
        [$etapas, $pagos] = $this->validarFechas($request);
        $metodo = config("vandu.proyectos.{$request->input('tipo')}");

        $proyecto = DB::transaction(function () use ($request, $presupuesto, $etapas, $pagos, $metodo) {
            $terminado = collect($etapas)->every(fn ($e) => $e['estado'] === 'completada');
            $proyecto = Proyecto::create([
                'cliente_id'     => $presupuesto->cliente_id,
                'presupuesto_id' => $presupuesto->id,
                'tipo'           => $request->input('tipo'),
                'nombre'         => $request->input('nombre'),
                'estado'         => $terminado ? 'terminado' : 'activo',
                'monto_total'    => $request->input('monto_total'),
                'fecha_inicio'   => collect($etapas)->pluck('fecha_inicio')->filter()->min() ?? now(config('vandu.zona_horaria'))->toDateString(),
            ]);
            foreach ($metodo['etapas'] as $i => $e) {
                $proyecto->etapas()->create($etapas[$i] + [
                    'clave' => $e['clave'], 'descripcion' => $e['descripcion'] ?? null, 'orden' => $i, 'es_fecha' => ! empty($e['fecha']),
                ]);
            }
            foreach ($metodo['pagos'] as $i => $pg) {
                $proyecto->pagos()->create($pagos[$i] + [
                    'clave' => $pg['clave'], 'concepto' => $pg['concepto'], 'porcentaje' => $pg['porcentaje'],
                    'antes_de' => $pg['antes_de'] ?? null, 'orden' => $i,
                ]);
            }
            if ($presupuesto->estado !== 'aceptada') {
                $presupuesto->update(['estado' => 'aceptada', 'aceptada_el' => $presupuesto->aceptada_el ?? $proyecto->fecha_inicio]);
            }
            return $proyecto;
        });

        return redirect()->route('admin.proyectos.show', $proyecto)->with('ok', "Proyecto creado a partir de {$presupuesto->folio}.");
    }

    /** Editar todas las fechas de un proyecto existente */
    public function fechas(Proyecto $proyecto)
    {
        $proyecto->load('etapas', 'pagos', 'presupuesto');
        $tz = config('vandu.zona_horaria');

        return view('admin.proyectos.fechas', [
            'modo'        => 'editar',
            'presupuesto' => $proyecto->presupuesto,
            'proyecto'    => $proyecto,
            'tipo'        => $proyecto->tipo,
            'nombre'      => $proyecto->nombre,
            'monto'       => $proyecto->monto_total,
            'inicio'      => $proyecto->fecha_inicio?->toDateString(),
            'etapas'      => $proyecto->etapas->map(fn ($e) => [
                'id' => $e->id, 'clave' => $e->clave, 'nombre' => $e->nombre, 'descripcion' => $e->descripcion,
                'es_fecha' => $e->es_fecha, 'dias' => collect(config("vandu.proyectos.{$proyecto->tipo}.etapas"))->firstWhere('clave', $e->clave)['dias'] ?? 1,
                'fecha_inicio' => $e->fecha_inicio?->toDateString() ?? '', 'fecha_fin' => $e->fecha_fin?->toDateString() ?? '',
                'estado' => $e->estado, 'completada_el' => $e->completada_at?->timezone($tz)->toDateString() ?? '',
            ]),
            'pagos'       => $proyecto->pagos->map(fn ($pg) => [
                'id' => $pg->id, 'clave' => $pg->clave, 'concepto' => $pg->concepto, 'porcentaje' => $pg->porcentaje,
                'antes_de' => $pg->antes_de, 'monto' => $pg->monto, 'pagado_el' => $pg->pagado_el?->toDateString() ?? '', 'referencia' => $pg->referencia ?? '',
            ]),
        ]);
    }

    public function guardarFechas(Request $request, Proyecto $proyecto)
    {
        $proyecto->load('etapas', 'pagos');
        [$etapas, $pagos] = $this->validarFechas($request, $proyecto);

        DB::transaction(function () use ($proyecto, $etapas, $pagos) {
            foreach ($proyecto->etapas->values() as $i => $e) {
                if (isset($etapas[$i])) $e->update($etapas[$i]);
            }
            foreach ($proyecto->pagos->values() as $i => $pg) {
                if (isset($pagos[$i])) $pg->update($pagos[$i]);
            }
            $todas = $proyecto->etapas()->where('estado', '!=', 'completada')->doesntExist();
            if ($todas && $proyecto->estado === 'activo') $proyecto->update(['estado' => 'terminado']);
            if (! $todas && $proyecto->estado === 'terminado') $proyecto->update(['estado' => 'activo']);
            $inicio = collect($etapas)->pluck('fecha_inicio')->filter()->min();
            if ($inicio) $proyecto->update(['fecha_inicio' => $inicio]);
        });

        return redirect()->route('admin.proyectos.show', $proyecto)->with('ok', 'Fechas guardadas.');
    }

    /** @return array{0: array<int, array>, 1: array<int, array>} etapas y pagos listos para guardar */
    private function validarFechas(Request $request, ?Proyecto $proyecto = null): array
    {
        $request->validate([
            'etapas'                 => 'required|array|min:1',
            'etapas.*.nombre'        => 'required|string|max:255',
            'etapas.*.fecha_inicio'  => 'nullable|date',
            'etapas.*.fecha_fin'     => 'nullable|date',
            'etapas.*.estado'        => ['required', Rule::in(array_keys(ProyectoEtapa::ESTADOS))],
            'etapas.*.completada_el' => 'nullable|date|before_or_equal:today',
            'pagos'                  => 'array',
            'pagos.*.monto'          => 'required|numeric|min:0',
            'pagos.*.pagado_el'      => 'nullable|date|before_or_equal:today',
            'pagos.*.referencia'     => 'nullable|string|max:255',
        ], [
            'etapas.*.completada_el.before_or_equal' => 'Una fecha de “completada” está en el futuro.',
            'pagos.*.pagado_el.before_or_equal'      => 'Una fecha de pago está en el futuro.',
        ]);

        $tz = config('vandu.zona_horaria');
        $errores = [];
        $etapas = collect($request->input('etapas'))->values()->map(function ($e, $i) use ($tz, &$errores) {
            if (! empty($e['fecha_inicio']) && ! empty($e['fecha_fin']) && $e['fecha_fin'] < $e['fecha_inicio']) {
                $errores["etapas.$i.fecha_fin"] = "En “{$e['nombre']}” la fecha final es antes del inicio.";
            }
            $completada = $e['estado'] === 'completada';
            $cuando = $e['completada_el'] ?? null ?: ($e['fecha_fin'] ?? null ?: ($e['fecha_inicio'] ?? null));
            return [
                'nombre'        => $e['nombre'],
                'fecha_inicio'  => $e['fecha_inicio'] ?: null,
                'fecha_fin'     => $e['fecha_fin'] ?? null ?: null,
                'estado'        => $e['estado'],
                'completada_at' => $completada
                    ? Carbon::parse($cuando ?: now($tz)->toDateString(), $tz)->setTime(18, 0)->setTimezone(config('app.timezone'))
                    : null,
            ];
        })->all();
        if ($errores) {
            throw \Illuminate\Validation\ValidationException::withMessages($errores);
        }

        $pagos = collect($request->input('pagos', []))->values()->map(fn ($pg) => [
            'monto'      => $pg['monto'],
            'pagado_el'  => $pg['pagado_el'] ?: null,
            'referencia' => $pg['referencia'] ?? null ?: null,
        ])->all();

        return [$etapas, $pagos];
    }

    public function show(Proyecto $proyecto)
    {
        $proyecto->load(['cliente', 'presupuesto', 'etapas.archivos', 'pagos', 'archivos']);

        return view('admin.proyectos.show', [
            'p'       => $proyecto,
            'galeria' => $proyecto->archivos->where('grupo', 'galeria')->values(),
        ]);
    }

    public function update(Request $request, Proyecto $proyecto)
    {
        $proyecto->update($request->validate([
            'nombre'          => 'required|string|max:255',
            'estado'          => ['required', Rule::in(array_keys(Proyecto::ESTADOS))],
            'monto_total'     => 'required|numeric|min:0',
            'mensaje_cliente' => 'nullable|string|max:2000',
            'notas_internas'  => 'nullable|string|max:5000',
        ]));

        return back()->with('ok', 'Proyecto actualizado.');
    }

    public function destroy(Proyecto $proyecto)
    {
        foreach ($proyecto->archivos as $a) {
            $a->delete(); // borra también los archivos del disco
        }
        $proyecto->delete();

        return redirect()->route('admin.proyectos.index')->with('ok', 'Proyecto eliminado. La cotización sigue disponible.');
    }

    /* ---------------- Etapas ---------------- */

    public function etapa(Request $request, Proyecto $proyecto, ProyectoEtapa $etapa)
    {
        abort_unless($etapa->proyecto_id === $proyecto->id, 404);

        $data = $request->validate([
            'estado'       => ['nullable', Rule::in(array_keys(ProyectoEtapa::ESTADOS))],
            'nombre'       => 'nullable|string|max:255',
            'descripcion'  => 'nullable|string|max:2000',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin'    => 'nullable|date|after_or_equal:fecha_inicio',
            'completada_el' => 'nullable|date|before_or_equal:today',
        ], [
            'completada_el.before_or_equal' => 'La fecha en que se completó no puede ser futura.','fecha_fin.after_or_equal' => 'La fecha final no puede ser antes del inicio.']);

        // Regla de la metodología: no se avanza una etapa con pagos pendientes que la habilitan
        if (! empty($data['estado']) && $data['estado'] !== 'pendiente') {
            $pendientes = $proyecto->load('pagos')->pagosQueBloquean($etapa);
            if ($pendientes->isNotEmpty() && ! $request->boolean('forzar')) {
                return back()->with('bloqueo', [
                    'etapa_id' => $etapa->id,
                    'estado'   => $data['estado'],
                    'mensaje'  => "“{$etapa->nombre}” requiere el pago de: " . $pendientes->pluck('concepto')->join(', ', ' y ') . '.',
                ]);
            }
        }

        $cambios = [];
        if (! empty($data['estado'])) {
            $cambios['estado'] = $data['estado'];
            $cambios['completada_at'] = $data['estado'] === 'completada' ? ($etapa->completada_at ?? now()) : null;
        }
        if ($request->filled('nombre')) $cambios['nombre'] = $data['nombre'];
        if ($request->has('descripcion')) $cambios['descripcion'] = $data['descripcion'];
        if ($request->filled('completada_el') && ($cambios['estado'] ?? $etapa->estado) === 'completada') {
            $cambios['completada_at'] = Carbon::parse($data['completada_el'], config('vandu.zona_horaria'))->setTime(18, 0)->setTimezone(config('app.timezone'));
        }
        if ($request->has('fechas')) {
            $cambios['fecha_inicio'] = $data['fecha_inicio'] ?? null;
            $cambios['fecha_fin'] = $etapa->es_fecha ? null : ($data['fecha_fin'] ?? null);
        }
        $etapa->update($cambios);

        // Si todas quedaron completadas, el proyecto se marca terminado
        if ($proyecto->etapas()->where('estado', '!=', 'completada')->doesntExist() && $proyecto->estado === 'activo') {
            $proyecto->update(['estado' => 'terminado']);
            return back()->with('ok', '¡Todas las etapas completadas! El proyecto quedó como terminado.');
        }

        return back()->with('ok', "“{$etapa->nombre}” actualizada.");
    }

    /* ---------------- Pagos ---------------- */

    public function pago(Request $request, Proyecto $proyecto, ProyectoPago $pago)
    {
        abort_unless($pago->proyecto_id === $proyecto->id, 404);

        $data = $request->validate([
            'accion'     => 'nullable|in:pagar,deshacer',
            'pagado_el'  => 'nullable|date',
            'monto'      => 'nullable|numeric|min:0',
            'referencia' => 'nullable|string|max:255',
        ]);

        if (($data['accion'] ?? null) === 'pagar') {
            $pago->pagado_el = $data['pagado_el'] ?? now(config('vandu.zona_horaria'))->toDateString();
        } elseif (($data['accion'] ?? null) === 'deshacer') {
            $pago->pagado_el = null;
        }
        if (isset($data['monto'])) $pago->monto = $data['monto'];
        if ($request->has('referencia')) $pago->referencia = $data['referencia'];
        $pago->save();

        return back()->with('ok', $pago->pagado ? "{$pago->concepto} registrado como pagado." : "{$pago->concepto} actualizado.");
    }

    /* ---------------- Archivos ---------------- */

    public function subir(Request $request, Proyecto $proyecto)
    {
        $data = $request->validate([
            'grupo'      => 'required|in:documento,galeria',
            'etapa_id'   => ['nullable', Rule::exists('proyecto_etapas', 'id')->where('proyecto_id', $proyecto->id)],
            'archivos'   => 'required|array|min:1|max:50',
            'archivos.*' => 'file|max:' . (int) config('vandu.max_archivo_mb', 512) * 1024,
        ], [
            'archivos.required' => 'Elige al menos un archivo.',
            'archivos.*.max'    => 'Cada archivo puede pesar hasta ' . config('vandu.max_archivo_mb', 512) . ' MB.',
            'archivos.*.uploaded' => 'Un archivo no se pudo subir. Puede que pese más de lo que permite el servidor (' . ini_get('upload_max_filesize') . ').',
        ]);

        foreach ($request->file('archivos') as $f) {
            ArchivosProyecto::guardar($proyecto, $f, $data['grupo'], $data['etapa_id'] ?? null);
        }

        $n = count($request->file('archivos'));
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'subidos' => $n]);
        }
        return back()->with('ok', $n === 1 ? 'Archivo subido.' : "$n archivos subidos.");
    }

    public function archivo(Request $request, Proyecto $proyecto, ProyectoArchivo $archivo)
    {
        abort_unless($archivo->proyecto_id === $proyecto->id, 404);
        $archivo->update($request->validate(['visible' => 'required|boolean']));

        return back()->with('ok', $archivo->visible ? 'Ahora el cliente puede verlo.' : 'Oculto para el cliente.');
    }

    public function borrarArchivo(Proyecto $proyecto, ProyectoArchivo $archivo)
    {
        abort_unless($archivo->proyecto_id === $proyecto->id, 404);
        $archivo->delete();

        return back()->with('ok', 'Archivo eliminado.');
    }

    public function verArchivo(Request $request, Proyecto $proyecto, ProyectoArchivo $archivo)
    {
        abort_unless($archivo->proyecto_id === $proyecto->id, 404);
        $version = in_array($request->query('v'), ['vista', 'miniatura']) ? $request->query('v') : 'original';

        return ArchivosProyecto::responder($archivo, $version, $request->boolean('descargar'));
    }
}
