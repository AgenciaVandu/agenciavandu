<?php

namespace App\Http\Middleware;

use App\Support\Cuentas;
use App\Support\Fitness\Portal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Portal del atleta: exige su sesión (guard "atleta") y trabaja dentro de su gimnasio.
 * Un atleta nunca pasa por el panel; si su gimnasio se suspende o lo dan de baja, sale.
 */
class AtletaAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('atleta');
        $a = $guard->user();
        $dominio = $request->attributes->get('cuenta_dominio');
        $entrar = fn ($msg = null) => redirect()->guest($dominio ? url('/atleta') : ($a ? Portal::url(\App\Models\Cuenta::find($a->cuenta_id)) : url('/')))->withErrors($msg ? ['email' => $msg] : []);

        if (! $a) {
            $c = $dominio ?? \App\Models\Cuenta::find($request->session()->get('atleta_cuenta'));
            return redirect()->guest($c ? Portal::url($c) : url('/'));
        }
        $cuenta = Cuentas::activar($a->cuenta_id);
        $salir = function (string $msg) use ($guard, $request, $entrar) {
            $guard->logout();
            $request->session()->forget('atleta_cuenta');
            return $entrar($msg);
        };
        if (! $cuenta || ! $cuenta->activa || ! $cuenta->tiene('fitness')) return $salir('Este portal no está disponible.');
        if ($dominio && $dominio->id !== $cuenta->id) return $salir('Tu usuario es de otro gimnasio.');
        if ($a->activo === false) return $salir('Tu acceso está desactivado. Pregunta en recepción.');

        if (! $a->ultimo_acceso_at || $a->ultimo_acceso_at->lt(now()->subMinutes(10))) {
            $a->forceFill(['ultimo_acceso_at' => now()])->saveQuietly();
        }
        return $next($request);
    }
}
