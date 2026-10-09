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

    /** Convierte una cotización (aceptada) en proyecto */
    public function store(Request $request, Presupuesto $presupuesto)
    {
        if ($presupuesto->proyecto) {
            return redirect()->route('admin.proyectos.show', $presupuesto->proyecto);
        }

        $data = $request->validate([
            'tipo'         => ['required', Rule::in(array_keys(config('vandu.proyectos')))],
            'fecha_inicio' => 'nullable|date',
        ]);

        $inicio = isset($data['fecha_inicio']) ? Carbon::parse($data['fecha_inicio'], config('vandu.zona_horaria')) : null;
        $proyecto = Proyecto::desdePresupuesto($presupuesto, $data['tipo'], $inicio);

        if ($presupuesto->estado !== 'aceptada') {
            $presupuesto->update(['estado' => 'aceptada']);
        }

        return redirect()->route('admin.proyectos.show', $proyecto)
            ->with('ok', "Proyecto creado a partir de {$presupuesto->folio}. Revisa las fechas propuestas.");
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
        ], ['fecha_fin.after_or_equal' => 'La fecha final no puede ser antes del inicio.']);

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
