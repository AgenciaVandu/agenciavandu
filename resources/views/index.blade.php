@push('estilos')
    <script>
        // Las animaciones de entrada solo se preparan si el JS del inicio carga; si en 3 s no cargó, se muestra todo
        (function (d) {
            d.classList.add('vx-js');
            setTimeout(function () { if (!window.vxListo) d.classList.remove('vx-js'); }, 3000);
        })(document.documentElement);
    </script>
    @vite(['resources/scss/app.scss', 'resources/scss/global.scss', 'resources/scss/vandu-inicio.scss', 'resources/js/app.js', 'resources/js/vandu-inicio.js'])
@endpush
@php
    $whatsapp = 'https://wa.me/529991460310';

    $menu = [['Servicios', '#servicios'], ['Trabajo', '#trabajo'], ['Nerby', '#nerby'], ['Proceso', '#proceso']];

    $servicios = [
        ['titulo' => 'Sitios web y SEO', 'corto' => 'Sitios a la medida, tiendas, SEO y GEO', 'texto' => 'Sitios a la medida, tiendas en línea, SEO y GEO, hosting y mantenimiento.', 'ruta' => route('servicios'), 'etiquetas' => ['A la medida', 'SEO y GEO']],
        ['titulo' => 'Sistemas y automatización', 'corto' => 'Paneles y procesos con WhatsApp', 'texto' => 'Paneles, portales y procesos conectados a WhatsApp: ventas, proyectos, inventario.', 'ruta' => '#nerby', 'etiquetas' => ['WhatsApp', 'Paneles']],
        ['titulo' => 'Video y video con IA', 'corto' => 'Reels, dron, comerciales con IA', 'texto' => 'Reels, video corporativo, dron, comerciales y presentadores hechos con IA.', 'ruta' => route('audiovisuales'), 'etiquetas' => ['Dron', 'IA']],
        ['titulo' => 'Fotografía', 'corto' => 'Alimentos, interiores, arquitectura', 'texto' => 'Alimentos, interiores, arquitectura, retrato empresarial y eventos.', 'ruta' => route('audiovisuales'), 'etiquetas' => ['Alimentos', 'Interiores']],
        ['titulo' => 'Redes sociales', 'corto' => 'Contenido mensual con reporte', 'texto' => 'Manejo mensual de Instagram, Facebook, TikTok y LinkedIn, con reporte.', 'ruta' => $whatsapp, 'servicio' => 'Redes sociales', 'etiquetas' => ['Contenido', 'Reportes']],
        ['titulo' => 'Producción e impresos', 'corto' => 'Rotulación, bordado, promocionales', 'texto' => 'Impresión, rotulación e instalación, bordado y promocionales.', 'ruta' => $whatsapp, 'servicio' => 'Impresión y rotulación', 'etiquetas' => ['Rotulación', 'Bordado']],
        ['titulo' => 'Diseño digital', 'corto' => 'Campañas y punto de venta', 'texto' => 'Campañas, promociones, presentaciones y material para punto de venta.', 'ruta' => $whatsapp, 'servicio' => 'Otro', 'etiquetas' => ['Campañas', 'Punto de venta']],
    ];

    $clientes = [
        ['KFC.svg', 'KFC', 34], ['burger-king.svg', 'Burger King', 38], ['pizza-hut.svg', 'Pizza Hut', 36], ['gpo-nicxa.svg', 'Grupo Nicxa', 30],
        ['gran-calzada.svg', 'Gran Calzada', 30], ['amavita.svg', 'Amavita', 28], ['osroca.svg', 'Osroca', 26], ['tokio07.svg', 'Tokio 07', 34], ['turismaya.svg', 'Turismaya', 30],
    ];

    $nerby = [
        ['titulo' => 'Lee tus tickets', 'ia' => true, 'ventana' => 'Gastos', 'texto' => 'Le tomas foto al ticket y la IA llena proveedor, fecha, total e IVA. Tú solo confirmas.'],
        ['titulo' => 'Factura timbrada', 'ia' => false, 'ventana' => 'Facturas', 'texto' => 'CFDI 4.0 con tu logo, desde la misma venta y en un clic.'],
        ['titulo' => 'En tu celular', 'ia' => false, 'ventana' => 'Inicio', 'texto' => 'Ventas, cobros y pendientes del día, donde estés.'],
    ];
    $ticket = [['Proveedor', 'Ferretería Itzimná'], ['Fecha', '28 oct 2026'], ['Total', '$791.00'], ['IVA', '$109.10'], ['Categoría', 'Mantenimiento'], ['Forma de pago', '']];

    $pasos = [
        ['Platicamos', 'Por WhatsApp o en llamada. Entendemos tu negocio y lo que necesitas.'],
        ['Cotización en 24 h', 'Clara y por etapas. La revisas y la aceptas desde tu portal.'],
        ['Anticipo y producción', 'En sitios web, 50% de anticipo. En foto y video, el saldo se liquida antes de la sesión.'],
        ['Entrega', 'Archivos y avances en tu portal, listos para usar.'],
    ];

    $preguntas = [
        ['¿Qué es SEO y GEO?', 'SEO es lo que hace que tu sitio aparezca en Google. GEO es lo mismo para las respuestas de inteligencia artificial como ChatGPT. Preparamos tu sitio para las dos.'],
        ['¿Ustedes se encargan del hosting?', 'Sí. Nos encargamos del dominio, el servidor y las actualizaciones para que tú no tengas que pensar en eso.'],
        ['¿Qué tipo de video hacen con IA?', 'Comerciales y anuncios, presentadores virtuales, animación de producto y fotos, y versiones de tus videos en otros idiomas.'],
        ['¿Trabajan con negocios fuera de Mérida?', 'Sí. Trabajamos con negocios de todo México con una forma de trabajo pensada para hacerlo a distancia.'],
    ];

    // Contacto paso a paso: lo que se elige aquí llega al formulario de WhatsApp (data-servicio)
    $necesidades = [['Sitio web', 'Página web'], ['Sistema', 'Sistema con WhatsApp'], ['Video', 'Video'], ['Fotografía', 'Fotografía'], ['Redes sociales', 'Redes sociales'], ['Impresos', 'Impresión y rotulación'], ['Nerby', 'Otro']];
    $cuando = ['Lo antes posible', 'Este mes', 'Solo estoy cotizando'];

    $temasWa = [
        ['Quiero un sitio web', 'Página web', ''],
        ['Necesito un sistema', 'Sistema con WhatsApp', ''],
        ['Foto, video o impresos', 'Fotografía', ''],
        ['Quiero conocer Nerby', 'Otro', 'Quiero conocer Nerby.'],
    ];

    // Buscador rápido: título, destino, tipo
    $buscable = collect([
        ['Servicios', '#servicios', 'Sección'], ['Nerby', '#nerby', 'Sección'], ['Foto, video e impresión', '#foto', 'Sección'],
        ['Cómo trabajamos', '#proceso', 'Sección'], ['Preguntas frecuentes', '#preguntas', 'Sección'],
    ])->concat(collect($servicios)->map(fn ($s) => [$s['titulo'], $s['ruta'], 'Servicio']))
      ->concat([['Cotizar mi proyecto', '#contacto', 'Contacto'], ['Escribir por WhatsApp', $whatsapp, 'WhatsApp'], ['Aviso de privacidad', route('privacidad'), 'Página']])
      ->all();

    $secciones = [['Inicio', '#inicio'], ['Servicios', '#servicios'], ['Nerby', '#nerby'], ['Foto y video', '#foto'], ['Proceso', '#proceso'], ['Contacto', '#contacto']];

    $n2 = fn ($i) => str_pad($i + 1, 2, '0', STR_PAD_LEFT);
    $flecha = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
