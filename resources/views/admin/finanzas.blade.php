@extends('admin.layout')
@section('titulo', 'Finanzas')

@php
    use App\Http\Controllers\Admin\FinanzasController as F;
    $d = fn ($n) => '$' . number_format($n ?? 0, 0);
    $corto = function ($n) {
        if ($n >= 1000000) return '$' . rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.') . ' M';
        if ($n >= 1000) return '$' . rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . ' k';
        return '$' . number_format($n, 0);
    };
    $q = fn (array $extra) => route('admin.finanzas', array_merge(['periodo' => $periodo, 'iva' => $conIva ? 'con' : 'sin'], $extra));
    $base = $conIva ? 'con IVA' : 'antes de IVA';
    $f = fn ($s) => $s ? ucfirst(\Illuminate\Support\Carbon::parse($s)->locale('es')->isoFormat('D MMM YYYY')) : null;

    // Escala de la gráfica
    $max = max(1, $meses->max(fn ($m) => max($m['cotizado'], $m['ganado'], $m['cobrado'])));
    $paso = 10 ** floor(log10($max));
    $tope = ceil($max / $paso) * $paso;
    if ($tope / $paso <= 3) { $paso /= 2; $tope = ceil($max / $paso) * $paso; }
    $marcas = collect(range(0, 4))->map(fn ($i) => $tope * $i / 4)->reverse()->values();
    $colores = ['cotizado' => '#4A5BD4', 'ganado' => '#00A862', 'cobrado' => '#B8650A'];
    $nombres = ['cotizado' => 'Cotizado', 'ganado' => 'Ganado', 'cobrado' => 'Cobrado'];

    $variacion = null;
    if ($kpi['ganadoAnterior'] !== null) {
        $variacion = $kpi['ganadoAnterior'] > 0 ? round(($kpi['ganado'] - $kpi['ganadoAnterior']) / $kpi['ganadoAnterior'] * 100) : ($kpi['ganado'] > 0 ? 100 : 0);
    }
    $embudoMax = max(1, collect($embudo)->max('n'));
    $tipoMax = max(1, $porTipo->max('monto') ?? 1);
@endphp

