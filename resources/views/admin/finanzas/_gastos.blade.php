{{-- Gastos y compras: gastos sueltos (tickets y facturas) y lo que se le compra a proveedores. Montos con IVA (lo que salió) --}}
@if($gas)
    @php $maxCat = max(1, $gas['porCategoria']->max('monto') ?? 1); $sinFac = $gas['n'] - $gas['conFactura']; @endphp
    <div class="kpis">
        <div class="panel kpi destacado">
            <div class="k"><i class="bi bi-receipt"></i> Gastos</div>
            <div class="v num">{{ $d($gas['total']) }}</div>
            <div class="s num">{{ $gas['n'] }} {{ $gas['n'] === 1 ? 'gasto' : 'gastos' }} · con IVA</div>
        </div>
        <a href="#proveedores" class="panel kpi text-reset text-decoration-none">
            <div class="k"><i class="bi bi-bag"></i> Compras a proveedores</div>
            <div class="v num">{{ $d($prov['comprado']) }}</div>
            <div class="s num">{{ $prov['n'] }} {{ $prov['n'] === 1 ? 'orden de compra' : 'órdenes de compra' }}</div>
        </a>
        <a href="#proveedores" class="panel kpi text-reset text-decoration-none {{ $prov['porPagar'] > 0 ? 'alerta' : '' }}">
            <div class="k"><i class="bi bi-hourglass-split"></i> Debes a proveedores</div>
            <div class="v num">{{ $d($prov['porPagar']) }}</div>
            <div class="s num">{{ $prov['nPorPagar'] }} {{ $prov['nPorPagar'] === 1 ? 'orden entregada o facturada' : 'órdenes entregadas o facturadas' }}</div>
        </a>
        <a href="{{ route('admin.gastos', ['sin_factura' => 1, 'periodo' => in_array($periodo, ['mes_ant', 'anio', 'todo', 'trimestre'], true) ? $periodo : null]) }}" class="panel kpi text-reset text-decoration-none {{ $sinFac > 0 ? 'alerta' : '' }}">
            <div class="k"><i class="bi bi-receipt-cutoff"></i> Sin factura</div>
            <div class="v num">{{ $sinFac }}</div>
            <div class="s num">{{ $gas['conFactura'] }} de {{ $gas['n'] }} gastos con factura</div>
        </a>
    </div>

    <section class="panel mb-4">
        <div class="panel-head"><div><h2>En qué se fue</h2><span class="ayuda">Gastos por categoría · {{ $periodoTexto }}</span></div>
            <div class="d-flex gap-2"><a href="{{ route('admin.gastos') }}" class="btn btn-borde btn-sm">Ver todos</a><a href="{{ route('admin.gastos.create') }}" class="btn btn-primario btn-sm"><i class="bi bi-plus-lg me-1"></i> Registrar gasto</a></div></div>
        @if($gas['porCategoria']->isEmpty())
            <div class="panel-body secundario">Sin gastos en este periodo.</div>
        @else
            <div class="panel-body hbar">
                @foreach($gas['porCategoria'] as $c)
                    <span class="et"><i class="bi {{ $c['icono'] }} me-1"></i>{{ $c['texto'] }}</span>
                    <span class="pista"><span style="width: {{ $c['monto'] / $maxCat * 100 }}%"></span></span>
                    <span class="val num">{{ $d($c['monto']) }}<small>{{ $c['n'] }} {{ $c['n'] === 1 ? 'gasto' : 'gastos' }}</small></span>
                @endforeach
            </div>
        @endif
    </section>
@endif

<div id="proveedores">
    @if($gas)<h2 class="fs-6 fw-semibold mt-5 mb-3 secundario text-uppercase" style="letter-spacing:.05em">Compras a proveedores</h2>@endif
    @include('admin.finanzas._proveedores', ['sinKpis' => (bool) $gas])
</div>
