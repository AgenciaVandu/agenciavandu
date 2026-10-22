@extends('admin.layout')
@section('titulo', 'Finanzas')
@include('admin.fitness._estilos')

@php
    $dinero = fn ($n) => '$' . number_format((float) $n, 0);
    $max = max(1, $meses->max('vendido'), $meses->max('cobrado'));
@endphp

@push('head')
<style>
    .barras2 { display: flex; align-items: flex-end; gap: 10px; height: 170px; padding: 16px 20px 8px; }
    .barras2 > div { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; height: 100%; justify-content: flex-end; min-width: 0; }
    .barras2 .par { display: flex; gap: 3px; align-items: flex-end; height: 100%; width: 100%; justify-content: center; }
    .barras2 .b { width: 42%; max-width: 18px; border-radius: 4px 4px 1px 1px; min-height: 2px; }
    .barras2 .v { background: var(--ink); } .barras2 .c { background: var(--green); }
    .barras2 .e { font-size: 11.5px; color: var(--muted); }
    .barras2 .actual .e { color: var(--ink); font-weight: 600; }
    .ley { display: flex; gap: 14px; font-size: 13px; color: var(--muted); } .ley i { display: inline-block; width: 10px; height: 10px; border-radius: 3px; margin-right: 5px; }
    .fin-grid { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 20px; align-items: start; }
    @media (max-width: 1199.98px) { .fin-grid { grid-template-columns: minmax(0, 1fr); } }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div><h1>Finanzas</h1><p class="sub">Lo que vendes en paquetes de clases. Cada paquete cuenta al asignarlo y se cobra cuando marcas el pago.</p></div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <nav class="segmento" aria-label="Periodo">
            @foreach(\App\Http\Controllers\Admin\FinanzasController::PERIODOS as $k => $t)<a href="{{ route('admin.finanzas', ['periodo' => $k]) }}" class="{{ $periodo === $k ? 'activo' : '' }}">{{ $t }}</a>@endforeach
        </nav>
        <a href="{{ route('admin.finanzas.exportar', ['periodo' => $periodo]) }}" class="btn btn-borde" data-recargar><i class="bi bi-download me-1"></i> Excel</a>
    </div>
</div>

<div class="panel fx-kpis">
    <div><div class="k"><i class="bi bi-bag-check"></i> Vendido</div><div class="v num">{{ $dinero($kpi['vendido']) }}</div><div class="s">{{ $kpi['paquetes'] }} {{ $kpi['paquetes'] === 1 ? 'paquete' : 'paquetes' }} · ticket {{ $dinero($kpi['ticket']) }}</div></div>
    <div><div class="k"><i class="bi bi-cash-coin"></i> Cobrado</div><div class="v num">{{ $dinero($kpi['cobrado']) }}</div><div class="s">por fecha de pago</div></div>
    <a href="{{ route('admin.clientes.index', ['ver' => 'pendientes']) }}"><div class="k"><i class="bi bi-exclamation-circle"></i> Por cobrar</div><div class="v num" style="{{ $kpi['porCobrar'] ? 'color: var(--red)' : '' }}">{{ $dinero($kpi['porCobrar']) }}</div><div class="s">{{ $kpi['nPorCobrar'] }} {{ $kpi['nPorCobrar'] === 1 ? 'paquete sin pagar' : 'paquetes sin pagar' }}</div></a>
    <div><div class="k"><i class="bi bi-arrow-repeat"></i> Renovaciones</div><div class="v num">{{ $kpi['renovaciones'] }}</div><div class="s">{{ $kpi['nuevos'] }} {{ $kpi['nuevos'] === 1 ? 'atleta nuevo' : 'atletas nuevos' }}</div></div>
</div>

