{{-- Pin en el mapa para el panel. Params: $id (prefijo), $lat, $lng (ids de los campos), $centro (opcional [lat, lng]) --}}
@once
@push('head')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>.nb-mapa { height: 260px; border-radius: 12px; border: 1px solid var(--line); z-index: 0; } .nb-mapa-estado { font-size: 12.5px; color: var(--muted); margin-top: 6px; }</style>
@endpush
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
@include('pedidos._mapa-js')
@endpush
@endonce
<div class="d-flex align-items-center justify-content-between gap-2 mb-2">
    <span class="form-label m-0">Ubicación <span class="secundario fw-normal">(toca el mapa o arrastra el pin)</span></span>
    <button type="button" class="btn btn-borde btn-sm" id="{{ $id }}-gps"><i class="bi bi-crosshair me-1"></i> Usar mi ubicación</button>
</div>
<div class="nb-mapa" id="{{ $id }}-mapa"></div>
<div class="nb-mapa-estado" id="{{ $id }}-estado"></div>
@push('scripts')
<script>
(function iniciar() {
    if (!window.L || !window.nerbyMapa) return setTimeout(iniciar, 120);
    const el = document.getElementById(@json($id . '-mapa'));
    if (!el) return;
    const ver = () => window.nerbyMapa({ el, campoLat: @json($lat), campoLng: @json($lng), centro: @json($centro ?? null), boton: @json($id . '-gps'), estado: @json($id . '-estado') });
    // Si está oculto (sección que se abre después), se inicia cuando se ve
    if (el.offsetParent === null) { const o = new IntersectionObserver((e) => { if (e[0].isIntersecting) { ver(); } }); o.observe(el); } else ver();
})();
</script>
@endpush
