@extends('lealtad.layout')
@section('titulo', 'Mi QR')
@push('head')
<style>
    body { background: var(--tinta); color: #fff; }
    .cab .marca img { filter: brightness(0) invert(1); }
    .pase { background: #fff; color: var(--tinta); border-radius: 28px; padding: 22px; margin-top: 14px; text-align: center; }
    #qr { display: grid; place-items: center; }
    #qr canvas, #qr img { width: min(72vw, 300px) !important; height: min(72vw, 300px) !important; }
    .reloj { height: 6px; border-radius: 99px; background: #EEE7FF; overflow: hidden; margin: 16px 0 10px; }
    .reloj span { display: block; height: 100%; background: linear-gradient(90deg, var(--purpura), var(--cian)); transition: width 1s linear; }
    .corto { font-size: 26px; font-weight: 800; letter-spacing: .06em; }
</style>
@endpush
@section('contenido')
<h1 style="color: #fff; text-align: center">Tu QR</h1>
<p style="text-align: center; color: #C9C2E6; margin: 0">Muéstralo en caja para sumar tu visita o recoger un premio.</p>
<div class="pase">
    <div id="qr" aria-label="Tu código QR"></div>
    <div class="reloj" aria-hidden="true"><span id="barra" style="width: 100%"></span></div>
    <div style="font-size: 13px; color: var(--gris)">Cambia cada minuto · si no lo leen, dicta este código</div>
    <div class="corto num" id="corto">{{ $codigo['corto'] }}</div>
    <div style="margin-top: 8px; font-weight: 700">{{ $p->nombre }}</div>
</div>
<p style="text-align: center; color: #C9C2E6; font-size: 14px; margin-top: 16px">Súbele el brillo a tu pantalla. Una captura de pantalla deja de servir en un par de minutos.</p>
@endsection
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
    let d = @json($codigo);
    const caja = document.getElementById('qr'), barra = document.getElementById('barra'), corto = document.getElementById('corto');
    const qr = new QRCode(caja, { text: d.qr, width: 600, height: 600, colorDark: '#1A103D', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
    let fin = Date.now() + d.restante * 1000;
    async function nuevo() {
        try {
            const r = await fetch(@json(route('lealtad.qr.codigo')), { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
            if (r.ok) { d = await r.json(); qr.clear(); qr.makeCode(d.qr); corto.textContent = d.corto; fin = Date.now() + d.restante * 1000; }
        } catch (e) {}
    }
    setInterval(() => {
        const s = Math.max(0, (fin - Date.now()) / 1000);
        barra.style.width = (s / 60 * 100) + '%';
        if (s <= 0.5) { fin = Date.now() + 60000; nuevo(); }
    }, 1000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) nuevo(); });
})();
</script>
@endpush