@endphp
<x-layouts.guest titulo="Páginas web, sistemas y video con IA en Mérida" tema="vx" :propio="true"
    meta-description="Agencia en Mérida para negocios de todo México: páginas web con SEO, sistemas conectados a WhatsApp con Nerby, video con IA, fotografía e impresión. Cotiza en 24 h.">

    {{-- ===== Barra flotante ===== --}}
    <header class="vx-cab" data-cab>
        <div class="vx-capsula">
            <a href="#inicio" class="vx-cab-logo"><img src="{{ asset('img/logo-vandu-blanco.svg') }}" alt="Agencia Vandu" width="96" height="24"></a>
            <nav class="vx-menu" aria-label="Principal" data-menu>
                <span class="vx-menu-luz" aria-hidden="true"></span>
                @foreach ($menu as $k => [$texto, $ref])
                    <a href="{{ $ref }}" data-menu-item="{{ $k }}" @if ($k === 0) aria-haspopup="true" aria-expanded="false" aria-controls="vx-mega" @endif><small>{{ $n2($k) }}</small><span data-descifra-hover>{{ $texto }}</span></a>
                @endforeach
            </nav>
            <button type="button" class="vx-buscar" data-paleta-abrir aria-label="Buscar en el sitio (tecla /)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                <span class="vx-kbd">/</span>
            </button>
            <span class="vx-reloj"><span class="vx-cuadro" aria-hidden="true"></span>Mérida <time data-reloj>--:--:--</time></span>
            <a href="#contacto" class="vx-cotizar">Cotizar <span class="vx-cotizar-caja">{!! $flecha !!}</span></a>
            <button type="button" class="vx-hamburguesa" data-movil-abrir aria-expanded="false" aria-controls="vx-movil" aria-label="Abrir menú">
                <span></span><span></span>
            </button>
            <span class="vx-avance-pag" data-avance-pag aria-hidden="true"></span>
        </div>
        <div class="vx-mega" id="vx-mega" data-mega>
            @foreach (array_chunk($servicios, 4, true) as $columna)
                <div class="vx-mega-col">
                    @foreach ($columna as $k => $s)
                        <a href="{{ $s['ruta'] }}" @isset($s['servicio']) data-servicio="{{ $s['servicio'] }}" @endisset><small>{{ $n2($k) }}</small><b>{{ $s['titulo'] }}</b><i aria-hidden="true">↗</i><span>{{ $s['corto'] }}</span></a>
                    @endforeach
                </div>
            @endforeach
            <div class="vx-mega-cta">
                <p class="vx-gris">¿No sabes por dónde empezar?</p>
                <p class="vx-mega-frase">Cuéntanos tu negocio y te decimos qué conviene primero.</p>
                <a href="#contacto">Platiquemos →</a>
            </div>
        </div>
        <div class="vx-movil" id="vx-movil" data-movil>
            @foreach ($secciones as $k => [$texto, $ref])
                <a href="{{ $ref }}"><small>{{ $n2($k) }}</small>{{ $texto }}</a>
            @endforeach
        </div>
    </header>

    {{-- ===== Portada ===== --}}
    <section class="vx-portada" id="inicio">
        <div class="vx-celdas" aria-hidden="true">
            <span style="left:960px;top:80px;animation-delay:0s"></span><span style="left:1200px;top:240px;animation-delay:1.4s"></span>
            <span style="left:1040px;top:400px;animation-delay:2.8s"></span><span style="left:1280px;top:80px;animation-delay:4.2s"></span>
            <span style="left:80px;top:640px;animation-delay:5.6s"></span><span style="left:1120px;top:560px;animation-delay:3.5s"></span>
        </div>
        <div class="vx-caja vx-portada-caja">
            <p class="vx-etiqueta vx-portada-etiqueta"><span data-descifra-carga>Agencia digital · Mérida, México</span><span class="vx-entra">Sitios · Sistemas · Video · Foto · Impresión</span></p>
            <h1 data-descifra>Que a tu negocio lo encuentren y lo elijan<span class="vx-verde">.</span></h1>
            <div class="vx-portada-pie vx-entra">
                <p>Diseñamos y programamos sitios y sistemas, y producimos el video, la foto y los impresos de tu marca. Un solo equipo para negocios de todo México.</p>
                <div class="vx-botones">
                    <a href="#contacto" class="vx-btn vx-btn-verde">Cotiza tu proyecto <span class="vx-fl" aria-hidden="true">→</span></a>
                    <a href="#trabajo" class="vx-btn vx-btn-linea">Ver el trabajo</a>
                </div>
            </div>
        </div>

        {{-- Planos: lo que construimos, dibujado en líneas. Con el cursor (o solos) se construyen --}}
        <div class="vx-caja" id="trabajo">
            <figure class="vx-ventana" data-aparece>
                <div class="vx-ventana-barra">
                    <span class="vx-puntos" aria-hidden="true"><i></i><i></i><i></i></span>
                    <span class="vx-ventana-titulo">Lo que construimos</span>
                    <span data-planos-contador>01 / 05</span>
                </div>
