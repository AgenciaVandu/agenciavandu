<?php

/*
|--------------------------------------------------------------------------
| Valores por defecto de las cotizaciones (presupuestos)
|--------------------------------------------------------------------------
| Se copian a cada cotización nueva. Después, todo es editable por cotización
| desde el panel, así que cambiar esto NO altera cotizaciones ya creadas.
*/

return [

    'emisor' => [
        'nombre'   => env('VANDU_EMISOR_NOMBRE', 'Alvar Buenfil'),
        'telefono' => env('VANDU_EMISOR_TELEFONO', '(999) 146 0310'),
        'sitio'    => env('VANDU_EMISOR_SITIO', 'agenciavandu.com'),
        'email'    => env('VANDU_EMISOR_EMAIL', 'ab@agenciavandu.com'),
    ],

    // WhatsApp (solo dígitos, con lada de país) para el botón de la vista del cliente
    'whatsapp' => env('VANDU_WHATSAPP', '529991460310'),

    'pago' => [
        'banco'        => env('VANDU_BANCO', 'Santander'),
        'clabe'        => env('VANDU_CLABE', '014910605738096757'),
        'beneficiario' => env('VANDU_BENEFICIARIO', 'Alvar Buenfil Vadillo'),
        'intro'        => 'Para realizar el pago correspondiente, favor de utilizar los siguientes datos:',
        'nota_comprobante' => 'Una vez realizado el pago, favor de enviar el comprobante a proyectos@agenciavandu.com o por WhatsApp al 999 146 0310 para su confirmación.',
        'nota_factura' => 'Este servicio incluye factura. Favor de proporcionar sus datos fiscales (RFC, razón social y uso de CFDI) para su emisión.',
    ],

    'titulo' => 'Presupuesto de servicios',

    // Zona horaria en la que capturas y ves la vigencia (la BD guarda en la zona de la app)
    'zona_horaria' => env('VANDU_ZONA_HORARIA', 'America/Merida'),

    'iva_porcentaje' => 16,

    // Días de vigencia por defecto al crear una cotización
    'vigencia_dias' => 15,

    // Prefijo del folio: CT-0001, CT-0002…
    'folio_prefijo' => 'CT-',

    'consideraciones' => [
        ['titulo' => 'Tiempos de entrega', 'items' => ['3 días hábiles después de la recepción del pago.']],
        ['titulo' => 'Condiciones de pago', 'items' => ['Pago por adelantado.']],
    ],
];
