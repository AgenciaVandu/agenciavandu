<?php

namespace App\Support\Dinero;

use App\Models\OrdenCompra;
use App\Models\Proyecto;
use App\Models\ProyectoPago;
use App\Models\Venta;
use App\Support\Cuentas;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Finanzas > Flujo: el dinero que de verdad entró y salió (no lo vendido ni lo facturado)
 * y lo que viene en las próximas semanas: lo que te deben pagar contra lo que tú debes pagar.
 */
class Flujo
{
    public const CLASES = [
        'cobro'      => ['texto' => 'Cobros de proyectos',   'icono' => 'bi-briefcase'],
        'venta'      => ['texto' => 'Ventas y abonos',       'icono' => 'bi-bag-check'],
        'licencia'   => ['texto' => 'Licencias de la plataforma', 'icono' => 'bi-patch-check'],
        'aportacion' => ['texto' => 'Aportaciones del dueño', 'icono' => 'bi-box-arrow-in-down-right'],
        'gasto'      => ['texto' => 'Gastos',                'icono' => 'bi-receipt'],
        'compra'     => ['texto' => 'Pagos a proveedores',   'icono' => 'bi-truck'],
        'retiro'     => ['texto' => 'Retiros del dueño',     'icono' => 'bi-box-arrow-up-right'],
        'ajuste'     => ['texto' => 'Ajustes de conciliación', 'icono' => 'bi-sliders'],
    ];

    private static function clase(object $f): string
    {
        return $f->clase === 'mov' ? ($f->tipo ?? 'ajuste') : $f->clase;
    }

    public static function datos(?string $desde, ?string $hasta): array
    {
        $tz = config('vandu.zona_horaria');
        $hoy = now($tz)->startOfDay();
        $filas = Movimientos::lista($desde, $hasta, true);
        $entradas = $filas->where('monto', '>', 0);
        $salidas = $filas->where('monto', '<', 0);
        $agrupar = fn (Collection $c) => $c->groupBy(fn ($f) => self::clase($f))->map(fn ($g, $k) => [
            'texto' => self::CLASES[$k]['texto'] ?? $k, 'icono' => self::CLASES[$k]['icono'] ?? 'bi-dot', 'monto' => round(abs($g->sum('monto')), 2), 'n' => $g->count(),
        ])->sortByDesc('monto')->values();

        // Últimos 12 meses
        $inicio = $hoy->copy()->startOfMonth()->subMonthsNoOverflow(11);
        $todo = Movimientos::lista($inicio->toDateString(), null, true);
        $meses = collect(range(11, 0))->map(function ($i) use ($hoy, $todo) {
            $m = $hoy->copy()->startOfMonth()->subMonthsNoOverflow($i);
            $del = $todo->filter(fn ($f) => substr($f->fecha, 0, 7) === $m->format('Y-m'));
            $e = round($del->where('monto', '>', 0)->sum('monto'), 2);
            $s = round(-$del->where('monto', '<', 0)->sum('monto'), 2);
            return ['etiqueta' => rtrim(ucfirst($m->locale('es')->isoFormat('MMM')), '.'), 'completa' => ucfirst($m->locale('es')->isoFormat('MMMM YYYY')),
                    'entradas' => $e, 'salidas' => $s, 'neto' => round($e - $s, 2), 'actual' => $i === 0];
        });

        $saldos = Movimientos::saldos();
        $saldoHoy = $saldos->isEmpty() ? null : round($saldos->sum('saldo'), 2);

        return [
            'entradas'   => round($entradas->sum('monto'), 2),
            'salidas'    => round(-$salidas->sum('monto'), 2),
            'neto'       => round($filas->sum('monto'), 2),
            'porClaseE'  => $agrupar($entradas),
            'porClaseS'  => $agrupar($salidas),
            'meses'      => $meses,
            'saldoHoy'   => $saldoHoy,
            'sinCuenta'  => $saldoHoy === null ? 0 : Movimientos::sinCuenta()->count(),
            'proyeccion' => self::proyeccion($hoy, $saldoHoy),
        ];
    }