<div class="vx-planos" data-planos>

<div class="vx-plano" data-plano="0" data-cur="Construir" style="grid-row:span 2">
<svg viewBox="0 0 520 470" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
<rect class="g" x="20" y="20" width="480" height="430" rx="8"/><line class="g" x1="20" y1="50" x2="500" y2="50"/>
<rect class="a" pathLength="1" x="20" y="20" width="480" height="430" rx="8"/>
<rect class="g" x="44" y="68" width="56" height="14" rx="2"/><rect class="tx" x="330" y="72" width="26" height="6"/><rect class="tx" x="366" y="72" width="26" height="6" style="transition-delay:0.08s"/><rect class="tx" x="402" y="72" width="26" height="6" style="transition-delay:0.16s"/><rect class="g" x="440" y="64" width="40" height="20" rx="4"/>
<rect class="tx" x="44" y="118" width="230" height="22" style="transition-delay:0.24s"/><rect class="tx" x="44" y="148" width="180" height="22" style="transition-delay:0.32s"/>
<rect class="tx" x="44" y="186" width="200" height="7" style="transition-delay:0.40s"/><rect class="tx" x="44" y="200" width="160" height="7" style="transition-delay:0.48s"/>
<rect class="g" x="44" y="226" width="118" height="34" rx="5"/><rect class="f" x="44" y="226" width="118" height="34" rx="5" style="transition-delay:1.12s"/>
<rect class="g" x="300" y="112" width="176" height="148" rx="4"/><line class="g" x1="300" y1="112" x2="476" y2="260"/><line class="g" x1="476" y1="112" x2="300" y2="260"/>
<rect class="a" pathLength="1" x="300" y="112" width="176" height="148" rx="4" style="transition-delay:0.24s"/><rect class="ft" x="300" y="112" width="176" height="148" rx="4" style="transition-delay:0.48s"/>
<rect class="g" x="44" y="292" width="128" height="134" rx="4"/><rect class="g" x="196" y="292" width="128" height="134" rx="4"/><rect class="g" x="348" y="292" width="128" height="134" rx="4"/>
<rect class="a" pathLength="1" x="44" y="292" width="128" height="134" rx="4" style="transition-delay:0.48s"/><rect class="a" pathLength="1" x="196" y="292" width="128" height="134" rx="4" style="transition-delay:0.64s"/><rect class="a" pathLength="1" x="348" y="292" width="128" height="134" rx="4" style="transition-delay:0.80s"/>
<rect class="tx" x="60" y="380" width="80" height="7" style="transition-delay:0.64s"/><rect class="tx" x="212" y="380" width="80" height="7" style="transition-delay:0.80s"/><rect class="tx" x="364" y="380" width="80" height="7" style="transition-delay:0.96s"/>
<g class="cur"><path d="M0 0 L0 22 L6 16 L11 26 L15 24 L10 15 L18 15 Z" fill="#F5F6F7" stroke="#0B0C0E" stroke-width="1.5"/></g>
</svg>
<div class="vx-plano-pie"><span><b>01</b>&nbsp; Sitio corporativo</span><span class="vx-plano-estado"><i>plano</i><i>en línea</i></span></div>
</div>

