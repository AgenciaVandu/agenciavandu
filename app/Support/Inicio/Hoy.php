<?php

namespace App\Support\Inicio;

use App\Models\User;
use App\Support\Cuentas;
use Illuminate\Support\Carbon;

/**
 * "Para hoy": lo que alguien tiene que hacer hoy en su negocio, según su rol y lo que tiene prendido.
 * Cada renglón lleva a la pantalla donde se resuelve. Lo que no aplica no aparece.
 */
class Hoy
{
    /** @return array<int, array{icono: string, color: string, titulo: string, detalle: string, url: string}> */
    public static function pendientes(User $u): array
    {
        $c = Cuentas::actual();
        if (! $c) return [];
        $hoy = Carbon::now(config('vandu.zona_horaria'))->toDateString();
        $d = fn ($n) => '$' . number_format($n, 0);
        $pl = fn ($n, $uno, $varios) => $n . ' ' . ($n === 1 ? $uno : $varios);
        $r = [];
        $intenta = function (callable $f) use (&$r) { try { if ($x = $f()) $r[] = $x; } catch (\Throwable $e) { report($e); } };

        // Pedidos por entregar
        if ($c->tiene('stock') && $u->puede('vender')) $intenta(function () use ($pl) {
            $n = \App\Models\Venta::porEntregar()->count();
            return $n ? ['icono' => 'bi-truck', 'color' => 'ambar', 'titulo' => $pl($n, 'pedido por entregar', 'pedidos por entregar'), 'detalle' => 'Surtir, enviar o marcar como entregado', 'url' => route('admin.pedidos', ['ver' => 'entregar'])] : null;
        });

        // Cobros que ya vencieron o vencen hoy (proyectos y ventas a crédito)
        if ($u->puede('finanzas') || $u->puede('proyectos') || $u->puede('vender')) $intenta(function () use ($c, $hoy, $d, $pl, $u) {
            $pagos = $c->tiene('proyectos') ? \App\Models\ProyectoPago::whereNull('pagado_el')->whereNotNull('vence_el')->whereDate('vence_el', '<=', $hoy)
                ->whereHas('proyecto', fn ($q) => $q->whereNotIn('estado', \App\Models\Proyecto::SIN_COBRO))->get(['monto']) : collect();
            $ventas = $c->tiene('stock') ? \App\Models\Venta::porCobrar()->whereNotNull('vence_el')->whereDate('vence_el', '<=', $hoy)->get() : collect();
            $n = $pagos->count() + $ventas->count();
            if (! $n) return null;
            $monto = $pagos->sum('monto') + $ventas->sum(fn ($v) => $v->total - $v->pagado);
            return ['icono' => 'bi-cash-coin', 'color' => 'rojo', 'titulo' => 'Cobrar ' . $d($monto), 'detalle' => $pl($n, 'pago vencido o que vence hoy', 'pagos vencidos o que vencen hoy'),
                'url' => \App\Support\Cobros\PorCobrar::puede($u) ? route('admin.cobrar') : route('admin.finanzas', ['seccion' => 'ventas']) . '#por-cobrar'];
        });

        // Entregas de proyecto (etapas) para hoy o atrasadas
        if ($c->tiene('proyectos') && $u->puede('proyectos')) $intenta(function () use ($hoy, $pl) {
            $n = \App\Models\ProyectoEtapa::where('estado', '!=', 'completada')->whereNotNull('fecha_fin')->whereDate('fecha_fin', '<=', $hoy)
                ->whereHas('proyecto', fn ($q) => $q->where('estado', 'activo'))->count();
            return $n ? ['icono' => 'bi-kanban', 'color' => 'ambar', 'titulo' => $pl($n, 'entrega de proyecto', 'entregas de proyecto'), 'detalle' => 'Para hoy o atrasadas', 'url' => route('admin.proyectos.index')] : null;
        });

        // Mis tareas para hoy o atrasadas (incluye seguimientos a prospectos)
        if ($c->tiene('tareas') || \App\Models\Tarea::where('asignada_a', $u->id)->whereNotNull('prospecto_id')->exists()) $intenta(function () use ($u, $hoy, $pl) {
            $q = \App\Models\Tarea::where('asignada_a', $u->id)->abiertas()->whereNotNull('fecha_limite')->whereDate('fecha_limite', '<=', $hoy);
            $n = $q->count();
            $atr = (clone $q)->whereDate('fecha_limite', '<', $hoy)->count();
            return $n ? ['icono' => 'bi-check2-square', 'color' => $atr ? 'rojo' : 'gris', 'titulo' => $pl($n, 'tarea para hoy', 'tareas para hoy'), 'detalle' => $atr ? $pl($atr, 'atrasada', 'atrasadas') : 'Tus pendientes del día', 'url' => route('admin.tareas.index')] : null;
        });

        // Prospectos nuevos sin atender
        if ($c->tiene('ventas') && $u->puede('ventas') && ! $c->tiene('fitness')) $intenta(function () use ($pl) {
            $n = \App\Models\Prospecto::where('nuevo', true)->count();
            return $n ? ['icono' => 'bi-funnel', 'color' => 'ambar', 'titulo' => $pl($n, 'prospecto nuevo', 'prospectos nuevos'), 'detalle' => 'Contéstales pronto: es cuando más compran', 'url' => route('admin.ventas.index')] : null;
        });

        // Cotizaciones que vencen en los próximos 2 días
        if ($c->tiene('cotizaciones') && $u->puede('cotizaciones')) $intenta(function () use ($pl) {
            $n = \App\Models\Presupuesto::whereNotIn('estado', ['aceptada', 'rechazada', 'cancelada', 'borrador'])
                ->whereBetween('vigente_hasta', [now(), now()->addDays(2)])->count();
            return $n ? ['icono' => 'bi-hourglass-split', 'color' => 'gris', 'titulo' => $pl($n, 'cotización por vencer', 'cotizaciones por vencer'), 'detalle' => 'Dales seguimiento antes de que venzan', 'url' => route('admin.presupuestos.index', ['filtro' => 'vigentes'])] : null;
        });

        // Publicaciones con cambios pedidos por el cliente
        if ($c->tiene('redes') && $u->puede('redes')) $intenta(function () use ($pl) {
            $n = \App\Models\RedesPost::where('estado', 'cambios')->count();
            return $n ? ['icono' => 'bi-chat-left-text', 'color' => 'ambar', 'titulo' => $pl($n, 'publicación con cambios', 'publicaciones con cambios'), 'detalle' => 'Tu cliente pidió ajustes', 'url' => route('admin.redes')] : null;
        });

        // Inventario bajo
        if ($c->tiene('stock') && $u->puede('productos')) $intenta(function () use ($pl) {
            $n = \App\Models\Producto::activos()->bajos()->count();
            return $n ? ['icono' => 'bi-box-seam', 'color' => 'gris', 'titulo' => $pl($n, 'producto en su mínimo', 'productos en su mínimo'), 'detalle' => 'Pídelos antes de que se acaben', 'url' => route('admin.productos.index', ['estado' => 'bajos'])] : null;
        });

        return $r;
    }
}
