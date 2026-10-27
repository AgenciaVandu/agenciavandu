{{-- Utilidad e impuestos: estado de resultados, IVA, facturas, paquete del contador y margen de cada venta con costeo --}}
@include('admin.finanzas._contabilidad')

<div id="rentabilidad">
    <h2 class="fs-6 fw-semibold mt-5 mb-3 secundario text-uppercase" style="letter-spacing:.05em">Margen de tus cotizaciones con costeo</h2>
    @include('admin.finanzas._rentabilidad')
</div>
