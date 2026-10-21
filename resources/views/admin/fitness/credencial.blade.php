<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex, nofollow">
    <title>Credencial · {{ $a->nombre }}</title>
    <link rel="preload" href="{{ route('vandu.fuente') }}" as="font" type="font/woff2" crossorigin>
    <style>
        @font-face { font-family: 'Geist'; src: url('{{ route('vandu.fuente') }}') format('woff2'); font-weight: 100 900; font-display: swap; }
        body { margin: 0; font-family: 'Geist', system-ui, sans-serif; background: #F4F5F7; color: #13161D; display: grid; place-items: center; min-height: 100vh; }
        .cred { width: 320px; background: #fff; border-radius: 20px; overflow: hidden; box-shadow: 0 12px 40px rgba(16,24,40,.15); }
        .top { background: #13161D; color: #fff; padding: 18px 20px; display: flex; align-items: center; gap: 12px; }
        .top img { max-height: 34px; max-width: 120px; }
        .top b { font-size: 16px; }
        .cuerpo { padding: 22px; text-align: center; }
        .foto { width: 84px; height: 84px; border-radius: 50%; object-fit: cover; margin: -64px auto 10px; border: 4px solid #fff; background: #E9EBEE; display: grid; place-items: center; font-weight: 650; font-size: 26px; }
        .nombre { font-size: 20px; font-weight: 650; }
        .sub { color: #5d6270; font-size: 13px; margin-top: 2px; }
        #qr { display: grid; place-items: center; margin: 18px auto 8px; }
        #qr canvas, #qr img { width: 220px !important; height: 220px !important; }
        .acciones { text-align: center; margin-top: 18px; }
        .acciones button { font: inherit; border: 1px solid #D5D8DE; background: #fff; border-radius: 10px; padding: 9px 16px; cursor: pointer; }
        @media print { body { background: #fff; } .cred { box-shadow: none; border: 1px solid #ddd; } .acciones { display: none; } }
    </style>
</head>
<body>
<div>
    <div class="cred">
        <div class="top">@if($cuenta?->logo(true))<img src="{{ $cuenta->logo(true) }}" alt="">@else<b>{{ $cuenta?->nombre }}</b>@endif</div>
        <div class="cuerpo" style="padding-top: 70px">
            @if($a->foto_url)<img class="foto" src="{{ $a->foto_url }}" alt="">@else<div class="foto">{{ $a->iniciales }}</div>@endif
            <div class="nombre">{{ $a->nombre }}</div>
            <div class="sub">Muéstralo en la entrada de tu clase</div>
            <div id="qr" data-qr="{{ $a->qr }}"></div>
        </div>
    </div>
    <div class="acciones"><button onclick="window.print()">Imprimir</button></div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>new QRCode(document.getElementById('qr'), { text: document.getElementById('qr').dataset.qr, width: 440, height: 440, correctLevel: QRCode.CorrectLevel.M });</script>
</body>
</html>
