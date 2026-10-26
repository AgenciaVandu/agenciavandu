<?php

namespace App\Support\Licencias;

use App\Models\Cuenta;
use App\Models\Licencia;
use App\Models\LicenciaComprobante;
use App\Models\LicenciaPago;
use App\Support\Cuentas;
use Illuminate\Support\Collection;

/**
 * Los números de la plataforma como negocio:
 *  - Ingreso recurrente mensual: suscripciones vigentes (las anuales cuentan 1/12) y mantenimientos de por vida (1/12).
 *  - Cobrado: lo que entró por mes (mensualidades, anualidades, implementaciones y mantenimiento).
 *  - Vencidos (en gracia, solo lectura o suspendidos), próximos vencimientos (30 días), clientes por plan,
 *    implementaciones cobradas y pendientes, y comprobantes por revisar.
 */
class Numeros
{
    public static function datos(): array
    {
        $tz = config('vandu.zona_horaria');
        $hoy = now($tz)->startOfDay();
        $licencias = Licencia::with(['plan', 'cuenta'])->get()->filter(fn ($l) => $l->cuenta && $l->cuenta_id !== Cuentas::principalId());
        $conEstado = $licencias->map(fn ($l) => (object) ['l' => $l, 'e' => Estado::de($l->cuenta)]);
        $mensual = fn (Licencia $l) => match ($l->tipo) {
            'suscripcion' => $l->periodo === 'anual' ? $l->precio / 12 : $l->precio,
            'perpetua' => (float) $l->mantenimiento / 12,
            default => 0.0,
        };
        // Recurrente: los que siguen activos (los suspendidos ya no cuentan hasta que paguen)
        $vigentes = $conEstado->filter(fn ($x) => $x->l->cuenta->activa && $x->e->clave !== 'suspendida' && in_array($x->l->tipo, ['suscripcion', 'perpetua'], true));
        $mrr = round($vigentes->sum(fn ($x) => $mensual($x->l)), 2);

        $pagos = LicenciaPago::where('fecha', '>=', $hoy->copy()->startOfMonth()->subMonthsNoOverflow(11)->toDateString())->get();
        $meses = collect(range(11, 0))->map(function ($i) use ($hoy, $pagos) {
            $m = $hoy->copy()->startOfMonth()->subMonthsNoOverflow($i);
            $del = $pagos->filter(fn ($p) => $p->fecha->format('Y-m') === $m->format('Y-m'));
            return ['etiqueta' => rtrim(ucfirst($m->locale('es')->isoFormat('MMM')), '.'), 'completa' => ucfirst($m->locale('es')->isoFormat('MMMM YYYY')),
                    'monto' => round($del->sum('monto'), 2), 'recurrente' => round($del->whereIn('concepto', ['periodo', 'mantenimiento'])->sum('monto'), 2), 'actual' => $i === 0];
        });
        $esteMes = $pagos->filter(fn ($p) => $p->fecha->format('Y-m') === $hoy->format('Y-m'));
        $mesPasado = $pagos->filter(fn ($p) => $p->fecha->format('Y-m') === $hoy->copy()->subMonthNoOverflow()->format('Y-m'));

        $vencidos = $conEstado->filter(fn ($x) => in_array($x->e->clave, ['gracia', 'lectura', 'suspendida'], true) && $x->l->cuenta->activa)
            ->sortBy(fn ($x) => $x->e->dias)->values();
        $limite = $hoy->copy()->addDays(30)->toDateString();
        $proximos = $conEstado->filter(fn ($x) => in_array($x->l->tipo, ['suscripcion', 'perpetua'], true) && $x->l->vence_el && $x->l->cuenta->activa
            && $x->l->vence_el->toDateString() >= $hoy->toDateString() && $x->l->vence_el->toDateString() <= $limite)
            ->sortBy(fn ($x) => $x->l->vence_el->toDateString())->values();

        $porPlan = $vigentes->groupBy(fn ($x) => $x->l->plan?->nombre ?? \App\Models\Licencia::TIPOS[$x->l->tipo]['texto'])
            ->map(fn ($g, $n) => ['nombre' => $n, 'n' => $g->count(), 'mrr' => round($g->sum(fn ($x) => $mensual($x->l)), 2)])->sortByDesc('mrr')->values();

        return [
            'mrr'          => $mrr,
            'arr'          => round($mrr * 12, 2),
            'clientes'     => $vigentes->count(),
            'cortesia'     => $conEstado->filter(fn ($x) => $x->l->tipo === 'cortesia')->count(),
            'sinLicencia'  => Cuenta::where('id', '!=', Cuentas::principalId())->whereNotIn('id', $licencias->pluck('cuenta_id'))->count(),
            'ticket'       => $vigentes->count() ? round($mrr / $vigentes->count(), 2) : null,
            'cobradoMes'   => round($esteMes->sum('monto'), 2),
            'cobradoAnt'   => round($mesPasado->sum('monto'), 2),
            'porConcepto'  => $esteMes->groupBy('concepto')->map(fn ($g, $k) => ['texto' => LicenciaPago::CONCEPTOS[$k] ?? $k, 'monto' => round($g->sum('monto'), 2), 'n' => $g->count()])->values(),
            'meses'        => $meses,
            'vencidos'     => $vencidos,
            'vencidoMonto' => round($vencidos->sum(fn ($x) => $x->l->tipo === 'perpetua' ? (float) $x->l->mantenimiento : $x->l->precio), 2),
            'proximos'     => $proximos,
            'proximoMonto' => round($proximos->sum(fn ($x) => $x->l->tipo === 'perpetua' ? (float) $x->l->mantenimiento : $x->l->precio), 2),
            'porPlan'      => $porPlan,
            'implCobrada'  => round(LicenciaPago::where('concepto', 'implementacion')->sum('monto'), 2),
            'implPendiente' => $licencias->filter(fn ($l) => $l->implementacion > 0 && ! $l->implementacion_pagada_el)->values(),
            'suspendidos'  => $conEstado->filter(fn ($x) => $x->e->clave === 'suspendida')->count(),
            'apagadas'     => Cuenta::where('activa', false)->count(),
            'comprobantes' => LicenciaComprobante::with(['cuenta', 'usuario' => fn ($q) => $q->withoutGlobalScope('cuenta')])->where('estado', 'pendiente')->latest('id')->get(),
        ];
    }
}
