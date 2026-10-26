@php
    $font = fn ($w) => 'file://' . resource_path("fonts/Geist-$w.ttf");
    $m = fn ($n) => '$' . number_format((float) $n, 2);
    $e = $e ?? config('vandu.emisor');
    $f = fn ($d) => $d ? $d->locale('es')->isoFormat('D [de] MMMM [de] YYYY') : '';
    $l = $p->licencia;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Recibo {{ $p->folio }}</title>
<style>
    @font-face { font-family: 'Geist'; font-weight: normal; src: url('{{ $font('Regular') }}') format('truetype'); }
    @font-face { font-family: 'Geist'; font-weight: bold;   src: url('{{ $font('Bold') }}') format('truetype'); }
    @page { size: A4; margin: 40pt 54pt 50pt 54pt; }
    body { font-family: 'Geist', sans-serif; font-size: 10.5pt; color: #111; margin: 0; }
    .hdr { background: #1A103D; color: #fff; border-radius: 8pt; padding: 18pt 22pt; }
    .hdr table { width: 100%; border-collapse: collapse; }
    .hdr td { vertical-align: middle; color: #fff; }
    .marca { font-size: 22pt; font-weight: bold; letter-spacing: -0.5pt; }
    .lema { font-size: 8.5pt; color: #C9C3E6; margin-top: 2pt; }
    .contacto { text-align: right; font-size: 8.5pt; line-height: 1.35; color: #E6E3F5; }
    h1 { font-size: 19pt; margin: 26pt 0 4pt; }
    .gris { color: #555; }
    table.datos { width: 100%; border-collapse: collapse; margin-top: 18pt; }
    .datos td { padding: 7pt 0; border-bottom: 0.6pt solid #E5E5E5; vertical-align: top; }
    .datos td.k { width: 150pt; color: #555; }
    .total { margin-top: 18pt; background: #F3F2F8; border-radius: 6pt; padding: 14pt 16pt; }
    .total table { width: 100%; border-collapse: collapse; }
    .total td { padding: 3pt 0; }
    .total .r { text-align: right; }
    .total .grande td { font-size: 14pt; font-weight: bold; padding-top: 7pt; }
    .nota { margin-top: 26pt; font-size: 8.8pt; color: #666; line-height: 1.35; }
</style>
</head>
<body>
<div class="hdr">
    <table><tr>
        <td><div class="marca">Nerby</div><div class="lema">Tu negocio, todo en orden.</div></td>
        <td class="contacto">{{ $e['nombre'] }}<br>{{ $e['telefono'] }}<br>{{ $e['email'] }}<br>{{ $e['sitio'] }}</td>
    </tr></table>
</div>

<h1>Recibo de pago</h1>
<div class="gris">Folio <b style="color:#111">{{ $p->folio }}</b> · {{ $f($p->fecha) }}</div>

<table class="datos">
    <tr><td class="k">Recibimos de</td><td><b>{{ $p->cuenta?->nombre }}</b></td></tr>
    <tr><td class="k">Concepto</td><td>{{ $p->concepto_texto }}{{ $l?->plan ? ' · plan ' . $l->plan->nombre : '' }}</td></tr>
    @if($p->cubre_desde && $p->cubre_hasta)
        <tr><td class="k">Periodo</td><td>Del {{ $f($p->cubre_desde) }} al {{ $f($p->cubre_hasta) }}</td></tr>
    @endif
    <tr><td class="k">Forma de pago</td><td>{{ \App\Models\LicenciaPago::FORMAS[$p->forma] ?? 'No indicada' }}{{ $p->referencia ? ' · ref. ' . $p->referencia : '' }}</td></tr>
</table>

<div class="total">
    <table>
        @if($p->iva > 0)
            <tr><td>Subtotal</td><td class="r">{{ $m($p->monto - $p->iva) }}</td></tr>
            <tr><td>IVA 16%</td><td class="r">{{ $m($p->iva) }}</td></tr>
        @endif
        <tr class="grande"><td>Total recibido</td><td class="r">{{ $m($p->monto) }}</td></tr>
    </table>
</div>

<p class="nota">Este recibo confirma el pago. No es un comprobante fiscal (CFDI); si necesitas factura, pídela con tus datos fiscales.</p>
</body>
</html>
