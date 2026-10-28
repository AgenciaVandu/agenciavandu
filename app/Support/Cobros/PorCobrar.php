<?php

namespace App\Support\Cobros;

use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Proyecto;
use App\Models\ProyectoPago;
use App\Models\User;
use App\Models\Venta;
use App\Support\Cuentas;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Todo lo que te deben, venga de donde venga: pagos de proyectos, ventas a crédito y créditos de autos.
 * Es la base del botón "Registrar pago": eliges al cliente y ves en un solo lugar qué te debe.
 */
class PorCobrar
{
    /** Qué puede cobrar esta persona según su rol y lo que tiene prendido el negocio */
    public static function fuentes(User $u): array
    {
        $c = Cuentas::actual();
        return array_filter([
            'proyectos' => $c?->tiene('proyectos') && ($u->puede('proyectos') || $u->puede('finanzas')),
            'ventas'    => $c?->tiene('stock') && ($u->puede('vender') || $u->puede('finanzas')),
            'creditos'  => $c?->tiene('autos') && $u->puede('cobranza'),
        ]);
    }

    public static function puede(User $u): bool
    {
        return (bool) self::fuentes($u);
    }

    /** @return Collection<int, array{tipo: string, id: int, cliente_id: ?int, titulo: string, detalle: string, monto: float, vence: ?Carbon, vencido: bool, url: string, modelo: mixed}> */
    public static function lista(User $u, ?int $clienteId = null): Collection
    {
        $f = self::fuentes($u);
        $hoy = Carbon::now(config('vandu.zona_horaria'))->startOfDay();
        $r = collect();

        if (! empty($f['proyectos'])) {
            ProyectoPago::whereNull('pagado_el')->where('monto', '>', 0)
                ->whereHas('proyecto', fn ($q) => $q->whereNotIn('estado', Proyecto::SIN_COBRO)->when($clienteId, fn ($q2) => $q2->where('cliente_id', $clienteId)))
                ->with('proyecto.cliente')->orderBy('vence_el')->get()
                ->each(fn ($p) => $r->push(['tipo' => 'proyecto', 'id' => $p->id, 'cliente_id' => $p->proyecto->cliente_id, 'cliente' => $p->proyecto->cliente,
                    'titulo' => $p->concepto, 'detalle' => 'Proyecto ' . $p->proyecto->nombre, 'monto' => (float) $p->monto,
                    'vence' => $p->vence_el ? Carbon::parse($p->vence_el) : null, 'vencido' => $p->vence_el && Carbon::parse($p->vence_el)->lt($hoy),
                    'url' => route('admin.proyectos.show', $p->proyecto) . '#pagos', 'modelo' => $p]));
        }
        if (! empty($f['ventas'])) {
            Venta::porCobrar()->when($clienteId, fn ($q) => $q->where('cliente_id', $clienteId))->with('cliente')->orderBy('fecha')->get()
                ->each(fn ($v) => $r->push(['tipo' => 'venta', 'id' => $v->id, 'cliente_id' => $v->cliente_id, 'cliente' => $v->cliente,
                    'titulo' => 'Venta ' . $v->folio, 'detalle' => 'Del ' . $v->fecha?->locale('es')->isoFormat('D MMM') . ' · total ' . Venta::dinero($v->total), 'monto' => $v->saldo,
                    'vence' => $v->vence_el, 'vencido' => $v->vence_el && $v->vence_el->lt($hoy),
                    'url' => route('admin.pedidos.show', $v), 'modelo' => $v]));
        }
        if (! empty($f['creditos'])) {
            Credito::conCobranza()->when($clienteId, fn ($q) => $q->where('cliente_id', $clienteId))->with(['cliente', 'unidad', 'letras'])->get()
                ->each(function ($c) use ($r) {
                    $prox = $c->proxima;
                    if (! $prox) return;
                    $atr = $c->atrasadas();
                    $r->push(['tipo' => 'credito', 'id' => $c->id, 'cliente_id' => $c->cliente_id, 'cliente' => $c->cliente,
                        'titulo' => $atr->isNotEmpty() ? $atr->count() . ($atr->count() === 1 ? ' letra atrasada' : ' letras atrasadas') : 'Letra ' . $prox->numero,
                        'detalle' => 'Crédito ' . ($c->unidad?->nombre ?? ''), 'monto' => $atr->isNotEmpty() ? (float) $c->vencido : (float) $prox->monto,
                        'vence' => $prox->vence_el ? Carbon::parse($prox->vence_el) : null, 'vencido' => $atr->isNotEmpty(),
                        'url' => route('admin.creditos.show', $c), 'modelo' => $c]);
                });
        }
        return $r->sortBy(fn ($x) => [$x['vencido'] ? 0 : 1, $x['vence']?->timestamp ?? PHP_INT_MAX])->values();
    }

    /** Clientes que te deben, con cuánto y si algo ya venció */
    public static function clientes(User $u): Collection
    {
        return self::lista($u)->groupBy(fn ($x) => $x['cliente_id'] ?: 'sin')->map(fn ($g, $k) => [
            'cliente_id' => $k === 'sin' ? null : (int) $k,
            'nombre' => $g->first()['cliente']?->empresa ?: ($g->first()['cliente']?->nombre ?: ($g->first()['modelo']->cliente_nombre ?? 'Sin cliente')),
            'monto' => round($g->sum('monto'), 2), 'n' => $g->count(), 'vencido' => $g->contains('vencido', true),
        ])->sortBy(fn ($x) => [$x['vencido'] ? 0 : 1, -$x['monto']])->values();
    }
}
