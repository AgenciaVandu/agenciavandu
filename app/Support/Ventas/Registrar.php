<?php

namespace App\Support\Ventas;

use App\Models\Cliente;
use App\Models\Presupuesto;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Support\Cuentas;
use App\Support\Inventario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Alta de ventas: desde Nueva venta (mostrador) o desde una cotización aceptada; abonos y cancelación */
class Registrar
{
    /**
     * @param array $d productos[{producto_id, cantidad, precio}], cliente_id|cliente_nombre|cliente_telefono, descuento, con_iva,
     *                 pago (contado|parcial|credito), forma, monto, dias, entrega (ahora|recoge|envio), envío…
     */
    public static function crear(array $d, User $u): Venta
    {
        return DB::transaction(function () use ($d, $u) {
            $cliente = self::cliente($d);
            $v = Venta::create([
                'cliente_id' => $cliente?->id,
                'cliente_nombre' => $cliente ? null : (trim((string) ($d['cliente_nombre'] ?? '')) ?: null),
                'cliente_telefono' => $cliente ? null : (trim((string) ($d['cliente_telefono'] ?? '')) ?: null),
                'user_id' => $u->id, 'sucursal_id' => $u->sucursal_id,
                'descuento' => round((float) ($d['descuento'] ?? 0), 2),
                'con_iva' => (bool) ($d['con_iva'] ?? false), 'iva_porcentaje' => (float) config('vandu.iva_porcentaje', 16),
                'credito' => ($d['pago'] ?? 'contado') === 'credito',
                'estado' => ($d['entrega'] ?? 'ahora') === 'ahora' ? 'entregada' : 'por_surtir',
                'entregada_at' => ($d['entrega'] ?? 'ahora') === 'ahora' ? now() : null,
                'lleva_envio' => ($d['entrega'] ?? '') === 'envio',
                'repartidor_id' => $d['repartidor_id'] ?? null,
                'notas' => $d['notas'] ?? null,
            ]);

            foreach (array_values($d['productos']) as $i => $f) {
                $p = Producto::findOrFail($f['producto_id']);
                $cant = round((float) $f['cantidad'], 2);
                $precio = round((float) ($f['precio'] ?? $p->precio), 2);
                $v->conceptos()->create(['producto_id' => $p->id, 'descripcion' => $p->nombre, 'unidad' => $p->unidad, 'cantidad' => $cant,
                    'precio' => $precio, 'importe' => round($cant * $precio, 2), 'orden' => $i]);
                Inventario::mover($p, 'vendido', -$cant, 'Venta ' . $v->folio, null, null, $v->id);
            }

            if ($v->lleva_envio) {
                if (isset($d['envio_costo']) && $d['envio_costo'] !== '' && $d['envio_costo'] !== null) {
                    $v->envio_costo = round((float) $d['envio_costo'], 2);
                    $v->envio_costo_manual = true;
                }
                if (($d['datos'] ?? 'cliente') === 'vendedor') {
                    $v->save();
                    Envios::guardarDatos($v, ['nombre' => $d['envio_nombre'] ?? $v->quien, 'telefono' => $d['envio_telefono'] ?? null, 'direccion' => $d['envio_direccion'] ?? null,
                        'referencias' => $d['envio_referencias'] ?? null, 'lat' => $d['envio_lat'] ?? null, 'lng' => $d['envio_lng'] ?? null], 'vendedor');
                    $v->refresh();
                } elseif (! $v->envio_costo_manual && ($c = Envios::costo(null)) !== null) {
                    $v->envio_costo = $c;   // tarifa fija o gratis; por km se calcula cuando llegue la ubicación
                }
            }
            $v->recalcular();
            $v->save();

            // Cobro
            $pago = $d['pago'] ?? 'contado';
            $monto = match ($pago) { 'contado' => $v->total, 'parcial' => min($v->total, round((float) ($d['monto'] ?? 0), 2)), default => 0 };
            if ($monto > 0) self::abonar($v, $monto, $d['forma'] ?? 'efectivo', $u);
            if ($v->credito || $pago === 'parcial') {
                $v->credito = true;
                $v->vence_el = now(config('vandu.zona_horaria'))->addDays(max(1, (int) ($d['dias'] ?? 15)))->toDateString();
                $v->save();
            }
            return $v->refresh();
        });
    }

