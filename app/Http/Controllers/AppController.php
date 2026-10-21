<?php

namespace App\Http\Controllers;

/**
 * El panel como app instalable (PWA): manifiesto, service worker, íconos y pantalla sin conexión.
 * Se sirven desde Laravel (no desde public/) para que se actualicen con git pull.
 */
class AppController extends Controller
{
    public const VERSION = '2026-10-21.2'; // súbela cuando cambien los archivos que guarda el service worker

    /** El negocio de quien instala: el del dominio propio o el de su usuario (el manifiesto no pasa por el middleware del panel) */
    private function cuenta(): ?\App\Models\Cuenta
    {
        return request()->attributes->get('cuenta_dominio')
            ?? (auth()->user()?->cuenta_id ? \App\Models\Cuenta::find(auth()->user()->cuenta_id) : null);
    }

    public function manifiesto()
    {
        $c = $this->cuenta();
        $nombre = \App\Support\IconoApp::nombre($c);
        return response()->json([
            'name'             => $nombre,
            'short_name'       => \Illuminate\Support\Str::limit($nombre, 12, ''),
            'description'      => 'El panel de tu negocio en ' . config('vandu.plataforma.nombre'),
            'id'               => '/admin',
            'start_url'        => '/admin',
            'scope'            => '/admin',
            'display'          => 'standalone',
            'orientation'      => 'any',
            'background_color' => '#1A103D',
            'theme_color'      => '#1A103D',
            'lang'             => 'es-MX',
            'icons' => [
                ['src' => \App\Support\IconoApp::url($c, 'icono-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => \App\Support\IconoApp::url($c, 'icono-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => \App\Support\IconoApp::url($c, 'icono-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Nueva cotización', 'url' => '/admin/presupuestos/create', 'icons' => [['src' => \App\Support\IconoApp::url($c, 'icono-192.png'), 'sizes' => '192x192']]],
                ['name' => 'Proyectos', 'url' => '/admin/proyectos'],
                ['name' => 'Finanzas', 'url' => '/admin/finanzas'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'private, max-age=3600']);   // cambia por negocio
    }

    public function icono(string $archivo)
    {
        // ?c= el negocio: su propio ícono si lo subió en "Mi negocio"
        $c = request()->integer('c') ? \App\Models\Cuenta::find(request()->integer('c')) : null;
        if ($c && ($propia = \App\Support\IconoApp::ruta($c, basename($archivo)))) {
            return response()->file($propia, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=31536000, immutable']);
        }
        $ruta = resource_path('img/app/' . basename($archivo));
        abort_unless(is_file($ruta), 404);
        return response()->file($ruta, ['Content-Type' => 'image/png', 'Cache-Control' => request()->has('v') ? 'public, max-age=31536000, immutable' : 'public, max-age=3600']);
    }

    public function sinConexion()
    {
        return response()->view('admin.sin-conexion')->header('Cache-Control', 'public, max-age=86400');
    }

    public function serviceWorker()
    {
        $js = view('admin.sw', [
            'version' => self::VERSION,
            'guardar' => [
                route('app.sin-conexion'),
                route('vandu.fuente'),
                \App\Support\IconoApp::url(null, 'icono-192.png'),
                'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
                'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css',
                'https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js',
                'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
            ],
        ])->render();

        return response($js, 200, [
            'Content-Type'           => 'application/javascript; charset=utf-8',
            'Cache-Control'          => 'no-cache',
            'Service-Worker-Allowed' => '/admin',
        ]);
    }
}
