<?php

namespace App\Support\Dinero;

use App\Models\CuentaDinero;
use App\Models\Gasto;
use App\Models\MovimientoDinero;
use App\Models\OrdenCompra;
use App\Models\ProyectoPago;
use App\Models\VentaPago;
use App\Support\Cuentas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * El dinero que de verdad entró y salió, con la cuenta donde pasó.
 *  - Entra: cobros de proyectos (pagos marcados como pagados) y pagos o abonos de ventas.
 *  - Sale: gastos y órdenes de compra pagadas.
 *  - Se mueve: traspasos, retiros y aportaciones del dueño y ajustes de conciliación.
 * Los montos son lo que pasó por la cuenta (con IVA).
 */
class Movimientos
{
    private static array $cache = [];

    /** La forma de pago de cualquier módulo, en las cuatro que entienden las cuentas */
    public static function forma(?string $f): ?string
    {
        return match ((string) $f) {
            '' => null,
            'efectivo' => 'efectivo',
            'transferencia', 'credito', 'spei', 'deposito' => 'transferencia',
            'tarjeta', 'tarjeta_credito', 'tarjeta_debito' => 'tarjeta',
            default => 'otro',
        };
    }

    /** @return Collection<CuentaDinero> las activas de este negocio (una consulta por petición) */
    public static function cuentas(): Collection
    {
        $id = Cuentas::actual()?->id;
        if (! $id) return collect();
        return self::$cache[$id] ??= CuentaDinero::activas()->get();
    }

    public static function olvidar(): void
    {
        self::$cache = [];
    }

    /** A qué cuenta cae una forma de pago: la que la tiene marcada, o la primera de su tipo */
    public static function cuentaPara(?string $forma): ?int
    {
        $f = self::forma($forma);
        if (! $f) return null;
        $c = self::cuentas();
        return $c->first(fn ($x) => in_array($f, (array) $x->formas, true))?->id;
    }

    /** Pone la cuenta si nadie la eligió (lo llaman los modelos al guardarse) */
    public static function asignar(Model $m, ?string $forma): void
    {
        if (! $m->cuenta_dinero_id && Cuentas::actual()) $m->cuenta_dinero_id = self::cuentaPara($forma);
    }

    /** Al crear una cuenta: lo que ya estaba registrado desde su fecha de inicio y sin cuenta cae aquí según su forma */
    public static function asignarPendientes(CuentaDinero $c): int
    {
        self::olvidar();
        $n = 0;
        $desde = $c->saldo_desde->toDateString();
        $toca = fn (?string $f) => self::cuentaPara($f) === $c->id;
        foreach (ProyectoPago::whereNull('cuenta_dinero_id')->whereNotNull('pagado_el')->whereDate('pagado_el', '>=', $desde)->get() as $x) {
            if ($toca($x->metodo)) { $x->forceFill(['cuenta_dinero_id' => $c->id])->saveQuietly(); $n++; }
        }
        foreach (VentaPago::whereNull('cuenta_dinero_id')->whereDate('fecha', '>=', $desde)->get() as $x) {
            if ($toca($x->forma)) { $x->forceFill(['cuenta_dinero_id' => $c->id])->saveQuietly(); $n++; }
        }
        foreach (Gasto::whereNull('cuenta_dinero_id')->whereDate('fecha', '>=', $desde)->get() as $x) {
            if ($toca($x->forma_pago)) { $x->forceFill(['cuenta_dinero_id' => $c->id])->saveQuietly(); $n++; }
        }
        foreach (OrdenCompra::whereNull('cuenta_dinero_id')->whereNotNull('pagada_el')->whereDate('pagada_el', '>=', $desde)->get() as $x) {
            if ($toca('transferencia')) { $x->forceFill(['cuenta_dinero_id' => $c->id])->saveQuietly(); $n++; }
        }
        return $n;
    }

    /** Modelo de cada clase de movimiento que se puede cambiar de cuenta */
    public const CLASES = ['cobro' => ProyectoPago::class, 'venta' => VentaPago::class, 'gasto' => Gasto::class, 'compra' => OrdenCompra::class];

