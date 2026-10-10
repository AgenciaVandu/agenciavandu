@php
    // Al enviar, el logo va incrustado en el correo; en la vista previa del panel, en base64
    $logo = isset($message) && ! $vistaPrevia ? $message->embed(resource_path('img/logo-vandu-correo.png')) : \App\Support\Correos::logoDataUri();
    $font = "-apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Helvetica, Arial, sans-serif";
    $emisor = config('vandu.emisor');
@endphp
<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light only">
<meta name="supported-color-schemes" content="light">
<title>{{ $asunto }}</title>
<style>
    body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table { border-collapse: collapse; }
    img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
    a { color: #13161D; }
    @media only screen and (max-width: 620px) {
        .contenedor { width: 100% !important; border-radius: 0 !important; }
        .px { padding-left: 24px !important; padding-right: 24px !important; }
        .titulo { font-size: 23px !important; line-height: 30px !important; }
        .boton a { display: block !important; }
        .ocultar-movil { display: none !important; }
    }
</style>
</head>
<body style="margin:0; padding:0; background-color:#EEF0F3;">
<div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:#EEF0F3;">{{ $preheader }}&#8202;&#847;&#8202;&#847;&#8202;&#847;&#8202;&#847;&#8202;&#847;&#8202;&#847;&#8202;&#847;&#8202;&#847;</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#EEF0F3;">
<tr><td align="center" style="padding:32px 12px;">

    <table role="presentation" class="contenedor" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px; background-color:#FFFFFF; border-radius:14px; overflow:hidden;">

        {{-- Encabezado --}}
        <tr>
            <td bgcolor="#13161D" class="px" style="background-color:#13161D; padding:28px 40px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td align="left" valign="middle"><img src="{{ $logo }}" width="112" height="36" alt="Agencia Vandu" style="display:block; width:112px; height:auto;"></td>
                        <td align="right" valign="middle" class="ocultar-movil" style="font-family:{{ $font }}; font-size:12px; color:#9BA1AE; letter-spacing:.02em;">{{ $emisor['sitio'] }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr><td height="4" bgcolor="#00F385" style="background-color:#00F385; height:4px; line-height:4px; font-size:4px;">&nbsp;</td></tr>

        {{-- Cuerpo --}}
        <tr>
            <td class="px" style="padding:40px 40px 8px; font-family:{{ $font }};">
                @if($titulo)
                    <h1 class="titulo" style="margin:0 0 20px; font-size:26px; line-height:33px; font-weight:700; letter-spacing:-.02em; color:#13161D;">{{ $titulo }}</h1>
                @endif
                @foreach($parrafos as $p)
                    <p style="margin:0 0 16px; font-size:16px; line-height:26px; color:#3F4450;">{!! nl2br(e($p)) !!}</p>
                @endforeach
            </td>
        </tr>

        {{-- Resumen --}}
        @if($resumen)
            <tr>
                <td class="px" style="padding:8px 40px 8px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F6F7F9; border:1px solid #E7E9EE; border-radius:12px;">
                        @foreach($resumen as $k => $v)
                            @php $ultimo = $loop->last; @endphp
                            <tr>
                                <td style="padding:{{ $loop->first ? '18px' : '10px' }} 20px {{ $ultimo ? '18px' : '10px' }}; font-family:{{ $font }}; font-size:13px; color:#6B7180; white-space:nowrap; {{ $ultimo ? 'border-top:1px solid #E7E9EE;' : '' }}" valign="top">{{ $k }}</td>
                                <td align="right" style="padding:{{ $loop->first ? '18px' : '10px' }} 20px {{ $ultimo ? '18px' : '10px' }}; font-family:{{ $font }}; font-size:{{ $ultimo ? '17px' : '14px' }}; font-weight:{{ $ultimo ? '700' : '600' }}; color:#13161D; {{ $ultimo ? 'border-top:1px solid #E7E9EE;' : '' }}">{{ $v }}</td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        @endif

        {{-- Miniaturas de la galería --}}
        @if($miniaturas)
            <tr>
                <td class="px" style="padding:8px 36px 0;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                        @foreach(array_chunk($miniaturas, 3) as $fila)
                            <tr>
                                @foreach($fila as $i => $m)
                                    <td width="33.33%" style="padding:4px;" valign="top">
                                        <a href="{{ $url }}" target="_blank" style="display:block; position:relative;">
                                            <img src="{{ $m['src'] }}" alt="{{ $m['alt'] }}" width="168" style="display:block; width:100%; max-width:168px; height:auto; border-radius:8px; border:0;">
                                        </a>
                                    </td>
                                @endforeach
                                @for($k = count($fila); $k < 3; $k++)<td width="33.33%" style="padding:4px;">&nbsp;</td>@endfor
                            </tr>
                        @endforeach
                    </table>
                    @if($mas > 0)
                        <p style="margin:8px 4px 0; font-family:{{ $font }}; font-size:13px; color:#6B7180;">y {{ $mas }} {{ $mas === 1 ? 'archivo más' : 'archivos más' }} en tu galería</p>
                    @endif
                </td>
            </tr>
        @endif

        {{-- Botón --}}
        @if($boton && $url)
            <tr>
                <td class="px boton" align="left" style="padding:24px 40px 8px;">
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td bgcolor="#13161D" style="background-color:#13161D; border-radius:10px;" align="center">
                                <a href="{{ $url }}" target="_blank" style="display:inline-block; padding:15px 30px; font-family:{{ $font }}; font-size:15px; font-weight:600; color:#FFFFFF; text-decoration:none; border-radius:10px;">{{ $boton }} &nbsp;&rarr;</a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        @endif

        {{-- Código de verificación para aceptar o pedir cambios en línea --}}
        @if(! empty($codigo))
            <tr>
                <td class="px" style="padding:24px 40px 8px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#F1FFF8; border:1px solid #BDF2D6; border-radius:12px;">
                        <tr><td style="padding:16px 20px 4px; font-family:{{ $font }}; font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#047A4B;">Tu código de verificación</td></tr>
                        <tr><td style="padding:2px 20px 4px; font-family:'SFMono-Regular', Menlo, Consolas, monospace; font-size:30px; font-weight:700; letter-spacing:.18em; color:#13161D;">{{ $codigo['formateado'] }}</td></tr>
                        <tr><td style="padding:2px 20px 16px; font-family:{{ $font }}; font-size:13.5px; line-height:1.5; color:#3F4450;">Úsalo en el enlace de tu cotización para <b>aceptarla</b> o <b>pedir cambios</b>. Válido hasta el {{ $codigo['vigencia'] }}.</td></tr>
                    </table>
                </td>
            </tr>
        @endif

        {{-- Datos bancarios --}}
        @if($banco)
            <tr>
                <td class="px" style="padding:24px 40px 8px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #E7E9EE; border-radius:12px;">
                        <tr><td colspan="2" style="padding:16px 20px 6px; font-family:{{ $font }}; font-size:12px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#6B7180;">Datos para tu pago</td></tr>
                        @foreach($banco as $k => $v)
                            <tr>
                                <td style="padding:6px 20px {{ $loop->last ? '16px' : '6px' }}; font-family:{{ $font }}; font-size:14px; color:#6B7180; white-space:nowrap;">{{ $k }}</td>
                                <td align="right" style="padding:6px 20px {{ $loop->last ? '16px' : '6px' }}; font-family:{{ $k === 'CLABE' ? "'SFMono-Regular', Menlo, Consolas, monospace" : $font }}; font-size:14px; font-weight:600; color:#13161D; letter-spacing:{{ $k === 'CLABE' ? '.04em' : '0' }};">{{ $v }}</td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        @endif

        {{-- Firma --}}
        <tr>
            <td class="px" style="padding:32px 40px 36px; font-family:{{ $font }};">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-top:1px solid #E7E9EE;">
                    <tr><td style="padding-top:24px;">
                        <p style="margin:0; font-size:15px; line-height:22px; font-weight:700; color:#13161D;">{{ $emisor['nombre'] }}</p>
                        <p style="margin:2px 0 0; font-size:14px; line-height:21px; color:#6B7180;">Agencia Vandu</p>
                        <p style="margin:8px 0 0; font-size:14px; line-height:21px; color:#6B7180;">
                            @if($emisor['telefono'])<a href="tel:{{ preg_replace('/\D/', '', $emisor['telefono']) }}" style="color:#13161D; text-decoration:none;">{{ $emisor['telefono'] }}</a> &nbsp;·&nbsp; @endif
                            <a href="https://{{ $emisor['sitio'] }}" style="color:#13161D; text-decoration:none;">{{ $emisor['sitio'] }}</a>
                        </p>
                    </td></tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Pie --}}
    <table role="presentation" class="contenedor" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:600px;">
        <tr>
            <td align="center" style="padding:20px 24px 8px; font-family:{{ $font }}; font-size:12px; line-height:18px; color:#8A90A0;">
                {{ config('vandu.correo.pie') }}<br>
                ¿Dudas? Responde este correo y te contestamos personalmente.
            </td>
        </tr>
    </table>

</td></tr>
</table>
</body>
</html>
