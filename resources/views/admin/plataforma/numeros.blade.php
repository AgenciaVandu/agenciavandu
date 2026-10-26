@extends('admin.layout')
@section('titulo', 'Números de la plataforma')

@php
    $d0 = fn ($v) => '$' . number_format((float) $v, 0);
    $f = fn ($d) => $d ? $d->locale('es')->isoFormat('D MMM') : '—';
    $maxMes = max(1, $n['meses']->max('monto'));
    $var = $n['cobradoAnt'] > 0 ? round(($n['cobradoMes'] - $n['cobradoAnt']) / $n['cobradoAnt'] * 100) : null;
@endphp

@push('head')
<style>
    .nm-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 20px; }
    .nm-kpi { padding: 16px 18px; }
    .nm-kpi .k { font-size: 13.5px; color: var(--muted); display: flex; gap: 7px; align-items: center; }
    .nm-kpi .v { font-size: 28px; font-weight: 600; letter-spacing: -.02em; margin-top: 8px; line-height: 1.1; }
    .nm-kpi .s { font-size: 13px; color: var(--muted); margin-top: 6px; }
    .nm-kpi.destacado { background: var(--ink); color: #fff; border-color: var(--ink); } .nm-kpi.destacado .k, .nm-kpi.destacado .s { color: #A9AEBA; }
    .nm-kpi.alerta .v { color: var(--red); }
    .nm-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 20px; margin-bottom: 20px; align-items: start; }
    .nm-barras { display: grid; grid-template-columns: repeat(12, 1fr); gap: 6px; align-items: end; height: 170px; padding: 16px 20px 0; }
    .nm-barras .col { display: grid; align-items: end; height: 100%; position: relative; }
    .nm-barras .col span { display: block; background: var(--ink); border-radius: 4px 4px 0 0; min-height: 2px; }
    .nm-barras .col.actual span { background: #00A862; }
    .nm-meses { display: grid; grid-template-columns: repeat(12, 1fr); gap: 6px; padding: 6px 20px 16px; font-size: 11.5px; color: var(--muted); text-align: center; }
    .nm-lista a, .nm-lista div.fila { display: flex; justify-content: space-between; gap: 12px; padding: 10px 20px; border-top: 1px solid #F1F2F4; color: inherit; text-decoration: none; font-size: 14px; align-items: center; }
    .nm-lista a:hover { background: #F8F9FA; }
    @media (max-width: 1199.98px) { .nm-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .nm-grid { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 575.98px) { .nm-kpi .v { font-size: 22px; } }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <a href="{{ route('admin.plataforma') }}" class="secundario small text-decoration-none"><i class="bi bi-arrow-left"></i> Plataforma</a>
        <h1>Números de la plataforma</h1>
        <p class="sub">Lo que te dejan las licencias: cuánto entra cada mes, quién debe y qué viene.</p>
    </div>
    <a href="{{ route('admin.finanzas', 'flujo') }}" class="btn btn-borde"><i class="bi bi-graph-up-arrow me-1"></i> Ver en mis Finanzas</a>
</div>

@if($n['comprobantes']->isNotEmpty())
    <section class="panel mb-4" style="border-color: #F5DDB0">
        <div class="panel-head"><div><h2>Comprobantes por revisar</h2><span class="ayuda">Negocios que dicen que ya pagaron</span></div></div>
        <div class="nm-lista">
            @foreach($n['comprobantes'] as $cp)
                <a href="{{ route('admin.plataforma.licencia', $cp->cuenta_id) }}"><span><span class="principal">{{ $cp->cuenta?->nombre }}</span> <span class="secundario">· {{ $f($cp->fecha) }} · {{ $cp->usuario?->name }}</span></span><span class="num fw-semibold">{{ $d0($cp->monto) }} <i class="bi bi-chevron-right secundario"></i></span></a>
            @endforeach
        </div>
    </section>
@endif

<div class="nm-kpis">
    <div class="panel nm-kpi destacado">
        <div class="k"><i class="bi bi-arrow-repeat"></i> Ingreso recurrente al mes</div>
        <div class="v num">{{ $d0($n['mrr']) }}</div>
        <div class="s">{{ $n['clientes'] }} {{ $n['clientes'] === 1 ? 'negocio paga' : 'negocios pagan' }}{{ $n['ticket'] ? ' · ' . $d0($n['ticket']) . ' en promedio' : '' }} · {{ $d0($n['arr']) }} al año</div>
    </div>
    <div class="panel nm-kpi">
        <div class="k"><i class="bi bi-cash-coin"></i> Cobrado este mes</div>
        <div class="v num">{{ $d0($n['cobradoMes']) }}</div>
        <div class="s">{{ $var !== null ? ($var >= 0 ? '+' : '') . $var . '% vs. el mes pasado' : 'Mes pasado: ' . $d0($n['cobradoAnt']) }}</div>
    </div>
    <div class="panel nm-kpi {{ $n['vencidos']->isNotEmpty() ? 'alerta' : '' }}">
        <div class="k"><i class="bi bi-exclamation-circle"></i> Vencidos</div>
        <div class="v num">{{ $d0($n['vencidoMonto']) }}</div>
        <div class="s">{{ $n['vencidos']->count() }} {{ $n['vencidos']->count() === 1 ? 'negocio' : 'negocios' }} sin pagar · {{ $n['suspendidos'] }} {{ $n['suspendidos'] === 1 ? 'suspendido' : 'suspendidos' }}</div>
    </div>
    <div class="panel nm-kpi">
        <div class="k"><i class="bi bi-calendar-event"></i> Por cobrar en 30 días</div>
        <div class="v num">{{ $d0($n['proximoMonto']) }}</div>
        <div class="s">{{ $n['proximos']->count() }} {{ $n['proximos']->count() === 1 ? 'vencimiento' : 'vencimientos' }}</div>
    </div>
</div>

<div class="nm-grid">
    <section class="panel">
        <div class="panel-head"><div><h2>Sin pagar</h2><span class="ayuda">Ya vencieron: en gracia, solo lectura o suspendidos</span></div></div>
        <div class="nm-lista">
            @forelse($n['vencidos'] as $x)
                <a href="{{ route('admin.plataforma.licencia', $x->l->cuenta_id) }}"><span><span class="principal">{{ $x->l->cuenta->nombre }}</span>
                    <span class="d-block secundario small">Venció el {{ $f($x->e->vence) }} · hace {{ abs($x->e->dias) }} {{ abs($x->e->dias) === 1 ? 'día' : 'días' }}</span></span>
                    <span class="text-end"><span class="estado estado-{{ $x->e->clase }}">{{ $x->e->texto }}</span><span class="d-block num fw-semibold mt-1">{{ $d0($x->l->tipo === 'perpetua' ? $x->l->mantenimiento : $x->l->precio) }}</span></span></a>
            @empty
                <div class="fila secundario">Todos al corriente.</div>
            @endforelse
        </div>
    </section>
    <section class="panel">
        <div class="panel-head"><div><h2>Vencen en los próximos 30 días</h2><span class="ayuda">Lo que deberías cobrar</span></div></div>
        <div class="nm-lista">
            @forelse($n['proximos'] as $x)
                <a href="{{ route('admin.plataforma.licencia', $x->l->cuenta_id) }}"><span><span class="principal">{{ $x->l->cuenta->nombre }}</span>
                    <span class="d-block secundario small">{{ $x->l->plan?->nombre ?? 'Mantenimiento' }} · {{ $f($x->l->vence_el) }}</span></span>
                    <span class="num fw-semibold">{{ $d0($x->l->tipo === 'perpetua' ? $x->l->mantenimiento : $x->l->precio) }}</span></a>
            @empty
                <div class="fila secundario">Nada vence en los próximos 30 días.</div>
            @endforelse
        </div>
    </section>
</div>

<div class="nm-grid">
    <section class="panel">
        <div class="panel-head"><div><h2>Cobrado por mes</h2><span class="ayuda">Mensualidades, anualidades, implementaciones y mantenimiento</span></div></div>
        <div class="nm-barras">
            @foreach($n['meses'] as $m)
                <div class="col {{ $m['actual'] ? 'actual' : '' }}" title="{{ $m['completa'] }}: {{ $d0($m['monto']) }} ({{ $d0($m['recurrente']) }} recurrente)"><span style="height: {{ $m['monto'] / $maxMes * 100 }}%"></span></div>
            @endforeach
        </div>
        <div class="nm-meses">@foreach($n['meses'] as $m)<span class="{{ $m['actual'] ? 'fw-semibold' : '' }}">{{ $m['etiqueta'] }}</span>@endforeach</div>
    </section>
    <section class="panel">
        <div class="panel-head"><div><h2>Por plan</h2><span class="ayuda">Negocios que pagan y lo que dejan al mes</span></div></div>
        <div class="nm-lista">
            @forelse($n['porPlan'] as $p)
                <div class="fila"><span><span class="principal">{{ $p['nombre'] }}</span> <span class="secundario">· {{ $p['n'] }}</span></span><span class="num fw-semibold">{{ $d0($p['mrr']) }}/mes</span></div>
            @empty
                <div class="fila secundario">Todavía no hay licencias de pago.</div>
            @endforelse
            <div class="fila secundario small"><span>Implementaciones cobradas</span><span class="num">{{ $d0($n['implCobrada']) }}</span></div>
            @if($n['implPendiente']->isNotEmpty())
                <div class="fila small"><span>Implementaciones por cobrar: {{ $n['implPendiente']->map(fn ($l) => $l->cuenta->nombre)->join(', ') }}</span><span class="num fw-semibold">{{ $d0($n['implPendiente']->sum('implementacion')) }}</span></div>
            @endif
            <div class="fila secundario small"><span>Cortesía o demo · sin licencia · cuentas apagadas</span><span class="num">{{ $n['cortesia'] }} · {{ $n['sinLicencia'] }} · {{ $n['apagadas'] }}</span></div>
        </div>
    </section>
</div>
@endsection
