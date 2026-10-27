{{-- Ventas y cobranza: lo vendido, quién te debe y cómo se vende --}}
@php $porCobrarTotal = $kpi['porCobrar']; @endphp
<div class="kpis">
    <div class="panel kpi destacado">
        <div class="k"><i class="bi bi-trophy"></i> Vendido</div>
        <div class="v num">{{ $d($kpi['ganado']) }}</div>
        <div class="s num">{{ $kpi['ganadas'] }} {{ $kpi['ganadas'] === 1 ? 'venta' : 'ventas' }}@if($variacion !== null) · <span class="delta {{ $variacion >= 0 ? 'sube' : 'baja' }}"><i class="bi {{ $variacion >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i>{{ abs($variacion) }}%</span> vs. antes @endif</div>
    </div>
    <div class="panel kpi">
        <div class="k"><i class="bi bi-bank"></i> Cobrado</div>
        <div class="v num">{{ $d($kpi['cobrado']) }}</div>
        <div class="s num">{{ $kpi['cobros'] }} {{ $kpi['cobros'] === 1 ? 'pago recibido' : 'pagos recibidos' }}</div>
    </div>
    <a href="#por-cobrar" class="panel kpi text-reset text-decoration-none {{ $porCobrarTotal > 0 ? 'alerta' : '' }}">
        <div class="k"><i class="bi bi-hourglass-split"></i> Te deben</div>
        <div class="v num">{{ $d($porCobrarTotal) }}</div>
        <div class="s num">{{ $kpi['pendientes'] }} {{ $kpi['pendientes'] === 1 ? 'pago pendiente' : 'pagos pendientes' }} · hoy</div>
    </a>
    <a href="{{ route('admin.presupuestos.index', ['filtro' => 'vigentes']) }}" class="panel kpi text-reset text-decoration-none">
        <div class="k"><i class="bi bi-send"></i> Cotizaciones abiertas</div>
        <div class="v num">{{ $d($kpi['enJuego']) }}</div>
        <div class="s num">{{ $kpi['abiertas'] }} {{ $kpi['abiertas'] === 1 ? 'abierta' : 'abiertas' }}@if($kpi['conversion'] !== null) · cierras {{ $kpi['conversion'] }}%@endif</div>
    </a>
</div>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 small secundario">
    <span>Montos {{ $base }}.</span>
    <span>Ver <a href="{{ $q(['iva' => $conIva ? 'sin' : 'con']) }}">{{ $conIva ? 'antes de IVA' : 'con IVA' }}</a> · <a href="{{ route('admin.finanzas.exportar', ['periodo' => in_array($periodo, array_keys(\App\Http\Controllers\Admin\FinanzasController::PERIODOS), true) ? $periodo : 'todo']) }}" data-recargar><i class="bi bi-download"></i> Exportar cotizaciones (CSV)</a></span>
</div>

