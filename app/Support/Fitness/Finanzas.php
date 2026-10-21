<?php

namespace App\Support\Fitness;

use App\Models\Membresia;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Dinero del gimnasio: cada paquete asignado es una venta por su precio.
 *  - Vendido se cuenta por la fecha en que se asignó.
 *  - Cobrado, por la fecha de pago.
 *  - Por cobrar: paquetes activos sin fecha de pago.
 * Los paquetes cancelados no cuentan.
 */
class Finanzas
{
    public static function datos(?string $desde, ?string $hasta): array
    {
        $tz = config('vandu.zona_horaria');
        $todas = Membresia::with('cliente:id,nombre')->where('estado', '!=', 'cancelada')->orderBy('id')->get();
        $fechaVenta = fn (Membresia $m) => $m->created_at->copy()->setTimezone($tz)->toDateString();
        $enRango = fn (?string $f) => $f && (! $desde || $f >= $desde) && (! $hasta || $f <= $hasta);

        $vendidas = $todas->filter(fn ($m) => $enRango($fechaVenta($m)));
        $cobradas = $todas->filter(fn ($m) => $m->pagado_el && $enRango($m->pagado_el->toDateString()));
        $pendientes = $todas->filter(fn ($m) => $m->estado === 'activa' && ! $m->pagado_el)->sortBy('created_at')->values();

        // Renovación: el atleta ya había tenido un paquete antes
        $primera = $todas->groupBy('cliente_id')->map(fn ($g) => $g->min('id'));
        $renov = $vendidas->filter(fn ($m) => $primera[$m->cliente_id] !== $m->id);

        $meses = collect(range(11, 0))->map(function ($i) use ($tz, $todas, $fechaVenta) {
            $mes = now($tz)->startOfMonth()->subMonthsNoOverflow($i)->format('Y-m');
            return [
                'etiqueta' => rtrim(ucfirst(Carbon::parse($mes . '-01')->locale('es')->isoFormat('MMM')), '.'),
                'vendido'  => round($todas->filter(fn ($m) => substr($fechaVenta($m), 0, 7) === $mes)->sum('precio'), 2),
                'cobrado'  => round($todas->filter(fn ($m) => $m->pagado_el?->format('Y-m') === $mes)->sum('precio'), 2),
                'actual'   => $i === 0,
            ];
        });

        return [
            'kpi' => [
                'vendido'     => round($vendidas->sum('precio'), 2),
                'paquetes'    => $vendidas->count(),
                'cobrado'     => round($cobradas->sum('precio'), 2),
                'porCobrar'   => round($pendientes->sum('precio'), 2),
                'nPorCobrar'  => $pendientes->count(),
                'ticket'      => $vendidas->count() ? round($vendidas->avg('precio'), 2) : 0,
                'renovaciones' => $renov->count(),
                'nuevos'      => $vendidas->count() - $renov->count(),
            ],
            'porPaquete' => $vendidas->groupBy('nombre')->map(fn (Collection $g, $n) => ['nombre' => $n, 'n' => $g->count(), 'monto' => round($g->sum('precio'), 2)])
                ->sortByDesc('monto')->values(),
            'porMetodo' => $cobradas->groupBy(fn ($m) => $m->metodo_pago ?: 'efectivo')->map(fn ($g, $k) => ['metodo' => Membresia::METODOS[$k] ?? $k, 'monto' => round($g->sum('precio'), 2), 'n' => $g->count()])->values(),
            'pendientes' => $pendientes,
            'meses' => $meses,
            'vendidas' => $vendidas,
        ];
    }
}