@push('head')
<style>
    .controles { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 20px; }
    .controles .rango { color: var(--muted); font-size: 13.5px; margin-left: 4px; }
    .kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 14px; }
    .kpi { padding: 16px 18px; }
    .kpi .k { font-size: 13.5px; color: var(--muted); display: flex; align-items: center; gap: 7px; }
    .kpi .v { font-size: 28px; font-weight: 600; letter-spacing: -.02em; line-height: 1.1; margin-top: 8px; }
    .kpi .s { font-size: 13px; color: var(--muted); margin-top: 6px; }
    .kpi.destacado { background: var(--ink); color: #fff; border-color: var(--ink); }
    .kpi.destacado .k, .kpi.destacado .s { color: #A9AEBA; }
    .delta { display: inline-flex; align-items: center; gap: 3px; font-weight: 600; }
    .delta.sube { color: var(--green); } .delta.baja { color: #FF8A80; }
    .kpi:not(.destacado) .delta.sube { color: var(--green-ink); } .kpi:not(.destacado) .delta.baja { color: var(--red); }
    .kpi.alerta .v { color: var(--amber); }
    .kpi.malo .v { color: var(--red); }

    .graf { position: relative; height: 260px; display: grid; grid-template-columns: 56px 1fr; }
    .graf .eje { position: relative; }
    .graf .eje span { position: absolute; right: 10px; transform: translateY(-50%); font-size: 12px; color: var(--muted); }
    .graf .area { position: relative; border-bottom: 1px solid var(--line-strong); }
    .graf .linea { position: absolute; left: 0; right: 0; border-top: 1px solid #EEF0F3; }
    .graf .cols { position: absolute; inset: 0; display: grid; grid-template-columns: repeat(12, 1fr); }
    .graf .col { position: relative; display: flex; align-items: flex-end; justify-content: center; gap: 2px; }
    .graf .col:hover, .graf .col:focus-visible { background: rgba(19,22,29,.035); outline: none; }
    .graf .barra { width: 9px; border-radius: 4px 4px 0 0; }
    .graf .tip { position: absolute; bottom: calc(100% + 8px); left: 50%; transform: translateX(-50%); z-index: 3; background: var(--ink); color: #fff; border-radius: 8px;
                 padding: 10px 12px; font-size: 13px; white-space: nowrap; box-shadow: 0 8px 24px rgba(16,24,40,.2); pointer-events: none; opacity: 0; transition: opacity .12s; }
    .graf .col:hover .tip, .graf .col:focus-visible .tip { opacity: 1; }
    .graf .col:nth-child(-n+2) .tip { left: 0; transform: none; }
    .graf .col:nth-last-child(-n+3) .tip { left: auto; right: 0; transform: none; }
    .graf .tip b { display: block; margin-bottom: 4px; }
    .graf .tip .fila { display: flex; justify-content: space-between; gap: 16px; }
    .graf .tip .fila span:first-child { display: inline-flex; gap: 6px; align-items: center; color: #C9CDD6; }
    .punto { width: 8px; height: 8px; border-radius: 2px; display: inline-block; }
    .meses { display: grid; grid-template-columns: 56px repeat(12, 1fr); margin-top: 8px; }
    .meses span { text-align: center; font-size: 12px; color: var(--muted); }
    .meses span.actual { color: var(--text); font-weight: 600; }
    .leyenda { display: flex; gap: 14px; font-size: 13px; color: var(--text-2); flex-wrap: wrap; }
    .leyenda span { display: inline-flex; align-items: center; gap: 6px; }

    .rejilla { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 20px; margin-top: 20px; align-items: start; }
    .hbar { display: grid; grid-template-columns: 170px minmax(0, 1fr) auto; gap: 10px 14px; align-items: center; }
    .hbar .et { font-size: 14px; color: var(--text-2); }
    .hbar .pista { height: 22px; background: #F1F2F4; border-radius: 6px; overflow: hidden; }
    .hbar .pista span { display: block; height: 100%; background: var(--ink); border-radius: 0 6px 6px 0; min-width: 2px; }
    .hbar .val { text-align: right; font-size: 14px; white-space: nowrap; }
    .hbar .val small { display: block; color: var(--muted); font-size: 12.5px; }
    .tasa-embudo { font-size: 12.5px; color: var(--muted); grid-column: 2 / 3; margin-top: -6px; }

    @media (max-width: 1199.98px) { .kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .rejilla { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 575.98px) { .kpi .v { font-size: 22px; } .hbar { grid-template-columns: 1fr auto; } .hbar .pista { grid-column: 1 / -1; order: 3; } .tasa-embudo { grid-column: 1 / -1; }
        .graf .barra { width: 5px; } .meses span { font-size: 10.5px; } }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <h1>Finanzas</h1>
        <p class="sub">Ventas, cobranza y conversión. Montos {{ $base }}.</p>
    </div>
    <a href="{{ route('admin.finanzas.exportar', ['periodo' => $periodo]) }}" class="btn btn-borde" data-recargar><i class="bi bi-download me-1"></i> Exportar CSV</a>
</div>

@include('admin._este-mes', ['mes' => \App\Support\EsteMes::datos($conIva), 'conIva' => $conIva, 'enlace' => $periodo !== 'mes' ? $q(['periodo' => 'mes']) : null, 'textoEnlace' => 'Ver el detalle de este mes'])

<div class="controles">
    <nav class="segmento" aria-label="Periodo">
        @foreach(F::PERIODOS as $k => $label)
            @php [$rd, $rh] = $rangos[$k]; @endphp
            <a href="{{ $q(['periodo' => $k]) }}" class="{{ $periodo === $k ? 'activo' : '' }}"
               title="{{ $rd ? $f($rd) . ' – ' . $f($rh) : 'Todo lo registrado, sin límite de fechas' }}">{{ $label }}</a>
        @endforeach
    </nav>
    <nav class="segmento" aria-label="IVA">
        <a href="{{ $q(['iva' => 'sin']) }}" class="{{ ! $conIva ? 'activo' : '' }}">Sin IVA</a>
        <a href="{{ $q(['iva' => 'con']) }}" class="{{ $conIva ? 'activo' : '' }}">Con IVA</a>
    </nav>
    <span class="rango num">{{ $desde ? $f($desde) . ' – ' . $f($hasta) : 'Todo lo registrado' }}</span>
</div>

{{-- ================= Indicadores ================= --}}
<div class="kpis">
    <div class="panel kpi destacado">
        <div class="k"><i class="bi bi-trophy"></i> Ganado</div>
        <div class="v num">{{ $d($kpi['ganado']) }}</div>
        <div class="s num">
            {{ $kpi['ganadas'] }} {{ $kpi['ganadas'] === 1 ? 'venta' : 'ventas' }}
            @if($variacion !== null)
                · <span class="delta {{ $variacion >= 0 ? 'sube' : 'baja' }}"><i class="bi {{ $variacion >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i>{{ abs($variacion) }}%</span> vs. periodo anterior
            @endif
        </div>
    </div>
    <div class="panel kpi">
        <div class="k"><i class="bi bi-bank"></i> Cobrado</div>
        <div class="v num">{{ $d($kpi['cobrado']) }}</div>
        <div class="s num">{{ $kpi['cobros'] }} {{ $kpi['cobros'] === 1 ? 'pago recibido' : 'pagos recibidos' }}</div>
    </div>
    <a href="#por-cobrar" class="panel kpi text-reset text-decoration-none {{ $kpi['porCobrar'] > 0 ? 'alerta' : '' }}">
        <div class="k"><i class="bi bi-hourglass-split"></i> Por cobrar</div>
        <div class="v num">{{ $d($kpi['porCobrar']) }}</div>
        <div class="s num">{{ $kpi['pendientes'] }} {{ $kpi['pendientes'] === 1 ? 'pago pendiente' : 'pagos pendientes' }} · hoy</div>
    </a>
    <a href="{{ route('admin.presupuestos.index', ['filtro' => 'vigentes']) }}" class="panel kpi text-reset text-decoration-none">
        <div class="k"><i class="bi bi-send"></i> En juego</div>
        <div class="v num">{{ $d($kpi['enJuego']) }}</div>
        <div class="s num">{{ $kpi['abiertas'] }} {{ $kpi['abiertas'] === 1 ? 'abierta' : 'abiertas' }}@if($kpi['negociacion']) · {{ $kpi['negociacion'] }} en negociación ({{ $d($kpi['negociacionMonto']) }})@endif</div>
    </a>
</div>
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

<div class="rejilla">
    {{-- ================= Embudo ================= --}}
    <section class="panel">
        <div class="panel-head"><h2>Embudo de ventas</h2><span class="ayuda">{{ F::PERIODOS[$periodo] }}</span></div>
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

{{-- ================= Pendientes de cobro ================= --}}
<section class="panel mt-4" id="por-cobrar">
    <div class="panel-head"><div><h2>Pendientes de cobro</h2><span class="ayuda">Pagos sin registrar de proyectos activos o terminados</span></div>
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

{{-- ================= Perdidas ================= --}}
<section class="panel mt-4" id="perdidas">
    <div class="panel-head"><div><h2>Cotizaciones perdidas</h2><span class="ayuda">Rechazadas o vencidas sin aceptar · {{ F::PERIODOS[$periodo] }}</span></div>
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
                        <td>@if($p->estado === 'rechazada')<span class="estado estado-rechazada">Rechazada</span>@else<span class="estado estado-borrador">Venció sin respuesta</span>@endif</td>
                        <td class="num">{!! $p->vistas ? '<i class="bi bi-eye text-secondary"></i> ' . $p->vistas : '<span class="secundario">No la abrió</span>' !!}</td>
                        <td class="text-end num fw-medium">{{ $d($monto($p)) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
