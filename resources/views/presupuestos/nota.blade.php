@php
    /** Nota de venta: para quien paga sin factura. Params: $p, $conIva, $formaPago, $texto */
    $font = fn ($w) => 'file://' . resource_path("fonts/Geist-$w.ttf");
    $cuentaPdf = \App\Support\Cuentas::esPrincipal() ? null : \App\Support\Cuentas::actual();
    $logo = $cuentaPdf ? $cuentaPdf->logo(true) : 'file://' . resource_path('pdf/logo-vandu-blanco.png');
    $m = fn ($n) => '$' . number_format((float) $n, 2);
    $subtotal = $p->subtotal;
    $iva = $conIva ? round($subtotal * $p->iva_porcentaje / 100, 2) : 0;
    $hoy = now(config('vandu.zona_horaria'));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Nota de venta {{ $p->nota_folio }}</title>
<style>
    @font-face { font-family: 'Geist'; font-weight: normal; src: url('{{ $font('Regular') }}') format('truetype'); }
    @font-face { font-family: 'Geist'; font-weight: bold;   src: url('{{ $font('Bold') }}') format('truetype'); }
    @page { size: A4; margin: 37pt 60pt 50pt 51pt; }
    html { margin: 37pt 60pt 50pt 51pt; }
    body, div, p, table { margin: 0; padding: 0; }
    body { font-family: 'Geist', sans-serif; font-size: 11pt; line-height: 0.91; color: #000; }
    b { font-weight: bold; }
    .hdr { width: 482pt; height: 80pt; background: #242424; border-radius: 8pt; position: relative; }
    .hdr img { position: absolute; left: 34pt; top: 29pt; width: 75pt; }
    .hdr .contacto { position: absolute; right: 41pt; top: 14pt; text-align: right; color: #fff; font-size: 8.8pt; line-height: 0.98; }
    .hdr .contacto .l { color: #e6e6e6; }
    .titulo { margin-top: 26pt; width: 100%; border-collapse: collapse; }
    .titulo td { vertical-align: top; padding: 0; }
    .titulo h1 { font-size: 20pt; margin: 0; }
    .folio { font-size: 13pt; font-weight: bold; }
    .gris { color: #555; }
    table.conceptos { width: 485pt; border-collapse: collapse; margin-top: 30pt; }
    .conceptos th { background: #f3f3f3; height: 24pt; padding: 0 5pt; font-weight: bold; text-align: left; vertical-align: middle; }
    .conceptos th.c, .conceptos td.c { text-align: center; }
    .conceptos th.r, .conceptos td.r { text-align: right; }
    .conceptos td { padding: 14pt 5pt; vertical-align: middle; border-bottom: 0.6pt solid #e5e5e5; }
    .conceptos tr.tot td { background: #f3f3f3; height: 22pt; padding: 0 5pt; border-bottom: 0.75pt solid #fff; }
    .conceptos tr.tot td.vacio { background: #fff; }
    .conceptos tr.final td { font-weight: bold; font-size: 12pt; }
    .info { margin-top: 24pt; font-size: 10pt; line-height: 1.1; }
    .aviso { margin-top: 28pt; padding: 10pt 12pt; border: 0.9pt solid #000; font-size: 9.5pt; line-height: 1.1; }
    .firma { margin-top: 60pt; width: 200pt; border-top: 0.75pt solid #000; padding-top: 5pt; font-size: 9.5pt; text-align: center; }
</style>
</head>
<body>
<div class="hdr">
    @if($logo)<img src="{{ $logo }}" alt="{{ config('vandu.marca.nombre') }}">@else<span style="color:#fff; font-size:20px; font-weight:bold">{{ config('vandu.marca.nombre') }}</span>@endif
    <div class="contacto">
        @if($p->emisor_nombre)<div><b>{{ $p->emisor_nombre }}</b></div>@endif
        @if($p->emisor_telefono)<div><b>{{ $p->emisor_telefono }}</b></div>@endif
        @if($p->emisor_sitio)<div class="l">{{ $p->emisor_sitio }}</div>@endif
        @if($p->emisor_email)<div class="l">{{ $p->emisor_email }}</div>@endif
    </div>
</div>

<table class="titulo">
    <tr>
        <td><h1>Nota de venta</h1><div class="gris" style="margin-top:6pt">Cotización {{ $p->folio }}</div></td>
        <td style="text-align:right"><div class="folio">{{ $p->nota_folio }}</div><div class="gris" style="margin-top:6pt">{{ \App\Models\Presupuesto::fechaLarga($hoy) }}</div></td>
    </tr>
</table>

<div style="margin-top:22pt"><span class="gris">Cliente</span><br><b>{{ $p->cliente_empresa ?: $p->cliente_nombre }}</b>@if($p->cliente_empresa)<br>{{ $p->cliente_nombre }}@endif</div>

<table class="conceptos">
    <thead><tr><th style="width:250pt">Concepto</th><th class="c" style="width:60pt">Cantidad</th><th class="r" style="width:80pt">Precio</th><th class="r">Importe</th></tr></thead>
    <tbody>
        @foreach($p->conceptos as $c)
            <tr>
                <td>@if($c->titulo)<b>{{ $c->titulo }}</b>@else{{ \Illuminate\Support\Str::limit(trim($c->descripcion), 140) }}@endif</td>
                <td class="c">{{ $c->cantidad_texto }}</td>
                <td class="r">{{ $m($c->precio) }}</td>
                <td class="r">{{ $m($c->importe) }}</td>
            </tr>
        @endforeach
        @if($conIva)
            <tr class="tot"><td class="vacio" colspan="2"></td><td class="r">Subtotal</td><td class="r">{{ $m($subtotal) }}</td></tr>
            <tr class="tot"><td class="vacio" colspan="2"></td><td class="r">IVA {{ rtrim(rtrim(number_format($p->iva_porcentaje, 2), '0'), '.') }}%</td><td class="r">{{ $m($iva) }}</td></tr>
        @endif
        <tr class="tot final"><td class="vacio" colspan="2"></td><td class="r">Total</td><td class="r">{{ $m($subtotal + $iva) }}</td></tr>
    </tbody>
</table>

<div class="info">
    @if($formaPago)<div><span class="gris">Forma de pago:</span> {{ config("vandu.metodos_pago.$formaPago", $formaPago) }}</div>@endif
    @if($texto)<div style="margin-top:6pt">{{ $texto }}</div>@endif
</div>

<div class="aviso">Este documento es una nota de venta y no es un comprobante fiscal (CFDI). Si más adelante necesitas factura, solicítala dentro del mismo mes en que realizaste el pago.</div>

<div class="firma">{{ $p->emisor_nombre ?: config('vandu.marca.nombre') }}</div>
</body>
</html>
