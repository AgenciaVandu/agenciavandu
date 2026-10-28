@extends('admin.layout')
@section('titulo', 'Inicio')

@php
    $dinero = fn ($n) => '$' . number_format((float) $n, 0);
    $maxMes = max(1, $meses->max('monto'));
    $hora = (int) now(config('vandu.zona_horaria'))->format('G');
    $saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
@endphp

@push('head')
<style>
    .ra-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 20px; }
    .ra-kpis .panel { padding: 18px 20px; }
    .ra-kpis .k { font-size: 13px; color: var(--muted); display: flex; align-items: center; gap: 6px; }
    .ra-kpis .v { font-size: 26px; font-weight: 650; letter-spacing: -.01em; margin-top: 4px; }
    .ra-kpis .s { font-size: 13px; color: var(--muted); }
    .ra-grid { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 20px; align-items: start; }
    .termo-barra { display: flex; height: 14px; border-radius: 99px; overflow: hidden; background: var(--sunken); }
    .termo-barra span { height: 100%; }
    .termo-ley { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; margin-top: 14px; }
    .termo-ley a { display: flex; gap: 8px; align-items: center; text-decoration: none; color: inherit; font-size: 14px; }
    .termo-ley .pt { width: 10px; height: 10px; border-radius: 50%; }
    .termo-ley b { margin-left: auto; }
    .barras { display: flex; align-items: flex-end; gap: 12px; height: 160px; padding-top: 10px; }
    .barras div { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; height: 100%; justify-content: flex-end; }
    .barras .b { width: 100%; max-width: 46px; background: var(--ink); border-radius: 6px 6px 2px 2px; min-height: 2px; }
    .barras .e { font-size: 12px; color: var(--muted); }
    .barras .m { font-size: 11.5px; color: var(--text-2); }
    .lista-r li { display: flex; align-items: center; gap: 12px; padding: 12px 20px; border-top: 1px solid var(--line); }
    .lista-r li:first-child { border-top: 0; }
    .termo { font-size: 12px; font-weight: 600; padding: 2px 9px; border-radius: 99px; color: #fff; white-space: nowrap; }
    @media (max-width: 1199.98px) { .ra-grid { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 767.98px) { .ra-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div><h1>{{ $saludo }}, {{ auth()->user()->primer_nombre }}</h1><p class="sub">{{ ucfirst(now(config('vandu.zona_horaria'))->locale('es')->isoFormat('dddd D [de] MMMM')) }}</p></div>
    <div class="d-flex gap-2">
        @if(auth()->user()->puede('unidades'))<a href="{{ route('admin.unidades.create') }}" class="btn btn-borde"><i class="bi bi-plus-lg me-1"></i> Nueva unidad</a>@endif
        @if(auth()->user()->puede('cobranza'))<a href="{{ route('admin.creditos.index') }}" class="btn btn-primario"><i class="bi bi-cash-coin me-1"></i> Cobranza</a>@endif
    </div>
</div>
@include('admin._arranque')

<div class="ra-kpis">
    <a class="panel text-decoration-none" style="color: inherit" href="{{ route('admin.unidades.index') }}">
        <div class="k"><i class="bi bi-car-front"></i> Por vender</div>
        <div class="v num">{{ $porVender['n'] }} <span style="font-size:15px; font-weight:400" class="secundario">unidades</span></div>
        <div class="s">{{ $dinero($porVender['valor']) }} en inventario{{ $porVender['apartadas'] ? ' · ' . $porVender['apartadas'] . ' apartadas' : '' }}</div>
    </a>
    <a class="panel text-decoration-none" style="color: inherit" href="{{ route('admin.creditos.index') }}">
        <div class="k"><i class="bi bi-hourglass-split"></i> Por cobrar</div>
        <div class="v num">{{ $dinero($cobranza['por_cobrar']) }}</div>
        <div class="s">{{ $totalActivos }} {{ $totalActivos === 1 ? 'crédito activo' : 'créditos activos' }}</div>
    </a>
    <a class="panel text-decoration-none" style="color: inherit" href="{{ route('admin.creditos.index', ['nivel' => $rangos[count($rangos) - 1]['nombre']]) }}">
        <div class="k"><i class="bi bi-exclamation-triangle"></i> Vencido</div>
        <div class="v num" style="{{ $cobranza['vencido'] > 0 ? 'color: var(--red)' : '' }}">{{ $dinero($cobranza['vencido']) }}</div>
        <div class="s">{{ $adeudos->count() }} {{ $adeudos->count() === 1 ? 'cliente con adeudo' : 'clientes con adeudo' }}</div>
    </a>
    <div class="panel">
        <div class="k"><i class="bi bi-calendar-check"></i> Este mes</div>
        <div class="v num">{{ $dinero($cobranza['cobrado_mes']) }}</div>
        <div class="s">cobrado · {{ $ventasMes['n'] }} {{ $ventasMes['n'] === 1 ? 'venta' : 'ventas' }} por {{ $dinero($ventasMes['monto']) }}</div>
    </div>
</div>

<div class="ra-grid">
    <div class="d-grid gap-4">
        <section class="panel panel-body">
            <div class="d-flex justify-content-between align-items-baseline mb-3"><h2 class="fs-6 fw-semibold m-0">Termómetro de clientes</h2><span class="secundario small">{{ $totalActivos }} con crédito activo</span></div>
            <div class="termo-barra" role="img" aria-label="Distribución de clientes por termómetro">
                @foreach($rangos as $r)@if(($porNivel[$r['nombre']] ?? 0) > 0)<span style="width: {{ $porNivel[$r['nombre']] / max(1, $totalActivos) * 100 }}%; background: {{ $r['color'] }}"></span>@endif @endforeach
            </div>
            <div class="termo-ley">
                @foreach($rangos as $r)
                    <a href="{{ route('admin.creditos.index', ['nivel' => $r['nombre']]) }}"><span class="pt" style="background: {{ $r['color'] }}"></span>{{ $r['nombre'] }}<b class="num">{{ $porNivel[$r['nombre']] ?? 0 }}</b></a>
                @endforeach
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Clientes con adeudo</h2><a href="{{ route('admin.creditos.index') }}" class="ayuda">Ver cobranza</a></div>
            @if($adeudos->isEmpty())
                <p class="secundario px-4 py-3 mb-0"><i class="bi bi-check-circle text-success me-1"></i> Todos al corriente.</p>
            @else
                <ul class="list-unstyled mb-0 lista-r">
                    @foreach($adeudos as $c)
                        @php $t = $c->termometro; $n = $c->atrasadas()->count(); @endphp
                        <li>
                            <a href="{{ route('admin.creditos.show', $c) }}" class="flex-grow-1 text-decoration-none" style="color: inherit; min-width: 0">
                                <span class="d-block fw-medium text-truncate">{{ $c->cliente?->nombre }}</span>
                                <span class="secundario small d-block text-truncate">{{ $c->unidad?->nombre }} · {{ $n }} {{ $n === 1 ? 'letra vencida' : 'letras vencidas' }}</span>
                            </a>
                            <b class="num" style="color: var(--red)">{{ $dinero($c->vencido) }}</b>
                            <span class="termo" style="background: {{ $t['color'] }}">{{ $t['nombre'] }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <div class="d-grid gap-4">
        <section class="panel panel-body">
            <h2 class="fs-6 fw-semibold mb-1">Cobrado por mes</h2>
            <div class="barras">
                @foreach($meses as $m)
                    <div><span class="m num">{{ $m['monto'] > 0 ? '$' . number_format($m['monto'] / 1000, 0) . 'k' : '' }}</span><span class="b" style="height: {{ $m['monto'] / $maxMes * 100 }}%"></span><span class="e">{{ $m['etiqueta'] }}</span></div>
                @endforeach
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Vencen esta semana</h2></div>
            @if($proximos->isEmpty())
                <p class="secundario px-4 py-3 mb-0">No hay letras por vencer en los próximos 7 días.</p>
            @else
                <ul class="list-unstyled mb-0 lista-r">
                    @foreach($proximos as [$c, $l])
                        <li>
                            <a href="{{ route('admin.creditos.show', $c) }}" class="flex-grow-1 text-decoration-none" style="color: inherit; min-width: 0">
                                <span class="d-block fw-medium text-truncate">{{ $c->cliente?->nombre }}</span>
                                <span class="secundario small">Letra {{ $l->numero }} · {{ $l->vence_el->locale('es')->isoFormat('ddd D MMM') }}</span>
                            </a>
                            <b class="num">${{ number_format($l->monto, 0) }}</b>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if($ventasMes['n'])
            <section class="panel panel-body">
                <h2 class="fs-6 fw-semibold mb-2">Ventas del mes</h2>
                @foreach(\App\Models\Credito::TIPOS as $k => $txt)
                    <div class="d-flex justify-content-between small py-1"><span>{{ $txt }}</span><b class="num">{{ $ventasMes['tipos'][$k] ?? 0 }}</b></div>
                @endforeach
            </section>
        @endif
    </div>
</div>
@endsection
