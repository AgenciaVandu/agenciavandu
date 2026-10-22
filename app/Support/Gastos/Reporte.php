<?php

namespace App\Support\Gastos;

use App\Models\Gasto;
use Illuminate\Support\Carbon;

/** Números de los gastos sueltos de un periodo (Gastos, Finanzas y el gimnasio usan los mismos) */
class Reporte
{
    public static function datos(?string $desde, ?string $hasta, bool $conIva = true, ?int $soloDe = null): array
    {
        $tz = config('vandu.zona_horaria');
        $base = Gasto::query()->when($soloDe, fn ($q) => $q->where('user_id', $soloDe));
        $g = (clone $base)->entre($desde, $hasta)->with('usuario:id,name')->get();
        $m = fn (Gasto $x) => $x->monto($conIva);
        $total = round($g->sum($m), 2);

        $todos = (clone $base)->where('fecha', '>=', now($tz)->startOfMonth()->subMonthsNoOverflow(11)->toDateString())->get(['fecha', 'subtotal', 'total']);
        $meses = collect(range(11, 0))->map(function ($i) use ($tz, $todos, $m) {
            $mes = now($tz)->startOfMonth()->subMonthsNoOverflow($i);
            return [
                'etiqueta' => rtrim(ucfirst($mes->locale('es')->isoFormat('MMM')), '.'),
                'monto'    => round($todos->filter(fn ($x) => $x->fecha->format('Y-m') === $mes->format('Y-m'))->sum($m), 2),
                'actual'   => $i === 0,
            ];
        });

        return [
            'total'        => $total,
            'n'            => $g->count(),
            'conFactura'   => $g->where('origen', 'xml')->count(),
            'conFoto'      => $g->where('origen', 'foto')->count(),
            'sinComprobante' => $g->filter(fn ($x) => ! $x->foto && ! $x->xml)->count(),
            'iva'          => round($g->sum('iva'), 2),
            'porCategoria' => $g->groupBy('categoria')->map(fn ($c, $k) => [
                'clave' => $k, 'texto' => Gasto::CATEGORIAS[$k]['texto'] ?? 'Otros', 'icono' => Gasto::CATEGORIAS[$k]['icono'] ?? 'bi-three-dots',
                'monto' => round($c->sum($m), 2), 'n' => $c->count(),
            ])->sortByDesc('monto')->values(),
            'porPersona'   => $g->groupBy('user_id')->map(fn ($c) => ['nombre' => $c->first()->usuario?->name ?? 'Sin usuario', 'monto' => round($c->sum($m), 2), 'n' => $c->count()])
                ->sortByDesc('monto')->values(),
            'meses'        => $meses,
        ];
    }

    /** Gastos ligados a cada proyecto (antes de IVA, como los costos): proyecto_id => monto */
    public static function porProyecto(iterable $proyectos): \Illuminate\Support\Collection
    {
        $ids = collect($proyectos)->filter()->values();
        if ($ids->isEmpty() || ! \App\Support\Cuentas::actual()?->tiene('gastos')) return collect();
        return Gasto::whereIn('proyecto_id', $ids)->get(['proyecto_id', 'subtotal'])->groupBy('proyecto_id')->map(fn ($c) => round($c->sum('subtotal'), 2));
    }
}
