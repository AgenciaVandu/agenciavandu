<?php

namespace App\Http\Controllers\Admin\Fitness;

use App\Http\Controllers\Controller;
use App\Support\Cuentas;

/** Base de las pantallas del gimnasio: solo existen para negocios con el giro Fitness */
abstract class FitnessController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless(Cuentas::actual()?->tiene('fitness'), 404);
            return $next($request);
        });
    }
}
