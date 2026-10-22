@php
    $pct = fn ($x) => $x === null ? '—' : rtrim(rtrim(number_format($x, 1), '0'), '.') . '%';
    $claseMargen = fn ($m) => $m === null ? '' : ($m < 15 ? 'margen-bajo' : 'margen-ok');
    $maxComp = max(1, $rent['porComponente']->max('monto') ?? 1);
@endphp

@if($rent['sinCosteo'] > 0)
    <div class="aviso-suave"><i class="bi bi-info-circle-fill"></i>
        <div>{{ $rent['sinCosteo'] }} {{ $rent['sinCosteo'] === 1 ? 'venta ganada no tiene' : 'ventas ganadas no tienen' }} costeo ({{ $d($rent['sinCosteoMonto']) }}), así que no entra{{ $rent['sinCosteo'] === 1 ? '' : 'n' }} en la utilidad.
            Para que cuente, abre la cotización y usa <b>Calcular precio con costos</b> en sus conceptos. Los costos se configuran en <a href="{{ route('admin.costeo') }}">Costeo</a>.</div>
    </div>
@endif

<div class="kpis">
    <div class="panel kpi destacado">
        <div class="k"><i class="bi bi-pie-chart"></i> Utilidad</div>
        <div class="v num">{{ $d($rent['utilidad']) }}</div>
        <div class="s num">{{ $rent['n'] }} {{ $rent['n'] === 1 ? 'venta con costeo' : 'ventas con costeo' }}</div>
    </div>
    <div class="panel kpi">
        <div class="k"><i class="bi bi-percent"></i> Margen</div>
        <div class="v num {{ $claseMargen($rent['margen']) }}">{{ $pct($rent['margen']) }}</div>
        <div class="s num">Utilidad ÷ lo vendido</div>
    </div>
    <div class="panel kpi">
        <div class="k"><i class="bi bi-cart"></i> Vendido (con costeo)</div>
        <div class="v num">{{ $d($rent['venta']) }}</div>
        <div class="s num">Lo que pagaron tus clientes por esos conceptos</div>
    </div>
    <div class="panel kpi">
        <div class="k"><i class="bi bi-diagram-2"></i> Costos</div>
        <div class="v num">{{ $d($rent['costo']) }}</div>
        <div class="s num">Terceros {{ $d($rent['terceros']) }} · Operativo {{ $d($rent['operativo']) }}</div>
    </div>
</div>

@if($rent['venta'] > 0)
    <section class="panel mb-4">
        <div class="panel-body">
            <div class="d-flex justify-content-between flex-wrap gap-2"><b>A dónde se va cada peso vendido</b><span class="secundario num">{{ $d($rent['venta']) }}</span></div>
            <div class="barra-rent" role="img" aria-label="Terceros {{ $pct($rent['terceros'] / $rent['venta'] * 100) }}, operativo {{ $pct($rent['operativo'] / $rent['venta'] * 100) }}, utilidad {{ $pct($rent['margen']) }}">
                <span style="width: {{ max(0, $rent['terceros'] / $rent['venta'] * 100) }}%; background: #4A5BD4"></span>
                <span style="width: {{ max(0, $rent['operativo'] / $rent['venta'] * 100) }}%; background: #B8650A"></span>
                <span style="width: {{ max(0, $rent['utilidad'] / $rent['venta'] * 100) }}%; background: #00A862"></span>
            </div>
            <div class="leyenda-rent num">
                <span><i class="punto" style="background:#4A5BD4"></i> Terceros {{ $pct($rent['terceros'] / $rent['venta'] * 100) }}</span>
                <span><i class="punto" style="background:#B8650A"></i> Operativo {{ $pct($rent['operativo'] / $rent['venta'] * 100) }}</span>
                <span><i class="punto" style="background:#00A862"></i> Utilidad {{ $pct($rent['margen']) }}</span>
            </div>
        </div>
    </section>
@endif

