<?php

/*
| Textos y listas del inicio de agenciavandu.com y de la barra del sitio.
| Cambiar un servicio, una pregunta o un paso es solo editar este archivo.
|
| Destinos de un enlace ("destino"):
|   ['ruta' => 'servicios']            una ruta con nombre
|   ['ancla' => 'nerby']               una sección del inicio (desde otra página lleva al inicio)
|   ['whatsapp' => 'Redes sociales']   abre el formulario de WhatsApp con ese servicio elegido
*/

return [

    // Barra: enlaces a secciones del inicio
    'menu' => [
        ['Servicios', 'servicios'],
        ['Trabajo', 'trabajo'],
        ['Nerby', 'nerby'],
        ['Proceso', 'proceso'],
    ],

    'servicios' => [
        ['titulo' => 'Sitios web y SEO', 'corto' => 'Sitios a la medida, tiendas, SEO y GEO', 'texto' => 'Sitios a la medida, tiendas en línea, SEO y GEO, hosting y mantenimiento.', 'etiquetas' => ['A la medida', 'SEO y GEO'], 'destino' => ['ruta' => 'servicios']],
        ['titulo' => 'Sistemas y automatización', 'corto' => 'Paneles y procesos con WhatsApp', 'texto' => 'Paneles, portales y procesos conectados a WhatsApp: ventas, proyectos, inventario.', 'etiquetas' => ['WhatsApp', 'Paneles'], 'destino' => ['ancla' => 'nerby']],
        ['titulo' => 'Video y video con IA', 'corto' => 'Reels, dron, comerciales con IA', 'texto' => 'Reels, video corporativo, dron, comerciales y presentadores hechos con IA.', 'etiquetas' => ['Dron', 'IA'], 'destino' => ['ruta' => 'audiovisuales']],
        ['titulo' => 'Fotografía', 'corto' => 'Alimentos, interiores, arquitectura', 'texto' => 'Alimentos, interiores, arquitectura, retrato empresarial y eventos.', 'etiquetas' => ['Alimentos', 'Interiores'], 'destino' => ['ruta' => 'audiovisuales']],
        ['titulo' => 'Redes sociales', 'corto' => 'Contenido mensual con reporte', 'texto' => 'Manejo mensual de Instagram, Facebook, TikTok y LinkedIn, con reporte.', 'etiquetas' => ['Contenido', 'Reportes'], 'destino' => ['whatsapp' => 'Redes sociales']],
        ['titulo' => 'Producción e impresos', 'corto' => 'Rotulación, bordado, promocionales', 'texto' => 'Impresión, rotulación e instalación, bordado y promocionales.', 'etiquetas' => ['Rotulación', 'Bordado'], 'destino' => ['whatsapp' => 'Impresión y rotulación']],
        ['titulo' => 'Diseño digital', 'corto' => 'Campañas y punto de venta', 'texto' => 'Campañas, promociones, presentaciones y material para punto de venta.', 'etiquetas' => ['Campañas', 'Punto de venta'], 'destino' => ['whatsapp' => 'Otro']],
    ],

    // Logos en public/img/logos-marcas: archivo, nombre, alto en px
    'marcas' => [
        ['KFC.svg', 'KFC', 34], ['burger-king.svg', 'Burger King', 38], ['pizza-hut.svg', 'Pizza Hut', 36], ['gpo-nicxa.svg', 'Grupo Nicxa', 30],
        ['gran-calzada.svg', 'Gran Calzada', 30], ['amavita.svg', 'Amavita', 28], ['osroca.svg', 'Osroca', 26], ['tokio07.svg', 'Tokio 07', 34], ['turismaya.svg', 'Turismaya', 30],
    ],

    // Planos de la portada: título y lo que dice al construirse
    'planos' => [
        ['Sitio corporativo', 'en línea'],
        ['Tienda en línea', '1 en el carrito'],
        ['Captación de prospectos', 'llegó a WhatsApp'],
        ['Sistema a la medida', 'en tiempo real'],
        ['Varias marcas, un sitio', '4 marcas'],
    ],

    'nerby' => [
        'funciones' => [
            ['titulo' => 'Lee tus tickets', 'ia' => true, 'ventana' => 'Gastos', 'texto' => 'Le tomas foto al ticket y la IA llena proveedor, fecha, total e IVA. Tú solo confirmas.'],
            ['titulo' => 'Factura timbrada', 'ia' => false, 'ventana' => 'Facturas', 'texto' => 'CFDI 4.0 con tu logo, desde la misma venta y en un clic.'],
            ['titulo' => 'En tu celular', 'ia' => false, 'ventana' => 'Inicio', 'texto' => 'Ventas, cobros y pendientes del día, donde estés.'],
        ],
        // Ticket de ejemplo: el campo vacío se marca como dudoso, como hace Nerby de verdad
        'ticket' => [['Proveedor', 'Ferretería Itzimná'], ['Fecha', '28 oct 2026'], ['Total', '$791.00'], ['IVA', '$109.10'], ['Categoría', 'Mantenimiento'], ['Forma de pago', '']],
    ],

    // Fotos y video: archivo en public/img, pie, alt; 'video' usa public/img/inicio/{video}.mp4 y .webp
    'galeria' => [
        ['img' => 'inicio/tokyo-tyger-fachada.webp', 'pie' => 'Tokyo Tyger · Fotografía de fachada', 'alt' => 'Fotografía de la fachada de Tokyo Tyger'],
        ['img' => 'hero/kuro-23.webp', 'pie' => 'Kuro · Alimentos', 'alt' => 'Platillo de Kuro'],
        ['video' => 'video-comida', 'pie' => 'Reel · Video', 'alt' => 'Reel para restaurante'],
        ['img' => 'inicio/samma-obra.webp', 'pie' => 'Samma · Arquitectura', 'alt' => 'Fotografía de obra para Samma'],
        ['img' => 'hero/impresos-tarjetas.webp', 'pie' => 'Impresos', 'alt' => 'Tarjetas impresas', 'oscuro' => true],
    ],

    'pasos' => [
        ['Platicamos', 'Por WhatsApp o en llamada. Entendemos tu negocio y lo que necesitas.'],
        ['Cotización en 24 h', 'Clara y por etapas. La revisas y la aceptas desde tu portal.'],
        ['Anticipo y producción', 'En sitios web, 50% de anticipo. En foto y video, el saldo se liquida antes de la sesión.'],
        ['Entrega', 'Archivos y avances en tu portal, listos para usar.'],
    ],

    'testimonio' => [
        'frase' => 'Hemos trabajado juntos desde hace más de 4 años y siempre terminamos satisfechos. 100% recomendados.',
        'nombre' => 'Alejandro Canto', 'puesto' => 'Director, Grupo Osorio',
    ],

    'datos' => [['Base', 'Mérida, Yucatán'], ['Cobertura', 'Todo México, a distancia'], ['Equipo', 'Diseño, código y producción']],

    'preguntas' => [
        ['¿Qué es SEO y GEO?', 'SEO es lo que hace que tu sitio aparezca en Google. GEO es lo mismo para las respuestas de inteligencia artificial como ChatGPT. Preparamos tu sitio para las dos.'],
        ['¿Ustedes se encargan del hosting?', 'Sí. Nos encargamos del dominio, el servidor y las actualizaciones para que tú no tengas que pensar en eso.'],
        ['¿Qué tipo de video hacen con IA?', 'Comerciales y anuncios, presentadores virtuales, animación de producto y fotos, y versiones de tus videos en otros idiomas.'],
        ['¿Trabajan con negocios fuera de Mérida?', 'Sí. Trabajamos con negocios de todo México con una forma de trabajo pensada para hacerlo a distancia.'],
    ],

    // Contacto paso a paso: texto del botón y servicio del formulario de WhatsApp
    'contacto' => [
        'necesidades' => [['Sitio web', 'Página web'], ['Sistema', 'Sistema con WhatsApp'], ['Video', 'Video'], ['Fotografía', 'Fotografía'], ['Redes sociales', 'Redes sociales'], ['Impresos', 'Impresión y rotulación'], ['Nerby', 'Otro']],
        'cuando' => ['Lo antes posible', 'Este mes', 'Solo estoy cotizando'],
        'correo' => 'proyectos@agenciavandu.com',
    ],

    // Botón flotante de WhatsApp: texto, servicio del formulario y mensaje ya escrito
    'whatsapp_temas' => [
        ['Quiero un sitio web', 'Página web', ''],
        ['Necesito un sistema', 'Sistema con WhatsApp', ''],
        ['Foto, video o impresos', 'Fotografía', ''],
        ['Quiero conocer Nerby', 'Otro', 'Quiero conocer Nerby.'],
    ],

    // Mapa lateral del inicio
    'secciones' => [['Inicio', 'inicio'], ['Servicios', 'servicios'], ['Nerby', 'nerby'], ['Foto y video', 'foto'], ['Proceso', 'proceso'], ['Contacto', 'contacto']],
];
