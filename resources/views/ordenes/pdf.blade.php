@php
    /** @var \App\Models\OrdenCompra $o */
    $font = fn ($w) => 'file://' . resource_path("fonts/Geist-$w.ttf");
    $cuentaPdf = \App\Support\Cuentas::esPrincipal() ? null : \App\Support\Cuentas::actual();
    $logo = $cuentaPdf ? $cuentaPdf->logo(true) : 'file://' . resource_path('pdf/logo-vandu-blanco.png');
    $e = config('vandu.emisor');
    $p = $o->proveedor;
    $din = fn ($v) => \App\Models\OrdenCompra::dinero($v);
    $fecha = fn ($f) => $f ? $f->locale('es')->isoFormat('D [de] MMMM [de] YYYY') : null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>{{ $o->folio }} · {{ $p->empresa }}</title>
<style>
    @font-face { font-family: 'Geist'; font-weight: normal; src: url('{{ $font('Regular') }}') format('truetype'); }
    @font-face { font-family: 'Geist'; font-weight: bold;   src: url('{{ $font('Bold') }}') format('truetype'); }

    @page { size: A4; margin: 37pt 60pt 50pt 51pt; }
    html { margin: 37pt 60pt 50pt 51pt; }
    body, div, p, h2, table { margin: 0; padding: 0; }
    body { font-family: 'Geist', sans-serif; font-size: 10.5pt; line-height: 0.95; color: #000; }
    b, .b { font-weight: bold; }
    .gris { color: #555; }

    .hdr { width: 482pt; height: 80pt; background: #242424; border-radius: 8pt; position: relative; }
    .hdr img { position: absolute; left: 34pt; top: 29pt; width: 75pt; }
    .hdr .contacto { position: absolute; right: 41pt; top: 14pt; text-align: right; color: #fff; font-size: 8.8pt; line-height: 0.98; }
    .hdr .contacto .l { color: #e6e6e6; }

    .doc { margin-top: 22pt; width: 485pt; }
    .doc td { vertical-align: top; }
    .tit { font-size: 17pt; font-weight: bold; }
    .folio { font-size: 11pt; text-align: right; }

    .partes { width: 485pt; border-collapse: collapse; margin-top: 18pt; }
    .partes td { width: 50%; vertical-align: top; padding: 10pt 12pt; background: #f3f3f3; }
    .partes td + td { border-left: 6pt solid #fff; }
    .partes .k { font-size: 8.5pt; color: #555; margin-bottom: 4pt; }
    .partes p { margin-top: 2.5pt; }

    table.conceptos { width: 485pt; border-collapse: collapse; margin-top: 22pt; }
    .conceptos th { background: #f3f3f3; height: 22pt; padding: 0 6pt; font-weight: bold; text-align: left; vertical-align: middle; }
    .conceptos th.c, .conceptos td.c { text-align: center; }
    .conceptos th.r, .conceptos td.r { text-align: right; }
    .conceptos td { padding: 10pt 6pt; vertical-align: top; border-bottom: 0.6pt solid #e2e2e2; }
    .conceptos tr.tot td { border-bottom: 0; padding: 5pt 6pt; }
    .conceptos tr.tot.final td { font-weight: bold; background: #f3f3f3; }

    h2 { font-size: 12pt; font-weight: bold; margin-top: 20pt; }
    .ind { margin-top: 7pt; font-size: 10pt; line-height: 1.05; }

    .pie { position: fixed; bottom: -13pt; right: 4pt; font-size: 9pt; }
    .pie .num:before { content: counter(page); }
    .nota { margin-top: 22pt; font-size: 9pt; color: #555; }
</style>
</head>
<body>

<div class="pie">{{ $o->folio }} · <span class="num"></span></div>

<div class="hdr">
    @if($logo)<img src="{{ $logo }}" alt="{{ config('vandu.marca.nombre') }}">@else<span style="color:#fff; font-size:20px; font-weight:bold">{{ config('vandu.marca.nombre') }}</span>@endif
    <div class="contacto">
        @if($e['nombre'] ?? null)<div class="b">{{ $e['nombre'] }}</div>@endif
        @if($e['telefono'] ?? null)<div class="b">{{ $e['telefono'] }}</div>@endif
        @if($e['sitio'] ?? null)<div class="l">{{ $e['sitio'] }}</div>@endif
        @if($e['email'] ?? null)<div class="l">{{ $e['email'] }}</div>@endif
    </div>
</div>

<table class="doc">
    <tr>
        <td><div class="tit">Orden de compra</div><div class="gris" style="margin-top:4pt">{{ $o->titulo }}</div></td>
        <td class="folio"><b>{{ $o->folio }}</b><br><span class="gris">{{ $fecha($o->fecha) }}</span></td>
    </tr>
</table>

<table class="partes">
    <tr>
        <td>
            <div class="k">PROVEEDOR</div>
            <p class="b">{{ $p->empresa }}</p>
            @if($p->nombre)<p>{{ $p->nombre }}</p>@endif
            @if($p->telefono || $p->email)<p class="gris">{{ collect([$p->telefono, $p->email])->filter()->implode(' · ') }}</p>@endif
            @if($p->rfc)<p class="gris">RFC {{ $p->rfc }}</p>@endif
        </td>
        <td>
            <div class="k">ENTREGA</div>
            <p class="b">{{ $o->fecha_entrega ? $fecha($o->fecha_entrega) : 'Por acordar' }}</p>
            @if($o->lugar_entrega)<p>{{ $o->lugar_entrega }}</p>@endif
            @if($o->proyecto)<p class="gris">Proyecto: {{ $o->proyecto->nombre }}</p>@endif
        </td>
    </tr>
</table>

<table class="conceptos">
    <thead>
        <tr>
            <th style="width:235pt">Concepto</th>
            <th class="c" style="width:70pt">Cantidad</th>
            <th class="r" style="width:80pt">Precio unitario</th>
            <th class="r">Importe</th>
        </tr>
    </thead>
    <tbody>
        @foreach($o->conceptos as $c)
            <tr>
                <td>{!! nl2br(e(trim($c->descripcion))) !!}</td>
                <td class="c">{{ $c->cantidad_texto }}</td>
                <td class="r">{{ $din($c->precio) }}</td>
                <td class="r">{{ $din($c->importe) }}</td>
            </tr>
        @endforeach
        <tr class="tot"><td></td><td></td><td class="r">Subtotal</td><td class="r">{{ $din($o->subtotal) }}</td></tr>
        @if($o->con_iva)
            <tr class="tot"><td></td><td></td><td class="r">IVA {{ rtrim(rtrim(number_format($o->iva_porcentaje, 2), '0'), '.') }}%</td><td class="r">{{ $din($o->iva) }}</td></tr>
        @endif
        <tr class="tot final"><td style="background:#fff"></td><td style="background:#fff"></td><td class="r">Total</td><td class="r">{{ $din($o->total) }}</td></tr>
    </tbody>
</table>

@if(trim((string) $o->indicaciones) !== '')
    <h2>Indicaciones</h2>
    <div class="ind">{!! nl2br(e(trim($o->indicaciones))) !!}</div>
@endif

@php $adjPdf = $o->archivos()->where('autor', 'agencia')->whereIn('tipo', \App\Models\OrdenCompra::TIPOS_AGENCIA)->orderByRaw("tipo = 'produccion' desc")->orderBy('id')->get(); @endphp
@if($adjPdf->isNotEmpty())
    <h2>Archivos adjuntos</h2>
    <div class="ind">@foreach($adjPdf as $a)• {{ $a->nombre }} <span class="gris">({{ $a->tipo_texto }})</span><br>@endforeach</div>
    <p class="gris" style="margin-top:5pt; font-size:9pt">Descárgalos desde el enlace de esta orden.</p>
@endif

<p class="nota">Sube tu factura (PDF y XML) y tus archivos en el enlace que te compartimos con esta orden. Cita el folio {{ $o->folio }} en tu factura.</p>

</body>
</html>
