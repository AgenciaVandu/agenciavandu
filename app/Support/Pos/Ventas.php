<?php

namespace App\Support\Pos;

use App\Models\PosVenta;
use App\Models\Producto;
use App\Support\Cuentas;
use App\Support\Inventario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Ventas
{
    public function cobrar(array $datos): PosVenta
    {
        $cuenta = Cuentas::actual();
        abort_unless($cuenta?->tiene('pos'), 404);

        return DB::transaction(function () use ($datos) {
            $existente = PosVenta::where('solicitud_id', $datos['solicitud_id'])->first();
            if ($existente) return $existente;

            $cantidades = [];
            foreach ($datos['productos'] as $linea) {
                $id = (int) $linea['producto_id'];
                $cantidades[$id] = round(($cantidades[$id] ?? 0) + (float) $linea['cantidad'], 2);
            }

            $productos = Producto::whereIn('id', array_keys($cantidades))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $detalles = [];
            $centavos = 0;
            foreach ($cantidades as $id => $cantidad) {
                $p = $productos->get($id);
                if (! $p || ! $p->activo || $cantidad <= 0) {
                    throw ValidationException::withMessages(['productos' => 'Hay productos no disponibles.']);
                }
                if ($p->controla_existencia && (float) $p->existencia < $cantidad) {
                    throw ValidationException::withMessages(['productos' => "Existencia insuficiente: {$p->nombre}."]);
                }
                $precio = (int) round((float) $p->precio * 100);
                $importe = (int) round($precio * $cantidad);
                $centavos += $importe;
                $detalles[] = ['producto_id' => $id, 'nombre' => $p->nombre, 'codigo' => $p->codigo_barras ?: $p->clave,
                    'cantidad' => $cantidad, 'precio' => $precio / 100, 'importe' => $importe / 100];
            }
            if ($centavos <= 0) throw ValidationException::withMessages(['productos' => 'El total debe ser mayor que cero.']);

            $recibido = $datos['forma_pago'] === 'efectivo' ? (int) round((float) $datos['recibido'] * 100) : $centavos;
            if ($recibido < $centavos) throw ValidationException::withMessages(['recibido' => 'El efectivo recibido no cubre la venta.']);

            // El folio usa el ID de la venta; la clave única impide repetir solicitudes.
            $venta = PosVenta::create([
                'user_id' => auth()->id(), 'folio' => 'PENDIENTE-' . $datos['solicitud_id'],
                'solicitud_id' => $datos['solicitud_id'], 'estado' => 'pagada',
                'subtotal' => $centavos / 100, 'descuento' => 0, 'impuestos' => 0, 'total' => $centavos / 100,
                'forma_pago' => $datos['forma_pago'], 'recibido' => $recibido / 100, 'cambio' => ($recibido - $centavos) / 100,
            ]);
            $venta->update(['folio' => 'POS-' . str_pad((string) $venta->id, 7, '0', STR_PAD_LEFT)]);
            $venta->detalles()->createMany($detalles);

            foreach ($cantidades as $id => $cantidad) {
                Inventario::mover($productos->get($id), 'venta', -$cantidad, 'Venta de mostrador ' . $venta->folio);
            }
            return $venta->load('detalles');
        }, 3);
    }

    public function cancelar(PosVenta $venta, string $motivo): void
    {
        DB::transaction(function () use ($venta, $motivo) {
            $venta = PosVenta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
            if ($venta->estado !== 'pagada') return;
            foreach ($venta->detalles as $detalle) {
                $p = Producto::whereKey($detalle->producto_id)->first();
                if ($p) Inventario::mover($p, 'devolucion', (float) $detalle->cantidad, 'Cancelación ' . $venta->folio);
            }
            $venta->update(['estado' => 'cancelada', 'cancelada_at' => now(), 'cancelada_por' => auth()->id(), 'motivo_cancelacion' => $motivo]);
        });
    }
}