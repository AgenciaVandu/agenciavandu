<?php

namespace App\Http\Middleware;

use App\Models\Cliente;
use App\Models\Presupuesto;
use App\Models\Proyecto;
use App\Support\Cuentas;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Enlaces del cliente (cotización, proyecto, redes): se abren con los datos y la marca del negocio que los mandó */
class CuentaPorEnlace
{
    public function handle(Request $request, Closure $next, string $tipo): Response
    {
        $token = (string) $request->route('token');
        $cuenta = match ($tipo) {
            'presupuesto' => Presupuesto::sinCuenta()->where('token', $token)->value('cuenta_id'),
            'proyecto'    => Proyecto::sinCuenta()->where('token', $token)->value('cuenta_id'),
            'redes'       => Cliente::sinCuenta()->where('redes_token', $token)->value('cuenta_id'),
            default       => null,
        };
        if ($cuenta) {
            $c = Cuentas::activar((int) $cuenta);
            abort_if($c && ! $c->activa, 404);
        }
        return $next($request);
    }
}
