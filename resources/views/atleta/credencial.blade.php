@extends('atleta.layout')
@section('titulo', 'Mi credencial')
@section('contenido')
<h1 style="text-align: center">Tu pase de entrada</h1>
<p class="sub" style="text-align: center">Acércalo al lector del torniquete o muéstralo en recepción.</p>
<div class="credencial">
    {{-- El QR cambia cada minuto y se calcula aquí mismo: funciona aunque no haya señal en la entrada --}}
    <div id="qr" data-inicial="{{ $qr }}" data-id="{{ $a->id }}" data-s="{{ $secreto }}" data-url="{{ route('atleta.credencial.codigo') }}" role="img" aria-label="Tu código QR de entrada"></div>
    <div class="reloj" aria-hidden="true"><span id="reloj"></span></div>
    <div class="quien">
        <div><b>{{ $a->nombre }}</b><br><span>{{ \App\Support\Cuentas::actual()?->nombre }}</span></div>
        @if($membresia)<div style="text-align: right"><span class="cifra">{{ $membresia->ilimitada ? '∞' : $membresia->restantes }}</span><br><span>{{ $membresia->ilimitada ? 'ilimitada' : ($membresia->restantes === 1 ? 'clase' : 'clases') }}</span></div>@endif
    </div>
</div>
<div class="vive">Se renueva cada minuto. Una captura de pantalla no sirve.</div>
<p class="sub" style="text-align: center; font-size: 14px; margin-top: 10px">Súbele el brillo a tu pantalla. Para entrar necesitas una clase reservada a esta hora.</p>
@unless($membresia)<div style="margin-top: 14px">@include('atleta._creditos')</div>@endunless
@endsection
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(() => {
    const el = document.getElementById('qr'), reloj = document.getElementById('reloj');
    const qr = new QRCode(el, { text: el.dataset.inicial, width: 600, height: 600, colorDark: '#0D1124', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
    const enc = new TextEncoder();
    let llave = null, actual = el.dataset.inicial, desfase = 0;
    const hex = (b) => [...new Uint8Array(b)].map(x => x.toString(16).padStart(2, '0')).join('');
    async function codigo(v) {
        if (window.crypto?.subtle) {
            llave ??= await crypto.subtle.importKey('raw', enc.encode(el.dataset.s), { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
            return 'FIT2:' + el.dataset.id + ':' + v + ':' + hex(await crypto.subtle.sign('HMAC', llave, enc.encode(el.dataset.id + '|' + v))).slice(0, 12);
        }
        const r = await fetch(el.dataset.url, { headers: { Accept: 'application/json' } });
        return (await r.json()).qr;
    }
    async function tic() {
        const t = Date.now() / 1000 + desfase, v = Math.floor(t / 60);
        reloj.style.transform = 'scaleX(' + (1 - (t % 60) / 60) + ')';
        try {
            const c = await codigo(v);
            if (c !== actual) { actual = c; qr.clear(); qr.makeCode(c); }
        } catch (e) {}
    }
    // Si el reloj del celular está muy desfasado, se corrige con la hora del servidor
    desfase = {{ time() }} - Date.now() / 1000;
    if (Math.abs(desfase) < 20) desfase = 0;
    tic(); setInterval(tic, 1000);
})();
</script>
@endpush
