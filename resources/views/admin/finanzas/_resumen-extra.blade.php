{{-- Utilidad y proveedores de un vistazo (el detalle está en sus secciones) --}}
<div class="kpis">
    <a href="{{ $q(['seccion' => 'rentabilidad']) }}" class="panel kpi text-reset text-decoration-none {{ $rent['utilidad'] > 0 ? 'bueno' : '' }}">
        <div class="k"><i class="bi bi-pie-chart"></i> Utilidad</div>
        <div class="v num">{{ $rent['n'] ? $d($rent['utilidad']) : '—' }}</div>
        <div class="s num">{{ $rent['n'] ? 'De ' . $rent['n'] . ' ' . ($rent['n'] === 1 ? 'venta con costeo' : 'ventas con costeo') . ' · antes de IVA' : 'Costea tus cotizaciones para verla' }}</div>
    </a>
    <a href="{{ $q(['seccion' => 'rentabilidad']) }}" class="panel kpi text-reset text-decoration-none">
        <div class="k"><i class="bi bi-percent"></i> Margen</div>
        <div class="v num {{ $rent['margen'] !== null && $rent['margen'] < 15 ? 'margen-bajo' : '' }}">{{ $rent['margen'] === null ? '—' : $rent['margen'] . '%' }}</div>
        <div class="s num">De cada $100 vendidos{{ $rent['margen'] !== null ? ', te quedan $' . round($rent['margen']) : '' }}</div>
    </a>
    <a href="{{ $q(['seccion' => 'proveedores']) }}" class="panel kpi text-reset text-decoration-none {{ $prov && $prov['porPagar'] > 0 ? 'alerta' : '' }}">
        <div class="k"><i class="bi bi-truck"></i> Por pagar a proveedores</div>
        <div class="v num">{{ $d($prov['porPagar'] ?? 0) }}</div>
        <div class="s num">{{ ($prov['nPorPagar'] ?? 0) }} {{ ($prov['nPorPagar'] ?? 0) === 1 ? 'orden entregada o facturada' : 'órdenes entregadas o facturadas' }}</div>
    </a>
    <a href="{{ $q(['seccion' => 'proveedores']) }}" class="panel kpi text-reset text-decoration-none">
        <div class="k"><i class="bi bi-bag"></i> Compras</div>
        <div class="v num">{{ $d($prov['comprado'] ?? 0) }}</div>
        <div class="s num">{{ ($prov['n'] ?? 0) }} {{ ($prov['n'] ?? 0) === 1 ? 'orden de compra' : 'órdenes de compra' }}</div>
    </a>
</div>
@if($gas)
{{-- Lo que entra menos lo que sale en el periodo: cobrado − compras a proveedores − gastos sueltos --}}
@php $queda = $kpi['cobrado'] - ($prov['comprado'] ?? 0) - $gas['total']; @endphp
<section class="panel mb-4">
    <div class="panel-head"><div><h2>Lo que te queda</h2><span class="ayuda">Lo cobrado menos lo comprado a proveedores y los gastos del periodo · {{ $base }}</span></div>
        <a href="{{ route('admin.gastos', ['periodo' => $periodo === 'mes' ? null : $periodo]) }}" class="btn btn-borde btn-sm"><i class="bi bi-receipt me-1"></i> Ver gastos</a></div>
    <div class="flujo">
        <div><span class="k">Cobrado</span><b class="num">{{ $d($kpi['cobrado']) }}</b></div>
        <span class="op">−</span>
        <div><span class="k">Compras a proveedores</span><b class="num">{{ $d($prov['comprado'] ?? 0) }}</b></div>
        <span class="op">−</span>
        <div><span class="k">Gastos ({{ $gas['n'] }})</span><b class="num">{{ $d($gas['total']) }}</b></div>
        <span class="op">=</span>
        <div class="total {{ $queda < 0 ? 'neg' : '' }}"><span class="k">Te queda</span><b class="num">{{ $queda < 0 ? '−' : '' }}{{ $d(abs($queda)) }}</b></div>
    </div>
    @if($gas['porCategoria']->isNotEmpty())
        <div class="secundario small px-3 pb-3">En qué se fue más: {{ $gas['porCategoria']->take(3)->map(fn ($c) => $c['texto'] . ' ' . $d($c['monto']))->join(' · ') }}</div>
    @endif
</section>
@endif