{{-- ================= Quién te debe ================= --}}
{{-- ================= Pendientes de cobro ================= --}}
@if($porCobrar->isNotEmpty() || ($porCobrarVentas->isEmpty() && \App\Support\Cuentas::actual()?->tiene('proyectos')))
<section class="panel" id="por-cobrar">
    <div class="panel-head"><div><h2>Quién te debe</h2><span class="ayuda">Pagos pendientes de tus proyectos</span></div>
        <span class="num fw-semibold">{{ $d($kpi['porCobrar']) }}</span></div>
    @if($porCobrar->isEmpty())
        <div class="vacio"><div class="ico"><i class="bi bi-check2-circle"></i></div><h3>Todo cobrado</h3><p>No hay pagos pendientes.</p></div>
    @else
        <div class="table-responsive">
            <table class="tabla">
                <thead><tr><th>Cliente</th><th>Proyecto</th><th>Pago</th><th>Bloquea / vence</th><th class="text-end">Monto</th><th><span class="visually-hidden">Recordatorio</span></th></tr></thead>
                <tbody>
                @foreach($porCobrar as $x)
                    @php $quien = $x->proyecto->cliente?->empresa ?: $x->proyecto->cliente?->nombre; $etapa = $x->proyecto->etapas->firstWhere('clave', $x->pago->antes_de); @endphp
                    <tr>
                        <td style="min-width:200px"><div class="persona">@include('admin._avatar', ['nombre' => $quien])<span class="principal text-truncate">{{ $quien }}</span></div></td>
                        <td><a href="{{ route('admin.proyectos.show', $x->proyecto) }}#pago-{{ $x->pago->id }}" class="text-reset">{{ \Illuminate\Support\Str::limit($x->proyecto->nombre, 40) }}</a>
                            <div class="secundario">{{ $x->proyecto->tipo_nombre }}{{ $x->proyecto->fecha_inicio ? ' · desde ' . $x->proyecto->fecha_inicio->locale('es')->isoFormat('D MMM') : '' }}</div></td>
                        <td>{{ $x->pago->concepto }}</td>
                        <td>
                            @if($x->pago->vence_el)<span class="vig {{ $x->pago->vencido ? 'vig-vencida' : 'vig-ok' }}"><i class="bi {{ $x->pago->vencido ? 'bi-exclamation-circle' : 'bi-calendar-event' }}"></i> {{ $x->pago->vencido ? 'Venció' : 'Vence' }} {{ $x->pago->vence_el->locale('es')->isoFormat('D MMM') }}</span>
                            @elseif($etapa && $etapa->estado !== 'completada')<span class="vig vig-pronto"><i class="bi bi-lock"></i> {{ $etapa->nombre }}</span>
                            @elseif($etapa)<span class="vig vig-vencida"><i class="bi bi-exclamation-circle"></i> {{ $etapa->nombre }} ya se hizo</span>
                            @else<span class="secundario">—</span>@endif
                        </td>
                        <td class="text-end num fw-medium">{{ $d($x->monto) }}</td>
                        <td class="text-end"><a href="{{ route('admin.proyectos.show', [$x->proyecto, 'correo' => 'recordatorio_pago@' . $x->pago->id]) }}" class="btn btn-fantasma btn-icono" title="Enviar recordatorio por correo" aria-label="Enviar recordatorio de {{ $x->pago->concepto }}"><i class="bi bi-envelope"></i></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@elseif($porCobrarVentas->isEmpty())
<section class="panel" id="por-cobrar"><div class="vacio"><div class="ico"><i class="bi bi-check2-circle"></i></div><h3>Nadie te debe</h3><p>No hay pagos pendientes.</p></div></section>
@endif

