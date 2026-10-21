<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** App del comensal (/lealtad): exige su sesión. No pertenece a ningún negocio, así que no activa cuenta */
class ComensalAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $c = Auth::guard('comensal')->user();
        if (! $c) return redirect()->guest(route('lealtad.entrar'));
        if (! $c->ultimo_acceso_at || $c->ultimo_acceso_at->lt(now()->subMinutes(10))) {
            $c->forceFill(['ultimo_acceso_at' => now()])->saveQuietly();
        }
        return $next($request);
    }
}
