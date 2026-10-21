<?php

namespace App\Http\Controllers\Admin\Lealtad;

use App\Http\Controllers\Controller;
use App\Support\Cuentas;

/** Base de las pantallas del programa de lealtad: solo existen en negocios con el módulo "lealtad" (giro Restaurante) */
abstract class LealtadController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless(Cuentas::actual()?->tiene('lealtad'), 404);
            return $next($request);
        });
    }
}
