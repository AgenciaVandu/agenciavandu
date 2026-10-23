@extends('admin.layout')
@section('titulo', 'Pedidos')

@php
    $d = fn ($n) => '$' . number_format((float) $n, 0);
    $tz = config('vandu.zona_horaria');
    $u = fn (array $x = []) => route('admin.pedidos', array_filter(array_merge(['ver' => $ver === 'hoy' ? null : $ver, 'q' => $q ?: null], $x), fn ($v) => $v !== null));
    $siguiente = ['por_surtir' => 'listo', 'listo' => 'en_camino', 'en_camino' => 'entregada'];
@endphp

@push('head')
<style>
    .pk { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 20px; }
    .pk > * { padding: 16px 20px; color: inherit; text-decoration: none; } .pk > * + * { border-left: 1px solid var(--line); }
    .pk .k { font-size: 13px; color: var(--muted); display: flex; gap: 6px; align-items: center; } .pk .v { font-size: 26px; font-weight: 700; } .pk .s { font-size: 12.5px; color: var(--muted); }
    @media (max-width: 767.98px) { .pk { grid-template-columns: repeat(2, minmax(0, 1fr)); } .pk > *:nth-child(3) { border-left: 0; } .pk > *:nth-child(n+3) { border-top: 1px solid var(--line); } }
    .pl > * + * { border-top: 1px solid var(--line); }
    .pf { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 12px; align-items: center; padding: 12px 20px; }
    .pf a.q { color: inherit; text-decoration: none; } .pf .t { font-weight: 600; } .pf .s { font-size: 13px; color: var(--muted); }
    .pf .m { text-align: right; font-weight: 700; white-space: nowrap; }
    .pb { font-size: 12px; font-weight: 600; padding: 2px 9px; border-radius: 99px; background: var(--sunken); color: var(--muted); white-space: nowrap; }
    .pb.ok { background: #E3FBEF; color: #047A4B; } .pb.pend { background: #FFF3DD; color: #9A5300; } .pb.azul { background: var(--marca-soft); color: var(--marca); } .pb.mal { background: #FDECEC; color: #B42318; }
    @media (max-width: 575.98px) { .pf { grid-template-columns: minmax(0, 1fr) auto; } .pf .acc { grid-column: 1 / -1; } }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div><h1>Pedidos</h1><p class="sub">Ventas de mostrador y pedidos con envío: quién pagó, qué falta entregar y qué falta cobrar.</p></div>
    <div class="d-flex flex-wrap gap-2">
        @if(auth()->user()->puede('configuracion'))<a href="{{ route('admin.pedidos.ajustes') }}" class="btn btn-borde"><i class="bi bi-sliders me-1"></i> Envíos e IVA</a>@endif
        <a href="{{ route('admin.pedidos.create') }}" class="btn btn-primario"><i class="bi bi-bag-plus me-1"></i> Nueva venta</a>
    </div>
</div>

<div class="panel pk">
    <a href="{{ $u(['ver' => null]) }}"><div class="k"><i class="bi bi-sun"></i> Vendido hoy</div><div class="v num">{{ $d($kpi['hoy']) }}</div><div class="s">{{ $kpi['nHoy'] }} {{ $kpi['nHoy'] === 1 ? 'venta' : 'ventas' }}</div></a>
    <div><div class="k"><i class="bi bi-calendar3"></i> Este mes</div><div class="v num">{{ $d($kpi['mes']) }}</div><div class="s">{{ $kpi['nMes'] }} ventas directas</div></div>
    <a href="{{ $u(['ver' => 'entregar']) }}"><div class="k"><i class="bi bi-truck"></i> Por entregar</div><div class="v num">{{ $kpi['entregar'] }}</div><div class="s">por surtir, listos y en camino</div></a>
    <a href="{{ $u(['ver' => 'cobrar']) }}"><div class="k"><i class="bi bi-cash-coin"></i> Por cobrar</div><div class="v num" style="{{ $kpi['vencidas'] ? 'color: var(--red)' : '' }}">{{ $d($kpi['cobrar']) }}</div><div class="s">{{ $kpi['nCobrar'] }} {{ $kpi['nCobrar'] === 1 ? 'venta' : 'ventas' }}{{ $kpi['vencidas'] ? ' · ' . $kpi['vencidas'] . ' vencidas' : '' }}</div></a>
</div>

<section class="panel">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 pt-3 pb-2">
        <nav class="segmento" aria-label="Vista">
            @foreach(\App\Http\Controllers\Admin\PedidosController::VISTAS as $k => $t)<a href="{{ $u(['ver' => $k === 'hoy' ? null : $k, 'page' => null]) }}" class="{{ $ver === $k ? 'activo' : '' }}">{{ $t }}</a>@endforeach
        </nav>
        <form class="buscador" method="get" role="search">
            @if($ver !== 'hoy')<input type="hidden" name="ver" value="{{ $ver }}">@endif
            <i class="bi bi-search"></i><input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Folio o cliente" aria-label="Buscar pedidos">
        </form>
    </div>
    @if($ventas->isEmpty())
        <div class="vacio"><div class="ico"><i class="bi bi-bag"></i></div><h3>{{ ['hoy' => 'Aún no hay ventas hoy', 'entregar' => 'Nada pendiente de entregar', 'cobrar' => 'Nadie te debe', 'todas' => 'Todavía no hay ventas'][$ver] }}</h3>
            <p>Registra una venta en segundos: toca los productos y cobra.</p><a href="{{ route('admin.pedidos.create') }}" class="btn btn-primario mt-2"><i class="bi bi-bag-plus me-1"></i> Nueva venta</a></div>
    @else
        <div class="pl">
            @foreach($ventas as $v)
                <div class="pf">
                    <a href="{{ route('admin.pedidos.show', $v) }}" class="q min-w-0">
                        <span class="t d-block text-truncate">{{ $v->quien }}@if($v->lleva_envio) <i class="bi bi-truck secundario" title="Con envío"></i>@endif</span>
                        <span class="s d-block text-truncate num">{{ $v->folio }} · {{ $v->fecha->isToday() ? $v->created_at->setTimezone($tz)->format('H:i') : $v->fecha->locale('es')->isoFormat('D MMM') }} · {{ $v->vendedor?->name }}@if($v->repartidor) · lo lleva {{ $v->repartidor->name }}@endif</span>
                    </a>
                    <div class="d-flex flex-wrap gap-1 justify-content-end acc">
                        <span class="pb {{ $v->estado_clase }}">{{ $v->estado_texto }}</span>
                        <span class="pb {{ $v->pago_clase }}">{{ $v->pago_texto }}</span>
                        @if(isset($siguiente[$v->estado]))
                            <form method="post" action="{{ route('admin.pedidos.estado', $v) }}">@csrf @method('patch')<input type="hidden" name="estado" value="{{ $siguiente[$v->estado] }}">
                                <button class="btn btn-borde btn-sm py-0" title="Pasar a {{ \App\Models\Venta::ESTADOS[$siguiente[$v->estado]]['texto'] }}"><i class="bi bi-arrow-right"></i> {{ \App\Models\Venta::ESTADOS[$siguiente[$v->estado]]['texto'] }}</button></form>
                        @endif
                    </div>
                    <div class="m num">{{ \App\Models\Venta::dinero($v->total) }}@if(! $v->pagada && $v->estado !== 'cancelada' && $v->pagado > 0)<div class="s fw-normal">resta {{ \App\Models\Venta::dinero($v->saldo) }}</div>@endif</div>
                </div>
            @endforeach
        </div>
        @if($ventas->hasPages())<div class="px-3 py-2">{{ $ventas->links() }}</div>@endif
    @endif
</section>
@endsection
