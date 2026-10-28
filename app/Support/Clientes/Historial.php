<?php

namespace App\Support\Clientes;

use App\Models\Cliente;
use App\Support\Cuentas;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Línea de tiempo de un cliente: todo lo que ha pasado con él, de lo más nuevo a lo más viejo */
class Historial
{
    /** @return Collection<int, array{fecha: Carbon, icono: string, color: string, texto: string, detalle: ?string, url: ?string}> */
    public static function de(Cliente $c, int $max = 30): Collection
    {
        $cu = Cuentas::actual();
        $d = fn ($n) => '$' . number_format((float) $n, 2);
        $e = collect();
        $add = function ($fecha, $icono, $color, $texto, $detalle = null, $url = null) use ($e) {
            if ($fecha) $e->push(['fecha' => $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha), 'icono' => $icono, 'color' => $color, 'texto' => $texto, 'detalle' => $detalle, 'url' => $url]);
        };
        $intenta = function (callable $f) { try { $f(); } catch (\Throwable $ex) { report($ex); } };

        $add($c->created_at, 'bi-person-plus', 'gris', $c->origen === 'sitio' ? 'Llegó por tu sitio web' : 'Lo diste de alta', $c->interes ? 'Le interesa: ' . $c->interes : null);

        if ($cu?->tiene('cotizaciones')) $intenta(function () use ($c, $add) {
            foreach ($c->presupuestos()->with('conceptos')->get() as $p) {
                $u = route('admin.presupuestos.edit', $p);
                $res = \Illuminate\Support\Str::limit($p->conceptos->first()?->resumen ?: $p->titulo, 60);
                $add($p->created_at, 'bi-file-earmark-text', 'gris', "Cotización {$p->folio}", $res, $u);
                if ($p->aceptada_el) $add($p->aceptada_el, 'bi-check2-circle', 'verde', "Aceptó la cotización {$p->folio}", $p->monto($p->modo_iva === 'desglosado' ? $p->total : $p->subtotal), $u);
                if ($p->cancelada_at) $add($p->cancelada_at, 'bi-x-circle', 'gris', "Se canceló {$p->folio}", $p->motivo_cancelacion, $u);
            }
        });
        if ($cu?->tiene('proyectos')) $intenta(function () use ($c, $add, $d) {
            foreach ($c->proyectos()->with('pagos')->get() as $pr) {
                $u = route('admin.proyectos.show', $pr);
                $add($pr->created_at, 'bi-kanban', 'azul', "Arrancó el proyecto {$pr->nombre}", null, $u);
                foreach ($pr->pagos->whereNotNull('pagado_el') as $pg) $add(Carbon::parse($pg->pagado_el)->setTime(12, 0), 'bi-cash-coin', 'verde', 'Pagó ' . $d($pg->monto), $pg->concepto . ' · ' . $pr->nombre, $u);
            }
        });
        if ($cu?->tiene('stock')) $intenta(function () use ($c, $add, $d) {
            foreach (\App\Models\Venta::where('cliente_id', $c->id)->with('pagos')->get() as $v) {
                $u = route('admin.pedidos.show', $v);
                $add($v->created_at, 'bi-bag', 'azul', ($v->estado === 'cancelada' ? 'Venta cancelada ' : 'Compró ') . $d($v->total), 'Venta ' . $v->folio, $u);
                foreach ($v->pagos as $pg) $add($pg->created_at, 'bi-cash-coin', 'verde', 'Pagó ' . $d($pg->monto), 'Venta ' . $v->folio . ' · ' . (\App\Models\Venta::FORMAS[$pg->forma] ?? $pg->forma), $u);
            }
        });
        $intenta(function () use ($c, $add) {
            foreach (\App\Models\Correo::where('cliente_id', $c->id)->latest()->take(20)->get() as $co)
                $add($co->created_at, 'bi-envelope', $co->estado === 'error' ? 'rojo' : 'gris', 'Le enviaste un correo', $co->asunto);
            foreach ($c->mensajes()->take(10)->get() as $m)
                $add($m->created_at, 'bi-chat-dots', 'ambar', 'Te escribió desde el sitio', $m->servicio);
        });

        return $e->sortByDesc(fn ($x) => $x['fecha']->timestamp)->values()->take($max);
    }
}
