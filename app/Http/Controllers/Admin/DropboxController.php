<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClienteConstancia;
use App\Models\Integracion;
use App\Models\Proyecto;
use App\Models\ProyectoArchivo;
use App\Support\ArchivosProyecto;
use App\Support\Dropbox\Dropbox;
use App\Support\Dropbox\DropboxError;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DropboxController extends Controller
{
    /** Estado de la conexión */
    public function index()
    {
        return view('admin.dropbox', [
            'configurado' => Dropbox::configurado(),
            'conectado'   => Dropbox::conectado(),
            'integracion' => Integracion::de('dropbox'),
            'raiz'        => Dropbox::raiz(),
            'enDropbox'   => ProyectoArchivo::where('origen', 'dropbox')->count() + ClienteConstancia::where('origen', 'dropbox')->count(),
            'locales'     => ProyectoArchivo::where('origen', 'local')->count() + ClienteConstancia::where('origen', 'local')->count(),
            'simulado'    => (bool) config('vandu.dropbox.simulado'),
        ]);
    }

    /** Inicia la conexión y recibe la respuesta de Dropbox */
    public function conectar(Request $request)
    {
        abort_unless(Dropbox::configurado(), 404);

        if ($request->filled('error')) {
            return redirect()->route('admin.dropbox')->withErrors(['dropbox' => 'Cancelaste la conexión con Dropbox.']);
        }
        if (! $request->filled('code')) {
            $estado = Str::random(40);
            $request->session()->put('dropbox_estado', $estado);
            return redirect()->away(Dropbox::urlAutorizar($estado));
        }
        abort_unless(hash_equals((string) $request->session()->pull('dropbox_estado'), (string) $request->query('state')), 403);

        try {
            $int = Dropbox::cliente()->conectar($request->query('code'));
            Dropbox::cliente()->crearCarpeta(Dropbox::raiz());
        } catch (DropboxError $e) {
            return redirect()->route('admin.dropbox')->withErrors(['dropbox' => $e->getMessage()]);
        }
        return redirect()->route('admin.dropbox')->with('ok', 'Dropbox conectado' . ($int->cuenta ? " ({$int->cuenta})" : '') . '.');
    }

    public function desconectar()
    {
        Dropbox::cliente()->desconectar();
        return redirect()->route('admin.dropbox')->with('ok', 'Dropbox desconectado. Los archivos siguen en tu Dropbox.');
    }

    /** Token temporal para que el navegador suba directo a Dropbox */
    public function token()
    {
        abort_unless(Dropbox::conectado(), 409, 'Dropbox no está conectado.');
        return response()->json(Dropbox::cliente()->tokenNavegador())->header('Cache-Control', 'no-store');
    }

    /** Carpeta de Dropbox donde va un archivo nuevo del proyecto */
    public function destino(Request $request, Proyecto $proyecto)
    {
        abort_unless(Dropbox::conectado(), 409, 'Dropbox no está conectado.');
        $d = $request->validate([
            'grupo'    => 'required|in:documento,galeria',
            'etapa_id' => ['nullable', Rule::exists('proyecto_etapas', 'id')->where('proyecto_id', $proyecto->id)],
        ]);
        $etapa = ! empty($d['etapa_id']) ? $proyecto->etapas()->find($d['etapa_id']) : null;
        return response()->json(['carpeta' => ArchivosProyecto::carpetaDestino($proyecto, $d['grupo'], $etapa)]);
    }

    /** El navegador terminó de subir un archivo a Dropbox: lo damos de alta en el panel */
    public function registrar(Request $request, Proyecto $proyecto)
    {
        $d = $request->validate([
            'grupo'      => 'required|in:documento,galeria',
            'etapa_id'   => ['nullable', Rule::exists('proyecto_etapas', 'id')->where('proyecto_id', $proyecto->id)],
            'dropbox_id' => 'required|string|max:120|starts_with:id:',
            'poster'     => 'nullable|file|mimes:jpg,jpeg,png,webp|max:8192',
        ]);
        $meta = Dropbox::cliente()->metadata($d['dropbox_id']);
        // Solo aceptamos archivos que estén dentro de la carpeta de este proyecto
        abort_unless(str_starts_with(mb_strtolower($meta['path_display']), mb_strtolower(ArchivosProyecto::carpetaProyecto($proyecto) . '/')), 422, 'El archivo no está en la carpeta del proyecto.');

        $a = ArchivosProyecto::registrarDropbox($proyecto, $meta, $d['grupo'], $d['etapa_id'] ?? null,
            $request->hasFile('poster') ? file_get_contents($request->file('poster')->getRealPath()) : null);

        return response()->json(['ok' => true, 'id' => $a->id]);
    }

    /** Busca en Galería y No publicado lo que agregaste o quitaste directo en Dropbox */
    public function sincronizar(Proyecto $proyecto)
    {
        try {
            $r = ArchivosProyecto::sincronizar($proyecto);
        } catch (DropboxError $e) {
            return back()->withErrors(['dropbox' => $e->getMessage()]);
        }
        if (! empty($r['sin_carpeta'])) {
            return back()->withErrors(['dropbox' => 'No encontré la carpeta Galería del proyecto en Dropbox (' . ArchivosProyecto::carpetaGaleria($proyecto) . '). Si la renombraste, regrésale su nombre.']);
        }
        $msg = $r['nuevos'] || $r['quitados']
            ? trim(($r['nuevos'] ? "{$r['nuevos']} nuevos" : '') . ($r['nuevos'] && $r['quitados'] ? ' · ' : '') . ($r['quitados'] ? "{$r['quitados']} quitados" : ''))
            : 'Todo al día';
        return back()->with('ok', "Galería sincronizada con Dropbox: $msg.");
    }

    /** Explorador de carpetas para importar */
    public function explorar(Request $request)
    {
        abort_unless(Dropbox::conectado(), 409);
        $ruta = '/' . trim((string) $request->query('ruta', Dropbox::raiz()), '/');
        $ruta = $ruta === '/' ? '/' : $ruta;

        try {
            $entradas = Dropbox::cliente()->listar($ruta);
        } catch (DropboxError $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
        // Ojo: ".tag" no se puede usar con where() porque Laravel lo toma como ruta con puntos
        $carpetas = collect($entradas)->filter(fn ($e) => ($e['.tag'] ?? '') === 'folder')->sortBy(fn ($e) => mb_strtolower($e['name']))
            ->map(fn ($e) => ['nombre' => $e['name'], 'ruta' => $e['path_display']])->values();
        $archivos = collect($entradas)->filter(fn ($e) => ($e['.tag'] ?? '') === 'file')->reject(fn ($e) => str_starts_with($e['name'], '.'))
            ->sortBy(fn ($e) => mb_strtolower($e['name']))->map(function ($e) {
                $mime = ArchivosProyecto::mimeDe($e['name']);
                return [
                    'nombre' => $e['name'], 'ruta' => $e['path_display'], 'id' => $e['id'],
                    'peso'   => self::peso((int) ($e['size'] ?? 0)),
                    'tipo'   => str_starts_with($mime, 'image/') ? 'foto' : (str_starts_with($mime, 'video/') ? 'video' : 'archivo'),
                ];
            })->values();

        $migas = [['nombre' => 'Dropbox', 'ruta' => '/']];
        $acum = '';
        foreach (array_filter(explode('/', $ruta)) as $parte) {
            $acum .= '/' . $parte;
            $migas[] = ['nombre' => $parte, 'ruta' => $acum];
        }

        return response()->json(compact('ruta', 'carpetas', 'archivos', 'migas'));
    }

    /** Mueve archivos de otra carpeta de Dropbox a la Galería del proyecto y los da de alta (en tandas) */
    public function importar(Request $request, Proyecto $proyecto)
    {
        $d = $request->validate(['ids' => 'required|array|min:1|max:10', 'ids.*' => 'string|starts_with:id:']);
        $dbx = Dropbox::cliente();
        $galeria = ArchivosProyecto::carpetaDestino($proyecto, 'galeria');
        $hechos = 0;
        $errores = [];
        foreach ($d['ids'] as $id) {
            try {
                $meta = $dbx->metadata($id);
                if (! str_starts_with(mb_strtolower($meta['path_display']), mb_strtolower($galeria . '/'))) {
                    $meta = $dbx->mover($id, $galeria . '/' . $meta['name']);
                }
                ArchivosProyecto::registrarDropbox($proyecto, $meta, 'galeria');
                $hechos++;
            } catch (\Throwable $e) {
                report($e);
                $errores[] = $e->getMessage();
            }
        }
        return response()->json(['ok' => ! $errores, 'importados' => $hechos, 'errores' => $errores]);
    }

    /** Solo pruebas locales */
    public function simulado(Request $request, string $id)
    {
        abort_unless(config('vandu.dropbox.simulado'), 404);
        return Dropbox::cliente()->responder($id, $request->boolean('zip'));
    }

    /** Solo pruebas locales: imita los endpoints de subida de Dropbox para el navegador */
    public function simuladoApi(Request $request, string $endpoint)
    {
        abort_unless(config('vandu.dropbox.simulado') && app()->environment('local', 'testing'), 404);
        $arg = json_decode($request->header('Dropbox-API-Arg', '{}'), true) ?: [];
        $cuerpo = $request->getContent();
        $dir = \App\Support\Dropbox\DropboxSimulado::base() . '/.sesiones';
        if (! is_dir($dir)) mkdir($dir, 0775, true);
        $dbx = Dropbox::cliente();
        $r = match ($endpoint) {
            'files/upload' => $dbx->subirContenido($cuerpo, $arg['path']),
            'files/upload_session/start' => (function () use ($dir, $cuerpo) { $id = Str::random(12); file_put_contents("$dir/$id", $cuerpo); return ['session_id' => $id]; })(),
            'files/upload_session/append_v2' => (function () use ($dir, $cuerpo, $arg) {
                $f = "$dir/{$arg['cursor']['session_id']}"; abort_unless(filesize($f) === $arg['cursor']['offset'], 409, 'offset');
                file_put_contents($f, $cuerpo, FILE_APPEND); return null; })(),
            'files/upload_session/finish' => (function () use ($dir, $cuerpo, $arg, $dbx) {
                $f = "$dir/{$arg['cursor']['session_id']}"; abort_unless(filesize($f) === $arg['cursor']['offset'], 409, 'offset');
                file_put_contents($f, $cuerpo, FILE_APPEND); $m = $dbx->subirArchivo($f, $arg['commit']['path']); unlink($f); return $m; })(),
            default => abort(404),
        };
        return response()->json($r);
    }

    private static function peso(int $b): string
    {
        return match (true) {
            $b >= 1073741824 => number_format($b / 1073741824, 1) . ' GB',
            $b >= 1048576    => number_format($b / 1048576, 1) . ' MB',
            default          => max(1, round($b / 1024)) . ' KB',
        };
    }
}
