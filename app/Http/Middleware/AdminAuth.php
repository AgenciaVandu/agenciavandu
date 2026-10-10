<?php

namespace App\Http\Middleware;

use App\Support\Permisos;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege el panel: si no hay sesión iniciada, manda a /admin/login
 * y después regresa a la página que se intentaba abrir.
 * Cada persona del equipo solo entra a las secciones que le da su rol.
 */
class AdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->guest(route('login'));
        }

        $u = $request->user();

        // Cada quien trabaja dentro de su negocio; la plataforma puede entrar a otro para dar soporte
        $vista = $u->plataforma ? $request->session()->get('cuenta_vista') : null;
        $cuenta = \App\Support\Cuentas::activar($vista ?: $u->cuenta_id) ?? \App\Support\Cuentas::activar($u->cuenta_id);
        if (! $cuenta || (! $cuenta->activa && ! $u->plataforma)) {
            Auth::logout();
            $request->session()->invalidate();
            return redirect()->route('login')->withErrors(['email' => 'La cuenta de tu negocio está suspendida. Escríbenos para reactivarla.']);
        }

        if ($u->activo === false) {
            Auth::logout();
            $request->session()->invalidate();
            return redirect()->route('login')->withErrors(['email' => 'Tu acceso al panel está desactivado. Habla con la agencia.']);
        }

        if (! $u->ultimo_acceso_at || $u->ultimo_acceso_at->lt(now()->subMinutes(5))) {
            $u->forceFill(['ultimo_acceso_at' => now()])->saveQuietly();
        }

        $ruta = $request->route()?->getName();
        if ($ruta && str_starts_with($ruta, 'admin.') && ! Permisos::puedeRuta($u, $ruta)) {
            // El inicio lleva a lo primero que sí puede ver
            if ($ruta === 'admin.resumen') return redirect()->to(Permisos::inicio($u));
            if ($request->expectsJson()) return response()->json(['message' => 'Tu rol no tiene acceso a esta sección.'], 403);
            return response()->view('admin.sin-permiso', ['inicio' => Permisos::inicio($u)], 403);
        }

        return $next($request);
    }
}
