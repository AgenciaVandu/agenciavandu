<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Support\Cobros\PorCobrar;
use Illuminate\Http\Request;

/** "Registrar pago": un solo lugar para cobrar, sin importar si es de un proyecto, una venta o un crédito */
class CobrarController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        abort_unless(PorCobrar::puede($u), 403);
        $cliente = $request->filled('cliente') ? Cliente::findOrFail((int) $request->query('cliente')) : null;
        return view('admin.cobrar', [
            'cliente'  => $cliente,
            'items'    => $cliente ? PorCobrar::lista($u, $cliente->id) : collect(),
            'clientes' => $cliente ? collect() : PorCobrar::clientes($u),
            'todos'    => $cliente ? collect() : Cliente::orderBy('nombre')->get(['id', 'nombre', 'empresa']),
        ]);
    }
}