@if($porCobrarVentas->isNotEmpty())
<section class="panel {{ $porCobrar->isNotEmpty() ? 'mt-4' : '' }}" @if($porCobrar->isEmpty()) id="por-cobrar" @endif>
    <div class="panel-head"><div><h2>{{ $porCobrar->isEmpty() ? 'Quién te debe' : 'Ventas a crédito por cobrar' }}</h2><span class="ayuda">Mostrador y pedidos con saldo</span></div></div>
    <div class="table-responsive">
        <table class="tabla">
            <thead><tr><th>Cliente</th><th>Venta</th><th>Vence</th><th class="text-end">Saldo</th></tr></thead>
            <tbody>
            @foreach($porCobrarVentas as $v)
                <tr>
                    <td><div class="persona">@include('admin._avatar', ['nombre' => $v->quien])<span class="principal text-truncate">{{ $v->quien }}</span></div></td>
                    <td><a href="{{ route('admin.pedidos.show', $v) }}" class="text-reset">{{ $v->folio }}</a><div class="secundario">{{ $v->fecha->locale('es')->isoFormat('D MMM') }}</div></td>
                    <td>@if($v->vence_el)<span class="vig {{ $v->vencida ? 'vig-vencida' : 'vig-ok' }}"><i class="bi {{ $v->vencida ? 'bi-exclamation-circle' : 'bi-calendar-event' }}"></i> {{ $v->vencida ? 'Venció' : 'Vence' }} {{ $v->vence_el->locale('es')->isoFormat('D MMM') }}</span>@else<span class="secundario">—</span>@endif</td>
                    <td class="text-end num fw-medium">{{ $d($conIva || $v->total <= 0 ? $v->saldo : $v->saldo * $v->neto / $v->total) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endif

@if(\App\Models\Presupuesto::exists())
<h2 class="fs-6 fw-semibold mt-5 mb-3 secundario text-uppercase" style="letter-spacing:.05em">Cómo vendes</h2>
<div class="kpis">
    <div class="panel kpi">
        <div class="k"><i class="bi bi-file-earmark-text"></i> Cotizado</div>
        <div class="v num">{{ $d($kpi['cotizado']) }}</div>
        <div class="s num">{{ $kpi['cotizadas'] }} {{ $kpi['cotizadas'] === 1 ? 'cotización' : 'cotizaciones' }}</div>
    </div>
    <a href="#perdidas" class="panel kpi text-reset text-decoration-none {{ $kpi['perdido'] > 0 ? 'malo' : '' }}">
        <div class="k"><i class="bi bi-x-octagon"></i> Perdido</div>
        <div class="v num">{{ $d($kpi['perdido']) }}</div>
        <div class="s num">{{ $kpi['perdidas'] }} rechazadas o vencidas</div>
    </a>
    <div class="panel kpi">
        <div class="k"><i class="bi bi-bullseye"></i> Conversión</div>
        <div class="v num">{{ $kpi['conversion'] === null ? '—' : $kpi['conversion'] . '%' }}</div>
        <div class="s num">{{ $kpi['conversionMonto'] === null ? 'Sin cotizaciones resueltas' : $kpi['conversionMonto'] . '% del dinero cotizado' }}</div>
    </div>
    <div class="panel kpi">
        <div class="k"><i class="bi bi-receipt"></i> Ticket promedio</div>
        <div class="v num">{{ $kpi['ticket'] === null ? '—' : $d($kpi['ticket']) }}</div>
        <div class="s num">{{ $kpi['diasCierre'] === null ? 'Por venta ganada' : 'Cierran en ' . $kpi['diasCierre'] . ' ' . ($kpi['diasCierre'] === 1 ? 'día' : 'días') . ' en promedio' }}</div>
    </div>
</div>

<div class="rejilla">
    {{-- ================= Embudo ================= --}}
    <section class="panel">
        <div class="panel-head"><h2>Embudo de ventas</h2><span class="ayuda">{{ $periodoTexto }}</span></div>
        <div class="panel-body hbar">
            @foreach($embudo as $i => $e)
                <span class="et">{{ $e['etiqueta'] }}</span>
                <span class="pista" role="img" aria-label="{{ $e['etiqueta'] }}: {{ $e['n'] }}"><span style="width: {{ $e['n'] / $embudoMax * 100 }}%; opacity: {{ 1 - $i * .18 }}"></span></span>
                <span class="val num">{{ $e['n'] }}<small>{{ $d($e['monto']) }}</small></span>
                @if($i > 0 && $embudo[0]['n'] > 0)
                    <span></span><span class="tasa-embudo num">{{ round($e['n'] / $embudo[0]['n'] * 100) }}% de las cotizadas</span><span></span>
                @endif
            @endforeach
        </div>
    </section>

    <div class="d-grid gap-4">
        {{-- ================= Por tipo ================= --}}
        <section class="panel">
            <div class="panel-head"><h2>Ganado por servicio</h2></div>
            @if($porTipo->isEmpty())
                <div class="panel-body secundario">Sin ventas en este periodo.</div>
            @else
                <div class="panel-body hbar">
                    @foreach($porTipo as $t)
                        <span class="et"><i class="bi {{ $t['icono'] }} me-1"></i>{{ $t['nombre'] }}</span>
                        <span class="pista" role="img" aria-label="{{ $t['nombre'] }}: {{ $d($t['monto']) }}"><span style="width: {{ $t['monto'] / $tipoMax * 100 }}%"></span></span>
                        <span class="val num">{{ $d($t['monto']) }}<small>{{ $t['n'] }} {{ $t['n'] === 1 ? 'venta' : 'ventas' }}</small></span>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- ================= Clientes ================= --}}
        <section class="panel">
            <div class="panel-head"><h2>Mejores clientes</h2><span class="ayuda">Por monto ganado</span></div>
            @if($clientes->isEmpty())
                <div class="panel-body secundario">Sin ventas en este periodo.</div>
            @else
                <ul class="list-unstyled m-0">
                    @foreach($clientes as $i => $c)
                        <li class="{{ $i ? 'border-top' : '' }}">
                            <a href="{{ $c['cliente'] ? route('admin.clientes.show', $c['cliente']) : '#' }}" class="d-flex align-items-center gap-3 px-3 py-2 text-reset text-decoration-none">
                                @include('admin._avatar', ['nombre' => $c['cliente']?->empresa ?: $c['cliente']?->nombre])
                                <span class="flex-grow-1 min-w-0"><span class="principal d-block text-truncate">{{ $c['cliente']?->empresa ?: $c['cliente']?->nombre }}</span>
                                    <span class="secundario">{{ $c['n'] }} {{ $c['n'] === 1 ? 'venta' : 'ventas' }}</span></span>
                                <span class="num fw-semibold">{{ $d($c['monto']) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</div>

<section class="panel mt-4" id="perdidas">
    <div class="panel-head"><div><h2>Cotizaciones perdidas</h2><span class="ayuda">Rechazadas o vencidas sin aceptar · {{ $periodoTexto }}</span></div>
        <span class="num fw-semibold">{{ $d($kpi['perdido']) }}</span></div>
    @if($perdidas->isEmpty())
        <div class="vacio"><div class="ico"><i class="bi bi-emoji-smile"></i></div><h3>Nada perdido</h3><p>Ninguna cotización rechazada o vencida en este periodo.</p></div>
    @else
        <div class="table-responsive">
            <table class="tabla">
                <thead><tr><th>Cliente</th><th>Folio</th><th>Motivo</th><th>Vistas</th><th class="text-end">Monto</th></tr></thead>
                <tbody>
                @foreach($perdidas as $p)
                    @php $quien = $p->cliente_empresa ?: $p->cliente_nombre; @endphp
                    <tr>
                        <td style="min-width:200px"><div class="persona">@include('admin._avatar', ['nombre' => $quien])<div><div class="principal text-truncate">{{ $quien }}</div><div class="secundario text-truncate">{{ \Illuminate\Support\Str::limit($p->conceptos->first()?->resumen, 40) }}</div></div></div></td>
                        <td class="num"><a href="{{ route('admin.presupuestos.edit', $p) }}" class="text-reset">{{ $p->folio }}</a><div class="secundario">{{ $p->fecha->locale('es')->isoFormat('D MMM') }}</div></td>
                        <td>@if($p->estado === 'cancelada')<span class="estado estado-cancelada">Cancelada</span>@if($p->motivo_cancelacion)<div class="secundario text-truncate" style="max-width:240px" title="{{ $p->motivo_cancelacion }}">{{ $p->motivo_cancelacion }}</div>@endif
                            @elseif($p->estado === 'rechazada')<span class="estado estado-rechazada">Rechazada</span>@else<span class="estado estado-borrador">Venció sin respuesta</span>@endif</td>
                        <td class="num">{!! $p->vistas ? '<i class="bi bi-eye text-secondary"></i> ' . $p->vistas : '<span class="secundario">No la abrió</span>' !!}</td>
                        <td class="text-end num fw-medium">{{ $d($monto($p)) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

@endif

{{-- ================= Tendencia ================= --}}
<section class="panel mt-4">
    <div class="panel-head">
        <div><h2>Tendencia de 12 meses</h2><span class="ayuda">Cotizado por fecha de cotización, ganado por fecha de aceptación, cobrado por fecha de pago</span></div>
        <div class="leyenda">@foreach($colores as $k => $c)<span><i class="punto" style="background: {{ $c }}"></i> {{ $nombres[$k] }}</span>@endforeach</div>
    </div>
    <div class="panel-body">
        <div class="graf" aria-hidden="true">
            <div class="eje">@foreach($marcas as $i => $m)<span class="num" style="top: {{ $i * 25 }}%">{{ $corto($m) }}</span>@endforeach</div>
            <div class="area">
                @foreach(range(0, 3) as $i)<div class="linea" style="top: {{ $i * 25 }}%"></div>@endforeach
                <div class="cols">
                    @foreach($meses as $m)
                        <div class="col" tabindex="0">
                            @foreach($colores as $k => $c)<div class="barra" style="height: {{ $m[$k] / $tope * 100 }}%; background: {{ $c }}"></div>@endforeach
                            <div class="tip">
                                <b>{{ $m['completa'] }}</b>
                                @foreach($colores as $k => $c)<div class="fila"><span><i class="punto" style="background: {{ $c }}"></i> {{ $nombres[$k] }}</span><span class="num">{{ $d($m[$k]) }}</span></div>@endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="meses" aria-hidden="true"><span></span>@foreach($meses as $m)<span class="{{ $m['actual'] ? 'actual' : '' }}">{{ $m['etiqueta'] }}</span>@endforeach</div>
        <table class="visually-hidden">
            <caption>Cotizado, ganado y cobrado por mes, {{ $base }}</caption>
            <tr><th>Mes</th><th>Cotizado</th><th>Ganado</th><th>Cobrado</th></tr>
            @foreach($meses as $m)<tr><td>{{ $m['completa'] }}</td><td>{{ $d($m['cotizado']) }}</td><td>{{ $d($m['ganado']) }}</td><td>{{ $d($m['cobrado']) }}</td></tr>@endforeach
        </table>
    </div>
</section>

