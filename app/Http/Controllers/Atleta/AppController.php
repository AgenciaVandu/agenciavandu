<?php

namespace App\Http\Controllers\Atleta;

use App\Http\Controllers\Controller;
use App\Models\Cuenta;
use App\Support\Fitness\Portal;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** El portal del atleta como app instalable: su manifiesto (con la marca del gimnasio) y su service worker (avisos) */
class AppController extends Controller
{
    public const VERSION = '2026-10-29.1';

    public function manifiesto(Request $request)
    {
        $c = $request->attributes->get('cuenta_dominio') ?? Cuenta::where('slug', (string) $request->query('n'))->first();
        abort_unless($c && $c->tiene('fitness'), 404);
        $inicio = parse_url(Portal::url($c), PHP_URL_PATH) ?: '/atleta';
        return response()->json([
            'name'             => $c->nombre,
            'short_name'       => Str::limit($c->nombre, 12, ''),
            'description'      => 'Reserva tus clases y entra con tu credencial',
            'id'               => $inicio,
            'start_url'        => $inicio,
            'scope'            => '/atleta',
            'display'          => 'standalone',
            'background_color' => '#13161D',
            'theme_color'      => '#13161D',
            'lang'             => 'es-MX',
            'icons' => [
                ['src' => route('app.icono', 'icono-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => route('app.icono', 'icono-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => route('app.icono', 'icono-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function serviceWorker()
    {
        return response(view('atleta.sw', ['version' => self::VERSION])->render(), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8', 'Cache-Control' => 'no-cache', 'Service-Worker-Allowed' => '/atleta',
        ]);
    }
}