<div class="rejilla" style="margin-top: 0">
    <section class="panel">
        <div class="panel-head"><h2>Margen por servicio</h2></div>
        @if($rent['porTipo']->isEmpty())
            <div class="panel-body secundario">Sin ventas con costeo en este periodo.</div>
        @else
            <div class="table-responsive">
                <table class="tabla">
                    <thead><tr><th>Servicio</th><th class="text-end">Vendido</th><th class="text-end">Utilidad</th><th class="text-end">Margen</th></tr></thead>
                    <tbody>
                    @foreach($rent['porTipo'] as $t)
                        <tr>
                            <td><i class="bi {{ $t['icono'] }} me-1"></i>{{ $t['nombre'] }} <span class="secundario">· {{ $t['n'] }}</span></td>
                            <td class="text-end num">{{ $d($t['venta']) }}</td>
                            <td class="text-end num">{{ $d($t['utilidad']) }}</td>
                            <td class="text-end num fw-semibold {{ $claseMargen($t['margen']) }}">{{ $pct($t['margen']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="panel">
        <div class="panel-head"><h2>En qué se gasta</h2><span class="ayuda">Costos capturados al cotizar</span></div>
        @if($rent['porComponente']->isEmpty())
            <div class="panel-body secundario">Sin costos en este periodo.</div>
        @else
            <div class="panel-body hbar">
                @foreach($rent['porComponente']->take(8) as $k)
                    <span class="et">{{ $k['nombre'] }}</span>
                    <span class="pista" role="img" aria-label="{{ $k['nombre'] }}: {{ $d($k['monto']) }}"><span style="width: {{ $k['monto'] / $maxComp * 100 }}%; background: {{ $k['clase'] === 'tercero' ? '#4A5BD4' : '#B8650A' }}"></span></span>
                    <span class="val num">{{ $d($k['monto']) }}<small>{{ $k['clase'] === 'tercero' ? 'Terceros' : 'Operativo' }}</small></span>
                @endforeach
            </div>
        @endif
    </section>
</div>

<section class="panel mt-4">
    <div class="panel-head"><div><h2>Utilidad por venta</h2><span class="ayuda">Ganadas en el periodo. «Compras reales» suma las órdenes de compra del proyecto{{ \App\Support\Cuentas::actual()?->tiene('gastos') ? ' y los gastos ligados a él' : '' }}.</span></div></div>
    @if($rent['filas']->isEmpty())
        <div class="vacio"><div class="ico"><i class="bi bi-calculator"></i></div><h3>Sin ventas con costeo</h3>
            <p>Al cotizar, usa <b>Calcular precio con costos</b> en cada concepto: aquí verás cuánto te deja cada venta.</p>
            <a href="{{ route('admin.costeo') }}" class="btn btn-borde">Revisar mis costos</a></div>
    @else
        <div class="table-responsive">
            <table class="tabla">
                <thead><tr><th>Cotización</th><th class="text-end">Vendido</th><th class="text-end">Costo</th><th class="text-end">Utilidad</th><th class="text-end">Margen</th><th class="text-end">Compras reales</th></tr></thead>
                <tbody>
                @foreach($rent['filas'] as $f)
                    @php $p = $f['p']; $quien = $p->cliente_empresa ?: $p->cliente_nombre; @endphp
                    <tr>
                        <td style="min-width: 200px"><a href="{{ route('admin.presupuestos.edit', $p) }}" class="fila-link"><span class="principal">{{ $quien }}</span></a>
                            <div class="secundario num">{{ $p->folio }} · {{ \Illuminate\Support\Str::limit($p->conceptos->first()?->resumen, 36) }}</div></td>
                        <td class="text-end num">{{ $d($f['venta']) }}</td>
                        <td class="text-end num">{{ $d($f['costo']) }}</td>
                        <td class="text-end num fw-semibold {{ $f['utilidad'] < 0 ? 'margen-bajo' : '' }}">{{ $d($f['utilidad']) }}</td>
                        <td class="text-end num {{ $claseMargen($f['margen']) }}">{{ $pct($f['margen']) }}</td>
                        <td class="text-end num">
                            @if($f['real'] === null && $f['gastos'] === null)<span class="secundario">—</span>
                            @else{{ $f['real'] === null ? '' : $d($f['real']) }}
                                @if($f['real'] !== null && $f['terceros'] > 0 && $f['real'] > $f['terceros'] * 1.05)<div class="margen-bajo" style="font-size: 12px">{{ $d($f['real'] - $f['terceros']) }} más de lo costeado</div>@endif
                                @if($f['gastos'] !== null)<div class="secundario" style="font-size: 12px">+ {{ $d($f['gastos']) }} en gastos · utilidad {{ $d($f['neta']) }}</div>@endif
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
