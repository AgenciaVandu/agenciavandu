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
        $metodoCliente = $presupuesto->cliente?->metodo_pago ?? '';
        $pagos = collect($metodo['pagos'])->values()->map(function ($pg, $i) use ($metodo, $total, &$acum, $metodoCliente) {
            $ultimo = $i === count($metodo['pagos']) - 1;
            $monto = $ultimo ? round($total - $acum, 2) : round($total * $pg['porcentaje'] / 100, 2);
            $acum += $monto;
            return $pg + ['id' => null, 'monto' => $monto, 'pagado_el' => '', 'referencia' => '', 'metodo' => $metodoCliente, 'vence_el' => ''];
        });
        $cli = $presupuesto->cliente;

        return view('admin.proyectos.fechas', [
            'modo'        => 'crear',
            'presupuesto' => $presupuesto,
            'proyecto'    => null,
            'tipo'        => $tipo,
            'nombre'      => (string) \Illuminate\Support\Str::of($presupuesto->conceptos->first()?->resumen ?: $metodo['nombre'])->before("\n")->limit(70, '…'),
            'monto'       => $total,
            'inicio'      => ($presupuesto->aceptada_el ?? now(config('vandu.zona_horaria')))->toDateString(),
            'etapas'      => collect($metodo['etapas'])->values()->map(fn ($e) => [
                'id' => null, 'clave' => $e['clave'], 'nombre' => $e['nombre'], 'descripcion' => $e['descripcion'] ?? null,
                'es_fecha' => ! empty($e['fecha']), 'dias' => $e['dias'] ?? 1,
                'fecha_inicio' => '', 'fecha_fin' => '', 'estado' => 'pendiente', 'completada_el' => '',
            ]),
            'pagos'       => $pagos,
            'forma'       => $cli?->metodo_pago === 'credito' ? 'credito' : 'contado',
            'diasCredito' => $cli?->dias_credito ?: config('vandu.credito.dias_por_defecto'),
            'pagoCredito' => $this->pagoCredito($total),
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
        $request->validate([
            'forma_pago'   => 'required|in:contado,credito',
            'dias_credito' => 'nullable|required_if:forma_pago,credito|integer|min:1|max:365',
        ], ['dias_credito.required_if' => 'Indica a cuántos días es el crédito.']);
        [$etapas, $pagos] = $this->validarFechas($request);
        $metodo = config("vandu.proyectos.{$request->input('tipo')}");
        $credito = $request->input('forma_pago') === 'credito';

        $proyecto = DB::transaction(function () use ($request, $presupuesto, $etapas, $pagos, $metodo, $credito) {
            $terminado = collect($etapas)->every(fn ($e) => $e['estado'] === 'completada');
            $proyecto = Proyecto::create([
                'cliente_id'     => $presupuesto->cliente_id,
                'presupuesto_id' => $presupuesto->id,
                'tipo'           => $request->input('tipo'),
                'nombre'         => $request->input('nombre'),
                'estado'         => $terminado ? 'terminado' : 'activo',
                'monto_total'    => $request->input('monto_total'),
                'forma_pago'     => $credito ? 'credito' : 'contado',
                'dias_credito'   => $credito ? (int) $request->input('dias_credito') : null,
                'fecha_inicio'   => collect($etapas)->pluck('fecha_inicio')->filter()->min() ?? now(config('vandu.zona_horaria'))->toDateString(),
            ]);
            // Las etapas son las que quedaron en pantalla: la agencia pudo agregar, quitar o reordenar
            $claves = [];
            foreach ($etapas as $i => $e) {
                unset($e['id']);
                $claves[] = $e['clave'];
                $proyecto->etapas()->create($e + ['orden' => $i]);
            }
            // A crédito: un solo pago diferido que no frena ninguna etapa
            $plantilla = $credito ? [$this->pagoCredito((float) $request->input('monto_total'))] : $metodo['pagos'];
            foreach ($plantilla as $i => $pg) {
                $antes = $pg['antes_de'] ?? null;
                $proyecto->pagos()->create(($pagos[$i] ?? ['monto' => 0]) + [
                    'clave' => $pg['clave'], 'concepto' => $pg['concepto'], 'porcentaje' => $pg['porcentaje'],
                    'antes_de' => in_array($antes, $claves, true) ? $antes : null, 'orden' => $i,
                ]);
            }
            // Gestión de redes: el cliente queda dado de alta en su calendario de contenido
            if (! empty($metodo['redes']) && $presupuesto->cliente) \App\Support\Redes::token($presupuesto->cliente);
            if ($presupuesto->estado !== 'aceptada') {
                $presupuesto->update(['estado' => 'aceptada', 'aceptada_el' => $presupuesto->aceptada_el ?? $proyecto->fecha_inicio]);
            }
            return $proyecto;
        });

        return redirect()->route('admin.proyectos.show', $proyecto)->with('ok', "Proyecto creado a partir de {$presupuesto->folio}.");
    }

    /** Plantilla del pago único de un proyecto a crédito */
    private function pagoCredito(float $total): array
    {
        return [
            'id' => null, 'clave' => 'credito', 'concepto' => config('vandu.credito.concepto'), 'porcentaje' => 100,
            'antes_de' => null, 'monto' => round($total, 2), 'pagado_el' => '', 'referencia' => '', 'metodo' => 'credito', 'vence_el' => '',
        ];
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
                'es_fecha' => $e->es_fecha, 'dias' => $e->dias ?? collect(config("vandu.proyectos.{$proyecto->tipo}.etapas"))->firstWhere('clave', $e->clave)['dias'] ?? 1,
                'fecha_inicio' => $e->fecha_inicio?->toDateString() ?? '', 'fecha_fin' => $e->fecha_fin?->toDateString() ?? '',
                'estado' => $e->estado, 'completada_el' => $e->completada_at?->timezone($tz)->toDateString() ?? '',
            ]),
            'pagos'       => $proyecto->pagos->map(fn ($pg) => [
                'id' => $pg->id, 'clave' => $pg->clave, 'concepto' => $pg->concepto, 'porcentaje' => $pg->porcentaje,
                'antes_de' => $pg->antes_de, 'monto' => $pg->monto, 'pagado_el' => $pg->pagado_el?->toDateString() ?? '', 'referencia' => $pg->referencia ?? '',
                'metodo' => $pg->metodo ?? ($proyecto->cliente?->metodo_pago ?? ''),
                'vence_el' => $pg->vence_el?->toDateString() ?? '',
            ]),
            'forma'       => $proyecto->forma_pago,
            'diasCredito' => $proyecto->dias_credito,
            'pagoCredito' => null,
        ]);
    }

    public function guardarFechas(Request $request, Proyecto $proyecto)
    {
        $proyecto->load('etapas', 'pagos');
        [$etapas, $pagos] = $this->validarFechas($request, $proyecto);

        if ($proyecto->a_credito && $request->filled('dias_credito')) {
            $proyecto->dias_credito = max(1, min(365, (int) $request->input('dias_credito')));
        }
        DB::transaction(function () use ($proyecto, $etapas, $pagos) {
            $proyecto->save();
            // Sincroniza etapas: actualiza las que siguen, crea las nuevas y borra las que se quitaron
            $existentes = $proyecto->etapas->keyBy('id');
            $siguen = [];
            foreach ($etapas as $i => $e) {
                $id = $e['id'];
                unset($e['id']);
                if ($id && $existentes->has($id)) {
                    $existentes[$id]->update($e + ['orden' => $i]);
                    $siguen[] = $id;
                } else {
                    $siguen[] = $proyecto->etapas()->create($e + ['orden' => $i])->id;
                }
            }
            $quitadas = $existentes->except($siguen);
            foreach ($quitadas as $e) $e->delete();
            $claves = array_column($etapas, 'clave');
            foreach ($proyecto->pagos->values() as $i => $pg) {
                if (isset($pagos[$i])) $pg->fill($pagos[$i]);
                if ($pg->antes_de && ! in_array($pg->antes_de, $claves, true)) $pg->antes_de = null;
                $pg->save();
            }
            $todas = $proyecto->etapas()->where('estado', '!=', 'completada')->doesntExist();
            if ($todas && $proyecto->estado === 'activo') $proyecto->update(['estado' => 'terminado']);
            if (! $todas && $proyecto->estado === 'terminado') $proyecto->update(['estado' => 'activo']);
            $inicio = collect($etapas)->pluck('fecha_inicio')->filter()->min();
            if ($inicio) $proyecto->update(['fecha_inicio' => $inicio]);
        });

        return redirect()->route('admin.proyectos.show', $proyecto)->with('ok', 'Etapas y fechas guardadas.');
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
            'etapas.*.id'            => 'nullable|integer',
            'etapas.*.clave'         => 'nullable|string|max:40',
            'etapas.*.descripcion'   => 'nullable|string|max:300',
            'etapas.*.dias'          => 'nullable|integer|min:1|max:365',
            'pagos'                  => 'array',
            'pagos.*.monto'          => 'required|numeric|min:0',
            'pagos.*.pagado_el'      => 'nullable|date|before_or_equal:today',
            'pagos.*.referencia'     => 'nullable|string|max:255',
            'pagos.*.metodo'         => ['nullable', Rule::in(array_keys(config('vandu.metodos_pago')))],
            'pagos.*.vence_el'       => 'nullable|date',
        ], [
            'etapas.*.completada_el.before_or_equal' => 'Una fecha de “completada” está en el futuro.',
            'pagos.*.pagado_el.before_or_equal'      => 'Una fecha de pago está en el futuro.',
        ]);

        $tz = config('vandu.zona_horaria');
        $errores = [];
        // Claves estables: las etapas existentes conservan la suya; las nuevas la sacan de su nombre
        $actuales = $proyecto ? $proyecto->etapas->pluck('clave', 'id') : collect();
        $usadas = [];
        $etapas = collect($request->input('etapas'))->values()->map(function ($e, $i) use ($tz, &$errores, $actuales, &$usadas) {
            $id = isset($e['id']) && $actuales->has((int) $e['id']) ? (int) $e['id'] : null;
            $clave = $id ? $actuales[$id] : (string) ($e['clave'] ?? '');
            if (! preg_match('/^[a-z0-9-]{1,40}$/', $clave) || in_array($clave, $usadas, true)) {
                $clave = \App\Support\TiposProyecto::clave($e['nombre'], array_merge($usadas, $actuales->values()->all()));
            }
            $usadas[] = $clave;
            if (! empty($e['fecha_inicio']) && ! empty($e['fecha_fin']) && $e['fecha_fin'] < $e['fecha_inicio']) {
                $errores["etapas.$i.fecha_fin"] = "En “{$e['nombre']}” la fecha final es antes del inicio.";
            }
            $completada = $e['estado'] === 'completada';
            $cuando = $e['completada_el'] ?? null ?: ($e['fecha_fin'] ?? null ?: ($e['fecha_inicio'] ?? null));
            return [
                'id'            => $id,
                'clave'         => $clave,
                'nombre'        => $e['nombre'],
                'descripcion'   => trim((string) ($e['descripcion'] ?? '')) ?: null,
                'es_fecha'      => ! empty($e['es_fecha']),
                'dias'          => isset($e['dias']) && $e['dias'] !== '' ? (int) $e['dias'] : null,
                'fecha_inicio'  => $e['fecha_inicio'] ?? null ?: null,
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
            'pagado_el'  => $pg['pagado_el'] ?? null ?: null,
            'referencia' => $pg['referencia'] ?? null ?: null,
            'metodo'     => $pg['metodo'] ?? null ?: null,
            'vence_el'   => $pg['vence_el'] ?? null ?: null,
        ])->all();

        return [$etapas, $pagos];
    }

    public function show(Proyecto $proyecto)
    {
        $proyecto->load(['cliente', 'presupuesto', 'etapas.archivos', 'pagos', 'archivos']);

        return view('admin.proyectos.show', [
            'p'         => $proyecto,
            'galeria'   => $proyecto->archivos->where('grupo', 'galeria')->values(),
            'grupos'    => \App\Support\Galeria::agrupada($proyecto, false),
            'secciones' => $proyecto->secciones()->get(),
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
        // La carpeta del proyecto en Dropbox va completa a la papelera de Dropbox (se puede recuperar)
        if ($proyecto->dropbox_carpeta && \App\Support\Dropbox\Dropbox::conectado()) {
            try { \App\Support\Dropbox\Dropbox::cliente()->borrar($proyecto->dropbox_carpeta); } catch (\Throwable $e) { report($e); }
            $proyecto->archivos()->where('origen', 'dropbox')->get()->each->deleteQuietly();
        }
        foreach ($proyecto->archivos()->get() as $a) {
            $a->delete(); // borra también los archivos del disco
        }
        \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory($proyecto->carpeta);
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
            'pagado_el'  => 'nullable|date|before_or_equal:today',
            'monto'      => 'nullable|numeric|min:0',
            'referencia' => 'nullable|string|max:255',
            'metodo'     => ['nullable', Rule::in(array_keys(config('vandu.metodos_pago')))],
            'vence_el'   => 'nullable|date',
        ]);

        if (($data['accion'] ?? null) === 'pagar') {
            $pago->pagado_el = $data['pagado_el'] ?? now(config('vandu.zona_horaria'))->toDateString();
        } elseif (($data['accion'] ?? null) === 'deshacer') {
            $pago->pagado_el = null;
        } elseif ($request->boolean('editar_fecha')) {
            $pago->pagado_el = $data['pagado_el'] ?? null; // vacío = pendiente
        }
        if (isset($data['monto'])) $pago->monto = $data['monto'];
        if ($request->has('referencia')) $pago->referencia = $data['referencia'];
        if ($request->has('metodo')) $pago->metodo = $data['metodo'] ?: null;
        if ($request->has('vence_el')) $pago->vence_el = $data['vence_el'] ?: null;
        $pago->save();

        return back()->with('ok', $pago->pagado ? "{$pago->concepto} registrado como pagado." : "{$pago->concepto} actualizado.");
    }

    /* ---------------- Archivos ---------------- */

    public function subir(Request $request, Proyecto $proyecto)
    {
        $data = $request->validate([
            'grupo'      => 'required|in:documento,galeria',
            'etapa_id'   => ['nullable', Rule::exists('proyecto_etapas', 'id')->where('proyecto_id', $proyecto->id)],
            'seccion_id' => ['nullable', Rule::exists('galeria_secciones', 'id')->where('proyecto_id', $proyecto->id)],
            'archivos'   => 'required|array|min:1|max:50',
            'archivos.*' => 'file|max:' . (int) config('vandu.max_archivo_mb', 512) * 1024,
        ], [
            'archivos.required' => 'Elige al menos un archivo.',
            'archivos.*.max'    => 'Cada archivo puede pesar hasta ' . config('vandu.max_archivo_mb', 512) . ' MB.',
            'archivos.*.uploaded' => 'Un archivo no se pudo subir. Puede que pese más de lo que permite el servidor (' . ini_get('upload_max_filesize') . ').',
        ]);

        try {
            foreach ($request->file('archivos') as $f) {
                ArchivosProyecto::guardar($proyecto, $f, $data['grupo'], $data['etapa_id'] ?? null, $data['seccion_id'] ?? null);
            }
        } catch (\App\Support\Dropbox\DropboxError $e) {
            return $request->expectsJson() ? response()->json(['message' => $e->getMessage()], 502) : back()->withErrors(['dropbox' => $e->getMessage()]);
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
        $visible = (bool) $request->validate(['visible' => 'required|boolean'])['visible'];
        try {
            ArchivosProyecto::cambiarVisibilidad($archivo, $visible);
        } catch (\App\Support\Dropbox\DropboxError $e) {
            return back()->withErrors(['dropbox' => $e->getMessage()]);
        }

        return back()->with('ok', $archivo->visible ? 'Ahora el cliente puede verlo.' : 'Oculto para el cliente.');
    }

    /* ---------------- Secciones de la galería ---------------- */

    public function nuevaSeccion(Request $request, Proyecto $proyecto)
    {
        $d = $request->validate(['nombre' => 'required|string|max:120'], ['nombre.required' => 'Ponle nombre a la sección.']);
        try {
            $s = \App\Support\Galeria::nueva($proyecto, $d['nombre']);
        } catch (\App\Support\Dropbox\DropboxError $e) {
            return back()->withErrors(['dropbox' => $e->getMessage()]);
        }
        return back()->with('ok', "Sección “{$s->nombre}” lista: lo que subas ahora cae ahí.");
    }

    public function seccion(Request $request, Proyecto $proyecto, \App\Models\GaleriaSeccion $seccion)
    {
        abort_unless($seccion->proyecto_id === $proyecto->id, 404);
        $d = $request->validate(['nombre' => 'nullable|string|max:120', 'mover' => 'nullable|in:arriba,abajo']);
        try {
            if (! empty($d['mover'])) \App\Support\Galeria::reordenar($seccion, $d['mover'] === 'arriba' ? 1 : -1);
            if (! empty($d['nombre'])) \App\Support\Galeria::renombrar($seccion, $d['nombre']);
        } catch (\App\Support\Dropbox\DropboxError $e) {
            return back()->withErrors(['dropbox' => $e->getMessage()]);
        }
        return back()->with('ok', ! empty($d['nombre']) ? 'Sección renombrada.' : 'Orden de las secciones actualizado.');
    }

    public function borrarSeccion(Proyecto $proyecto, \App\Models\GaleriaSeccion $seccion)
    {
        abort_unless($seccion->proyecto_id === $proyecto->id, 404);
        if ($seccion->archivos()->exists()) return back()->withErrors(['seccion' => 'Solo se pueden quitar secciones vacías. Mueve o elimina sus archivos primero.']);
        $seccion->delete();
        return back()->with('ok', "Se quitó la sección “{$seccion->nombre}”.");
    }

    public function moverArchivo(Request $request, Proyecto $proyecto, ProyectoArchivo $archivo)
    {
        abort_unless($archivo->proyecto_id === $proyecto->id && $archivo->grupo === 'galeria', 404);
        $d = $request->validate(['seccion_id' => ['required', Rule::exists('galeria_secciones', 'id')->where('proyecto_id', $proyecto->id)]]);
        $s = $proyecto->secciones()->findOrFail($d['seccion_id']);
        try {
            \App\Support\Galeria::mover($archivo, $s);
        } catch (\App\Support\Dropbox\DropboxError $e) {
            return back()->withErrors(['dropbox' => $e->getMessage()]);
        }
        return back()->with('ok', "Movido a “{$s->nombre}”.");
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
        $version = in_array($request->query('v'), ['vista', 'miniatura', 'correo']) ? $request->query('v') : 'original';

        return ArchivosProyecto::responder($archivo, $version, $request->boolean('descargar'));
    }
}
