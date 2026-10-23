<?php

namespace App\Support;

use App\Models\Presupuesto;
use App\Models\Producto;
use App\Models\ProductoMovimiento;
use Illuminate\Support\Facades\DB;

/**
 * Toda entrada o salida de existencias pasa por aquí, así el historial siempre cuadra con lo que hay.
 * Las cotizaciones aceptadas descuentan su mercancía; si se cancelan o se editan, se ajusta la diferencia.
 */
class Inventario
{
    /** ¿El negocio activo usa inventario? */
    public static function activo(): bool
    {
        return (bool) Cuentas::actual()?->tiene('stock');
    }

    /**
     * Mueve existencias. $cantidad positiva entra, negativa sale.
     * Con $fijarEn, deja la existencia exactamente en ese número (ajuste por conteo físico).
     */
    public static function mover(Producto $p, string $tipo, float $cantidad, ?string $nota = null, ?int $presupuestoId = null, ?float $fijarEn = null, ?int $ventaId = null): ?ProductoMovimiento
    {
        return DB::transaction(function () use ($p, $tipo, $cantidad, $nota, $presupuestoId, $fijarEn, $ventaId) {
            $p = Producto::sinCuenta()->whereKey($p->id)->lockForUpdate()->first();
            if (! $p || ! $p->controla_existencia) return null;
            if ($fijarEn !== null) $cantidad = round($fijarEn - $p->existencia, 2);
            if (abs($cantidad) < 0.005) return null;

            $p->existencia = round($p->existencia + $cantidad, 2);
            $p->saveQuietly();

            return $p->movimientos()->create([
                'tipo' => $tipo, 'cantidad' => round($cantidad, 2), 'existencia' => $p->existencia,
                'presupuesto_id' => $presupuestoId, 'venta_id' => $ventaId, 'user_id' => auth()->id(), 'nota' => $nota ? mb_substr($nota, 0, 255) : null,
            ]);
        });
    }

    /**
     * Deja el inventario de acuerdo con la cotización: si está aceptada, descuenta lo que lleva;
     * si no (o se canceló), regresa lo que había descontado. Si se editó estando aceptada, ajusta solo la diferencia.
     * Se puede llamar las veces que sea: no descuenta dos veces.
     */
    public static function sincronizarCotizacion(Presupuesto $p): void
    {
        $quiere = [];
        if ($p->estado === 'aceptada') {
            foreach ($p->conceptos()->whereNotNull('producto_id')->get(['producto_id', 'cantidad']) as $c) {
                $quiere[$c->producto_id] = ($quiere[$c->producto_id] ?? 0) + (float) $c->cantidad;
            }
        }

        $hay = ProductoMovimiento::where('presupuesto_id', $p->id)->whereIn('tipo', ['venta', 'devolucion'])
            ->selectRaw('producto_id, SUM(cantidad) as total')->groupBy('producto_id')->pluck('total', 'producto_id')
            ->map(fn ($t) => -(float) $t)->all();   // lo que ya salió por esta cotización

        foreach (array_unique(array_merge(array_keys($quiere), array_keys($hay))) as $id) {
            $diferencia = round(($quiere[$id] ?? 0) - ($hay[$id] ?? 0), 2);
            if (abs($diferencia) < 0.005 || ! ($producto = Producto::find($id))) continue;
            $diferencia > 0
                ? self::mover($producto, 'venta', -$diferencia, 'Cotización ' . $p->folio, $p->id)
                : self::mover($producto, 'devolucion', -$diferencia, ($p->estado === 'aceptada' ? 'Se editó la cotización ' : 'Ya no está aceptada la cotización ') . $p->folio, $p->id);
        }
    }

    /** ¿Puede ver costos y márgenes? Sin inventario, todo sigue como siempre; con inventario, solo quien administra productos */
    public static function veCostos(?\App\Models\User $u = null): bool
    {
        $u ??= auth()->user();
        if (! $u) return false;
        if (! self::activo()) return true;
        return $u->puede('productos');
    }
}