    /**
     * Todo lo que entró y salió entre dos fechas (null = sin límite).
     * Cada renglón: fecha, clase, id, concepto, detalle, monto (+ entra, − sale), cuenta (id o null), forma, url.
     * Los traspasos salen dos veces (de una cuenta y a otra); con $soloExternos se omiten para sumar el flujo real.
     */
    public static function lista(?string $desde, ?string $hasta, bool $soloExternos = false): Collection
    {
        $rango = fn ($q, string $col) => $q->when($desde, fn ($w) => $w->whereDate($col, '>=', $desde))->when($hasta, fn ($w) => $w->whereDate($col, '<=', $hasta));
        $filas = collect();

        foreach ($rango(ProyectoPago::with('proyecto:id,nombre,cliente_id', 'proyecto.cliente:id,nombre,empresa')->whereNotNull('pagado_el'), 'pagado_el')->get() as $p) {
            $filas->push((object) ['fecha' => $p->pagado_el->toDateString(), 'clase' => 'cobro', 'id' => $p->id, 'concepto' => $p->concepto ?: 'Cobro',
                'detalle' => $p->proyecto?->cliente?->empresa ?: ($p->proyecto?->cliente?->nombre ?: $p->proyecto?->nombre), 'monto' => round((float) $p->monto, 2),
                'cuenta' => $p->cuenta_dinero_id, 'forma' => self::forma($p->metodo), 'url' => $p->proyecto ? route('admin.proyectos.show', $p->proyecto_id) : null]);
        }
        if (Cuentas::actual()?->tiene('stock')) {
            foreach ($rango(VentaPago::with('venta:id,folio,cliente_id,cliente_nombre,estado', 'venta.cliente:id,nombre,empresa')->whereHas('venta', fn ($q) => $q->vigentes()), 'fecha')->get() as $p) {
                $filas->push((object) ['fecha' => $p->fecha->toDateString(), 'clase' => 'venta', 'id' => $p->id, 'concepto' => 'Venta ' . $p->venta?->folio,
                    'detalle' => $p->venta?->quien, 'monto' => round((float) $p->monto, 2), 'cuenta' => $p->cuenta_dinero_id, 'forma' => self::forma($p->forma),
                    'url' => $p->venta ? route('admin.pedidos.show', $p->venta_id) : null]);
            }
        }
        if (Cuentas::actual()?->tiene('gastos')) {
            foreach ($rango(Gasto::query(), 'fecha')->get() as $g) {
                $filas->push((object) ['fecha' => $g->fecha->toDateString(), 'clase' => 'gasto', 'id' => $g->id, 'concepto' => $g->concepto,
                    'detalle' => $g->proveedor ?: $g->categoria_texto, 'monto' => -round((float) $g->total, 2), 'cuenta' => $g->cuenta_dinero_id, 'forma' => self::forma($g->forma_pago),
                    'url' => route('admin.gastos.edit', $g)]);
            }
        }
        foreach ($rango(OrdenCompra::with('conceptos', 'proveedor:id,empresa')->whereNotNull('pagada_el')->where('estado', 'pagada'), 'pagada_el')->get() as $o) {
            $filas->push((object) ['fecha' => $o->pagada_el->toDateString(), 'clase' => 'compra', 'id' => $o->id, 'concepto' => 'Orden ' . $o->folio,
                'detalle' => $o->proveedor?->empresa, 'monto' => -round($o->total_final, 2), 'cuenta' => $o->cuenta_dinero_id, 'forma' => 'transferencia',
                'url' => route('admin.ordenes.show', $o)]);
        }
        foreach ($rango(MovimientoDinero::with('de:id,nombre', 'a:id,nombre'), 'fecha')->get() as $m) {
            if ($m->tipo === 'traspaso' && $soloExternos) continue;
            $concepto = $m->tipo_texto . ($m->nota ? ': ' . $m->nota : '');
            if ($m->de_id) $filas->push((object) ['fecha' => $m->fecha->toDateString(), 'clase' => 'mov', 'id' => $m->id, 'concepto' => $concepto,
                'detalle' => $m->tipo === 'traspaso' ? 'A ' . $m->a?->nombre : null, 'monto' => -round($m->monto, 2), 'cuenta' => $m->de_id, 'forma' => null, 'url' => null, 'tipo' => $m->tipo]);
            if ($m->a_id) $filas->push((object) ['fecha' => $m->fecha->toDateString(), 'clase' => 'mov', 'id' => $m->id, 'concepto' => $concepto,
                'detalle' => $m->tipo === 'traspaso' ? 'De ' . $m->de?->nombre : null, 'monto' => round($m->monto, 2), 'cuenta' => $m->a_id, 'forma' => null, 'url' => null, 'tipo' => $m->tipo]);
        }

        return $filas->sortByDesc(fn ($f) => $f->fecha . sprintf('%010d', $f->id))->values();
    }

    /**
     * Saldo que el panel espera en cada cuenta a una fecha: saldo inicial + lo que pasó por ella desde su inicio.
     * @return Collection<int, array{cuenta: CuentaDinero, saldo: float, entradas: float, salidas: float}>
     */
    public static function saldos(?string $hasta = null): Collection
    {
        $cuentas = CuentaDinero::activas()->with('conciliaciones')->get();
        if ($cuentas->isEmpty()) return collect();
        $desde = $cuentas->min(fn ($c) => $c->saldo_desde->toDateString());
        $filas = self::lista($desde, $hasta)->filter(fn ($f) => $f->cuenta);

        return $cuentas->mapWithKeys(function ($c) use ($filas) {
            $mias = $filas->filter(fn ($f) => $f->cuenta === $c->id && $f->fecha >= $c->saldo_desde->toDateString());
            return [$c->id => [
                'cuenta'   => $c,
                'entradas' => round($mias->where('monto', '>', 0)->sum('monto'), 2),
                'salidas'  => round(-$mias->where('monto', '<', 0)->sum('monto'), 2),
                'saldo'    => round($c->saldo_inicial + $mias->sum('monto'), 2),
            ]];
        });
    }

    /** Movimientos desde que hay cuentas que no quedaron en ninguna (forma sin cuenta, o sin forma) */
    public static function sinCuenta(): Collection
    {
        $c = self::cuentas();
        if ($c->isEmpty()) return collect();
        return self::lista($c->min(fn ($x) => $x->saldo_desde->toDateString()), null)
            ->filter(fn ($f) => ! $f->cuenta && $f->clase !== 'mov')->values();
    }
}
