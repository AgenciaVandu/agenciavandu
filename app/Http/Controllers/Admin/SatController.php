<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Facturas\CatalogoSat;
use Illuminate\Http\Request;

/** Búsqueda en los catálogos del SAT mientras se escribe (clave de producto o servicio y clave de unidad) */
class SatController extends Controller
{
    public function buscar(Request $request, string $tipo)
    {
        abort_unless(in_array($tipo, ['productos', 'unidades'], true), 404);
        return response()->json(CatalogoSat::buscar($tipo, (string) $request->query('q', '')));
    }
}
