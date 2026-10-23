<?php

namespace App\Support\Ventas;

use App\Models\Venta;
use App\Models\VentaPago;
use App\Support\Cuentas;
use Illuminate\Support\Collection;

/**
 * Lo que las ventas directas suman en Resumen y Finanzas.
 *  - Vendido: ventas que no salieron de una cotización (esas ya cuentan como cotización ganada), por su fecha.
 *  - Cobrado: cada pago o abono por su fecha (incluye las que salieron de cotización: no tienen proyecto).
 *  - Por cobrar: el saldo de las ventas vigentes.
 * Sin IVA: cada pago se reparte según la proporción neto/total de su venta.
 */
class Numeros
{
    public static function activo(): bool
    {
        return (bool) Cuentas::actual()?->tiene('stock');
    }

    private static function factor(Venta $v, bool $conIva): float
    {
        return $conIva || $v->total <= 0 ? 1.0 : $v->neto / $v->total;
    }

    /** Pagos entre dos fechas (cada uno con su monto ya ajustado al IVA elegido) */
    public static function pagos(?string $desde, ?string $hasta, bool $conIva): Collection
    {
        if (! self::activo()) return collect();
        return VentaPago::with('venta')->whereHas('venta', fn ($q) => $q->vigentes())
            ->when($desde, fn ($q) => $q->whereDate('fecha', '>=', $desde))->when($hasta, fn ($q) => $q->whereDate('fecha', '<=', $hasta))
            ->get()->map(fn ($p) => (object) ['fecha' => $p->fecha->toDateString(), 'monto' => round($p->monto * self::factor($p->venta, $conIva), 2), 'pago' => $p]);
    }

    public static function vendidas(?string $desde, ?string $hasta): Collection
    {
        if (! self::activo()) return collect();
        return Venta::vigentes()->whereNull('presupuesto_id')
            ->when($desde, fn ($q) => $q->whereDate('fecha', '>=', $desde))->when($hasta, fn ($q) => $q->whereDate('fecha', '<=', $hasta))->get();
    }

    public static function monto(Venta $v, bool $conIva): float
    {
        return $conIva ? $v->total : $v->neto;
    }

    public static function porCobrar(bool $conIva): array
    {
        if (! self::activo()) return ['monto' => 0.0, 'n' => 0];
        $v = Venta::porCobrar()->get();
        return ['monto' => round($v->sum(fn ($x) => $x->saldo * self::factor($x, $conIva)), 2), 'n' => $v->count()];
    }
}