<div class="vx-plano" data-plano="1" data-cur="Construir">
<svg viewBox="0 0 300 210" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
<rect class="g" x="14" y="12" width="272" height="188" rx="6"/><line class="g" x1="14" y1="36" x2="286" y2="36"/>
<path class="g" d="M252 20 h4 l4 10 h14 l3 -7 h-19"/><circle class="f" cx="280" cy="19" r="6" style="transition-delay:0.96s"/><text class="ap" x="280" y="22" font-size="8" text-anchor="middle" fill="#0B0C0E" font-family="Geist-semibold, sans-serif" style="transition-delay:0.96s">1</text>
<rect class="g" x="28" y="48" width="74" height="66" rx="3"/><rect class="g" x="113" y="48" width="74" height="66" rx="3"/><rect class="g" x="198" y="48" width="74" height="66" rx="3"/>
<rect class="g" x="28" y="122" width="74" height="66" rx="3"/><rect class="g" x="113" y="122" width="74" height="66" rx="3"/><rect class="g" x="198" y="122" width="74" height="66" rx="3"/>
<rect class="a" pathLength="1" x="28" y="48" width="74" height="66" rx="3"/><rect class="a" pathLength="1" x="113" y="48" width="74" height="66" rx="3" style="transition-delay:0.08s"/><rect class="a" pathLength="1" x="198" y="48" width="74" height="66" rx="3" style="transition-delay:0.16s"/>
<rect class="a" pathLength="1" x="28" y="122" width="74" height="66" rx="3" style="transition-delay:0.24s"/><rect class="a" pathLength="1" x="113" y="122" width="74" height="66" rx="3" style="transition-delay:0.32s"/><rect class="a" pathLength="1" x="198" y="122" width="74" height="66" rx="3" style="transition-delay:0.40s"/>
<rect class="ft" x="113" y="48" width="74" height="66" rx="3" style="transition-delay:0.72s"/>
<rect class="tx" x="36" y="96" width="40" height="5"/><rect class="tx" x="121" y="96" width="40" height="5" style="transition-delay:0.08s"/><rect class="tx" x="206" y="96" width="40" height="5" style="transition-delay:0.16s"/><rect class="tx" x="36" y="170" width="40" height="5" style="transition-delay:0.24s"/><rect class="tx" x="121" y="170" width="40" height="5" style="transition-delay:0.32s"/><rect class="tx" x="206" y="170" width="40" height="5" style="transition-delay:0.40s"/>
</svg>
<div class="vx-plano-pie"><span><b>02</b>&nbsp; Tienda en línea</span><span class="vx-plano-estado"><i>plano</i><i>1 en el carrito</i></span></div>
</div>

<div class="vx-plano" data-plano="2" data-cur="Construir">
<svg viewBox="0 0 300 210" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
<rect class="g" x="20" y="18" width="146" height="176" rx="6"/>
<rect class="g" x="34" y="40" width="118" height="22" rx="3"/><rect class="g" x="34" y="72" width="118" height="22" rx="3"/><rect class="g" x="34" y="104" width="118" height="44" rx="3"/>
<rect class="tx" x="42" y="49" width="60" height="5"/><rect class="tx" x="42" y="81" width="74" height="5" style="transition-delay:0.24s"/><rect class="tx" x="42" y="113" width="90" height="5" style="transition-delay:0.48s"/><rect class="tx" x="42" y="123" width="70" height="5" style="transition-delay:0.56s"/>
<rect class="g" x="34" y="160" width="118" height="22" rx="4"/><rect class="f" x="34" y="160" width="118" height="22" rx="4" style="transition-delay:0.72s"/>
<path class="g" d="M152 171 C190 171 186 108 222 108" stroke-dasharray="3 4"/><path class="a" pathLength="1" d="M152 171 C190 171 186 108 222 108" style="transition-delay:0.80s"/>
<rect class="g" x="222" y="52" width="58" height="112" rx="10"/><rect class="a" pathLength="1" x="222" y="52" width="58" height="112" rx="10" style="transition-delay:0.96s"/>
<rect class="f" x="230" y="94" width="40" height="18" rx="6" style="transition-delay:1.20s"/><path class="ap" d="M236 103 l4 4 l8 -8" fill="none" stroke="#0B0C0E" stroke-width="2" style="transition-delay:1.20s"/>
</svg>
<div class="vx-plano-pie"><span><b>03</b>&nbsp; Captación de prospectos</span><span class="vx-plano-estado"><i>plano</i><i>llegó a WhatsApp</i></span></div>
</div>

<div class="vx-plano" data-plano="3" data-cur="Construir">
<svg viewBox="0 0 300 210" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
<rect class="g" x="14" y="12" width="272" height="188" rx="6"/><line class="g" x1="70" y1="12" x2="70" y2="200"/>
<rect class="tx" x="24" y="28" width="34" height="6"/><rect class="tx" x="24" y="44" width="28" height="5" style="transition-delay:0.08s"/><rect class="tx" x="24" y="56" width="32" height="5" style="transition-delay:0.16s"/><rect class="tx" x="24" y="68" width="26" height="5" style="transition-delay:0.24s"/>
<rect class="g" x="84" y="26" width="58" height="34" rx="3"/><rect class="g" x="150" y="26" width="58" height="34" rx="3"/><rect class="g" x="216" y="26" width="58" height="34" rx="3"/>
<rect class="tx" x="92" y="40" width="34" height="9" style="transition-delay:0.16s"/><rect class="tx" x="158" y="40" width="30" height="9" style="transition-delay:0.24s"/><rect class="tx" x="224" y="40" width="38" height="9" style="transition-delay:0.32s"/>
<rect class="barra" x="92" y="90" width="18" height="96"/><rect class="barra" x="122" y="120" width="18" height="66" style="transition-delay:0.08s"/><rect class="barra" x="152" y="104" width="18" height="82" style="transition-delay:0.16s"/><rect class="barra" x="182" y="78" width="18" height="108" style="transition-delay:0.24s"/><rect class="barra" x="212" y="112" width="18" height="74" style="transition-delay:0.32s"/><rect class="barra" x="242" y="72" width="18" height="114" style="transition-delay:0.40s"/>
<line class="g" x1="84" y1="186" x2="274" y2="186"/>
</svg>
<div class="vx-plano-pie"><span><b>04</b>&nbsp; Sistema a la medida</span><span class="vx-plano-estado"><i>plano</i><i>en tiempo real</i></span></div>
</div>

