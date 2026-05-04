<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #f0f0f0; }
        .header { background-color: #000; padding: 20px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 24px; letter-spacing: 2px; }
        .content { padding: 30px; background-color: #fff; }
        .item { margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .label { font-weight: bold; color: #555; text-transform: uppercase; font-size: 12px; display: block; }
        .value { font-size: 16px; color: #000; }
        .footer { text-align: center; padding: 20px; font-size: 11px; color: #999; }
        .badge { background-color: #e3f2fd; color: #0d47a1; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>AGENCIA VANDU</h1>
    </div>
    <div class="content">
        <h2 style="color: #000; border-bottom: 2px solid #000; padding-bottom: 10px;">Nueva Solicitud de Cotización</h2>
        
        <div class="item">
            <span class="label">Cliente</span>
            <span class="value">{{ $datos['name'] }} {{ $datos['lastname'] }}</span>
        </div>

        <div class="item">
            <span class="label">Servicio de Interés</span>
            <span class="badge">
                @switch($datos['service'])
                    @case('web') Diseño o rediseño web @break
                    @case('photo') Fotografía (Alimentos/Interiores) @break
                    @case('digital') Diseño On-Demand @break
                    @case('ia-friendly') Servicios AI Friendly @break
                    @default {{ $datos['service'] }}
                @endswitch
            </span>
        </div>

        <div class="item">
            <span class="label">Correo Electrónico</span>
            <span class="value"><a href="mailto:{{ $datos['email'] }}" style="color: #007bff; text-decoration: none;">{{ $datos['email'] }}</a></span>
        </div>

        <div class="item">
            <span class="label">Teléfono</span>
            <span class="value">
                <a href="https://wa.me/52{{ $datos['phone'] }}" style="color: #25d366; text-decoration: none; font-weight: bold;">
                    {{ $datos['phone'] }} (Enviar WhatsApp)
                </a>
            </span>
        </div>

        <p style="margin-top: 30px; font-style: italic; color: #666;">
            Este mensaje fue generado automáticamente desde el formulario de contacto de agenciavandu.com
        </p>
    </div>
    <div class="footer">
        &copy; {{ date('Y') }} Agencia Vandu | Mérida, Yucatán.
    </div>
</body>
</html>