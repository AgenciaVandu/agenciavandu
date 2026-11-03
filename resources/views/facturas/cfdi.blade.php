@php
    /** @var array $d  datos del XML (Representacion::leer)  @var array $marca */
    use App\Support\Facturas\Representacion as R;
    $font = fn ($w) => 'file://' . resource_path("fonts/Geist-$w.ttf");
    $acento = $marca['color'];
    $m = fn ($n) => '$' . number_format((float) $n, 2);
    $regimenes = config('vandu.sat.regimenes', []);
    $usos = config('vandu.sat.usos_cfdi', []);
    // "Régimen 626 · Simplificado de Confianza" (sin repetir la palabra)
    $regimenes = array_map(fn ($t) => preg_replace('/^Régimen\s+(de\s+)?/u', '', $t), $regimenes);
    $cat = fn (array $c, ?string $k) => $k ? $k . (isset($c[$k]) ? ' · ' . $c[$k] : '') : '—';
    $fecha = fn (?string $f) => $f ? \Illuminate\Support\Carbon::parse($f)->locale('es')->isoFormat('D [de] MMMM [de] YYYY, HH:mm') : '—';
    $tasa = fn ($t) => $t !== null && $t !== '' ? rtrim(rtrim(number_format((float) $t * 100, 4, '.', ''), '0'), '.') . '%' : '';
    // Los sellos van en renglones fijos (dompdf no corta cadenas largas sin espacios)
    $cortar = fn (?string $s, int $n = 172) => $s ? implode('<br>', array_map('e', str_split($s, $n))) : '—';
    $folio = trim(($d['serie'] ?? '') . '-' . ($d['folio'] ?? ''), '-') ?: '—';
    $esPago = $d['tipo'] === 'P';
    $borrador = ! empty($d['borrador']);
    $qr = $borrador ? null : \App\Support\Facturas\Qr::png($d['qr_url'], 6, 2);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>{{ $d['titulo'] }} {{ $folio }}</title>
