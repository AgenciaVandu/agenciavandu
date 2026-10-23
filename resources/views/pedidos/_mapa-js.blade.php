{{-- Mapa con pin (Leaflet + OpenStreetMap): clic o arrastrar para mover, botón para usar la ubicación del celular.
     window.nerbyMapa({ el, lat, lng, campoLat, campoLng, centro: [lat, lng], boton, estado, editable }) --}}
<script>
window.nerbyMapa = window.nerbyMapa || function (cfg) {
    const el = typeof cfg.el === 'string' ? document.getElementById(cfg.el) : cfg.el;
    if (!el || !window.L) return;
    L.Icon.Default.imagePath = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/';
    if (el._mapa) { el._mapa.invalidateSize(); return el._mapa; }
    const fLat = document.getElementById(cfg.campoLat), fLng = document.getElementById(cfg.campoLng);
    const ini = (fLat && fLat.value && fLng.value) ? [parseFloat(fLat.value), parseFloat(fLng.value)] : null;
    const mapa = L.map(el, { scrollWheelZoom: false }).setView(ini || cfg.centro || [20.9674, -89.5926], ini ? 16 : 13);
    el._mapa = mapa;
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(mapa);
    let pin = null;
    const estado = cfg.estado ? document.getElementById(cfg.estado) : null;
    const poner = (lat, lng, centrar) => {
        if (pin) pin.setLatLng([lat, lng]); else {
            pin = L.marker([lat, lng], { draggable: cfg.editable !== false }).addTo(mapa);
            pin.on('dragend', () => { const p = pin.getLatLng(); guardar(p.lat, p.lng); });
        }
        if (centrar) mapa.setView([lat, lng], 17);
        guardar(lat, lng);
    };
    const guardar = (lat, lng) => {
        if (fLat) { fLat.value = lat.toFixed(7); fLat.dispatchEvent(new Event('input', { bubbles: true })); }
        if (fLng) { fLng.value = lng.toFixed(7); fLng.dispatchEvent(new Event('input', { bubbles: true })); }
    };
    if (ini) poner(ini[0], ini[1], false);
    if (cfg.editable !== false) mapa.on('click', (e) => poner(e.latlng.lat, e.latlng.lng, false));
    const boton = cfg.boton ? document.getElementById(cfg.boton) : null;
    if (boton) boton.addEventListener('click', () => {
        if (!navigator.geolocation) { if (estado) estado.textContent = 'Tu navegador no comparte la ubicación. Toca el mapa donde está la entrega.'; return; }
        if (estado) estado.textContent = 'Buscando tu ubicación…';
        navigator.geolocation.getCurrentPosition(
            (p) => { poner(p.coords.latitude, p.coords.longitude, true); if (estado) estado.textContent = 'Listo. Si el pin no quedó exacto, arrástralo a tu puerta.'; },
            () => { if (estado) estado.textContent = 'No pudimos obtener tu ubicación. Toca el mapa donde está la entrega.'; },
            { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 });
    });
    setTimeout(() => mapa.invalidateSize(), 200);
    return mapa;
};
</script>
