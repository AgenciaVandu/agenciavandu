@php
    /** @var \App\Models\Presupuesto $p */
    $font = fn ($w) => 'file://' . resource_path("fonts/Geist-$w.ttf");
    $secciones = $p->consideraciones_limpias;
    $n = 0;
    // Diseño del negocio: hoja membretada, márgenes, papel, color y letra
    $dd = \App\Support\DisenoDocumento::de();
    [, $anchoMm, $altoMm] = \App\Support\DisenoDocumento::PAPELES[$dd['papel']];
    $pt = fn ($mm) => round($mm * 72 / 25.4, 2) . 'pt';
    $membrete = $dd['membrete'] ? \Illuminate\Support\Facades\Storage::disk('local')->path($dd['membrete']) : null;
    $margen = $membrete ? $pt($dd['sup']) . ' ' . $pt($dd['der']) . ' ' . $pt($dd['inf']) . ' ' . $pt($dd['izq']) : '37pt 60pt 50pt 51pt';
    $conEncabezado = ! $membrete || $dd['con_encabezado'];
    $fabrica = $dd['color'] === '#242424';
    $suave = $fabrica ? '#f3f3f3' : \App\Support\DisenoDocumento::tinte($dd['color'], .88);
    $tinta = \App\Support\DisenoDocumento::tintaSobre($dd['color']);
    $familia = \App\Support\DisenoDocumento::LETRAS[$dd['letra']]['pdf'];
    $pl = $dd['plantilla'];
    $oscuro = \App\Support\DisenoDocumento::encabezadoOscuro($dd);
    $logo = \App\Support\DisenoDocumento::logo($oscuro);
    $tintaHdr = in_array($pl, ['clasica', 'moderna'], true) ? $tinta : '#13161D';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>{{ $p->folio }} · {{ $p->cliente_empresa ?: $p->cliente_nombre }}</title>
