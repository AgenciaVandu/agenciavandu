<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Support\Push\Notificar;
use App\Support\Ventas\Envios;
use Illuminate\Http\Request;

/** Enlace del cliente: llena sus datos de envío (con su ubicación) y ve su nota y el estado de su pedido */
class PedidoPublicoController extends Controller
{
    private function venta(string $token): Venta
    {
        $v = Venta::where('token', $token)->with('conceptos')->first();
        abort_unless($v, 404);
        return $v;
    }

    public function show(string $token)
    {
        $v = $this->venta($token);
        return view('pedidos.publico', ['v' => $v, 'envios' => Envios::ajustes(), 'abierto' => $this->abierto($v)]);
    }

    public function guardar(Request $request, string $token)
    {
        $v = $this->venta($token);
        abort_unless($this->abierto($v), 410, 'Este pedido ya no recibe cambios.');
        $d = $request->validate([
            'nombre' => 'required|string|max:160', 'telefono' => 'required|string|max:30',
            'direccion' => 'required|string|max:300', 'referencias' => 'nullable|string|max:300',
            'lat' => 'nullable|numeric|between:-90,90', 'lng' => 'nullable|numeric|between:-180,180',
        ], ['nombre.required' => 'Escribe tu nombre.', 'telefono.required' => 'Escribe tu teléfono.', 'direccion.required' => 'Escribe tu dirección.']);
        Envios::guardarDatos($v, $d, 'cliente');
        Notificar::evento('pedido_datos', "{$v->folio}: {$d['nombre']} mandó sus datos de envío", $d['direccion'] . ($v->envio_km ? " · {$v->envio_km} km" : ''), route('admin.pedidos.show', $v), 'pedido-' . $v->id);
        return redirect()->route('pedido.publico', $token)->with('ok', '¡Listo! Recibimos tus datos de envío.');
    }

    public function nota(string $token)
    {
        $v = $this->venta($token);
        return Admin\PedidosController::pdf($v)->stream('nota-' . $v->folio . '.pdf');
    }

    /** Puede llenar o corregir sus datos mientras el pedido no salga, no se cancele y hasta 7 días después de la venta */
    private function abierto(Venta $v): bool
    {
        return $v->lleva_envio && in_array($v->estado, ['por_surtir', 'listo'], true) && $v->created_at->gt(now()->subDays(7));
    }
}