    /** Cotización aceptada solo de productos → venta (el inventario ya lo descontó la cotización) */
    public static function desdeCotizacion(Presupuesto $p, User $u): Venta
    {
        if ($ya = Venta::where('presupuesto_id', $p->id)->first()) return $ya;
        return DB::transaction(function () use ($p, $u) {
            $p->loadMissing('conceptos', 'cliente');
            $iva = round($p->total - $p->subtotal, 2);
            $v = Venta::create([
                'cliente_id' => $p->cliente_id, 'cliente_nombre' => $p->cliente_id ? null : ($p->cliente_empresa ?: $p->cliente_nombre),
                'presupuesto_id' => $p->id, 'user_id' => $u->id, 'sucursal_id' => $u->sucursal_id,
                'fecha' => ($p->aceptada_el ?? now(config('vandu.zona_horaria')))->toDateString(),
                'con_iva' => $iva > 0, 'iva_porcentaje' => (float) $p->iva_porcentaje, 'estado' => 'por_surtir',
                'credito' => (bool) $p->cliente?->dias_credito,
            ]);
            foreach ($p->conceptos as $i => $c) {
                $v->conceptos()->create(['producto_id' => $c->producto_id, 'descripcion' => $c->titulo ?: \Illuminate\Support\Str::limit(trim((string) $c->descripcion), 250),
                    'unidad' => $c->producto?->unidad, 'cantidad' => (float) $c->cantidad, 'precio' => (float) $c->precio, 'importe' => (float) $c->importe, 'orden' => $i]);
            }
            $v->recalcular();
            // Respeta el total de la cotización (descuentos o redondeos que traiga)
            $v->descuento = max(0, round($v->subtotal - $p->subtotal, 2));
            $v->recalcular();
            if ($v->credito) $v->vence_el = now(config('vandu.zona_horaria'))->addDays((int) $p->cliente->dias_credito)->toDateString();
            $v->save();
            return $v;
        });
    }

    public static function abonar(Venta $v, float $monto, string $forma, ?User $u, ?string $fecha = null, ?string $nota = null): void
    {
        $monto = round($monto, 2);
        if ($monto <= 0) return;
        if ($monto > $v->saldo + 0.01) throw ValidationException::withMessages(['monto' => 'El abono es mayor que lo que debe (' . Venta::dinero($v->saldo) . ').']);
        $v->pagos()->create(['cuenta_id' => $v->cuenta_id, 'monto' => $monto, 'fecha' => $fecha ?: now(config('vandu.zona_horaria'))->toDateString(),
            'forma' => array_key_exists($forma, Venta::FORMAS) ? $forma : 'otro', 'user_id' => $u?->id, 'nota' => $nota]);
        $v->recalcular();
        $v->save();
    }

    /** Cancela: regresa la mercancía (si la venta la descontó) y deja de contar en Finanzas */
    public static function cancelar(Venta $v): void
    {
        DB::transaction(function () use ($v) {
            if (! $v->presupuesto_id) {
                foreach ($v->conceptos as $c) {
                    if ($c->producto) Inventario::mover($c->producto, 'devolucion', $c->cantidad, 'Se canceló la venta ' . $v->folio, null, null, $v->id);
                }
            }
            $v->update(['estado' => 'cancelada', 'cancelada_at' => now()]);
        });
    }

    /** Cliente elegido, o uno nuevo si se pidió guardarlo (o es a crédito). Se busca por teléfono para no duplicar */
    private static function cliente(array $d): ?Cliente
    {
        if (! empty($d['cliente_id'])) return Cliente::find($d['cliente_id']);
        $nombre = trim((string) ($d['cliente_nombre'] ?? ''));
        $tel = trim((string) ($d['cliente_telefono'] ?? ''));
        if ($nombre === '' || (empty($d['guardar_cliente']) && ($d['pago'] ?? 'contado') === 'contado')) return null;
        $digitos = preg_replace('/\D/', '', $tel);
        if (strlen($digitos) >= 10) {
            $ya = Cliente::whereRaw("REPLACE(REPLACE(REPLACE(telefono, ' ', ''), '-', ''), '+52', '') like ?", ['%' . substr($digitos, -10)])->first();
            if ($ya) return $ya;
        }
        return Cliente::create(['nombre' => $nombre, 'telefono' => $tel ?: null, 'origen' => 'mostrador']);
    }
}
