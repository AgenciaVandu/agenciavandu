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