    /**
     * Las próximas 8 semanas: cobros pendientes por su fecha de vencimiento y pagos a proveedores por su fecha de entrega.
     * Lo vencido va en la primera fila (todavía se espera); lo que no tiene fecha se cuenta aparte.
     */
    private static function proyeccion(Carbon $hoy, ?float $saldoHoy): array
    {
        $cobrar = collect();
        foreach (ProyectoPago::with('proyecto:id,nombre,estado,cliente_id', 'proyecto.cliente:id,nombre,empresa')->whereNull('pagado_el')->get() as $p) {
            if (! $p->proyecto || in_array($p->proyecto->estado, Proyecto::SIN_COBRO, true)) continue;
            $cobrar->push((object) ['fecha' => $p->vence_el?->toDateString(), 'monto' => (float) $p->monto, 'quien' => $p->proyecto->cliente?->empresa ?: ($p->proyecto->cliente?->nombre ?: $p->proyecto->nombre),
                'concepto' => $p->concepto, 'url' => route('admin.proyectos.show', $p->proyecto_id)]);
        }
        if (Cuentas::actual()?->tiene('stock')) {
            foreach (Venta::porCobrar()->with('cliente:id,nombre,empresa')->get() as $v) {
                $cobrar->push((object) ['fecha' => $v->vence_el?->toDateString(), 'monto' => $v->saldo, 'quien' => $v->quien, 'concepto' => 'Venta ' . $v->folio, 'url' => route('admin.pedidos.show', $v)]);
            }
        }
        $pagar = OrdenCompra::with('conceptos', 'proveedor:id,empresa')->whereIn('estado', ['entregada', 'facturada'])->get()->map(fn ($o) => (object) [
            'fecha' => ($o->fecha_entrega ?? $o->fecha)?->toDateString(), 'monto' => $o->total_final, 'quien' => $o->proveedor?->empresa, 'concepto' => 'Orden ' . $o->folio, 'url' => route('admin.ordenes.show', $o),
        ]);

        $lunes = $hoy->copy()->startOfWeek(Carbon::MONDAY);
        $semanas = collect(range(0, 7))->map(fn ($i) => ['desde' => $lunes->copy()->addWeeks($i), 'hasta' => $lunes->copy()->addWeeks($i)->endOfWeek(Carbon::SUNDAY)]);
        $fin = $semanas->last()['hasta']->toDateString();
        $hoyS = $hoy->toDateString();

        $saldo = $saldoHoy ?? 0.0;
        $filas = $semanas->map(function ($s, $i) use ($cobrar, $pagar, $hoyS, &$saldo) {
            $d = $s['desde']->toDateString(); $h = $s['hasta']->toDateString();
            // La semana actual también se lleva lo vencido
            $toca = fn ($x) => $x->fecha && ($i === 0 ? $x->fecha <= $h : ($x->fecha >= $d && $x->fecha <= $h));
            $e = round($cobrar->filter($toca)->sum('monto'), 2);
            $sa = round($pagar->filter($toca)->sum('monto'), 2);
            $saldo = round($saldo + $e - $sa, 2);
            return ['etiqueta' => $i === 0 ? 'Esta semana' : ($i === 1 ? 'La próxima' : ucfirst($s['desde']->locale('es')->isoFormat('D MMM'))),
                    'rango' => $s['desde']->locale('es')->isoFormat('D MMM') . ' – ' . $s['hasta']->locale('es')->isoFormat('D MMM'),
                    'entra' => $e, 'sale' => $sa, 'neto' => round($e - $sa, 2), 'saldo' => $saldo,
                    'vencido' => $i === 0 ? round($cobrar->filter(fn ($x) => $x->fecha && $x->fecha < $hoyS)->sum('monto'), 2) : 0];
        });

        $minimo = $filas->min('saldo');
        return [
            'semanas'     => $filas,
            'conSaldo'    => $saldoHoy !== null,
            'alerta'      => $saldoHoy !== null && $minimo < 0 ? $filas->first(fn ($f) => $f['saldo'] < 0) : null,
            'cobrarSin'   => round($cobrar->whereNull('fecha')->sum('monto'), 2),
            'nCobrarSin'  => $cobrar->whereNull('fecha')->count(),
            'cobrarDespues' => round($cobrar->filter(fn ($x) => $x->fecha && $x->fecha > $fin)->sum('monto'), 2),
            'pagarDespues'  => round($pagar->filter(fn ($x) => $x->fecha && $x->fecha > $fin)->sum('monto'), 2),
            'proximosCobros' => $cobrar->filter(fn ($x) => $x->fecha && $x->fecha <= $fin)->sortBy('fecha')->take(8)->values(),
            'proximosPagos'  => $pagar->filter(fn ($x) => $x->fecha && $x->fecha <= $fin)->sortBy('fecha')->take(8)->values(),
        ];
    }
}