<div class="vx-plano" data-plano="4" data-cur="Construir">
<svg viewBox="0 0 300 210" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
<rect class="g" x="14" y="12" width="272" height="188" rx="6"/><line class="g" x1="14" y1="36" x2="286" y2="36"/>
<rect class="g" x="40" y="52" width="100" height="62" rx="4"/><rect class="g" x="160" y="52" width="100" height="62" rx="4"/><rect class="g" x="40" y="124" width="100" height="62" rx="4"/><rect class="g" x="160" y="124" width="100" height="62" rx="4"/>
<rect class="ft" x="40" y="52" width="100" height="62" rx="4"/><rect class="ft" x="160" y="52" width="100" height="62" rx="4" style="transition-delay:0.16s"/><rect class="ft" x="40" y="124" width="100" height="62" rx="4" style="transition-delay:0.32s"/><rect class="ft" x="160" y="124" width="100" height="62" rx="4" style="transition-delay:0.48s"/>
<circle class="g gira" cx="90" cy="83" r="16"/><rect class="g gira" x="194" y="67" width="32" height="32" style="transition-delay:0.16s"/><path class="g gira" d="M90 139 l18 30 h-36 Z" style="transition-delay:0.32s"/><path class="g gira" d="M210 139 l5 10 l11 2 l-8 8 l2 11 l-10 -5 l-10 5 l2 -11 l-8 -8 l11 -2 Z" style="transition-delay:0.48s"/>
<circle class="a" pathLength="1" cx="90" cy="83" r="16"/><rect class="a" pathLength="1" x="194" y="67" width="32" height="32" style="transition-delay:0.16s"/>
</svg>
<div class="vx-plano-pie"><span><b>05</b>&nbsp; Varias marcas, un sitio</span><span class="vx-plano-estado"><i>plano</i><i>4 marcas</i></span></div>
</div>


