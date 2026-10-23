<?php

namespace App\Support;

use App\Models\Presupuesto;
use App\Models\ProyectoPago;
use Illuminate\Support\Carbon;

/**
 * Números del mes en curso para el Resumen y Finanzas:
 * lo cobrado (pagos registrados), lo vendido (cotizaciones aceptadas) y la comparación
 * contra el mismo tramo del mes pasado (del día 1 al mismo día).
 */
class EsteMes
{
    /** Preferencia de IVA compartida por Resumen y Finanzas (la última que elegiste en Finanzas) */
    public static function conIva(): bool
    {
        return request()->cookie('vandu_iva') === 'con';
    }

    public static function fechaAceptada(Presupuesto $p): string
    {
        return ($p->aceptada_el ?? $p->updated_at->copy()->setTimezone(config('vandu.zona_horaria')))->toDateString();
    }

    /** @return array{cobrado: float, cobradoSinIva: float, pagos: int, anterior: float, variacion: ?int, vendido: float, ventas: int, mes: string, alDia: string} */
    public static function datos(bool $conIva = true): array
    {
        $tz = config('vandu.zona_horaria');
        $hoy = now($tz);
        $ini = $hoy->copy()->startOfMonth()->toDateString();
        $fin = $hoy->toDateString();
        $antIni = $hoy->copy()->subMonthNoOverflow()->startOfMonth();
        $antFin = $antIni->copy()->day(min($hoy->day, $antIni->daysInMonth))->toDateString();

        $pagos = ProyectoPago::with('proyecto.presupuesto.conceptos')->whereNotNull('pagado_el')
            ->whereDate('pagado_el', '>=', $antIni->toDateString())->whereDate('pagado_el', '<=', $fin)->get();

        // Factor para expresar un pago sin IVA, según la cotización de origen (redondeado por pago, igual que en Finanzas)
        $sinIva = function (ProyectoPago $pg) {
            $p = $pg->proyecto?->presupuesto;
            return $p && $p->total > 0 ? round($pg->monto * $p->subtotal / $p->total, 2) : (float) $pg->monto;
        };
        $delMes = $pagos->filter(fn ($pg) => $pg->pagado_el->toDateString() >= $ini);
        $previos = $pagos->filter(fn ($pg) => $pg->pagado_el->toDateString() <= $antFin);

        $cobrado = round($delMes->sum('monto'), 2);
        $cobradoSinIva = round($delMes->sum($sinIva), 2);
        $anterior = round($conIva ? $previos->sum('monto') : $previos->sum($sinIva), 2);
        $actual = $conIva ? $cobrado : $cobradoSinIva;

        // Ventas directas (mostrador y pedidos)
        $vPagos = \App\Support\Ventas\Numeros::pagos($antIni->toDateString(), $fin, true);
        $vSin = \App\Support\Ventas\Numeros::pagos($antIni->toDateString(), $fin, false);
        $cobrado = round($cobrado + $vPagos->filter(fn ($x) => $x->fecha >= $ini)->sum('monto'), 2);
        $cobradoSinIva = round($cobradoSinIva + $vSin->filter(fn ($x) => $x->fecha >= $ini)->sum('monto'), 2);
        $anterior = round($anterior + ($conIva ? $vPagos : $vSin)->filter(fn ($x) => $x->fecha <= $antFin)->sum('monto'), 2);
        $actual = $conIva ? $cobrado : $cobradoSinIva;
        $directas = \App\Support\Ventas\Numeros::vendidas($ini, $fin);

        // Igual que Finanzas: por la fecha en que se aceptó (o la última modificación si no la tiene)
        $ventas = Presupuesto::with('conceptos')->where('estado', 'aceptada')->get()
            ->filter(fn ($p) => ($f = static::fechaAceptada($p)) >= $ini && $f <= $fin);

        return [
            'cobrado'       => $actual,
            'cobradoConIva' => $cobrado,
            'cobradoSinIva' => $cobradoSinIva,
            'pagos'         => $delMes->count() + $vPagos->filter(fn ($x) => $x->fecha >= $ini)->count(),
            'anterior'      => $anterior,
            'variacion'     => $anterior > 0 ? (int) round(($actual - $anterior) / $anterior * 100) : null,
            'vendido'       => round($ventas->sum(fn ($p) => $conIva ? $p->total : $p->subtotal) + $directas->sum(fn ($v) => \App\Support\Ventas\Numeros::monto($v, $conIva)), 2),
            'ventas'        => $ventas->count() + $directas->count(),
            'mes'           => ucfirst($hoy->locale('es')->isoFormat('MMMM')),
            'alDia'         => $hoy->day . ' de ' . $antIni->locale('es')->isoFormat('MMMM'),
        ];
    }
}
