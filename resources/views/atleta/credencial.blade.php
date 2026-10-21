@extends('atleta.layout')
@section('titulo', 'Mi credencial')
@section('contenido')
<h1 style="text-align: center">Tu credencial</h1>
<p class="sub" style="text-align: center">Muéstrala en la entrada para registrar tu asistencia.</p>
<div class="qr-grande" style="margin-top: 16px"><div id="qr" data-qr="{{ $a->qr }}" aria-label="Tu código QR"></div></div>
<p style="text-align: center; font-weight: 650; font-size: 19px; margin: 14px 0 2px">{{ $a->nombre }}</p>
<p class="sub" style="text-align: center; font-size: 14px">Puedes guardarla como captura de pantalla. Es personal: no la compartas.</p>
<div style="margin-top: 16px">@include('atleta._creditos')</div>
@endsection
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>new QRCode(document.getElementById('qr'), { text: document.getElementById('qr').dataset.qr, width: 600, height: 600, correctLevel: QRCode.CorrectLevel.M });</script>
@endpush
