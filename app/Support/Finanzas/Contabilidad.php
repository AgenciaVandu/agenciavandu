<?php

namespace App\Support\Finanzas;

use App\Models\Gasto;
use App\Models\OrdenCompra;
use App\Models\Presupuesto;
use App\Models\ProyectoPago;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Support\Cuentas;
use App\Support\EsteMes;
use Illuminate\Support\Collection;

/**
 * Finanzas > Contabilidad: lo que el dueño necesita antes de hablar con su contador.
 *  - Estado de resultados (antes de IVA): ventas − costo de lo vendido − gastos = utilidad.
 *      · Ventas: cotizaciones aceptadas en el periodo + ventas directas que no salieron de una cotización.
 *      · Costo: órdenes de compra del proyecto si las hay; si no, el costeo de la cotización; si no, el costo
 *        de los productos del catálogo que salieron. Lo que no tiene costo se avisa (la utilidad sale inflada).
 *      · Gastos: los del módulo de Gastos, por categoría.
 *  - IVA (sobre lo cobrado y lo pagado, como lo pide el SAT): IVA de cada cobro − IVA de facturas de gastos
 *    y de órdenes de compra con XML. Un ticket sin factura no da IVA acreditable.
 *  - Comprobantes: gastos con factura (deducibles) contra gastos sin factura.
 * Es una estimación para planear: no considera retenciones, tasa 0 ni exentos.
 */
class Contabilidad
{
    public static function datos(?string $desde, ?string $hasta): array
    {
        $gastos = Cuentas::actual()?->tiene('gastos')
            ? Gasto::entre($desde, $hasta)->orderByDesc('fecha')->get()
            : null;

        return [
            'resultados'   => self::resultados($desde, $hasta, $gastos),
            'iva'          => self::iva($desde, $hasta, $gastos),
            'comprobantes' => $gastos ? self::comprobantes($gastos) : null,
        ];
    }

    private static function enRango(?string $f, ?string $desde, ?string $hasta): bool
    {
        return $f && (! $desde || $f >= $desde) && (! $hasta || $f <= $hasta);
    }

    /** Costo de los productos del catálogo en una venta (con el costo que tienen hoy) */
    private static function costoProductos(Venta $v): array
    {
        $costo = 0.0;
        $faltan = 0;
        foreach ($v->conceptos as $c) {
            if ($c->producto && $c->producto->costo !== null) $costo += $c->cantidad * $c->producto->costo;
            else $faltan++;
        }
        return [round($costo, 2), $faltan];
    }

    private static function resultados(?string $desde, ?string $hasta, ?Collection $gastos): array
    {
        $cot = Presupuesto::with(['conceptos', 'proyecto', 'venta.conceptos.producto'])->where('estado', 'aceptada')->get()
            ->filter(fn ($p) => self::enRango(EsteMes::fechaAceptada($p), $desde, $hasta));

        // Lo que de verdad se le compró a proveedores para cada proyecto (lo facturado si ya hay XML)
        $ids = $cot->pluck('proyecto.id')->filter()->values();
        $oc = $ids->isEmpty() ? collect() : OrdenCompra::with('conceptos')->whereIn('proyecto_id', $ids)->where('estado', '!=', 'cancelada')
            ->where('estado', '!=', 'borrador')->get()->groupBy('proyecto_id')->map(fn ($g) => round($g->sum(fn ($o) => $o->subtotal_final), 2));

        $ventasCot = 0.0; $costoCot = 0.0; $sinCosto = 0; $sinCostoMonto = 0.0;
        foreach ($cot as $p) {
            $ventasCot += $p->subtotal;
            if ($p->proyecto && isset($oc[$p->proyecto->id])) {
                $costoCot += $oc[$p->proyecto->id];
            } elseif ($p->usa_costeo) {
                $costoCot += $p->costo;
            } elseif ($p->venta) {
                [$c, $faltan] = self::costoProductos($p->venta);
                $costoCot += $c;
                if ($faltan) { $sinCosto++; $sinCostoMonto += $p->subtotal; }
            } else {
                $sinCosto++; $sinCostoMonto += $p->subtotal;
            }
        }

        $ventasDir = 0.0; $costoDir = 0.0; $nDir = 0;
        if (Cuentas::actual()?->tiene('stock')) {
            $ventas = Venta::with('conceptos.producto')->vigentes()->whereNull('presupuesto_id')
                ->when($desde, fn ($q) => $q->whereDate('fecha', '>=', $desde))->when($hasta, fn ($q) => $q->whereDate('fecha', '<=', $hasta))->get();
            foreach ($ventas as $v) {
                $ventasDir += $v->neto;
                [$c, $faltan] = self::costoProductos($v);
                $costoDir += $c;
                if ($faltan) { $sinCosto++; $sinCostoMonto += $v->neto; }
            }
            $nDir = $ventas->count();
        }

        $ventasTot = round($ventasCot + $ventasDir, 2);
        $costo = round($costoCot + $costoDir, 2);
        $bruta = round($ventasTot - $costo, 2);

        $porCategoria = $gastos ? $gastos->groupBy('categoria')->map(fn ($c, $k) => [
            'texto' => Gasto::CATEGORIAS[$k]['texto'] ?? 'Otros', 'icono' => Gasto::CATEGORIAS[$k]['icono'] ?? 'bi-three-dots',
            'monto' => round($c->sum('subtotal'), 2), 'n' => $c->count(),
        ])->sortByDesc('monto')->values() : collect();
        $gastosTot = round($porCategoria->sum('monto'), 2);
        $utilidad = round($bruta - $gastosTot, 2);

        return [
            'ventasCot'    => round($ventasCot, 2), 'nCot' => $cot->count(),
            'ventasDir'    => round($ventasDir, 2), 'nDir' => $nDir,
            'ventas'       => $ventasTot,
            'costo'        => $costo,
            'bruta'        => $bruta,
            'margenBruto'  => $ventasTot > 0 ? round($bruta / $ventasTot * 100, 1) : null,
            'gastos'       => $gastosTot,
            'porCategoria' => $porCategoria,
            'conGastos'    => $gastos !== null,
            'utilidad'     => $utilidad,
            'margen'       => $ventasTot > 0 ? round($utilidad / $ventasTot * 100, 1) : null,
            'sinCosto'     => $sinCosto,
            'sinCostoMonto'=> round($sinCostoMonto, 2),
        ];
    }