@if($gas)
    @php $queda = $kpi['cobrado'] - $gas['total']; $mas = $gas['porCategoria']->first(); @endphp
    <div class="panel fx-kpis">
        <a href="{{ route('admin.gastos', ['periodo' => $periodo === 'mes' ? null : $periodo]) }}"><div class="k"><i class="bi bi-receipt"></i> Gastos</div><div class="v num">{{ $dinero($gas['total']) }}</div><div class="s">{{ $gas['n'] }} {{ $gas['n'] === 1 ? 'gasto' : 'gastos' }} en el periodo</div></a>
        <div><div class="k"><i class="bi bi-piggy-bank"></i> Te queda</div><div class="v num" style="color: {{ $queda < 0 ? 'var(--red)' : 'var(--green-ink, #047A4B)' }}">{{ $queda < 0 ? '−' : '' }}{{ $dinero(abs($queda)) }}</div><div class="s">cobrado menos gastos</div></div>
        <div><div class="k"><i class="bi bi-percent"></i> Margen</div><div class="v num">{{ $kpi['cobrado'] > 0 && $queda >= 0 ? round($queda / $kpi['cobrado'] * 100) . '%' : '—' }}</div><div class="s">{{ $queda < 0 ? 'gastaste más de lo que cobraste' : 'de cada $100 cobrados' }}</div></div>
        <div><div class="k"><i class="bi {{ $mas['icono'] ?? 'bi-pie-chart' }}"></i> Donde más gastas</div><div class="v num" style="font-size: 20px">{{ $mas['texto'] ?? '—' }}</div><div class="s">{{ $mas ? $dinero($mas['monto']) . ' · ' . ($gas['total'] > 0 ? round($mas['monto'] / $gas['total'] * 100) : 0) . '% del gasto' : 'sin gastos en el periodo' }}</div></div>
    </div>
@endif

<div class="fin-grid">
    <div class="d-grid gap-4">
        <section class="panel">
            <div class="panel-head"><h2>Últimos 12 meses</h2><div class="ley"><span><i style="background: var(--ink)"></i>Vendido</span><span><i style="background: var(--green)"></i>Cobrado</span></div></div>
            <div class="barras2" role="img" aria-label="Vendido y cobrado por mes">
                @foreach($meses as $m)
                    <div class="{{ $m['actual'] ? 'actual' : '' }}" title="{{ $m['etiqueta'] }}: vendido {{ $dinero($m['vendido']) }}, cobrado {{ $dinero($m['cobrado']) }}">
                        <div class="par"><span class="b v" style="height: {{ round($m['vendido'] / $max * 100) }}%"></span><span class="b c" style="height: {{ round($m['cobrado'] / $max * 100) }}%"></span></div>
                        <span class="e">{{ $m['etiqueta'] }}</span></div>
                @endforeach
            </div>
        </section>
        <section class="panel">
            <div class="panel-head"><h2>Por paquete</h2></div>
            <div class="table-responsive"><table class="tabla">
                <thead><tr><th>Paquete</th><th class="text-end">Vendidos</th><th class="text-end">Monto</th></tr></thead>
                <tbody>@forelse($porPaquete as $p)<tr><td>{{ $p['nombre'] }}</td><td class="text-end num">{{ $p['n'] }}</td><td class="text-end num fw-medium">{{ $dinero($p['monto']) }}</td></tr>
                @empty<tr><td colspan="3" class="secundario">Sin ventas en este periodo.</td></tr>@endforelse</tbody>
            </table></div>
        </section>
    </div>
    <aside class="d-grid gap-4">
        <section class="panel">
            <div class="panel-head"><h2>Te deben</h2></div>
            <div class="fx-lista">
                @forelse($pendientes->take(12) as $m)
                    <a class="fx-fila text-reset text-decoration-none" href="{{ route('admin.clientes.show', [$m->cliente_id, 'ver' => 'atleta']) }}">
                        <div class="flex-grow-1 min-w-0"><div class="principal text-truncate">{{ $m->cliente?->nombre }}</div><div class="secundario small">{{ $m->nombre }} · desde {{ $m->created_at->locale('es')->isoFormat('D MMM') }}</div></div>
                        <b class="num">{{ $dinero($m->precio) }}</b></a>
                @empty<div class="panel-body secundario small">Nadie te debe.</div>@endforelse
            </div>
        </section>
        @if($porMetodo->isNotEmpty())
        <section class="panel">
            <div class="panel-head"><h2>Cómo te pagaron</h2></div>
            <div class="fx-lista">@foreach($porMetodo as $x)<div class="fx-fila"><span class="flex-grow-1">{{ $x['metodo'] }}</span><span class="secundario small">{{ $x['n'] }}</span><b class="num">{{ $dinero($x['monto']) }}</b></div>@endforeach</div>
        </section>
        @endif
    </aside>
</div>
@endsection
