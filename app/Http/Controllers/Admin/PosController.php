<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PosVenta;
use App\Models\Producto;
use App\Support\Cuentas;
use App\Support\Pos\Ventas;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PosController extends Controller
{
    public function __construct()
    {
        abort_unless(Cuentas::actual()?->tiene('pos'), 404);
    }

    public function index()
    {
        return view('admin.pos.index', ['solicitudId' => (string) Str::uuid()]);
    }

    public function buscar(Request $request)
    {
        $texto = trim((string) $request->query('q'));
        abort_if($texto === '', 422);
        $productos = Producto::activos()->where(function ($q) use ($texto) {
            $q->where('codigo_barras', $texto)->orWhere('clave', $texto)
                ->orWhere('nombre', 'like', '%' . addcslashes($texto, '%_') . '%');
        })->limit(15)->get(['id', 'nombre', 'clave', 'codigo_barras', 'precio', 'existencia', 'controla_existencia']);
        return response()->json($productos);
    }

    public function cobrar(Request $request, Ventas $ventas)
    {
        $d = $request->validate([
            'solicitud_id' => 'required|uuid',
            'forma_pago' => 'required|in:efectivo,tarjeta,transferencia',
            'recibido' => 'required_if:forma_pago,efectivo|nullable|numeric|min:0',
            'productos' => 'required|array|min:1|max:100',
            'productos.*.producto_id' => 'required|integer',
            'productos.*.cantidad' => 'required|numeric|gt:0|max:100000',
        ]);
        $venta = $ventas->cobrar($d);
        return redirect()->route('admin.pos.ticket', $venta);
    }

    public function historial()
    {
        return view('admin.pos.historial', ['ventas' => PosVenta::latest()->paginate(30)]);
    }

    public function ticket(PosVenta $venta)
    {
        return view('admin.pos.ticket', ['venta' => $venta->load('detalles'), 'cuenta' => Cuentas::actual()]);
    }

    public function cancelar(Request $request, PosVenta $venta, Ventas $ventas)
    {
        abort_unless($request->user()->esSuperAdmin(), 403);
        $d = $request->validate(['motivo' => 'required|string|max:255']);
        $ventas->cancelar($venta, $d['motivo']);
        return redirect()->route('admin.pos.historial')->with('ok', 'Venta cancelada.');
    }
}