<style>
    @font-face { font-family: 'Geist'; font-weight: normal; src: url('{{ $font('Regular') }}') format('truetype'); }
    @font-face { font-family: 'Geist'; font-weight: bold; src: url('{{ $font('SemiBold') }}') format('truetype'); }
    @page { size: letter; margin: 0; }
    body { margin: 0; font-family: 'Geist', sans-serif; font-size: 8.4pt; color: #1F2430; line-height: 1.05; }
    b { font-weight: bold; }
    .hoja { padding: 26pt 42pt 46pt 42pt; }
    .franja { height: 5pt; background: {{ $acento }}; }
    table { border-collapse: collapse; width: 100%; }
    td, th { vertical-align: top; padding: 0; }
    .etq { font-size: 6.6pt; letter-spacing: .9pt; text-transform: uppercase; color: #7A8090; }
    .gris { color: #5E6472; }
    .suave { color: #8A90A0; }
    .num { text-align: right; white-space: nowrap; }

    /* Encabezado */
    .enc td { vertical-align: middle; }
    .logo { max-height: 40pt; max-width: 180pt; }
    .marca-nombre { font-size: 15pt; font-weight: bold; color: #13161D; letter-spacing: -.2pt; }
    .titulo { font-size: 8pt; letter-spacing: 1.6pt; text-transform: uppercase; color: {{ $acento }}; font-weight: bold; }
    .folio { font-size: 24pt; font-weight: bold; color: #13161D; letter-spacing: -.4pt; margin-top: 4pt; }
    .fecha { color: #5E6472; margin-top: 5pt; }

    /* Bloques de datos */
    .sep { height: 1pt; background: #E6E8EC; margin: 12pt 0 11pt; }
    .bloque td.col { width: 50%; }
    .cliente { font-size: 9.6pt; font-weight: bold; color: #13161D; margin: 5pt 0 3pt; }
    .lineas div { margin-top: 2.4pt; }
    .kv td { padding: 2.1pt 0; border-bottom: .6pt solid #EEF0F3; }
    .kv td.k { color: #7A8090; width: 32%; }
    .kv tr:last-child td { border-bottom: 0; }

    /* Conceptos */
    .conceptos { margin-top: 16pt; }
    .conceptos th { font-size: 6.6pt; letter-spacing: .8pt; text-transform: uppercase; color: #7A8090; font-weight: normal; text-align: left;
        padding: 0 6pt 7pt 0; border-bottom: 1.2pt solid {{ $acento }}; }
    .conceptos td { padding: 6pt 6pt 6pt 0; border-bottom: .6pt solid #E6E8EC; }
    .conceptos th.num, .conceptos td.num { padding-right: 0; padding-left: 6pt; }
    .desc { font-size: 9pt; color: #13161D; }
    .detalle { font-size: 7pt; color: #8A90A0; margin-top: 3pt; line-height: 1.2; }

    /* Totales */
    .pie-tabla { margin-top: 10pt; }
    .letra { font-size: 7.6pt; color: #3D4352; line-height: 1.3; margin-top: 4pt; }
    .nota { margin-top: 9pt; padding: 8pt 11pt; background: #F6F7F9; color: #3D4352; line-height: 1.3; }
    .totales td { padding: 3.4pt 0; }
    .totales .k { color: #5E6472; }
    .totales .total td { padding-top: 9pt; border-top: 1.2pt solid #13161D; font-size: 12pt; font-weight: bold; color: #13161D; }

    /* Timbre fiscal */
    .timbre { margin-top: 12pt; border-top: .6pt solid #E6E8EC; padding-top: 9pt; }
    .qr { width: 84pt; height: 84pt; }
    .fiscal td { padding: 1.2pt 0; }
    .fiscal .k { color: #7A8090; width: 33%; }
    .uuid { font-size: 9pt; font-weight: bold; color: #13161D; letter-spacing: .3pt; }
    .sellos { margin-top: 8pt; }
    .sellos .etq { margin: 4pt 0 1pt; }
    .sello { font-family: 'DejaVu Sans Mono', monospace; font-size: 4.9pt; color: #5E6472; line-height: 1.12; }

    .marca-agua { position: fixed; top: 300pt; left: 0; right: 0; text-align: center; font-size: 58pt; font-weight: bold; color: rgba(180, 35, 24, .09); letter-spacing: 4pt; transform: rotate(-24deg); }
    .aviso-previa { margin-top: 14pt; padding: 12pt 14pt; border: 1pt dashed #E5A39C; background: #FDF3F2; color: #8A2A20; line-height: 1.35; }
    .pie { position: fixed; left: 42pt; right: 42pt; bottom: 26pt; font-size: 6.8pt; color: #8A90A0; border-top: .6pt solid #E6E8EC; padding-top: 7pt; }
    .avoid { page-break-inside: avoid; }
</style>
</head>
<body>
<div class="franja"></div>
@if($borrador)<div class="marca-agua">VISTA PREVIA</div>@endif
<div class="pie">
    <table><tr>
        <td>{{ $borrador ? 'Vista previa · Este documento no tiene validez fiscal hasta que se timbre' : 'Este documento es una representación impresa de un CFDI ' . ($d['version'] ?? '4.0') }}</td>
        <td class="num">{{ collect([$marca['sitio'], $marca['email'], $marca['telefono']])->filter()->implode('  ·  ') }}</td>
    </tr></table>
</div>

<div class="hoja">
    {{-- Encabezado: marca y folio --}}
    <table class="enc"><tr>
        <td style="width: 60%">
            @if($marca['logo'])<img class="logo" src="{{ $marca['logo'] }}" alt="{{ $marca['nombre'] }}">@else<div class="marca-nombre">{{ $marca['nombre'] ?: $d['emisor']['nombre'] }}</div>@endif
        </td>
        <td style="width: 40%; text-align: right">
            <div class="titulo">{{ $d['titulo'] }}</div>
            <div class="folio">{{ $folio }}</div>
            @if($borrador)<div class="fecha" style="color:#B42318">Folio provisional</div>@endif
            <div class="fecha">{{ $fecha($d['fecha']) }}</div>
        </td>
    </tr></table>

    <div class="sep"></div>

    {{-- Emisor, cliente y datos del comprobante --}}
    <table class="bloque"><tr>
        <td style="width: 33%; padding-right: 16pt">
            <div class="etq">Emisor</div>
            <div class="cliente">{{ $d['emisor']['nombre'] }}</div>
            <div class="lineas gris">
                <div>RFC <b style="color:#13161D">{{ $d['emisor']['rfc'] }}</b></div>
                <div>Régimen {{ $cat($regimenes, $d['emisor']['regimen']) }}</div>
                @if($marca['direccion'])<div>{{ $marca['direccion'] }}</div>@endif
                <div>Lugar de expedición: C.P. {{ $d['lugar'] }}</div>
            </div>
        </td>
        <td style="width: 31%; padding-right: 16pt">
            <div class="etq">{{ ['E' => 'Nota para', 'P' => 'Cliente'][$d['tipo']] ?? 'Facturado a' }}</div>
            <div class="cliente">{{ $d['receptor']['nombre'] }}</div>
            <div class="lineas gris">
                <div>RFC <b style="color:#13161D">{{ $d['receptor']['rfc'] }}</b></div>
                <div>Régimen {{ $cat($regimenes, $d['receptor']['regimen']) }}</div>
                <div>Domicilio fiscal: C.P. {{ $d['receptor']['cp'] }}</div>
                <div>Uso del CFDI: {{ $cat($usos, $d['receptor']['uso']) }}</div>
            </div>
        </td>
        <td style="width: 36%">
            <div class="etq" style="margin-bottom: 4pt">Comprobante</div>
            <table class="kv">
                <tr><td class="k">Tipo</td><td>{{ $cat(R::TIPOS, $d['tipo']) }}</td></tr>
                @unless($esPago)
                    <tr><td class="k">Método</td><td>{{ $cat(R::METODOS, $d['metodo']) }}</td></tr>
                    <tr><td class="k">Forma</td><td>{{ $cat(R::FORMAS, $d['forma']) }}</td></tr>
                @endunless
                <tr><td class="k">Moneda</td><td>{{ $d['moneda'] === 'XXX' ? 'XXX · pagos abajo' : $d['moneda'] }}{{ $d['tipo_cambio'] && $d['tipo_cambio'] != 1 ? ' · T.C. ' . $d['tipo_cambio'] : '' }}</td></tr>
                <tr><td class="k">Exportación</td><td>{{ $cat(R::EXPORTACION, $d['exportacion']) }}</td></tr>
                @if($d['condiciones'])<tr><td class="k">Condiciones</td><td>{{ $d['condiciones'] }}</td></tr>@endif
                @if($d['global'])
                    <tr><td class="k">Global</td><td>{{ R::PERIODICIDAD[$d['global']['periodicidad']] ?? $d['global']['periodicidad'] }} · {{ R::MESES[$d['global']['meses']] ?? $d['global']['meses'] }} {{ $d['global']['anio'] }}</td></tr>
                @endif
            </table>
        </td>
    </tr></table>

    @if($d['relacionados'])
        <div class="nota" style="margin-top: 16pt">
            <span class="etq">CFDI relacionados</span><br>
            @foreach($d['relacionados'] as $r)
                <div style="margin-top: 3pt">{{ $cat(R::RELACIONES, $r['tipo']) }} — <b>{{ $r['uuid'] }}</b></div>
            @endforeach
        </div>
    @endif

    {{-- Conceptos --}}
    <table class="conceptos">
        <thead><tr>
            <th style="width: 7%">Cant.</th>
            <th>Descripción</th>
            <th style="width: 17%">Clave SAT · Unidad</th>
            <th class="num" style="width: 13%">P. unitario</th>
            <th class="num" style="width: 13%">Importe</th>
        </tr></thead>
        <tbody>
        @foreach($d['conceptos'] as $c)
            <tr class="avoid">
                <td>{{ rtrim(rtrim(number_format($c['cantidad'], 4, '.', ','), '0'), '.') }}</td>
                <td>
                    <div class="desc">{{ $c['descripcion'] }}</div>
                    <div class="detalle">
                        @if($c['sku'])No. {{ $c['sku'] }} · @endif{{ $cat(R::OBJETOS, $c['objeto']) }}
                        @foreach($c['impuestos'] as $i)
                            <br>{{ $i['tipo'] }} {{ $i['impuesto'] }} {{ $i['factor'] === 'Exento' ? 'exento' : $tasa($i['tasa']) }} · base {{ $m($i['base']) }}{{ $i['factor'] === 'Exento' ? '' : ' · ' . $m($i['importe']) }}
                        @endforeach
                        @if($c['descuento'] > 0)<br>Descuento {{ $m($c['descuento']) }}@endif
                    </div>
                </td>
                <td class="gris">{{ $c['clave'] }}<br>{{ $c['clave_unidad'] }}{{ $c['unidad'] ? ' · ' . $c['unidad'] : '' }}</td>
                <td class="num">{{ $m($c['unitario']) }}</td>
                <td class="num" style="color:#13161D">{{ $m($c['importe']) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    {{-- Pagos (complemento de pago 2.0) --}}
    @if($esPago)
        @foreach($d['pagos'] as $p)
            <div class="avoid" style="margin-top: 20pt">
                <div class="etq" style="margin-bottom: 6pt">Pago recibido</div>
                <table style="margin-bottom: 12pt"><tr>
                    <td style="width: 30%"><div class="etq">Fecha de pago</div><div style="margin-top: 3pt">{{ $fecha($p['fecha']) }}</div></td>
                    <td style="width: 26%"><div class="etq">Forma de pago</div><div style="margin-top: 3pt">{{ $cat(R::FORMAS, $p['forma']) }}</div></td>
                    <td style="width: 22%"><div class="etq">No. de operación</div><div style="margin-top: 3pt">{{ $p['operacion'] ?: '—' }}</div></td>
                    <td style="width: 22%; text-align: right"><div class="etq">Monto</div><div style="margin-top: 3pt; font-size: 11pt; font-weight: bold; color: #13161D">{{ $m($p['monto']) }} <span style="font-size: 7.5pt; color:#7A8090; font-weight: normal">{{ $p['moneda'] }}</span></div></td>
                </tr></table>
                <table class="conceptos" style="margin-top: 0">
                    <thead><tr>
                        <th>Documento relacionado</th><th style="width: 9%">Parc.</th>
                        <th class="num" style="width: 14%">Saldo anterior</th><th class="num" style="width: 13%">Pagado</th><th class="num" style="width: 14%">Saldo insoluto</th>
                    </tr></thead>
                    <tbody>
                    @foreach($p['documentos'] as $r)
                        <tr>
                            <td><div class="desc">{{ trim(($r['serie'] ?? '') . '-' . ($r['folio'] ?? ''), '-') ?: 'Factura' }}</div><div class="detalle">{{ $r['uuid'] }}</div></td>
                            <td>{{ $r['parcialidad'] }}</td>
                            <td class="num">{{ $m($r['anterior']) }}</td>
                            <td class="num" style="color:#13161D">{{ $m($r['pagado']) }}</td>
                            <td class="num">{{ $m($r['insoluto']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif

    {{-- Importe con letra y totales --}}
    <table class="pie-tabla avoid"><tr>
        <td style="width: 56%; padding-right: 28pt">
            <div class="etq">Importe con letra</div>
            <div class="letra">{{ $d['letra'] }}</div>
        </td>
        <td style="width: 44%">
            <table class="totales">
                @unless($esPago)
                    <tr><td class="k">Subtotal</td><td class="num">{{ $m($d['subtotal']) }}</td></tr>
                    @if($d['descuento'] > 0)<tr><td class="k">Descuento</td><td class="num">−{{ $m($d['descuento']) }}</td></tr>@endif
                    @foreach($d['traslados'] as $t)
                        <tr><td class="k">{{ $t['impuesto'] }} trasladado {{ $tasa($t['tasa']) }}</td><td class="num">{{ $m($t['importe']) }}</td></tr>
                    @endforeach
                    @foreach($d['retenciones'] as $t)
                        <tr><td class="k">{{ $t['impuesto'] }} retenido</td><td class="num">−{{ $m($t['importe']) }}</td></tr>
                    @endforeach
                @endunless
                <tr class="total"><td>{{ $esPago ? 'Total pagado' : 'Total' }}</td><td class="num">{{ $m($d['total']) }} <span style="font-size: 7.5pt; color:#7A8090; font-weight: normal">{{ $d['moneda'] === 'XXX' ? 'MXN' : $d['moneda'] }}</span></td></tr>
            </table>
        </td>
    </tr></table>

    @if($borrador)
        <div class="aviso-previa avoid">
            <b>Vista previa sin validez fiscal.</b> Al timbrarla, el SAT le asigna el folio fiscal (UUID), los sellos digitales, la cadena original y el código QR, que aparecerán en este espacio. El folio y la fecha pueden cambiar si se emite otra factura antes.
        </div>
    @else
    {{-- Timbre fiscal digital --}}
    <div class="timbre">
        <table class="avoid"><tr>
            <td style="width: 96pt"><img class="qr" src="{{ $qr }}" alt="QR de verificación del SAT"></td>
            <td>
                <div class="etq">Folio fiscal (UUID)</div>
                <div class="uuid" style="margin: 3pt 0 8pt">{{ $d['uuid'] }}</div>
                <table class="fiscal">
                    <tr><td class="k">Fecha y hora de certificación</td><td>{{ $fecha($d['timbrado']) }}</td></tr>
                    <tr><td class="k">No. de certificado del emisor</td><td>{{ $d['cert_emisor'] }}</td></tr>
                    <tr><td class="k">No. de certificado del SAT</td><td>{{ $d['cert_sat'] }}</td></tr>
                    <tr><td class="k">RFC del proveedor de certificación</td><td>{{ $d['pac'] }}</td></tr>
                </table>
            </td>
        </tr></table>
        <div class="sellos">
            <div class="etq">Sello digital del CFDI</div>
            <div class="sello">{!! $cortar($d['sello_cfd']) !!}</div>
            <div class="etq">Sello digital del SAT</div>
            <div class="sello">{!! $cortar($d['sello_sat']) !!}</div>
            <div class="etq">Cadena original del complemento de certificación digital del SAT</div>
            <div class="sello">{!! $cortar($d['cadena']) !!}</div>
        </div>
    </div>
    @endif
</div>
</body>
</html>
