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

    /*
    |--------------------------------------------------------------------------
    | Proyectos: metodología por tipo
    |--------------------------------------------------------------------------
    | Al convertir una cotización aceptada en proyecto se copian estas etapas y
    | pagos. Después todo es editable por proyecto.
    |   pagos.*.antes_de => clave de la etapa que no puede empezar sin ese pago
    |   etapas.*.dias    => duración estimada (para proponer fechas)
    |   etapas.*.fecha   => la etapa se agenda en un día concreto (levantamiento)
    */
    'proyectos' => [
        'web' => [
            'nombre' => 'Desarrollo web',
            'icono'  => 'bi-window-stack',
            'pagos'  => [
                ['clave' => 'anticipo', 'concepto' => 'Anticipo', 'porcentaje' => 50, 'antes_de' => 'definicion'],
                ['clave' => 'saldo',    'concepto' => 'Saldo',    'porcentaje' => 50, 'antes_de' => 'lanzamiento'],
            ],
            'etapas' => [
                ['clave' => 'definicion',  'nombre' => 'Definición del producto', 'dias' => 5,  'descripcion' => 'Objetivos, alcance, mapa del sitio y contenidos.'],
                ['clave' => 'prototipo',   'nombre' => 'Prototipado',             'dias' => 5,  'descripcion' => 'Estructura de cada pantalla para validar la navegación.'],
                ['clave' => 'diseno',      'nombre' => 'Diseño del sitio',        'dias' => 7,  'descripcion' => 'Diseño visual con tu marca, listo para tu aprobación.'],
                ['clave' => 'desarrollo',  'nombre' => 'Desarrollo',              'dias' => 15, 'descripcion' => 'Programación y carga de contenidos.'],
                ['clave' => 'lanzamiento', 'nombre' => 'Puesta en marcha',        'dias' => 3,  'descripcion' => 'Publicación en tu dominio, pruebas finales y capacitación.'],
                ['clave' => 'soporte',     'nombre' => 'Soporte',                 'dias' => 30, 'descripcion' => 'Ajustes y acompañamiento después del lanzamiento.'],
            ],
        ],
        'audiovisual' => [
            'nombre'  => 'Audiovisuales',
            'icono'   => 'bi-camera-reels',
            'galeria' => true,
            'pagos'   => [
                ['clave' => 'anticipo', 'concepto' => 'Anticipo',                   'porcentaje' => 50, 'antes_de' => 'levantamiento'],
                ['clave' => 'saldo',    'concepto' => 'Saldo para confirmar fecha', 'porcentaje' => 50, 'antes_de' => 'levantamiento'],
            ],
            'etapas' => [
                ['clave' => 'levantamiento', 'nombre' => 'Grabación / levantamiento', 'dias' => 1, 'fecha' => true, 'descripcion' => 'Día de grabación o sesión de fotografía. Se confirma con el pago del saldo.'],
                ['clave' => 'entrega',       'nombre' => 'Entrega',                    'dias' => 1, 'fecha' => true, 'descripcion' => 'El material final queda disponible en tu galería para verlo y descargarlo.'],
            ],
        ],
    ],

    // Tamaño máximo por archivo al subir entregables (también lo limita upload_max_filesize del servidor)
    'max_archivo_mb' => env('VANDU_MAX_ARCHIVO_MB', 512),

    // Palabras que hacen sugerir "Audiovisuales" al convertir una cotización
    'palabras_audiovisual' => ['foto', 'video', 'vídeo', 'grabación', 'sesión', 'dron', 'audiovisual', 'filmación', 'reel'],
];