    private static function iva(?string $desde, ?string $hasta, ?Collection $gastos): array
    {
        // IVA cobrado: la parte de IVA de cada pago recibido, en proporción a su cotización o venta
        $pp = ProyectoPago::with('proyecto.presupuesto.conceptos')->whereNotNull('pagado_el')
            ->when($desde, fn ($q) => $q->whereDate('pagado_el', '>=', $desde))->when($hasta, fn ($q) => $q->whereDate('pagado_el', '<=', $hasta))->get();
        $ivaProy = $pp->sum(function ($pg) {
            $p = $pg->proyecto?->presupuesto;
            return $p && $p->total > 0 ? $pg->monto * $p->iva / $p->total : 0;
        });
        $cobradoProy = $pp->sum('monto');

        $ivaVen = 0.0; $cobradoVen = 0.0;
        if (Cuentas::actual()?->tiene('stock')) {
            $vp = VentaPago::with('venta')->whereHas('venta', fn ($q) => $q->vigentes())
                ->when($desde, fn ($q) => $q->whereDate('fecha', '>=', $desde))->when($hasta, fn ($q) => $q->whereDate('fecha', '<=', $hasta))->get();
            $ivaVen = $vp->sum(fn ($x) => $x->venta->total > 0 ? $x->monto * $x->venta->iva / $x->venta->total : 0);
            $cobradoVen = $vp->sum('monto');
        }
        $trasladado = round($ivaProy + $ivaVen, 2);

        // IVA acreditable: solo lo que tiene factura (XML)
        $ivaGastos = $gastos ? round($gastos->whereNotNull('xml')->sum('iva'), 2) : 0.0;
        $perdido = $gastos ? round($gastos->whereNull('xml')->sum('iva'), 2) : 0.0;
        $ordenes = OrdenCompra::whereNotNull('factura_iva')->whereNotIn('estado', ['borrador', 'cancelada'])
            ->when($desde, fn ($q) => $q->whereDate('fecha', '>=', $desde))->when($hasta, fn ($q) => $q->whereDate('fecha', '<=', $hasta))->get(['id', 'factura_iva']);
        $ivaOrdenes = round($ordenes->sum('factura_iva'), 2);
        $acreditable = round($ivaGastos + $ivaOrdenes, 2);
        $saldo = round($trasladado - $acreditable, 2);

        return [
            'cobrado'     => round($cobradoProy + $cobradoVen, 2),
            'trasladado'  => $trasladado,
            'gastos'      => $ivaGastos,
            'ordenes'     => $ivaOrdenes,
            'nOrdenes'    => $ordenes->count(),
            'acreditable' => $acreditable,
            'saldo'       => $saldo,                // > 0 a pagar, < 0 a favor
            'perdido'     => $perdido,              // IVA de tickets sin factura: no se puede acreditar
            'conGastos'   => $gastos !== null,
        ];
    }

    private static function comprobantes(Collection $gastos): array
    {
        $con = $gastos->whereNotNull('xml');
        $sin = $gastos->whereNull('xml');
        return [
            'con'       => round($con->sum('total'), 2), 'nCon' => $con->count(),
            'sin'       => round($sin->sum('total'), 2), 'nSin' => $sin->count(),
            'pct'       => $gastos->sum('total') > 0 ? round($con->sum('total') / $gastos->sum('total') * 100) : null,
            'pendientes'=> $sin->sortByDesc('total')->take(8)->values(),
        ];
    }
}
