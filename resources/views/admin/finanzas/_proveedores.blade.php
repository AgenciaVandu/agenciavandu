@php $m = $prov['monto']; @endphp
@unless($sinKpis ?? false)
<div class="kpis">
    <div class="panel kpi destacado">
        <div class="k"><i class="bi bi-bag"></i> Comprado</div>
        <div class="v num">{{ $d($prov['comprado']) }}</div>
        <div class="s num">{{ $prov['n'] }} {{ $prov['n'] === 1 ? 'orden de compra' : 'órdenes de compra' }}</div>
    </div>
    <div class="panel kpi {{ $prov['porPagar'] > 0 ? 'alerta' : '' }}">
        <div class="k"><i class="bi bi-hourglass-split"></i> Por pagar</div>
        <div class="v num">{{ $d($prov['porPagar']) }}</div>
        <div class="s num">{{ $prov['nPorPagar'] }} entregadas o facturadas</div>
    </div>
    <div class="panel kpi">
        <div class="k"><i class="bi bi-gear"></i> En producción</div>
        <div class="v num">{{ $d($prov['enCurso']) }}</div>
        <div class="s num">{{ $prov['nEnCurso'] }} enviadas o en proceso</div>
    </div>
    <div class="panel kpi">
        <div class="k"><i class="bi bi-check2-circle"></i> Pagado</div>
        <div class="v num">{{ $d($prov['pagado']) }}</div>
        <div class="s num">{{ $prov['sinFactura'] ? $prov['sinFactura'] . ' ' . ($prov['sinFactura'] === 1 ? 'orden sigue' : 'órdenes siguen') . ' sin factura' : 'Facturas al día' }}</div>
    </div>
</div>
@endunless

<div class="rejilla" style="margin-top: 0">
    <section class="panel">
        <div class="panel-head"><h2>Por proveedor</h2></div>
        @if($prov['porProveedor']->isEmpty())
            <div class="panel-body secundario">Sin órdenes de compra en este periodo.</div>
        @else
            <div class="table-responsive">
                <table class="tabla">
                    <thead><tr><th>Proveedor</th><th class="text-end">Comprado</th><th class="text-end">Por pagar</th></tr></thead>
                    <tbody>
                    @foreach($prov['porProveedor'] as $x)
                        <tr>
                            <td><a href="{{ route('admin.proveedores.show', $x['proveedor']) }}" class="fila-link"><span class="principal">{{ $x['proveedor']?->empresa }}</span></a>
                                <div class="secundario">{{ $x['proveedor']?->rubro ?: 'Sin rubro' }} · {{ $x['n'] }} {{ $x['n'] === 1 ? 'orden' : 'órdenes' }}</div></td>
                            <td class="text-end num">{{ $d($x['comprado']) }}</td>
                            <td class="text-end num {{ $x['porPagar'] > 0 ? 'fw-semibold' : 'secundario' }}">{{ $x['porPagar'] > 0 ? $d($x['porPagar']) : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="panel">
        <div class="panel-head"><h2>Por rubro</h2></div>
        @if($prov['porRubro']->isEmpty())
            <div class="panel-body secundario">Sin órdenes de compra en este periodo.</div>
        @else
            @php $maxR = max(1, $prov['porRubro']->max('monto')); @endphp
            <div class="panel-body hbar">
                @foreach($prov['porRubro'] as $r)
                    <span class="et">{{ $r['rubro'] }}</span>
                    <span class="pista"><span style="width: {{ $r['monto'] / $maxR * 100 }}%"></span></span>
                    <span class="val num">{{ $d($r['monto']) }}<small>{{ $r['n'] }} {{ $r['n'] === 1 ? 'orden' : 'órdenes' }}</small></span>
                @endforeach
            </div>
        @endif
    </section>
</div>

<section class="panel mt-4" id="por-pagar">
    <div class="panel-head"><div><h2>Por pagar</h2><span class="ayuda">Órdenes entregadas o facturadas que aún no marcas como pagadas</span></div><span class="num fw-semibold">{{ $d($prov['porPagar']) }}</span></div>
    @if($prov['pendientes']->isEmpty())
        <div class="vacio"><div class="ico"><i class="bi bi-check2-circle"></i></div><h3>No le debes a nadie</h3><p>Cuando una orden se entregue o te llegue su factura, aparecerá aquí hasta que la marques como pagada.</p></div>
    @else
        <div class="table-responsive">
            <table class="tabla">
                <thead><tr><th>Orden</th><th>Proveedor</th><th>Factura</th><th class="text-end">Monto</th></tr></thead>
                <tbody>
                @foreach($prov['pendientes'] as $o)
                    <tr>
                        <td style="min-width: 180px"><a href="{{ route('admin.ordenes.show', $o) }}" class="fila-link"><span class="principal num">{{ $o->folio }}</span></a>
                            <div class="secundario text-truncate" style="max-width: 260px">{{ $o->titulo }}{{ $o->proyecto ? ' · ' . $o->proyecto->nombre : '' }}</div></td>
                        <td>{{ $o->proveedor?->empresa }}</td>
                        <td>@if($o->archivos->contains('tipo', 'factura'))<span class="estado estado-facturada">Recibida</span>@else<span class="estado estado-en_proceso">Falta</span>@endif</td>
                        <td class="text-end num fw-semibold">{{ $d($m($o)) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