</div>
                <figcaption class="vx-ventana-pie">
                    <span>Diseñados y programados por nosotros, sin plantillas.</span>
                    <span class="vx-verde vx-solo-escritorio">Pasa el cursor sobre un plano</span>
                </figcaption>
                <div class="vx-barra-avance"><span data-planos-avance></span></div>
            </figure>
        </div>
    </section>

    {{-- ===== Marcas ===== --}}
    <section class="vx-marcas" aria-label="Marcas que han confiado en nosotros">
        <p class="vx-marcas-titulo">Han confiado en nosotros</p>
        <div class="vx-marcas-pista">
            <div class="vx-cinta">
                @foreach ([false, true] as $copia)
                    @foreach ($clientes as [$archivo, $nombre, $alto])
                        <img src="{{ asset('img/logos-marcas/' . $archivo) }}" alt="{{ $copia ? '' : $nombre }}" @if ($copia) aria-hidden="true" @endif style="height:{{ $alto }}px" loading="lazy">
                    @endforeach
                @endforeach
            </div>
        </div>
    </section>

    {{-- ===== Servicios ===== --}}
    <section class="vx-claro vx-servicios" id="servicios">
        <div class="vx-caja">
            <div class="vx-encabezado">
                <div>
                    <p class="vx-num" data-aparece><b>01</b> Servicios</p>
                    <h2 data-descifra>Diseñamos, programamos y producimos.</h2>
                </div>
                <p class="vx-intro" data-aparece>Siete servicios, un solo equipo. No tienes que coordinar a nadie más.</p>
            </div>
            <div class="vx-raya" data-aparece></div>
            @foreach ($servicios as $k => $s)
                <a class="vx-fila" href="{{ $s['ruta'] }}" @isset($s['servicio']) data-servicio="{{ $s['servicio'] }}" @endisset data-aparece>
                    <span class="vx-fila-n">{{ $n2($k) }}</span>
                    <b>{{ $s['titulo'] }}</b>
                    <span class="vx-fila-d">{{ $s['texto'] }}</span>
                    <span class="vx-fila-t">@foreach ($s['etiquetas'] as $e)<span class="vx-tag">{{ $e }}</span>@endforeach</span>
                    <span class="vx-fila-f" aria-hidden="true">↗</span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- ===== Nerby: somos aliados; la IA se prueba aquí mismo ===== --}}
    <section class="vx-nerby" id="nerby">
        <div class="vx-caja">
            <div class="vx-nerby-sello" data-aparece>
                <img src="{{ asset('img/logo-vandu-blanco.svg') }}" alt="Agencia Vandu" height="22">
                <span class="vx-por" aria-hidden="true">×</span>
                <img src="{{ asset('img/inicio/nerby-logo-blanco.svg') }}" alt="Nerby" height="26">
                <span class="vx-nerby-aliados">Somos aliados de Nerby</span>
            </div>
            <div class="vx-encabezado">
                <h2 data-descifra>Que tu negocio trabaje para ti<span class="vx-morado">.</span></h2>
                <p class="vx-intro" data-aparece>Nerby junta ventas, cotizaciones, facturación y WhatsApp en un solo sistema, con inteligencia artificial que hace el trabajo pesado. En Vandu lo instalamos, lo configuramos y lo adaptamos a tu giro.</p>
            </div>
            <div class="vx-nerby-grid" data-nerby>
                <div>
                    <div class="vx-nerby-tabs" role="tablist" aria-label="Funciones de Nerby">
                        @foreach ($nerby as $k => $f)
                            <button type="button" role="tab" id="vx-ntab-{{ $k }}" aria-controls="vx-npanel-{{ $k }}" aria-selected="{{ $k === 0 ? 'true' : 'false' }}" @class(['vx-ntab', 'vx-on' => $k === 0]) data-ntab="{{ $k }}" data-ventana="{{ $f['ventana'] }}">
                                <span class="vx-ntab-n">{{ $n2($k) }}</span>
                                <span class="vx-ntab-t">{{ $f['titulo'] }} @if ($f['ia'])<span class="vx-ia">IA</span>@endif</span>
                                <span></span>
                                <span class="vx-ntab-d">{{ $f['texto'] }}</span>
                            </button>
                        @endforeach
                    </div>
                    <div class="vx-nerby-cta">
                        <a href="{{ $whatsapp }}" data-servicio="Otro" data-mensaje="Quiero una demo de Nerby." class="vx-nbtn">Pide tu demo de Nerby <span aria-hidden="true">→</span></a>
                        <img src="{{ asset('img/inicio/nerby-mascota.webp') }}" alt="" width="64" height="75" loading="lazy">
                    </div>
                </div>

                <figure class="vx-nerby-ventana" data-aparece>
                    <div class="vx-nerby-barra">
                        <img src="{{ asset('img/inicio/nerby-logo-blanco.svg') }}" alt="" height="16"><span data-nerby-ventana>Gastos</span><span class="vx-nerby-ej">Ejemplo</span>
                    </div>

                    <div class="vx-npanel" id="vx-npanel-0" role="tabpanel" aria-labelledby="vx-ntab-0" data-npanel="0">
                        <div class="vx-ticket-grid" data-ticket>
                            <div class="vx-ticket" aria-label="Ticket de ejemplo de Ferretería Itzimná por $791.00">
                                <p class="vx-ticket-c"><b>FERRETERÍA ITZIMNÁ</b></p>
                                <p class="vx-ticket-c vx-ticket-gris">Mérida, Yuc.</p>
                                <p><span>28/10/2026</span><span>14:32</span></p>
                                <p class="vx-ticket-sep"><span>2 CINTA MASKING</span><span>90.00</span></p>
                                <p><span>1 BROCHA 3"</span><span>89.00</span></p>
                                <p><span>1 PINTURA 4 L</span><span>612.00</span></p>
                                <p class="vx-ticket-sep"><span>SUBTOTAL</span><span>681.90</span></p>
                                <p><span>IVA 16%</span><span>109.10</span></p>
                                <p><b>TOTAL</b><b>791.00</b></p>
                                <p class="vx-ticket-borroso">PAGO: T•••••</p>
                                <span class="vx-escaneo" aria-hidden="true"></span>
                            </div>
                            <div>
                                <p class="vx-nerby-gris">Nuevo gasto · leído de la foto</p>
                                @foreach ($ticket as $k => [$etiqueta, $valor])
                                    <div class="vx-campo"><span>{{ $etiqueta }}</span><span class="vx-campo-val" data-campo="{{ $k }}" data-valor="{{ $valor }}"></span></div>
                                @endforeach
                                <p class="vx-aviso" data-ticket-aviso aria-live="polite"></p>
                                <div class="vx-ticket-botones">
                                    <button type="button" class="vx-nbtn" data-ticket-leer>Leer ticket con IA</button>
                                    <button type="button" class="vx-nbtn vx-nbtn-linea" data-ticket-ok hidden>Confirmar gasto</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="vx-npanel" id="vx-npanel-1" role="tabpanel" aria-labelledby="vx-ntab-1" data-npanel="1" hidden>
                        <img src="{{ asset('img/inicio/nerby-facturas.webp') }}" alt="Pantalla de facturación de Nerby" loading="lazy" width="1400" height="875">
                    </div>
                    <div class="vx-npanel vx-npanel-movil" id="vx-npanel-2" role="tabpanel" aria-labelledby="vx-ntab-2" data-npanel="2" hidden>
                        <img src="{{ asset('img/inicio/nerby-movil.webp') }}" alt="Nerby en el celular" loading="lazy" width="600" height="1319">
                    </div>
                </figure>
            </div>
        </div>
    </section>

    {{-- ===== Foto, video e impresión ===== --}}
    <section class="vx-foto" id="foto">
        <div class="vx-caja">
            <div class="vx-encabezado">
                <div>
                    <p class="vx-num" data-aparece><b>02</b> Foto, video e impresión</p>
                    <h2 data-descifra>Lo que tu cliente ve antes de entrar.</h2>
                </div>
                <a href="{{ route('audiovisuales') }}" class="vx-enlace" data-aparece>Ver audiovisuales <span class="vx-fl" aria-hidden="true">→</span></a>
            </div>
            <div class="vx-galeria">
                <figure class="vx-g-grande" data-aparece data-cur="Ver"><a href="{{ route('audiovisuales') }}"><img src="{{ asset('img/inicio/tokyo-tyger-fachada.webp') }}" alt="Fotografía de la fachada de Tokyo Tyger" loading="lazy"><figcaption>Tokyo Tyger · Fotografía de fachada</figcaption></a></figure>
                <figure data-aparece data-cur="Ver"><a href="{{ route('audiovisuales') }}"><img src="{{ asset('img/hero/kuro-23.webp') }}" alt="Platillo de Kuro" loading="lazy"><figcaption>Kuro · Alimentos</figcaption></a></figure>
                <figure data-aparece data-cur="Ver"><a href="{{ route('audiovisuales') }}">
                    <video muted loop playsinline preload="none" poster="{{ asset('img/inicio/video-comida.webp') }}" data-src="{{ asset('img/inicio/video-comida.mp4') }}" aria-label="Reel para restaurante"></video>
                    <figcaption>Reel · Video</figcaption></a></figure>
                <figure data-aparece data-cur="Ver"><a href="{{ route('audiovisuales') }}"><img src="{{ asset('img/inicio/samma-obra.webp') }}" alt="Fotografía de obra para Samma" loading="lazy"><figcaption>Samma · Arquitectura</figcaption></a></figure>
                <figure data-aparece data-cur="Ver"><a href="{{ route('audiovisuales') }}"><img src="{{ asset('img/hero/impresos-tarjetas.webp') }}" alt="Tarjetas impresas" loading="lazy"><figcaption class="vx-oscuro-txt">Impresos</figcaption></a></figure>
            </div>
        </div>
    </section>

    {{-- ===== Proceso ===== --}}
    <section class="vx-claro vx-proceso" id="proceso">
        <div class="vx-caja">
            <p class="vx-num" data-aparece><b>03</b> Cómo trabajamos</p>
            <h2 data-descifra>De la primera plática a la entrega.</h2>
            <div class="vx-proceso-linea" aria-hidden="true"><span data-proceso-linea></span></div>
            <ol class="vx-pasos">
                @foreach ($pasos as $k => [$titulo, $texto])
                    <li data-aparece>
                        <span class="vx-paso-n">{{ $n2($k) }}</span>
                        <h3 data-descifra>{{ $titulo }}</h3>
                        <p>{{ $texto }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- ===== Testimonio y datos ===== --}}
    <section class="vx-clientes">
        <div class="vx-caja">
            <p class="vx-num" data-aparece><b>04</b> Clientes</p>
            <blockquote data-aparece>
                <p>“Hemos trabajado juntos desde hace más de 4 años y siempre terminamos satisfechos. 100% recomendados.”</p>
                <footer><span class="vx-guion" aria-hidden="true"></span><span><b>Alejandro Canto</b><br>Director, Grupo Osorio</span></footer>
            </blockquote>
            <div class="vx-raya vx-raya-clara" data-aparece></div>
            <dl class="vx-datos">
                <div data-aparece><dt>Base</dt><dd>Mérida, Yucatán</dd></div>
                <div data-aparece><dt>Cobertura</dt><dd>Todo México, a distancia</dd></div>
                <div data-aparece><dt>Equipo</dt><dd>Diseño, código y producción</dd></div>
            </dl>
        </div>
    </section>

    {{-- ===== Preguntas ===== --}}
    <section class="vx-preguntas" id="preguntas">
        <div class="vx-caja vx-preguntas-grid">
            <div>
                <p class="vx-num" data-aparece><b>05</b> Preguntas</p>
                <h2 data-descifra>Lo que más nos preguntan.</h2>
            </div>
            <div class="vx-faq">
                @foreach ($preguntas as $k => [$p, $r])
                    <details @if ($k === 0) open @endif data-aparece>
                        <summary>{{ $p }} <span aria-hidden="true"></span></summary>
                        <p>{{ $r }}</p>
                    </details>
                @endforeach
            </div>
        </div>
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => collect($preguntas)->map(fn ($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]])->all(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    </section>

    {{-- ===== Contacto paso a paso ===== --}}
    <section class="vx-contacto" id="contacto">
        <div class="vx-caja vx-contacto-grid">
            <div>
                <h2 data-descifra>¿Platicamos?</h2>
                <p class="vx-contacto-txt" data-aparece>Cuéntanos qué necesitas y te decimos cuál es la mejor opción para tu negocio y tu presupuesto.</p>
            </div>
            <div class="vx-conv" data-conv data-aparece>
                <div class="vx-conv-pasos" aria-hidden="true"><i></i><i></i><i></i></div>

                <div class="vx-conv-paso" data-paso="0">
                    <p class="vx-conv-n">1 de 3</p>
                    <p class="vx-conv-p" id="vx-p1">¿Qué necesitas?</p>
                    <div class="vx-ops" role="group" aria-labelledby="vx-p1">
                        @foreach ($necesidades as [$texto, $servicio])
                            <button type="button" class="vx-op" data-necesidad="{{ $texto }}" data-servicio="{{ $servicio }}" aria-pressed="false">{{ $texto }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="vx-conv-paso" data-paso="1" hidden>
                    <p class="vx-conv-n">2 de 3</p>
                    <label class="vx-conv-p" for="vx-negocio">¿Cómo se llama tu negocio?</label>
                    <input id="vx-negocio" type="text" maxlength="120" autocomplete="organization" placeholder="Ej. Panadería La Espiga" data-negocio>
                </div>
                <div class="vx-conv-paso" data-paso="2" hidden>
                    <p class="vx-conv-n">3 de 3</p>
                    <p class="vx-conv-p" id="vx-p3">¿Para cuándo lo necesitas?</p>
                    <div class="vx-ops" role="group" aria-labelledby="vx-p3">
                        @foreach ($cuando as $texto)
                            <button type="button" class="vx-op" data-cuando="{{ $texto }}" aria-pressed="false">{{ $texto }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="vx-conv-paso" data-paso="3" hidden>
                    <p class="vx-conv-n">Tu mensaje, listo para enviar</p>
                    <p class="vx-burbuja" data-conv-mensaje aria-live="polite"></p>
                    <div class="vx-conv-botones">
                        <a class="vx-sig" href="{{ $whatsapp }}" data-conv-enviar>Enviar por WhatsApp →</a>
                        <button type="button" class="vx-sig vx-sig-linea" data-llamar>Prefiero que me llamen</button>
                    </div>
                </div>
                <div class="vx-conv-paso vx-conv-form" data-paso="4" hidden>
                    <p class="vx-conv-p">Déjanos tus datos y te llamamos.</p>
                    <x-form />
                </div>

                <div class="vx-conv-nav">
                    <button type="button" class="vx-atras" data-conv-atras hidden>← Atrás</button>
                    <button type="button" class="vx-sig" data-conv-sig disabled>Siguiente →</button>
                </div>
                <div class="vx-correo">
                    <span>O escribe a</span>
                    <input type="text" class="correo" value="proyectos@agenciavandu.com" readonly aria-label="Correo de contacto">
                    <button type="button" aria-label="Copiar correo"><img src="{{ asset('img/copy.svg') }}" class="copiado" width="14" alt=""></button>
                    <span id="message" aria-live="polite"></span>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== Mapa de la página ===== --}}
    <nav class="vx-riel" aria-label="Secciones de la página">
        @foreach ($secciones as $k => [$texto, $ref])
            <a href="{{ $ref }}" data-riel="{{ $ref }}"><i></i><b>{{ $n2($k) }}</b><span>{{ $texto }}</span></a>
        @endforeach
    </nav>

    {{-- ===== Buscador rápido (tecla /) ===== --}}
    <div class="vx-paleta" data-paleta hidden>
        <div class="vx-paleta-caja" role="dialog" aria-modal="true" aria-label="Buscar en el sitio">
            <input type="text" placeholder="Busca: sitio web, Nerby, fotos, cotizar…" data-paleta-q aria-label="Buscar" autocomplete="off">
            <div class="vx-paleta-res" data-paleta-res role="listbox">
                @foreach ($buscable as [$texto, $ref, $tipo])
                    <a href="{{ $ref }}" role="option" data-paleta-op data-texto="{{ \Illuminate\Support\Str::ascii(mb_strtolower($texto . ' ' . $tipo)) }}"><span>{{ $texto }}</span><small>{{ $tipo }}</small></a>
                @endforeach
                <p class="vx-paleta-nada" data-paleta-nada hidden>Nada con eso. ¿Lo platicamos por WhatsApp?</p>
            </div>
            <p class="vx-paleta-pie"><span><span class="vx-kbd">↑</span> <span class="vx-kbd">↓</span> moverte</span><span><span class="vx-kbd">Enter</span> abrir</span><span><span class="vx-kbd">Esc</span> cerrar</span></p>
        </div>
    </div>

    {{-- ===== Botón flotante de WhatsApp ===== --}}
    <div class="vx-wa" data-wa>
        <div class="vx-wa-panel" id="vx-wa-panel" data-wa-panel>
            <div class="vx-wa-cab">
                <p class="vx-wa-meta"><span>WhatsApp · Agencia Vandu</span><span>Mérida <time data-reloj>--:--</time></span></p>
                <p class="vx-wa-titulo">¿En qué te ayudamos?</p>
            </div>
            @foreach ($temasWa as $k => [$texto, $servicio, $mensaje])
                <a href="{{ $whatsapp }}" data-servicio="{{ $servicio }}" @if ($mensaje) data-mensaje="{{ $mensaje }}" @endif><small>{{ $n2($k) }}</small><span>{{ $texto }}</span><i aria-hidden="true">↗</i></a>
            @endforeach
            <p class="vx-wa-nota">Se abre WhatsApp con tu mensaje listo para enviar.</p>
        </div>
        <button type="button" class="vx-wa-btn" data-wa-btn aria-expanded="false" aria-controls="vx-wa-panel" aria-label="Escríbenos por WhatsApp">
            <span class="vx-wa-ico">
                <svg class="vx-wa-w" width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91C21.95 6.45 17.5 2 12.04 2zm5.8 14.02c-.24.68-1.41 1.3-1.95 1.35-.5.05-1.13.07-1.82-.11-.42-.13-.96-.31-1.65-.61-2.9-1.25-4.8-4.17-4.94-4.36-.14-.19-1.18-1.57-1.18-3 0-1.43.75-2.13 1.02-2.42.27-.29.58-.36.78-.36h.56c.18 0 .42-.07.66.5.24.58.82 2.01.89 2.16.07.14.12.31.02.5-.1.19-.14.31-.29.48-.14.17-.3.38-.43.51-.14.14-.29.3-.13.59.17.29.74 1.22 1.59 1.98 1.09.97 2.01 1.27 2.3 1.42.29.14.46.12.63-.07.17-.19.72-.84.91-1.13.19-.29.38-.24.65-.14.26.1 1.68.79 1.97.94.29.14.48.22.55.34.07.12.07.7-.17 1.38z"/></svg>
                <svg class="vx-wa-x" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </span>
            <span class="vx-wa-txt"><b data-wa-texto>Escríbenos</b><small><span class="vx-cuadro" aria-hidden="true"></span>Respondemos por WhatsApp</small></span>
            <span class="vx-pts" aria-hidden="true"><i></i><i></i><i></i></span>
            <span class="vx-psst" aria-hidden="true">psst</span>
        </button>
    </div>

    {{-- ===== Cursor ===== --}}
    <div class="vx-mira" data-mira aria-hidden="true"><span class="vx-mira-anillo"></span><span class="vx-mira-punto"></span><span class="vx-mira-etq" data-mira-etq></span></div>

</x-layouts.guest>
