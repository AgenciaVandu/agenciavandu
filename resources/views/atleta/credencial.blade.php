@extends('atleta.layout')
@section('titulo', 'Mi credencial')
@section('contenido')
<h1 style="text-align: center">Tu pase de entrada</h1>
<p class="sub" style="text-align: center">Muéstralo en recepción para registrar tu asistencia.</p>
<div class="credencial">
    <div id="qr" data-qr="{{ $a->qr }}" role="img" aria-label="Tu código QR de entrada"></div>
    <div class="quien">
        <div><b>{{ $a->nombre }}</b><br><span>{{ \App\Support\Cuentas::actual()?->nombre }}</span></div>
        @if($membresia)<div style="text-align: right"><span class="cifra">{{ $membresia->ilimitada ? '∞' : $membresia->restantes }}</span><br><span>{{ $membresia->ilimitada ? 'ilimitada' : ($membresia->restantes === 1 ? 'clase' : 'clases') }}</span></div>@endif
    </div>
</div>
<p class="sub" style="text-align: center; font-size: 14px; margin-top: 18px">Súbele el brillo a tu pantalla. Es personal: no la compartas.</p>
@unless($membresia)<div style="margin-top: 14px">@include('atleta._creditos')</div>@endunless
@endsection
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>new QRCode(document.getElementById('qr'), { text: document.getElementById('qr').dataset.qr, width: 600, height: 600, colorDark: '#0D1124', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });</script>
@endpush
