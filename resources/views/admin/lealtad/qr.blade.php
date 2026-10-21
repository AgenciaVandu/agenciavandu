<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Cartel del programa · {{ $cuenta->nombre }}</title>
    <link rel="preload" href="{{ route('vandu.fuente') }}" as="font" type="font/woff2" crossorigin>
    <style>
        @font-face { font-family: 'Geist'; src: url('{{ route('vandu.fuente') }}') format('woff2'); font-weight: 100 900; font-display: swap; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Geist', system-ui, sans-serif; color: #1A103D; background: #EDEAF5; }
        .barra { display: flex; gap: 10px; justify-content: center; padding: 16px; }
        .barra a, .barra button { font: inherit; font-size: 14px; padding: 9px 16px; border-radius: 10px; border: 1px solid #D6D1E2; background: #fff; color: #1A103D; text-decoration: none; cursor: pointer; }
        .barra button { background: #6B3BFF; color: #fff; border-color: #6B3BFF; font-weight: 600; }
        .hoja { width: 148mm; min-height: 210mm; margin: 0 auto 32px; background: #fff; border-radius: 6mm; padding: 14mm 12mm 10mm; display: flex; flex-direction: column; align-items: center; text-align: center; box-shadow: 0 10px 40px rgba(26,16,61,.12); }
        .logo { max-height: 22mm; max-width: 70mm; object-fit: contain; }
        .nombre { font-size: 26px; font-weight: 800; letter-spacing: -.02em; }
        h1 { font-size: 34px; line-height: 1.05; letter-spacing: -.03em; margin: 8mm 0 3mm; font-weight: 800; }
        .sub { font-size: 16px; color: #4A4460; margin: 0 0 6mm; max-width: 110mm; }
        .qr { padding: 5mm; border-radius: 6mm; border: 2px solid #1A103D; display: inline-block; }
        .qr canvas, .qr img { width: 62mm !important; height: 62mm !important; display: block; }
        .premios { margin-top: 7mm; display: grid; gap: 2.5mm; width: 100%; max-width: 110mm; text-align: left; }
        .premios div { display: flex; align-items: center; gap: 3mm; font-size: 15px; }
        .premios b { width: 10mm; height: 10mm; border-radius: 3mm; background: #EFE9FF; color: #6B3BFF; display: grid; place-items: center; font-size: 14px; flex: none; }
        .pie { margin-top: auto; padding-top: 8mm; font-size: 11.5px; color: #6D6884; display: flex; align-items: center; gap: 2mm; }
        .pie img { height: 4.2mm; }
        @media print { body { background: #fff; } .barra { display: none; } .hoja { box-shadow: none; margin: 0; border-radius: 0; } @page { size: A5; margin: 0; } }
    </style>
</head>
<body>
<div class="barra"><a href="{{ route('admin.resumen') }}">← Volver al panel</a><button onclick="window.print()">Imprimir</button><a href="{{ $url }}" target="_blank" rel="noopener">Abrir la página</a></div>
<div class="hoja">
    @if($cuenta->logo(false))<img class="logo" src="{{ $cuenta->logo(false) }}" alt="{{ $cuenta->nombre }}">@else<div class="nombre">{{ $cuenta->nombre }}</div>@endif
    <h1>{{ $campana ? $campana->nombre : 'Únete a nuestro programa' }}</h1>
    <p class="sub">{{ $campana?->descripcion ?: 'Escanea, únete gratis y suma visitas para ganar premios.' }}@if($campana) Del {{ $campana->vigencia_texto }}.@endif</p>
    <div class="qr"><div id="qr" data-url="{{ $url }}"></div></div>
    @if($campana)
        <div class="premios">@foreach($campana->premios as $p)<div><b>{{ $p->visitas }}</b><span>{{ $p->nombre }}</span></div>@endforeach</div>
    @endif
    <div class="pie">Programa de lealtad con <img src="{{ route('plataforma.img', 'logo-palabra-oscuro.svg') }}" alt="{{ config('vandu.plataforma.nombre') }}"></div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>new QRCode(document.getElementById('qr'), { text: document.getElementById('qr').dataset.url, width: 600, height: 600, colorDark: '#1A103D', correctLevel: QRCode.CorrectLevel.M });</script>
</body>
</html>
