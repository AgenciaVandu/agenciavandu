@extends('admin.layout')
@section('titulo', 'Cobranza')

@php $dinero = fn ($n) => '$' . number_format((float) $n, 0); @endphp

@push('head')
<style>
    .kpis-c { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 16px; }
    .kpis-c > div { padding: 16px 20px; } .kpis-c > div + div { border-left: 1px solid var(--line); }
    .kpis-c .k { font-size: 13px; color: var(--muted); } .kpis-c .v { font-size: 24px; font-weight: 600; }
    .termometro { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; }
    .termometro a { flex: 1 1 140px; display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-radius: 12px; border: 1px solid var(--line); background: var(--surface); text-decoration: none; color: inherit; }
    .termometro a.activo { box-shadow: 0 0 0 2px var(--ink); }
    .termometro .pt { width: 12px; height: 12px; border-radius: 50%; flex: none; }
    .termometro b { font-size: 20px; margin-left: auto; }
    .termo { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600; padding: 3px 10px; border-radius: 99px; color: #fff; white-space: nowrap; }
    .cli { display: flex; gap: 12px; align-items: center; text-decoration: none; color: inherit; min-width: 0; }
    .cli img, .cli .ph { width: 56px; height: 42px; border-radius: 8px; object-fit: cover; background: var(--sunken); flex: none; display: grid; place-items: center; color: var(--faint); }
    @media (max-width: 767.98px) { .kpis-c { grid-template-columns: repeat(2, minmax(0, 1fr)); } .kpis-c > div:nth-child(3) { border-left: 0; } .kpis-c > div:nth-child(n+3) { border-top: 1px solid var(--line); } }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div><h1>Cobranza</h1><p class="sub">Los créditos del lote: lo que falta por cobrar, lo vencido y cómo va cada cliente.</p></div>
    <a href="{{ route('admin.unidades.index') }}" class="btn btn-borde"><i class="bi bi-car-front me-1"></i> Unidades</a>
</div>

<div class="panel kpis-c">
    <div><div class="k">Por cobrar</div><div class="v num">{{ $dinero($resumen['por_cobrar']) }}</div></div>
    <div><div class="k">Vencido</div><div class="v num" style="{{ $resumen['vencido'] > 0 ? 'color: var(--red)' : '' }}">{{ $dinero($resumen['vencido']) }}</div></div>
    <div><div class="k">Cobrado este mes</div><div class="v num">{{ $dinero($resumen['cobrado_mes']) }}</div></div>
    <div><div class="k">Clientes con adeudo</div><div class="v num">{{ $resumen['con_adeudo'] }} <span class="secundario" style="font-size:14px; font-weight:400">de {{ $resumen['activos'] }}</span></div></div>
</div>

<div class="termometro" aria-label="Termómetro de clientes">
    @foreach($rangos as $i => $r)
        <a href="{{ route('admin.creditos.index', array_filter(['nivel' => $nivel === $r['nombre'] ? null : $r['nombre'], 'sucursal' => $sucursal])) }}" class="{{ $nivel === $r['nombre'] ? 'activo' : '' }}">
            <span class="pt" style="background: {{ $r['color'] }}"></span>
            <span><span class="d-block fw-medium">{{ $r['nombre'] }}</span><span class="secundario small">{{ $r['hasta'] === null ? 'más de ' . ($rangos[$i - 1]['hasta'] ?? 0) : ($r['hasta'] == 0 ? 'al corriente' : 'hasta ' . $r['hasta'] . ' ' . ($r['hasta'] == 1 ? 'letra' : 'letras')) }}</span></span>
            <b class="num">{{ $porNivel[$r['nombre']] ?? 0 }}</b>
        </a>
    @endforeach
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <nav class="segmento" aria-label="Ver">
        <a href="{{ route('admin.creditos.index') }}" class="{{ $vista === 'activos' ? 'activo' : '' }}">Créditos activos</a>
        <a href="{{ route('admin.creditos.index', ['ver' => 'liquidados']) }}" class="{{ $vista === 'liquidados' ? 'activo' : '' }}">Liquidados</a>
        <a href="{{ route('admin.creditos.index', ['ver' => 'ventas']) }}" class="{{ $vista === 'ventas' ? 'activo' : '' }}">Todas las ventas</a>
    </nav>
    <div class="d-flex gap-2 flex-wrap">
        @if($sucursales->count() > 1)
            <form method="get">@if($vista !== 'activos')<input type="hidden" name="ver" value="{{ $vista }}">@endif
                <select name="sucursal" class="form-select" onchange="this.form.submit()" aria-label="Sucursal"><option value="">Todas las sucursales</option>
                    @foreach($sucursales as $s)<option value="{{ $s->id }}" @selected($sucursal === $s->id)>{{ $s->nombre }}</option>@endforeach</select></form>
        @endif
        <form class="buscador" method="get" role="search">@if($vista !== 'activos')<input type="hidden" name="ver" value="{{ $vista }}">@endif
            <i class="bi bi-search"></i><input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Cliente o teléfono" aria-label="Buscar cliente"></form>
    </div>
</div>

<div class="panel">
    @if($creditos->isEmpty())
        <div class="vacio"><div class="ico"><i class="bi bi-cash-coin"></i></div>
            <h3>{{ $nivel || $q ? 'Nadie coincide' : ($vista === 'activos' ? 'Sin créditos activos' : 'Sin ventas todavía') }}</h3>
            <p>{{ $vista === 'activos' && ! $nivel && ! $q ? 'Cuando vendas una unidad a crédito del lote, aquí verás sus letras y pagos.' : 'Prueba con otro filtro.' }}</p></div>
    @else
        <div class="table-responsive">
            <table class="tabla">
                <thead><tr><th>Cliente y unidad</th>
                    @if($vista === 'activos')<th>Próxima letra</th><th class="text-end">Vencido</th><th class="text-end">Por cobrar</th><th>Termómetro</th>
                    @else<th>Venta</th><th class="text-end">Precio</th><th>Fecha</th>@endif</tr></thead>
                <tbody>
                    @foreach($creditos as $c)
                        <tr>
                            <td style="min-width: 260px">
                                <a href="{{ route('admin.creditos.show', $c) }}" class="cli">
                                    @if($c->unidad?->portada)<img src="{{ $c->unidad->portada->miniatura_url }}" alt="">@else<span class="ph"><i class="bi bi-car-front"></i></span>@endif
                                    <span style="min-width:0"><span class="d-block fw-medium text-truncate">{{ $c->cliente?->nombre ?? 'Sin cliente' }}</span><span class="secundario small d-block text-truncate">{{ $c->unidad?->nombre }}</span></span>
                                </a>
                            </td>
                            @if($vista === 'activos')
                                @php $p = $c->proxima; $atr = $c->atrasadas()->count(); $t = $c->termometro; @endphp
                                <td class="text-nowrap">@if($p)<span class="num">{{ $p->numero }}/{{ $c->plazo }} · ${{ number_format($p->monto, 0) }}</span><div class="secundario small">{{ $p->vence_el->locale('es')->isoFormat('D MMM') }}</div>@endif</td>
                                <td class="text-end num" style="{{ $atr ? 'color: var(--red)' : '' }}">{{ $atr ? $dinero($c->vencido) : '—' }}@if($atr)<div class="secundario small">{{ $atr }} {{ $atr === 1 ? 'letra' : 'letras' }}</div>@endif</td>
                                <td class="text-end num">{{ $dinero($c->por_cobrar) }}</td>
                                <td><span class="termo" style="background: {{ $t['color'] }}">{{ $t['nombre'] }}</span></td>
                            @else
                                <td>{{ \App\Models\Credito::TIPOS[$c->tipo] }}{{ $c->estado === 'liquidado' && $c->tipo === 'credito' ? ' · liquidado' : '' }}</td>
                                <td class="text-end num">{{ $dinero($c->precio) }}</td>
                                <td class="secundario text-nowrap">{{ $c->fecha->locale('es')->isoFormat('D MMM YYYY') }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
