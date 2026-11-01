{{-- Pestañas de la sección Facturas --}}
<nav class="segmento mb-3" aria-label="Facturación">
    <a href="{{ route('admin.facturas') }}" class="{{ request()->routeIs('admin.facturas') ? 'activo' : '' }}"><i class="bi bi-inbox"></i> Bandeja</a>
    <a href="{{ route('admin.facturas.global') }}" class="{{ request()->routeIs('admin.facturas.global*') ? 'activo' : '' }}"><i class="bi bi-people"></i> Factura global</a>
    <a href="{{ route('admin.facturas.reportes') }}" class="{{ request()->routeIs('admin.facturas.reportes*') ? 'activo' : '' }}"><i class="bi bi-bar-chart"></i> Reportes</a>
</nav>
