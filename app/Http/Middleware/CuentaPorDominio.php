<?php

namespace App\Http\Middleware;

use App\Models\Cuenta;
use App\Support\Cuentas;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Un negocio con dominio propio (app.losos.mx): ahí todo se abre con su cuenta y su marca.
 * En ese dominio solo existen su panel y sus enlaces públicos; el sitio de la agencia no se muestra.
 */
class CuentaPorDominio
{
    /** Lo que sí se sirve en el dominio de un negocio */
    public const PERMITIDAS = ['admin', 'admin/*', 'cotizacion/*', 'proyecto/*', 'cliente/*', 'proveedor/*', 'redes/*', 'catalogo/*', 'autos/*', 'atleta', 'atleta/*', 'vandu-fuente.woff2', 'plataforma/*', 'lealtad', 'lealtad/*'];

    private static array $cache = [];

    public function handle(Request $request, Closure $next): Response
    {
        $c = self::cuenta($request->getHost());
        if (! $c) return $next($request);

        abort_unless($c->activa, 404);
        $request->attributes->set('cuenta_dominio', $c);
        Cuentas::activar($c);

        if (! $request->is(...self::PERMITIDAS)) return redirect('/admin');
        return $next($request);
    }

    public static function cuenta(?string $host): ?Cuenta
    {
        $host = mb_strtolower((string) $host);
        if ($host === '' || $host === mb_strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST))) return null;
        if (! array_key_exists($host, self::$cache)) {
            try { self::$cache[$host] = Cuenta::where('dominio', $host)->first(); } catch (Throwable) { self::$cache[$host] = null; }
        }
        return self::$cache[$host];
    }

    /** Para pruebas o después de cambiar el dominio de una cuenta */
    public static function olvidar(): void
    {
        self::$cache = [];
    }
}
