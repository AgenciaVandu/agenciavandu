<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, \Closure $next)
    {
        // Define aquí tu usuario y contraseña
        $usuario = 'alvarVandu';
        $password = '22$d9%/:l:O#J*Iiw'; // 

        if ($request->getUser() != $usuario || $request->getPassword() != $password) {
            $headers = ['WWW-Authenticate' => 'Basic realm="alvarVandu"'];
            return response('No autorizado.', 401, $headers);
        }

        return $next($request);
    }
}
