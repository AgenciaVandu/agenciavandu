<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CorreoVandu;
use App\Models\Cliente;
use App\Models\Proyecto;
use App\Models\Tarea;
use App\Models\TareaArchivo;
use App\Models\User;
use App\Support\ArchivosProyecto;
use App\Support\Dropbox\Dropbox;
use App\Support\Dropbox\DropboxError;
use App\Support\Push\Notificar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Tareas del equipo. Quien tiene "Gestionar tareas" crea, asigna y ve todas;
 * los demás ven las suyas, las avanzan y suben su trabajo a la carpeta de Dropbox de cada una.
 */
class TareasController extends Controller
{
    /** Partes de hasta 4 MB: pasan por el servidor sin chocar con el límite de subida del hosting */
    public const PARTE = 4 * 1024 * 1024;

    /** Tamaño de cada parte según lo que acepte este servidor (upload_max_filesize / post_max_size) */
    public static function parte(): int
    {
        $bytes = function (string $v): int {
            $v = trim($v);
            if ($v === '' || $v === '0' || $v === '-1') return PHP_INT_MAX;
            $n = (int) $v;
            return match (strtolower(substr($v, -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
        };
        $limite = min($bytes((string) ini_get('upload_max_filesize')), $bytes((string) ini_get('post_max_size')) - 64 * 1024);
        return max(256 * 1024, min(self::PARTE, $limite - 16 * 1024));
    }

    public function index(Request $request)
    {
        $yo = $request->user();
        $gestiona = $yo->puede('tareas');
        $ver = $gestiona ? (string) $request->query('ver', 'todas') : 'mias';
        $terminadas = $request->query('estado') === 'terminadas';

        $q = Tarea::with(['cliente', 'proyecto.cliente', 'responsable'])->withCount('archivos');
        if ($ver === 'mias' || ! $gestiona) $q->where('asignada_a', $yo->id);
        elseif ($ver === 'sin') $q->whereNull('asignada_a');
        elseif (ctype_digit($ver)) $q->where('asignada_a', (int) $ver);

        $q = $terminadas
            ? $q->where('estado', 'terminada')->latest('terminada_at')->limit(60)
            : $q->abiertas()->orderByDesc('urgente')->orderByRaw('fecha_limite is null')->orderBy('fecha_limite')->orderBy('id');
        $tareas = $q->get();

        return view('admin.tareas.index', [
            'tareas'     => $tareas,
            'grupos'     => $terminadas ? null : collect(['revision', 'en_curso', 'pendiente'])->mapWithKeys(fn ($e) => [$e => $tareas->where('estado', $e)->values()]),
            'gestiona'   => $gestiona,
            'ver'        => $ver,
            'terminadas' => $terminadas,
            'equipo'     => $gestiona ? User::where('activo', true)->orderBy('name')->get() : collect(),
            'mias'       => Tarea::where('asignada_a', $yo->id)->abiertas()->count(),
        ]);
    }

    public function create(Request $request)
    {
        $this->gestiona($request);
        $t = new Tarea([
            'proyecto_id' => $request->integer('proyecto') ?: null,
            'cliente_id'  => $request->integer('cliente') ?: null,
            'asignada_a'  => $request->integer('para') ?: null,
            'estado'      => 'pendiente',
        ]);
        if ($t->proyecto_id && ! $t->cliente_id) $t->cliente_id = Proyecto::find($t->proyecto_id)?->cliente_id;
        return $this->formulario($t);
    }

    public function store(Request $request)
    {
        $this->gestiona($request);
        $t = new Tarea($this->validar($request) + ['creada_por' => $request->user()->id, 'estado' => 'pendiente']);
        $this->prepararCarpeta($t);
        $t->save();
        if ($t->asignada_a && $t->asignada_a !== $request->user()->id) $this->avisarAsignada($t, $request->boolean('correo', true));
        return redirect()->route('admin.tareas.show', $t)->with('ok', 'Tarea creada' . ($t->responsable ? ' y asignada a ' . $t->responsable->primer_nombre : '') . '.');
    }

    public function show(Request $request, Tarea $tarea)
    {
        abort_unless($tarea->puedeVer($request->user()), 403);
        $tarea->load(['cliente', 'proyecto.cliente', 'responsable', 'autor', 'aprobador', 'etapa', 'comentarios.user', 'archivos.user']);
        return view('admin.tareas.show', [
            't'          => $tarea,
            'gestiona'   => $request->user()->puede('tareas'),
            'mia'        => $tarea->asignada_a === $request->user()->id,
            'archivos'   => $this->archivos($tarea),
            'incluidos'  => $tarea->archivos->whereNotNull('proyecto_archivo_id')->values(),
            'etapasProyecto' => $tarea->proyecto?->etapas()->get() ?? collect(),
            'seccionesProyecto' => $tarea->proyecto?->secciones()->get() ?? collect(),
            'destinos'   => Tarea::DESTINOS,
            'dropbox'    => Dropbox::conectado(),
            'parte'      => self::parte(),
        ]);
    }

    public function edit(Request $request, Tarea $tarea)
    {
        $this->gestiona($request);
        return $this->formulario($tarea);
    }

    public function update(Request $request, Tarea $tarea)
    {
        $this->gestiona($request);
        $antes = $tarea->asignada_a;
        $tarea->fill($this->validar($request));
        $this->prepararCarpeta($tarea);
        $tarea->save();
        if ($tarea->asignada_a && $tarea->asignada_a !== $antes && $tarea->asignada_a !== $request->user()->id) $this->avisarAsignada($tarea->fresh(), $request->boolean('correo', true));
        return redirect()->route('admin.tareas.show', $tarea)->with('ok', 'Tarea guardada.');
    }

    public function destroy(Request $request, Tarea $tarea)
    {
        $this->gestiona($request);
        $tarea->delete();
        return redirect()->route('admin.tareas.index')->with('ok', "Se eliminó la tarea “{$tarea->titulo}”. Lo que se subió sigue en Dropbox.");
    }

    /** Avanzar la tarea: empezar, entregar a revisión, regresar a trabajo o terminar */
    public function estado(Request $request, Tarea $tarea)
    {
        $yo = $request->user();
        abort_unless($tarea->puedeVer($yo), 403);
        $gestiona = $yo->puede('tareas');
        $d = $request->validate(['estado' => ['required', Rule::in(array_keys(Tarea::ESTADOS))], 'nota' => 'nullable|string|max:2000']);
        $nuevo = $d['estado'];
        // Quien solo hace la tarea puede empezarla, entregarla o retomarla; terminarla la decide quien gestiona
        abort_if(! $gestiona && ($nuevo === 'terminada' || $tarea->estado === 'terminada'), 403, 'Quien gestiona las tareas es quien la da por terminada.');

        // Pedir ajustes abre una nueva vuelta: lo que se suba después cuenta como la siguiente entrega
        if ($tarea->estado === 'revision' && $nuevo === 'en_curso') $tarea->ronda++;
        $tarea->estado = $nuevo;
        if ($nuevo === 'revision') $tarea->entregada_at = now();
        $tarea->terminada_at = $nuevo === 'terminada' ? now() : null;
        $tarea->save();
        if (! empty($d['nota'])) $tarea->comentarios()->create(['user_id' => $yo->id, 'texto' => $d['nota']]);

        $quien = $yo->primer_nombre;
        if ($nuevo === 'revision') {
            $this->avisar($this->gestores($tarea, $yo), 'tarea_entregada', "$quien entregó: {$tarea->titulo}", $tarea->donde ?: 'Lista para revisar', $tarea);
        } elseif ($tarea->asignada_a && $tarea->asignada_a !== $yo->id && in_array($nuevo, ['en_curso', 'terminada'], true)) {
            $this->avisar([$tarea->asignada_a], 'tarea_comentario', $nuevo === 'terminada' ? "Tarea terminada: {$tarea->titulo}" : "Hay ajustes en: {$tarea->titulo}", $d['nota'] ?? ($nuevo === 'terminada' ? '¡Buen trabajo!' : 'Revisa los comentarios.'), $tarea);
        }

        $msg = ['pendiente' => 'Tarea marcada como pendiente.', 'en_curso' => $yo->id === $tarea->asignada_a ? 'Tarea en curso. ¡Éxito!' : 'La tarea regresó a “en curso”.', 'revision' => 'Entregada. Quien la gestiona ya recibió el aviso.', 'terminada' => 'Tarea terminada.'][$nuevo];
        return back()->with('ok', $msg);
    }

    public function comentar(Request $request, Tarea $tarea)
    {
        $yo = $request->user();
        abort_unless($tarea->puedeVer($yo), 403);
        $d = $request->validate(['texto' => 'required|string|max:2000'], ['texto.required' => 'Escribe tu comentario.']);
        $tarea->comentarios()->create(['user_id' => $yo->id, 'texto' => $d['texto']]);
        $para = $tarea->asignada_a === $yo->id ? $this->gestores($tarea, $yo) : array_filter([$tarea->asignada_a !== $yo->id ? $tarea->asignada_a : null]);
        $this->avisar($para, 'tarea_comentario', "{$yo->primer_nombre} comentó en: {$tarea->titulo}", Str::limit($d['texto'], 120), $tarea);
        return back()->with('ok', 'Comentario agregado.');
    }

    /* ---------------- Entregas a Dropbox ---------------- */

    /**
     * Subida en partes: el navegador manda cada parte al panel y el panel la pasa a Dropbox.
     * Así nadie del equipo recibe acceso a todo el Dropbox de la agencia.
     */
    public function subir(Request $request, Tarea $tarea)
    {
        $yo = $request->user();
        abort_unless($tarea->puedeVer($yo), 403);
        abort_unless($tarea->carpeta, 422, 'Esta tarea no tiene carpeta de entrega.');
        abort_unless(Dropbox::conectado(), 409, 'Dropbox no está conectado.');
        abort_if($tarea->estado === 'terminada' && ! $yo->puede('tareas'), 422, 'La tarea ya está terminada.');

        $d = $request->validate([
            'accion' => 'required|in:iniciar,agregar,terminar',
            'sesion' => 'nullable|required_unless:accion,iniciar|string|max:200',
            'offset' => 'nullable|required_unless:accion,iniciar|integer|min:0',
            'nombre' => 'nullable|required_if:accion,terminar|string|max:200',
            'parte'  => 'required|file|max:' . (intdiv(self::PARTE, 1024) + 64),
        ], ['parte.uploaded' => 'El servidor no aceptó esta parte del archivo. Intenta de nuevo.', 'parte.max' => 'La parte es demasiado grande para el servidor.']);
        $parte = file_get_contents($request->file('parte')->getRealPath());
        $dbx = Dropbox::cliente();

        try {
            if ($d['accion'] === 'iniciar' && ! $request->filled('nombre')) {
                return response()->json(['sesion' => $dbx->sesionIniciar($parte)]);
            }
            if ($d['accion'] === 'iniciar') {
                // Archivo de una sola parte
                $meta = $dbx->subirContenido($parte, $tarea->carpeta . '/' . Dropbox::nombreSeguro($request->input('nombre')));
            } elseif ($d['accion'] === 'agregar') {
                $dbx->sesionAgregar($d['sesion'], (int) $d['offset'], $parte);
                return response()->json(['ok' => true]);
            } else {
                $meta = $dbx->sesionTerminar($d['sesion'], (int) $d['offset'], $parte, $tarea->carpeta . '/' . Dropbox::nombreSeguro($d['nombre']));
            }
        } catch (DropboxError $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        $a = $tarea->archivos()->create([
            'user_id' => $yo->id, 'nombre' => $meta['name'], 'ruta' => $meta['path_display'],
            'dropbox_id' => $meta['id'] ?? null, 'tamano' => (int) ($meta['size'] ?? 0), 'ronda' => $tarea->ronda,
        ]);
        if ($tarea->estado === 'pendiente') $tarea->update(['estado' => 'en_curso']);
        Cache::forget('tarea.carpeta.' . $tarea->id);

        return response()->json(['ok' => true, 'archivo' => ['id' => $a->id, 'nombre' => $a->nombre, 'peso' => $a->peso]]);
    }

    public function verArchivo(Request $request, Tarea $tarea, string $id)
    {
        abort_unless($tarea->puedeVer($request->user()), 403);
        $ruta = $this->rutaEnCarpeta($tarea, $id);
        try {
            if ($request->query('t')) {
                $tam = $request->query('t') === 'g' ? 'w1024h768' : 'w256h256';
                $jpg = Cache::remember(\App\Support\Cuentas::clave("dropbox.mini.$tam." . md5($ruta)), now()->addHours(12), function () use ($ruta, $tam) {
                    try { return base64_encode(Dropbox::cliente()->miniatura($ruta, $tam)); } catch (DropboxError) { return ''; }
                });
                abort_if($jpg === '', 404);
                return response(base64_decode($jpg), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=86400']);
            }
            return redirect()->away(Dropbox::cliente()->enlaceTemporal($ruta));
        } catch (DropboxError $e) {
            abort(404, $e->getMessage());
        }
    }

    public function quitarArchivo(Request $request, Tarea $tarea, string $id)
    {
        $yo = $request->user();
        abort_unless($tarea->puedeVer($yo), 403);
        $ruta = $this->rutaEnCarpeta($tarea, $id);
        $registro = $tarea->archivos()->where('dropbox_id', $id)->first();
        // Cada quien quita lo suyo; quien gestiona puede quitar cualquiera
        abort_unless($yo->puede('tareas') || ($registro && $registro->user_id === $yo->id), 403, 'Solo puedes quitar los archivos que tú subiste.');
        try {
            Dropbox::cliente()->borrar($ruta);
        } catch (DropboxError $e) {
            return back()->withErrors(['archivo' => $e->getMessage()]);
        }
        $registro?->delete();
        Cache::forget('tarea.carpeta.' . $tarea->id);
        return back()->with('ok', 'Archivo quitado (queda unos días en la papelera de Dropbox).');
    }

    /**
     * Aprobar: lo elegido de la carpeta de la tarea pasa al proyecto (galería o documentos de su etapa),
     * la tarea se termina y, si así se pidió, la etapa del proyecto avanza.
     */
    public function aprobar(Request $request, Tarea $tarea)
    {
        $this->gestiona($request);
        abort_if($tarea->estado === 'terminada', 422, 'La tarea ya está terminada.');
        $yo = $request->user();
        $d = $request->validate([
            'ids'      => 'array|max:500',
            'ids.*'    => 'string|starts_with:id:',
            'publicar' => 'nullable|boolean',
            'nota'     => 'nullable|string|max:2000',
            'destino'  => ['nullable', Rule::in(array_keys(Tarea::DESTINOS))],
            'etapa_id' => ['nullable', Rule::exists('proyecto_etapas', 'id')->where('proyecto_id', (int) $tarea->proyecto_id)],
            'seccion'  => 'nullable|string|max:20',
            'seccion_nombre' => 'nullable|string|max:120',
        ]);
        // Al aprobar se puede ajustar a dónde va lo entregado
        if ($tarea->proyecto_id && $request->has('destino')) {
            $tarea->destino = $d['destino'] ?: null;
            $tarea->etapa_id = $d['etapa_id'] ?? null;
            $tarea->completar_etapa = $tarea->etapa_id && $request->boolean('completar_etapa');
            $tarea->save();
            $tarea->load('etapa');
        }
        $p = $tarea->proyecto;
        $ids = array_values(array_unique($d['ids'] ?? []));
        $incluidos = 0;
        $errores = [];

        if ($p && $tarea->destino && $ids) {
            abort_unless(Dropbox::conectado(), 409, 'Dropbox no está conectado.');
            $grupo = $tarea->destino === 'galeria' ? 'galeria' : 'documento';
            $visible = $grupo !== 'galeria' || $request->boolean('publicar', true);
            $etapa = $tarea->etapa;
            // En la galería, cada entrega va a su sección (por defecto una nueva con el nombre de la tarea)
            $seccion = null;
            if ($grupo === 'galeria') {
                $elegida = (string) ($d['seccion'] ?? 'nueva');
                $seccion = $elegida !== 'nueva' && ctype_digit($elegida) ? $p->secciones()->find((int) $elegida) : null;
                $seccion ??= \App\Support\Galeria::nueva($p, ($d['seccion_nombre'] ?? '') ?: $tarea->titulo);
            }
            $carpeta = $grupo === 'galeria'
                ? \App\Support\Galeria::carpeta($p, $seccion, $visible)
                : ArchivosProyecto::carpetaDestino($p, 'documento', $etapa);
            $dbx = Dropbox::cliente();
            $dbx->crearCarpeta($carpeta);
            foreach ($ids as $id) {
                try {
                    $ruta = $this->rutaEnCarpeta($tarea, $id);
                    $meta = $dbx->mover($ruta, $carpeta . '/' . basename($ruta));
                    $pa = ArchivosProyecto::registrarDropbox($p, $meta, $grupo, $grupo === 'documento' ? $etapa?->id : null, null, $visible, $seccion?->id);
                    $registro = $tarea->archivos()->where('dropbox_id', $id)->first()
                        ?? $tarea->archivos()->create(['user_id' => null, 'nombre' => $meta['name'], 'ruta' => $ruta, 'dropbox_id' => $id, 'tamano' => (int) ($meta['size'] ?? 0), 'ronda' => $tarea->ronda]);
                    $registro->update(['proyecto_archivo_id' => $pa->id, 'ruta' => $meta['path_display']]);
                    $incluidos++;
                } catch (Throwable $e) {
                    report($e);
                    $errores[] = basename($id);
                }
            }
            Cache::forget('tarea.carpeta.' . $tarea->id);
            if ($errores && ! $incluidos) return back()->withErrors(['aprobar' => 'No se pudo pasar ningún archivo al proyecto. Revisa la conexión con Dropbox e intenta de nuevo.']);
        }

        $tarea->forceFill(['estado' => 'terminada', 'terminada_at' => now(), 'aprobada_por' => $yo->id, 'entregada_at' => $tarea->entregada_at ?? now()])->save();

        // La etapa avanza sola, salvo que espere un pago
        $etapaMsg = '';
        if ($p && $tarea->completar_etapa && $tarea->etapa && $tarea->etapa->estado !== 'completada') {
            $pendientes = $p->load('pagos')->pagosQueBloquean($tarea->etapa);
            if ($pendientes->isEmpty()) {
                $tarea->etapa->update(['estado' => 'completada', 'completada_at' => now()]);
                $etapaMsg = " La etapa “{$tarea->etapa->nombre}” quedó completada.";
                if ($p->etapas()->where('estado', '!=', 'completada')->doesntExist() && $p->estado === 'activo') $p->update(['estado' => 'terminado']);
            } else {
                $etapaMsg = " La etapa “{$tarea->etapa->nombre}” no se marcó completada porque espera el pago de " . $pendientes->pluck('concepto')->join(', ', ' y ') . '.';
            }
        }

        $donde = $tarea->destino === 'galeria' ? 'a la galería' . (isset($seccion) && $seccion ? " (sección “{$seccion->nombre}”)" : '') . ($request->boolean('publicar', true) ? '' : ' sin publicar') : ($tarea->etapa ? "a los documentos de “{$tarea->etapa->nombre}”" : 'a los documentos');
        $resumen = $incluidos ? "Aprobada: $incluidos " . ($incluidos === 1 ? 'archivo pasó' : 'archivos pasaron') . " $donde del proyecto." : 'Tarea aprobada y terminada.';
        $tarea->comentarios()->create(['user_id' => $yo->id, 'texto' => trim('✓ ' . $resumen . (! empty($d['nota']) ? "\n" . $d['nota'] : ''))]);
        if ($tarea->asignada_a && $tarea->asignada_a !== $yo->id) {
            $this->avisar([$tarea->asignada_a], 'tarea_comentario', "Aprobada: {$tarea->titulo}", $d['nota'] ?? ($incluidos ? "$incluidos archivos ya están en el proyecto. ¡Buen trabajo!" : '¡Buen trabajo!'), $tarea);
        }
        return redirect()->route('admin.tareas.show', $tarea)->with('ok', $resumen . $etapaMsg . ($errores ? ' ' . count($errores) . ' no se pudieron pasar; siguen en la carpeta de la tarea.' : ''));
    }

    /** Crea una carpeta nueva dentro de otra (desde el selector de carpeta) */
    public function carpeta(Request $request)
    {
        $this->gestiona($request);
        abort_unless(Dropbox::conectado(), 409);
        $d = $request->validate(['en' => 'required|string|max:400', 'nombre' => 'required|string|max:90']);
        $ruta = rtrim('/' . trim($d['en'], '/'), '/') . '/' . Dropbox::nombreSeguro($d['nombre']);
        try {
            Dropbox::cliente()->crearCarpeta($ruta);
        } catch (DropboxError $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['ruta' => $ruta]);
    }

    /* ---------------- Apoyo ---------------- */

    private function gestiona(Request $request): void
    {
        abort_unless($request->user()->puede('tareas'), 403, 'Tu rol no puede crear ni editar tareas.');
    }

    private function formulario(Tarea $t)
    {
        $proyectos = Proyecto::with('cliente')->where('estado', 'activo')->orWhere('id', $t->proyecto_id)->latest()->get();
        $raiz = Dropbox::raiz();
        $proyectos->load('etapas');
        if (! $t->exists && $t->proyecto_id && ! $t->destino) {
            $p = $proyectos->firstWhere('id', $t->proyecto_id);
            $t->destino = $p && $p->tiene_galeria ? 'galeria' : 'documento';
        }
        return view('admin.tareas.form', [
            't'         => $t,
            'etapasPorProyecto' => $proyectos->mapWithKeys(fn ($p) => [$p->id => [
                'galeria' => (bool) $p->tiene_galeria,
                'etapas'  => $p->etapas->map(fn ($e) => ['id' => (string) $e->id, 'nombre' => $e->nombre, 'estado' => $e->estado])->values(),
            ]]),
            'clientes'  => Cliente::orderByRaw("coalesce(nullif(empresa, ''), nombre)")->get(['id', 'nombre', 'empresa']),
            'proyectos' => $proyectos,
            'equipo'    => User::with('rol')->where('activo', true)->orderBy('name')->get(),
            'dropbox'   => Dropbox::conectado(),
            // Carpeta sugerida según el proyecto o el cliente
            'bases'     => [
                'proyectos' => $proyectos->mapWithKeys(fn ($p) => [$p->id => ['ruta' => ArchivosProyecto::carpetaProyecto($p), 'cliente' => $p->cliente_id]]),
                'clientes'  => Cliente::get(['id', 'nombre', 'empresa'])->mapWithKeys(fn ($c) => [$c->id => $raiz . '/Clientes/' . Dropbox::nombreSeguro($c->empresa ?: $c->nombre)]),
                'raiz'      => $raiz . '/Equipo',
                'inicio'    => $raiz,
            ],
        ]);
    }

    private function validar(Request $request): array
    {
        $d = $request->validate([
            'titulo'       => 'required|string|max:160',
            'descripcion'  => 'nullable|string|max:5000',
            'cliente_id'   => ['nullable', Rule::exists('clientes', 'id')->where('cuenta_id', \App\Support\Cuentas::id())],
            'proyecto_id'  => ['nullable', Rule::exists('proyectos', 'id')->where('cuenta_id', \App\Support\Cuentas::id())],
            'asignada_a'   => ['nullable', Rule::exists('users', 'id')->where('activo', true)->where('cuenta_id', \App\Support\Cuentas::id())],
            'fecha_limite' => 'nullable|date',
            'carpeta'      => ['nullable', 'string', 'max:480', 'regex:/^\/[^\\\\<>:"|?*]*$/'],
        ], [
            'titulo.required' => 'Escribe qué hay que hacer.',
            'carpeta.regex'   => 'La carpeta debe empezar con “/” y no llevar caracteres raros.',
        ]);
        $d['urgente'] = $request->boolean('urgente');
        if (! empty($d['proyecto_id']) && empty($d['cliente_id'])) $d['cliente_id'] = Proyecto::find($d['proyecto_id'])?->cliente_id;
        // Qué pasa con lo entregado al aprobar: solo aplica si la tarea es de un proyecto
        $extra = $request->validate([
            'destino'  => ['nullable', Rule::in(array_keys(Tarea::DESTINOS))],
            'etapa_id' => ['nullable', Rule::exists('proyecto_etapas', 'id')->where('proyecto_id', (int) ($d['proyecto_id'] ?? 0))],
        ], ['etapa_id.exists' => 'Esa etapa no es del proyecto elegido.']);
        $d['destino'] = ! empty($d['proyecto_id']) ? ($extra['destino'] ?? null) : null;
        $d['etapa_id'] = ! empty($d['proyecto_id']) ? ($extra['etapa_id'] ?? null) : null;
        $d['completar_etapa'] = $d['etapa_id'] ? $request->boolean('completar_etapa') : false;
        if (! empty($d['carpeta'])) $d['carpeta'] = rtrim(preg_replace('#/+#', '/', $d['carpeta']), '/') ?: null;
        return $d;
    }

    private function prepararCarpeta(Tarea $t): void
    {
        if (! $t->carpeta || ! $t->isDirty('carpeta') || ! Dropbox::conectado()) return;
        try { Dropbox::cliente()->crearCarpeta($t->carpeta); } catch (Throwable $e) { report($e); }
    }

    /** Lo que hay en la carpeta de la tarea (incluye lo que se haya puesto directo en Dropbox) */
    private function archivos(Tarea $t): array
    {
        $propios = $t->archivos->keyBy('dropbox_id');
        if (! $t->carpeta || ! Dropbox::conectado()) {
            return $t->archivos->whereNull('proyecto_archivo_id')->map(fn ($a) => ['id' => $a->dropbox_id, 'nombre' => $a->nombre, 'peso' => $a->peso, 'tipo' => $a->tipo, 'quien' => $a->user?->primer_nombre, 'cuando' => $a->created_at, 'mio' => $a->user_id, 'ronda' => $a->ronda])->values()->all();
        }
        try {
            $entradas = Cache::remember('tarea.carpeta.' . $t->id, now()->addSeconds(30), fn () => Dropbox::cliente()->listar($t->carpeta));
        } catch (DropboxError $e) {
            return ['error' => $e->getMessage()];
        }
        return collect($entradas)->filter(fn ($e) => ($e['.tag'] ?? '') === 'file' && ! str_starts_with($e['name'], '.'))
            ->sortByDesc(fn ($e) => $e['server_modified'] ?? '')
            ->map(function ($e) use ($propios) {
                $a = $propios->get($e['id']);
                $tmp = new TareaArchivo(['nombre' => $e['name'], 'tamano' => (int) ($e['size'] ?? 0)]);
                return [
                    'id' => $e['id'], 'nombre' => $e['name'], 'peso' => $tmp->peso, 'tipo' => $tmp->tipo,
                    'quien' => $a?->user?->primer_nombre, 'cuando' => $a?->created_at ?? (isset($e['server_modified']) ? \Illuminate\Support\Carbon::parse($e['server_modified']) : null),
                    'mio' => $a?->user_id, 'ronda' => $a?->ronda ?? 1,
                ];
            })->values()->all();
    }

    /** Ruta de un archivo, solo si está dentro de la carpeta de la tarea */
    private function rutaEnCarpeta(Tarea $t, string $id): string
    {
        abort_unless($t->carpeta && Dropbox::conectado() && str_starts_with($id, 'id:'), 404);
        try {
            $meta = Dropbox::cliente()->metadata($id);
        } catch (DropboxError) {
            abort(404);
        }
        abort_unless(str_starts_with(mb_strtolower($meta['path_display']), mb_strtolower($t->carpeta . '/')), 404);
        return $meta['path_display'];
    }

    /** Quien creó la tarea y quienes gestionan tareas (sin incluir a quien hace la acción) */
    private function gestores(Tarea $t, User $yo): array
    {
        $ids = User::where('activo', true)->with('rol')->get()->filter(fn ($u) => $u->puede('tareas'))->pluck('id');
        if ($t->creada_por) $ids->push($t->creada_por);
        return $ids->unique()->reject(fn ($id) => $id === $yo->id)->values()->all();
    }

    private function avisar(array $usuarios, string $evento, string $titulo, ?string $cuerpo, Tarea $t): void
    {
        if ($usuarios) Notificar::aUsuarios($usuarios, $evento, $titulo, $cuerpo, route('admin.tareas.show', $t), 'tarea-' . $t->id);
    }

    private function avisarAsignada(Tarea $t, bool $correo): void
    {
        $u = $t->responsable;
        if (! $u) return;
        $cuando = $t->cuando ? " · Para: {$t->cuando}" : '';
        $this->avisar([$u->id], 'tarea_asignada', 'Nueva tarea: ' . $t->titulo, ($t->donde ?: 'Te la asignaron') . $cuando, $t);
        if (! $correo) return;
        try {
            Mail::to($u->email, $u->name)->send(new CorreoVandu(
                asunto: 'Nueva tarea: ' . $t->titulo,
                titulo: $t->titulo,
                cuerpo: "Hola {$u->primer_nombre}, te asignaron una tarea" . ($t->donde ? " de {$t->donde}" : '') . '.' . ($t->descripcion ? "\n\n" . $t->descripcion : '') . ($t->carpeta ? "\n\nSube tu trabajo desde el panel; se guarda solo en la carpeta del cliente." : ''),
                boton: 'Ver la tarea',
                url: route('admin.tareas.show', $t),
                resumen: array_filter(['Para' => $t->fecha_limite ? ucfirst($t->fecha_limite->locale('es')->isoFormat('dddd D [de] MMMM')) : null, 'Prioridad' => $t->urgente ? 'Urgente' : null]),
            ));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
