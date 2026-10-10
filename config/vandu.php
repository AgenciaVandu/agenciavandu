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
        // Servicio mensual de redes: se liga al calendario de contenido del cliente (Redes sociales)
        'redes' => [
            'nombre' => 'Gestión de redes sociales',
            'icono'  => 'bi-grid-3x3-gap',
            'redes'  => true,
            'pagos'  => [
                ['clave' => 'mensualidad', 'concepto' => 'Mensualidad', 'porcentaje' => 100, 'antes_de' => 'produccion'],
            ],
            'etapas' => [
                ['clave' => 'estrategia',  'nombre' => 'Estrategia del mes',       'dias' => 2,  'descripcion' => 'Objetivos, fechas importantes y temas del mes.'],
                ['clave' => 'parrilla',    'nombre' => 'Parrilla de contenido',    'dias' => 3,  'descripcion' => 'Calendario con cada publicación, formato y red.'],
                ['clave' => 'produccion',  'nombre' => 'Producción de contenido',  'dias' => 7,  'descripcion' => 'Diseño, fotografía, video y textos de cada publicación.'],
                ['clave' => 'revision',    'nombre' => 'Revisión y aprobación',    'dias' => 3,  'descripcion' => 'Revisas cómo se ve cada post y lo apruebas en tu enlace.'],
                ['clave' => 'publicacion', 'nombre' => 'Publicación y comunidad',  'dias' => 20, 'descripcion' => 'Publicamos según el calendario y atendemos comentarios y mensajes.'],
                ['clave' => 'reporte',     'nombre' => 'Reporte del mes',          'dias' => 2,  'descripcion' => 'Resultados del mes y aprendizajes para el siguiente.'],
            ],
        ],
        // Impresión, instalación, rotulación, bordado y artículos publicitarios.
        // costeo => en la cotización cada concepto se calcula con proveedor + gasolina + utilidad.
        'produccion' => [
            'nombre' => 'Producción e impresión',
            'icono'  => 'bi-printer',
            'costeo' => true,
            // Texto base de "Observaciones" al elegir este tipo (editable en cada cotización)
            'observaciones' => "Las medidas, colores y acabados finales se confirman con una prueba digital antes de producir.\nLos colores impresos pueden variar ligeramente respecto a como se ven en pantalla.\nLos archivos del cliente deben entregarse en vectores (AI, PDF o SVG) o en alta resolución.\nPara la instalación, el área debe estar libre y accesible el día acordado.",
            'pagos'  => [
                ['clave' => 'anticipo', 'concepto' => 'Anticipo', 'porcentaje' => 50, 'antes_de' => 'fabricacion'],
                ['clave' => 'saldo',    'concepto' => 'Saldo',    'porcentaje' => 50, 'antes_de' => 'entrega'],
            ],
            'etapas' => [
                ['clave' => 'fabricacion', 'nombre' => 'Fabricación',            'dias' => 7, 'descripcion' => 'Impresión, rotulación, bordado o fabricación de tus piezas. Arranca con el anticipo.'],
                ['clave' => 'entrega',     'nombre' => 'Entrega / instalación', 'dias' => 1, 'fecha' => true, 'descripcion' => 'Entregamos o instalamos en el lugar acordado. El saldo se liquida antes de la entrega.'],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Correos desde el panel
    |--------------------------------------------------------------------------
    | desde: la cuenta que envía (créala en cPanel y pon sus datos SMTP en .env).
    | responder_a: a dónde llegan las respuestas del cliente.
    | Plantillas: textos base editables antes de enviar. Variables disponibles:
    |   {nombre} {empresa} {folio} {concepto} {monto} {vigencia} {proyecto}
    |   {pago} {monto_pago} {fecha_limite} {siguiente} {entregables} {firma}
    */
    // Redes sociales: calendario de contenido, vistas previas y aprobación del cliente
    'redes' => [
        'redes' => [
            'instagram' => ['nombre' => 'Instagram', 'icono' => 'bi-instagram', 'color' => '#E1306C', 'formatos' => ['post', 'carrusel', 'reel', 'historia']],
            'facebook'  => ['nombre' => 'Facebook',  'icono' => 'bi-facebook',  'color' => '#1877F2', 'formatos' => ['post', 'carrusel', 'reel', 'historia']],
            'tiktok'    => ['nombre' => 'TikTok',    'icono' => 'bi-tiktok',    'color' => '#111111', 'formatos' => ['video', 'carrusel']],
            'linkedin'  => ['nombre' => 'LinkedIn',  'icono' => 'bi-linkedin',  'color' => '#0A66C2', 'formatos' => ['post', 'carrusel', 'video']],
        ],
        'formatos' => [
            'post'     => ['nombre' => 'Publicación', 'icono' => 'bi-image'],
            'carrusel' => ['nombre' => 'Carrusel',    'icono' => 'bi-collection'],
            'reel'     => ['nombre' => 'Reel',        'icono' => 'bi-film'],
            'historia' => ['nombre' => 'Historia',    'icono' => 'bi-phone'],
            'video'    => ['nombre' => 'Video',       'icono' => 'bi-play-btn'],
        ],
        'max_mb' => (int) env('VANDU_REDES_MAX_MB', 200),
    ],

    // Notificaciones push en el celular y la compu (las claves VAPID se crean solas si no se definen aquí)
    'push' => [
        'publica'  => env('VANDU_PUSH_PUBLICA'),
        'privada'  => env('VANDU_PUSH_PRIVADA'),
        'contacto' => env('VANDU_PUSH_CONTACTO', 'proyectos@agenciavandu.com'),
        'resumen_hora' => env('VANDU_PUSH_RESUMEN', '08:50'),
        // Cuánto esperar antes de volver a avisar que el mismo cliente abrió lo mismo
        'repetir_vista_horas' => 3,
        'eventos' => [
            'cotizacion_abierta'    => ['texto' => 'Un cliente abre una cotización', 'ayuda' => 'La primera vez y cuando vuelve a abrirla después de unas horas', 'icono' => 'bi-eye'],
            'cotizacion_aceptada'   => ['texto' => 'Un cliente acepta su cotización', 'ayuda' => 'La aceptó en línea con su código de verificación', 'icono' => 'bi-check-circle'],
            'cambios_solicitados'   => ['texto' => 'Un cliente pide cambios', 'ayuda' => 'Escribió qué quiere ajustar en su cotización', 'icono' => 'bi-chat-left-text'],
            'redes_aprobado'        => ['texto' => 'Un cliente aprueba su contenido', 'ayuda' => 'Aprobó uno o varios posts de su calendario', 'icono' => 'bi-check2-square'],
            'redes_cambios'         => ['texto' => 'Un cliente comenta un post', 'ayuda' => 'Pidió un cambio o dejó una observación en su contenido', 'icono' => 'bi-chat-square-dots'],
            'cotizacion_descargada' => ['texto' => 'Un cliente descarga la cotización', 'ayuda' => 'Cuando baja el PDF desde su enlace', 'icono' => 'bi-file-earmark-arrow-down'],
            'proyecto_visto'        => ['texto' => 'Un cliente revisa su proyecto', 'ayuda' => 'Abre su página de avance o de entrega', 'icono' => 'bi-kanban'],
            'mensaje_sitio'         => ['texto' => 'Llega un mensaje desde el sitio', 'ayuda' => 'Alguien llenó el formulario de cotizar en agenciavandu.com', 'icono' => 'bi-chat-dots'],
            'resumen_diario'        => ['texto' => 'Resumen de la mañana', 'ayuda' => 'Cobros vencidos o por vencer, etapas del día y cotizaciones a punto de vencer', 'icono' => 'bi-sunrise'],
        ],
    ],

    'correo' => [
        'desde'       => env('VANDU_CORREO_DESDE', 'proyectos@agenciavandu.com'),
        'nombre'      => env('VANDU_CORREO_NOMBRE', 'Agencia Vandu'),
        'responder_a' => env('VANDU_CORREO_RESPONDER_A', env('VANDU_EMISOR_EMAIL', 'ab@agenciavandu.com')),
        'pie'         => 'Agencia Vandu · Mérida, Yucatán',

        'plantillas' => [
            'bienvenida' => [
                'nombre' => 'Bienvenida', 'icono' => 'bi-stars', 'para' => ['cliente', 'presupuesto', 'proyecto'],
                'asunto' => 'Bienvenido a Agencia Vandu',
                'titulo' => 'Qué gusto trabajar contigo',
                'cuerpo' => "Hola {nombre},\n\nGracias por confiar en Agencia Vandu. A partir de hoy tienes un equipo dedicado a que tu proyecto salga como lo imaginas.\n\nCualquier duda, idea o cambio, respóndeme este correo o escríbeme por WhatsApp: estamos para ayudarte.",
            ],
            'cotizacion' => [
                'nombre' => 'Enviar cotización', 'icono' => 'bi-file-earmark-text', 'para' => ['presupuesto'],
                'asunto' => 'Cotización {folio} · {concepto}',
                'titulo' => 'Tu cotización está lista',
                'cuerpo' => "Hola {nombre},\n\nTe comparto la cotización {folio} para {concepto}. Puedes verla en línea o descargarla en PDF; los precios están vigentes hasta el {vigencia}.\n\nSi quieres ajustar algo, con gusto lo revisamos.",
                'boton'  => 'Ver mi cotización', 'pdf' => true, 'resumen' => true, 'codigo' => true,
            ],
            'por_vencer' => [
                'nombre' => 'Cotización por vencer', 'icono' => 'bi-hourglass-split', 'para' => ['presupuesto'],
                'asunto' => 'Tu cotización {folio} vence el {vigencia}',
                'titulo' => 'Tu cotización está por vencer',
                'cuerpo' => "Hola {nombre},\n\nTe recuerdo que la cotización {folio} sigue vigente hasta el {vigencia}. Si quieres arrancar, con tu confirmación apartamos fechas.\n\n¿Te queda alguna duda? Con gusto la resolvemos.",
                'boton'  => 'Revisar cotización', 'resumen' => true, 'codigo' => true,
            ],
            'inicio_proyecto' => [
                'nombre' => 'Arranque de proyecto', 'icono' => 'bi-rocket-takeoff', 'para' => ['proyecto'],
                'asunto' => 'Arrancamos: {proyecto}',
                'titulo' => 'Tu proyecto ya está en marcha',
                'cuerpo' => "Hola {nombre},\n\n¡Arrancamos con {proyecto}! Desde el enlace de abajo puedes seguir el avance, las fechas de cada etapa y descargar los archivos cuando estén listos.\n\nLo siguiente: {siguiente}.",
                'boton'  => 'Ver mi proyecto', 'resumen' => true,
            ],
            'recordatorio_pago' => [
                'nombre' => 'Recordatorio de pago', 'icono' => 'bi-cash-coin', 'para' => ['proyecto'],
                'asunto' => 'Recordatorio de pago · {proyecto}',
                'titulo' => 'Recordatorio de pago',
                'cuerpo' => "Hola {nombre},\n\nTe escribo para recordarte el {pago} de {monto_pago} correspondiente a {proyecto}{fecha_limite}.\n\nAbajo van los datos bancarios. Cuando lo realices, respóndeme con tu comprobante y lo registramos. Si ya lo hiciste, muchas gracias y omite este mensaje.",
                'boton'  => 'Ver estado del proyecto', 'resumen' => true, 'banco' => true,
            ],
            'entrega' => [
                'nombre' => 'Entrega lista', 'icono' => 'bi-box-seam', 'para' => ['proyecto'],
                'asunto' => 'Tu entrega está lista · {proyecto}',
                'titulo' => 'Tu entrega está lista',
                'cuerpo' => "Hola {nombre},\n\nTe aviso que el material de {proyecto} ya está disponible. Desde el enlace puedes verlo y descargarlo cuando quieras.\n\nGracias por trabajar con nosotros; nos encantará saber qué te pareció.",
                'boton'  => 'Ver y descargar',
            ],
            'entrega_digital' => [
                'nombre' => 'Entrega digital', 'icono' => 'bi-images', 'para' => ['proyecto'],
                'asunto' => 'Tu entrega está lista · {proyecto}',
                'titulo' => 'Tu material ya está listo',
                'cuerpo' => "Hola {nombre},\n\nYa puedes ver y descargar {entregables} de {proyecto}. Preparamos una galería para que lo revises con calma desde tu computadora o tu celular.\n\nSi algo necesita un ajuste, respóndeme este correo y lo vemos.",
                'boton'  => 'Ver mi entrega', 'enlace' => 'entrega', 'miniaturas' => true,
            ],
            'factura' => [
                'nombre' => 'Enviar factura', 'icono' => 'bi-receipt', 'para' => ['proyecto', 'presupuesto', 'cliente'],
                'asunto' => 'Factura · {concepto}',
                'titulo' => 'Tu factura está lista',
                'cuerpo' => "Hola {nombre},\n\nTe comparto la factura correspondiente a {concepto}. Adjunto encontrarás el PDF y el XML para tu contabilidad.\n\nSi necesitas algún ajuste en los datos fiscales o que la enviemos a otro correo, respóndeme este mensaje y lo corregimos.\n\nGracias por tu confianza.",
                'adjuntos' => true, 'para_factura' => true,
            ],
            'libre' => [
                'nombre' => 'En blanco', 'icono' => 'bi-pencil', 'para' => ['cliente', 'presupuesto', 'proyecto'],
                'asunto' => '',
                'titulo' => '',
                'cuerpo' => "Hola {nombre},\n\n",
            ],
        ],
    ],

    /*
    | Dropbox: ahí se guardan los archivos del panel (fotos, videos, documentos, constancias).
    | Crea una app en dropbox.com/developers y pon sus llaves en .env.
    */
    'dropbox' => [
        'app_key'    => env('DROPBOX_APP_KEY'),
        'app_secret' => env('DROPBOX_APP_SECRET'),
        'carpeta'    => env('DROPBOX_CARPETA', 'Vandu'),   // carpeta raíz dentro de tu Dropbox
        'simulado'   => (bool) env('VANDU_DROPBOX_SIMULADO', false), // solo pruebas locales
    ],

    // Métodos de pago (cliente y cada pago registrado)
    'metodos_pago' => [
        'transferencia'   => 'Transferencia',
        'efectivo'        => 'Efectivo',
        'credito'         => 'Crédito',
        'tarjeta_credito' => 'Tarjeta de crédito',
    ],

    // Proyectos a crédito (empresas que pagan diferido): un solo pago que vence N días después de la entrega
    'credito' => [
        'concepto' => 'Pago a crédito',
        'dias'     => [15, 30, 45, 60, 90],
        'dias_por_defecto' => 30,
    ],

    // Catálogos del SAT para los datos de facturación del cliente
    'sat' => [
        'regimenes' => [
            '601' => 'General de Ley Personas Morales',
            '603' => 'Personas Morales con Fines no Lucrativos',
            '605' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios',
            '606' => 'Arrendamiento',
            '612' => 'Personas Físicas con Actividades Empresariales y Profesionales',
            '616' => 'Sin obligaciones fiscales',
            '620' => 'Sociedades Cooperativas de Producción',
            '621' => 'Incorporación Fiscal',
            '622' => 'Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras',
            '623' => 'Opcional para Grupos de Sociedades',
            '624' => 'Coordinados',
            '625' => 'Actividades Empresariales con ingresos a través de Plataformas Tecnológicas',
            '626' => 'Régimen Simplificado de Confianza',
        ],
        'usos_cfdi' => [
            'G01'  => 'Adquisición de mercancías',
            'G03'  => 'Gastos en general',
            'I04'  => 'Equipo de computo y accesorios',
            'I08'  => 'Otra maquinaria y equipo',
            'S01'  => 'Sin efectos fiscales',
            'CP01' => 'Pagos',
        ],
    ],

    // Tamaño máximo por archivo al subir entregables (también lo limita upload_max_filesize del servidor)
    'max_archivo_mb' => env('VANDU_MAX_ARCHIVO_MB', 512),

    // Palabras que hacen sugerir "Audiovisuales" al convertir una cotización
    // Palabras que hacen sugerir "Producción e impresión"
    'palabras_produccion' => ['impres', 'lona', 'vinil', 'rotul', 'bordad', 'playera', 'gorra', 'taza', 'termo', 'pluma', 'promocional', 'publicitari', 'instalación', 'letrero', 'señalética', 'sticker', 'etiqueta', 'display', 'uniforme', 'caja'],

    'palabras_redes' => ['redes sociales', 'community', 'social media', 'parrilla', 'gestión de redes', 'manejo de redes', 'publicaciones', 'instagram', 'facebook', 'tiktok', 'linkedin', 'contenido mensual'],
    'palabras_audiovisual' => ['foto', 'video', 'vídeo', 'grabación', 'sesión', 'dron', 'audiovisual', 'filmación', 'reel'],
];
