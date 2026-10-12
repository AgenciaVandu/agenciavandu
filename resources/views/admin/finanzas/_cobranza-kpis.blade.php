<div class="kpis">
    <div class="panel kpi destacado">
        <div class="k"><i class="bi bi-bank"></i> Cobrado</div>
        <div class="v num">{{ $d($kpi['cobrado']) }}</div>
        <div class="s num">{{ $kpi['cobros'] }} {{ $kpi['cobros'] === 1 ? 'pago recibido' : 'pagos recibidos' }}</div>
    </div>
    <div class="panel kpi {{ $kpi['porCobrar'] > 0 ? 'alerta' : '' }}">
        <div class="k"><i class="bi bi-hourglass-split"></i> Por cobrar</div>
        <div class="v num">{{ $d($kpi['porCobrar']) }}</div>
        <div class="s num">{{ $kpi['pendientes'] }} {{ $kpi['pendientes'] === 1 ? 'pago pendiente' : 'pagos pendientes' }} · hoy</div>
    </div>
    <div class="panel kpi">
        <div class="k"><i class="bi bi-trophy"></i> Ganado en el periodo</div>
        <div class="v num">{{ $d($kpi['ganado']) }}</div>
        <div class="s num">{{ $kpi['ganado'] > 0 ? round($kpi['cobrado'] / $kpi['ganado'] * 100) . '% ya cobrado' : 'Sin ventas en el periodo' }}</div>
    </div>
    <div class="panel kpi">
        <div class="k"><i class="bi bi-truck"></i> Por pagar a proveedores</div>
        @php $pp = \App\Models\OrdenCompra::whereIn('estado', ['entregada', 'facturada'])->with('conceptos')->get()->sum(fn ($o) => $conIva ? $o->total : $o->subtotal); @endphp
        <div class="v num">{{ $d($pp) }}</div>
        <div class="s num"><a href="{{ $q(['seccion' => 'proveedores']) }}" class="text-reset">Ver órdenes</a></div>
    </div>
</div>