<style>
    @font-face { font-family: 'Geist'; font-weight: normal; src: url('{{ $font('Regular') }}') format('truetype'); }
    @font-face { font-family: 'Geist'; font-weight: bold;   src: url('{{ $font('Bold') }}') format('truetype'); }

    /* Medidas tomadas del PDF original (A4, puntos).
       Nota: dompdf calcula el interlineado con las métricas de Geist; 0.91 ≈ 1.3 real. */
    @page { size: {{ $dd['papel'] === 'carta' ? 'letter' : 'A4' }}; margin: {{ $margen }}; }
    html { margin: {{ $margen }}; }
    body, div, p, h2, h3, ul, li, table { margin: 0; padding: 0; }
    body { font-family: '{{ $familia }}', sans-serif; font-size: 11pt; line-height: 0.91; color: #000; }
    b, .b { font-weight: bold; }

    /* Encabezado negro */
    .hdr { width: 100%; height: 80pt; background: {{ $dd['color'] }}; border-radius: 8pt; position: relative; }
    .hdr img { position: absolute; left: 34pt; top: 29pt; width: 75pt; }
    .hdr .contacto { position: absolute; right: 41pt; top: 14pt; text-align: right; color: {{ $tintaHdr }};
                     font-size: 8.8pt; line-height: 0.98; }
    .hdr .contacto .l { color: {{ $tintaHdr === '#ffffff' ? '#e6e6e6' : '#3f4450' }}; }

    .membrete { z-index: -1; position: {{ $dd['en_todas'] ? 'fixed' : 'absolute' }}; top: -{{ $pt($dd['sup']) }}; left: -{{ $pt($dd['izq']) }}; width: {{ $pt($anchoMm) }}; height: {{ $pt($altoMm) }}; }
    .membrete img { width: 100%; height: 100%; }

    /* Cliente / fecha */
    .datos { width: 100%; margin-top: {{ $conEncabezado ? '25pt' : '0' }}; border-collapse: collapse; }
    .datos td { vertical-align: top; padding: 0; }

    /* Conceptos */
    table.conceptos { width: 100%; border-collapse: collapse; margin-top: {{ $conEncabezado ? '53.8pt' : '28pt' }}; }
    .conceptos th { background: {{ $suave }}; height: 24pt; padding: 0 5pt; font-weight: bold; text-align: left; vertical-align: middle; }
    .conceptos th.c, .conceptos td.c { text-align: center; }
    .conceptos td { padding: 20pt 5pt 19.5pt; vertical-align: middle; }
    .conceptos td.desc { padding-right: 12pt; }
    .conceptos tr.tot td { background: {{ $suave }}; height: 24pt; padding: 0 5pt; }
    .conceptos tr.tot td.vacio { background: #fff; }
    .conceptos tr.tot + tr.tot td { border-top: 0.75pt solid #fff; }
    .conceptos tr.tot.final td { font-weight: bold;@unless($fabrica) background: {{ $dd['color'] }}; color: {{ $tinta }};@endunless }
    .conceptos tr.tot.final td.vacio { background: #fff; }

    /* Observaciones */
    .obs { width: 100%; margin-top: 18pt; page-break-inside: avoid; }
    .obs h2 { margin-top: 0; }
    .obs p { font-size: 10pt; line-height: 0.91; margin-top: 6pt; }
    .obs h2 + p { margin-top: 9pt; }

    /* Consideraciones */
    h2 { font-size: 13pt; line-height: 0.91; font-weight: bold; margin-top: 10.9pt; }
    .sec { margin-left: 21pt; page-break-inside: avoid; }
    .sec h3 { font-size: 11pt; font-weight: bold; margin-top: 12pt; }
    .sec ul { margin: 12pt 0 0 18pt; list-style: none; }
    .sec li { padding-left: 18pt; position: relative; }
    .sec li .v { position: absolute; left: 1pt; top: 2.6pt; font-family: 'DejaVu Sans'; font-size: 6.6pt; }
    .sec li + li { margin-top: 4pt; }
    .sec.pago h3 { font-size: 10pt; line-height: 0.91; }
    .chico { font-size: 10pt; line-height: 0.91; }

    table.banco { width: 96%; border-collapse: collapse; margin-top: 18pt; font-size: 10pt; line-height: 0.91; font-weight: bold; }
    .banco td { border: 0.9pt solid #000; height: 23.4pt; padding: 0 5pt; vertical-align: middle; width: 50%; }
    .banco td.t { text-align: center; }

    .nota { margin-top: 17.7pt; }
    .nota + .chico { margin-top: 3pt; }

    .pie { position: fixed; bottom: -13pt; right: 4pt; font-size: 10pt; }
    .pie .num:before { content: counter(page); }

    /* Plantilla elegida (va al final para ganarle a lo de arriba) */
@if($pl === 'moderna')
    .hdr { border-radius: 0; }
    .conceptos th { background: {{ $dd['color'] }}; color: {{ $tinta }}; }
@elseif($pl === 'minimal')
    .hdr { background: none; height: 64pt; border-bottom: 2pt solid {{ $dd['color'] }}; border-radius: 0; }
    .hdr img { left: 0; top: 12pt; }
    .hdr .contacto { right: 0; top: 4pt; }
    .conceptos th { background: none; border-bottom: 1.2pt solid {{ $dd['color'] }}; }
    .conceptos tbody tr td { border-bottom: 0.5pt solid #e3e4e8; }
    .conceptos tr.tot td { border-bottom: 0; }
@elseif($pl === 'elegante')
    .hdr { background: none; height: auto; border-radius: 0; text-align: center; padding-bottom: 12pt; border-bottom: 3pt double {{ $dd['color'] }}; }
    .hdr img { position: static; width: 90pt; }
    .hdr .contacto { position: static; text-align: center; margin-top: 8pt; font-size: 8.5pt; }
    .hdr .contacto div { display: inline; }
    .hdr .contacto div + div:before { content: '  ·  '; color: #9aa0ad; }
    .conceptos th { background: none; border-top: 0.75pt solid {{ $dd['color'] }}; border-bottom: 0.75pt solid {{ $dd['color'] }}; font-size: 9pt; text-transform: uppercase; letter-spacing: 1pt; }
    h2 { color: {{ $dd['color'] }}; text-transform: uppercase; letter-spacing: 1pt; font-size: 11pt; }
@endif
</style>
</head>
<body>

@if($membrete)<div class="membrete"><img src="file://{{ $membrete }}" alt=""></div>@endif
<div class="pie"><span class="num"></span></div>

@if($conEncabezado)
<div class="hdr">
    @if($logo)<img src="{{ $logo }}" alt="{{ config('vandu.marca.nombre') }}">@else<span style="color:{{ $tintaHdr }}; font-size:20px; font-weight:bold; position:absolute; left:{{ $pl === 'minimal' ? '0' : '34pt' }}; top:28pt">{{ config('vandu.marca.nombre') }}</span>@endif
    <div class="contacto">
        @if($p->emisor_nombre)<div class="b">{{ $p->emisor_nombre }}</div>@endif
        @if($p->emisor_telefono)<div class="b">{{ $p->emisor_telefono }}</div>@endif
        @if($p->emisor_sitio)<div class="l">{{ $p->emisor_sitio }}</div>@endif
        @if($p->emisor_email)<div class="l">{{ $p->emisor_email }}</div>@endif
    </div>
</div>
@endif

<table class="datos">
    <tr>
        <td><b>{{ $p->cliente_nombre }}</b><br>{{ $p->cliente_empresa }}</td>
        <td style="text-align:right"><b>{{ $p->fecha_larga }}</b><br>{{ $p->titulo }}</td>
    </tr>
</table>

<table class="conceptos">
    <thead>
        <tr>
            <th style="width:50%">Concepto</th>
            <th class="c" style="width:19%">Cantidad</th>
            <th class="c">Costo</th>
        </tr>
    </thead>
    <tbody>
        @foreach($p->conceptos as $c)
            <tr>
                <td class="desc">@if($c->titulo)<b>{{ $c->titulo }}</b>@if(trim($c->descripcion))<br>@endif @endif{!! nl2br(e(trim($c->descripcion))) !!}</td>
                <td class="c">{{ $c->cantidad_texto }}</td>
                <td class="c">{{ $p->monto($c->importe) }}</td>
            </tr>
        @endforeach
        <tr class="tot">
            <td class="vacio"></td>
            <td class="c">Subtotal</td>
            <td class="c">{{ $p->monto($p->subtotal) }}</td>
        </tr>
        @if($p->modo_iva === 'desglosado')
            <tr class="tot">
                <td class="vacio"></td>
                <td class="c">IVA {{ rtrim(rtrim(number_format($p->iva_porcentaje, 2), '0'), '.') }}%</td>
                <td class="c">{{ $p->monto($p->iva) }}</td>
            </tr>
            <tr class="tot final">
                <td class="vacio"></td>
                <td class="c">Total</td>
                <td class="c">{{ $p->monto($p->total) }}</td>
            </tr>
        @endif
    </tbody>
</table>

@if($obs = $p->observaciones_lineas)
    <div class="obs">
        <h2>Observaciones</h2>
        @foreach($obs as $linea)<p>{{ $linea }}</p>@endforeach
    </div>
@endif

@if(count($secciones) || $p->mostrar_pago)
    <h2>Consideraciones</h2>
@endif

@foreach($secciones as $s)
    <div class="sec">
        <h3>{{ ++$n }}. {{ $s['titulo'] }}</h3>
        @if(count($s['items']))
            <ul>
                @foreach($s['items'] as $item)<li><span class="v">●</span>{{ $item }}</li>@endforeach
            </ul>
        @endif
    </div>
@endforeach

@if($p->mostrar_pago)
    <div class="sec pago">
        <h3>{{ ++$n }}. Confirmación de servicios</h3>
        @if($p->pago_intro)<p class="chico" style="margin-top:19pt">{{ $p->pago_intro }}</p>@endif

        <table class="banco">
            <tr><td class="t" colspan="2">Datos Bancarios</td></tr>
            @if($p->clabe)<tr><td>CLABE</td><td>{{ $p->clabe }}</td></tr>@endif
            @if($p->banco)<tr><td>Banco</td><td>{{ $p->banco }}</td></tr>@endif
            @if($p->beneficiario)<tr><td>Beneficiario</td><td>{{ $p->beneficiario }}</td></tr>@endif
        </table>

        @if($p->nota_comprobante)<p class="chico b nota">{{ $p->nota_comprobante }}</p>@endif
        @if($p->nota_factura)<p class="chico">{{ $p->nota_factura }}</p>@endif
    </div>
@endif

</body>
</html>
