<?php

namespace App\Http\Controllers;

/**
 * El panel como app instalable (PWA): manifiesto, service worker, íconos y pantalla sin conexión.
 * Se sirven desde Laravel (no desde public/) para que se actualicen con git pull.
 */
class AppController extends Controller
{
    public const VERSION = '2026-10-09.3'; // súbela cuando cambien los archivos que guarda el service worker

    public function manifiesto()
    {
        return response()->json([
            'name'             => 'Vandu · Panel',
            'short_name'       => 'Vandu',
            'description'      => 'Cotizaciones, proyectos y entregas de Agencia Vandu',
            'id'               => '/admin',
            'start_url'        => '/admin',
            'scope'            => '/admin',
            'display'          => 'standalone',
            'orientation'      => 'any',
            'background_color' => '#13161D',
            'theme_color'      => '#13161D',
            'lang'             => 'es-MX',
            'icons' => [
                ['src' => route('app.icono', 'icono-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => route('app.icono', 'icono-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => route('app.icono', 'icono-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Nueva cotización', 'url' => '/admin/presupuestos/create', 'icons' => [['src' => route('app.icono', 'icono-192.png'), 'sizes' => '192x192']]],
                ['name' => 'Proyectos', 'url' => '/admin/proyectos'],
                ['name' => 'Finanzas', 'url' => '/admin/finanzas'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function icono(string $archivo)
    {
        $ruta = resource_path('img/app/' . basename($archivo));
        abort_unless(is_file($ruta), 404);
        return response()->file($ruta, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=604800']);
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
                route('app.icono', 'icono-192.png'),
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
