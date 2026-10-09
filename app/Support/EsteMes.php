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

        // Factor para expresar un pago sin IVA, según la cotización de origen
        $sinIva = function (ProyectoPago $pg) {
            $p = $pg->proyecto?->presupuesto;
            return $p && $p->total > 0 ? $pg->monto * $p->subtotal / $p->total : $pg->monto;
        };
        $delMes = $pagos->filter(fn ($pg) => $pg->pagado_el->toDateString() >= $ini);
        $previos = $pagos->filter(fn ($pg) => $pg->pagado_el->toDateString() <= $antFin);

        $cobrado = round($delMes->sum('monto'), 2);
        $cobradoSinIva = round($delMes->sum($sinIva), 2);
        $anterior = round($conIva ? $previos->sum('monto') : $previos->sum($sinIva), 2);
        $actual = $conIva ? $cobrado : $cobradoSinIva;

        $ventas = Presupuesto::with('conceptos')->where('estado', 'aceptada')->whereDate('aceptada_el', '>=', $ini)->whereDate('aceptada_el', '<=', $fin)->get();

        return [
            'cobrado'       => $actual,
            'cobradoConIva' => $cobrado,
            'cobradoSinIva' => $cobradoSinIva,
            'pagos'         => $delMes->count(),
            'anterior'      => $anterior,
            'variacion'     => $anterior > 0 ? (int) round(($actual - $anterior) / $anterior * 100) : null,
            'vendido'       => round($ventas->sum(fn ($p) => $conIva ? $p->total : $p->subtotal), 2),
            'ventas'        => $ventas->count(),
            'mes'           => ucfirst($hoy->locale('es')->isoFormat('MMMM')),
            'alDia'         => $hoy->day . ' de ' . $antIni->locale('es')->isoFormat('MMMM'),
        ];
    }
}
