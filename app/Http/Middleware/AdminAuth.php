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
        // En el dominio propio de un negocio solo entra su equipo (y la plataforma, que ve esa cuenta)
        $dominio = $request->attributes->get('cuenta_dominio');
        if ($dominio && $u->cuenta_id !== $dominio->id) {
            if (! $u->plataforma) {
                Auth::logout();
                $request->session()->invalidate();
                return redirect()->route('login')->withErrors(['email' => "Tu usuario no pertenece a {$dominio->nombre}."]);
            }
            $vista = $dominio->id;
        }
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

        // Licencia: vencida y sin pagar pasa a solo lectura y luego a suspensión (la plataforma siempre entra)
        $licencia = \App\Support\Licencias\Estado::de($cuenta);
        \Illuminate\Support\Facades\View::share('licenciaEstado', $licencia);
        // Mi plan (ver, subir comprobante, recibos) siempre abre: es la salida para renovar
        if (! $u->plataforma && $ruta !== 'logout' && ! str_starts_with((string) $ruta, 'admin.mi-plan')) {
            if ($licencia->clave === 'suspendida') {
                return response()->view('admin.licencia-suspendida', ['e' => $licencia, 'cuenta' => $cuenta, 'pago' => \App\Support\Licencias\Estado::datosPago()], 402);
            }
            if ($licencia->clave === 'lectura' && ! $request->isMethodSafe()) {
                $msg = 'El panel está en solo lectura porque el plan venció. Puedes ver y descargar todo; para registrar o cambiar algo, renueva el plan.';
                if ($request->expectsJson()) return response()->json(['message' => $msg, 'ok' => false, 'mensaje' => $msg], 423);
                return back()->with('error', $msg);
            }
        }

        if ($ruta && str_starts_with($ruta, 'admin.') && ! Permisos::puedeRuta($u, $ruta)) {
            // El inicio lleva a lo primero que sí puede ver
            if ($ruta === 'admin.resumen') return redirect()->to(Permisos::inicio($u));
            if ($request->expectsJson()) return response()->json(['message' => 'Tu rol no tiene acceso a esta sección.'], 403);
            return response()->view('admin.sin-permiso', ['inicio' => Permisos::inicio($u)], 403);
        }

        return $next($request);
    }
}
