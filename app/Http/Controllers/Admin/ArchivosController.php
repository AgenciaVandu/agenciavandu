<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proyecto;
use App\Support\ArchivosProyecto;
use App\Support\Dropbox\Dropbox;
use App\Support\Dropbox\DropboxError;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/** Ver lo que hay en tu Dropbox sin salir del panel: carpetas, miniaturas, fotos, videos y PDF */
class ArchivosController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.archivos', [
            'conectado' => Dropbox::conectado(),
            'raiz'      => Dropbox::raiz(),
            'inicio'    => $this->rutaLimpia($request->query('ruta', Dropbox::raiz())),
        ]);
    }

    public function listar(Request $request)
    {
        abort_unless(Dropbox::conectado(), 409);
        $ruta = $this->rutaLimpia($request->query('ruta', Dropbox::raiz()));

        try {
            $entradas = Dropbox::cliente()->listar($ruta);
        } catch (DropboxError $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $proyectos = $this->proyectos();
        return response()->json([
            'ruta'     => $ruta,
            'migas'    => $this->migas($ruta),
            'proyecto' => $this->proyectoDe($ruta, $proyectos),
        ] + $this->formatear($entradas, $proyectos));
    }

    public function buscar(Request $request)
    {
        abort_unless(Dropbox::conectado(), 409);
        $q = trim((string) $request->query('q'));
        abort_if(mb_strlen($q) < 2, 422);
        $ruta = $request->boolean('todo') ? '/' : Dropbox::raiz();

        try {
            $entradas = Dropbox::cliente()->buscar($q, $ruta);
        } catch (DropboxError $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['q' => $q] + $this->formatear($entradas, $this->proyectos(), true));
    }

    /** Miniatura de una foto (Dropbox la genera; se guarda unas horas para que la vista cargue rápido) */
    public function miniatura(Request $request)
    {
        abort_unless(Dropbox::conectado(), 409);
        $id = $this->id($request);
        $tam = $request->query('t') === 'g' ? 'w1024h768' : 'w256h256';

        $jpg = Cache::remember(\App\Support\Cuentas::clave("dropbox.mini.$tam." . md5($id)), now()->addHours(12), function () use ($id, $tam) {
            try { return base64_encode(Dropbox::cliente()->miniatura($id, $tam)); } catch (DropboxError) { return ''; }
        });
        abort_if($jpg === '', 404);

        return response(base64_decode($jpg), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=86400']);
    }

    /** Abre el archivo (lo sirve Dropbox con un enlace temporal; no pasa por el servidor) */
    public function ver(Request $request)
    {
        abort_unless(Dropbox::conectado(), 409);
        try {
            return redirect()->away(Dropbox::cliente()->enlaceTemporal($this->id($request)));
        } catch (DropboxError $e) {
            abort(404, $e->getMessage());
        }
    }

    // ---------- ayudas ----------

    private function formatear(array $entradas, $proyectos, bool $conCarpeta = false): array
    {
        $carpetas = collect($entradas)->filter(fn ($e) => ($e['.tag'] ?? '') === 'folder')
            ->sortBy(fn ($e) => mb_strtolower($e['name']))
            ->map(fn ($e) => array_filter([
                'nombre'   => $e['name'],
                'ruta'     => $e['path_display'],
                'proyecto' => $proyectos->get(mb_strtolower($e['path_display'])),
                'en'       => $conCarpeta ? self::relativa(dirname($e['path_display'])) : null,
            ]))->values();

        $archivos = collect($entradas)->filter(fn ($e) => ($e['.tag'] ?? '') === 'file')
            ->reject(fn ($e) => str_starts_with($e['name'], '.'))
            ->sortBy(fn ($e) => mb_strtolower($e['name']))
            ->map(function ($e) use ($conCarpeta) {
                $mime = ArchivosProyecto::mimeDe($e['name']);
                $ext = strtolower(pathinfo($e['name'], PATHINFO_EXTENSION));
                $tipo = match (true) {
                    str_starts_with($mime, 'image/') && ! in_array($ext, ['heic', 'svg'], true) => 'foto',
                    str_starts_with($mime, 'video/') => 'video',
                    $ext === 'pdf' => 'pdf',
                    default => 'archivo',
                };
                return array_filter([
                    'id'     => $e['id'],
                    'nombre' => $e['name'],
                    'ext'    => $ext,
                    'tipo'   => $tipo,
                    'bytes'  => (int) ($e['size'] ?? 0),
                    'peso'   => self::peso((int) ($e['size'] ?? 0)),
                    'fecha'  => isset($e['server_modified'])
                        ? Carbon::parse($e['server_modified'])->setTimezone(config('vandu.zona_horaria'))->locale('es')->isoFormat('D MMM YYYY')
                        : null,
                    'en'     => $conCarpeta ? self::relativa(dirname($e['path_display'])) : null,
                ], fn ($v) => $v !== null);
            })->values();

        return [
            'carpetas' => $carpetas,
            'archivos' => $archivos,
            'total'    => self::peso($archivos->sum('bytes')),
        ];
    }

    /** Carpetas de Dropbox que pertenecen a un proyecto del panel */
    private function proyectos()
    {
        return Proyecto::whereNotNull('dropbox_carpeta')->with('cliente')->get(['id', 'nombre', 'cliente_id', 'dropbox_carpeta'])
            ->mapWithKeys(fn ($p) => [mb_strtolower(rtrim($p->dropbox_carpeta, '/')) => [
                'nombre' => $p->nombre, 'cliente' => $p->cliente?->empresa ?: $p->cliente?->nombre, 'url' => route('admin.proyectos.show', $p),
            ]]);
    }

    private function proyectoDe(string $ruta, $proyectos): ?array
    {
        $r = mb_strtolower($ruta);
        foreach ($proyectos as $carpeta => $p) {
            if ($r === $carpeta || str_starts_with($r, $carpeta . '/')) return $p;
        }
        return null;
    }

    private function migas(string $ruta): array
    {
        $migas = [['nombre' => 'Dropbox', 'ruta' => '/']];
        $acum = '';
        foreach (array_filter(explode('/', $ruta), 'strlen') as $parte) {
            $acum .= '/' . $parte;
            $migas[] = ['nombre' => $parte, 'ruta' => $acum];
        }
        return $migas;
    }

    private function rutaLimpia(?string $ruta): string
    {
        $r = '/' . trim(str_replace('\\', '/', (string) $ruta), '/');
        return str_contains($r, '/..') ? Dropbox::raiz() : $r;
    }

    private function id(Request $request): string
    {
        $id = (string) $request->query('id');
        abort_unless(preg_match('/^id:[A-Za-z0-9_-]+$/', $id), 404);
        return $id;
    }

    /** "/Vandu/Proyectos/X" → "Proyectos / X" (para mostrar dónde está un resultado de búsqueda) */
    private static function relativa(string $ruta): string
    {
        $raiz = Dropbox::raiz();
        $r = str_starts_with(mb_strtolower($ruta), mb_strtolower($raiz)) ? mb_substr($ruta, mb_strlen($raiz)) : $ruta;
        return trim(str_replace('/', ' / ', trim($r, '/'))) ?: trim($raiz, '/');
    }

    public static function peso(int $b): string
    {
        return match (true) {
            $b >= 1073741824 => number_format($b / 1073741824, 1) . ' GB',
            $b >= 1048576    => number_format($b / 1048576, 1) . ' MB',
            $b >= 1024       => number_format($b / 1024) . ' KB',
            default          => $b . ' B',
        };
    }
}
