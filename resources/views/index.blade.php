@push('estilos')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&display=swap">
    @vite(['resources/scss/app.scss', 'resources/scss/global.scss', 'resources/scss/editorial.scss', 'resources/js/app.js', 'resources/js/hero.js'])
@endpush
@php
    $whatsapp = 'https://wa.me/529991460310';

    $clientes = [
        ['KFC.svg', 'KFC', true], ['burger-king.svg', 'Burger King', true], ['pizza-hut.svg', 'Pizza Hut', true], ['gpo-nicxa.svg', 'Grupo Nicxa', false],
        ['gran-calzada.svg', 'Gran Calzada', false], ['amavita.svg', 'Amavita', false], ['osroca.svg', 'Osroca', false], ['tokio07.svg', 'Tokio 07', true],
        ['turismaya.svg', 'Turismaya', false], ['saama-gray.svg', 'Saama', false], ['casitas 1.svg', 'Casitas', false],
    ];

    // Índice de la página (anclas)
    $indice = [
        ['#problema', 'Lo que vemos'], ['#servicios', 'Qué hacemos'], ['#trabajo', 'Sitios web'], ['#sistemas', 'Sistemas'],
        ['#contenido', 'Video, foto y diseño'], ['#metodo', 'Cómo trabajamos'], ['#diferencia', 'La diferencia'], ['#preguntas', 'Preguntas'],
    ];

    $problemas = [
        ['titulo' => 'Se ve distinto en cada lugar', 'texto' => 'El sitio dice una cosa, Instagram otra y la lona del local otra. El cliente no sabe a quién le está comprando.'],
        ['titulo' => 'Los mensajes se pierden', 'texto' => 'Te escriben por WhatsApp, nadie da seguimiento y la venta se va con el que contestó primero.'],
        ['titulo' => 'Todo pasa por ti', 'texto' => 'Cotizaciones, pedidos y pendientes viven en tu celular. Si tú no estás, el negocio se detiene.'],
    ];

    // Sitios web destacados. Cada clip vive en public/img/inicio/{video}.mp4 con su imagen fija {video}.webp
    $sitios = collect([
        ['video' => 'web-stev', 'cliente' => 'Stev Seguridad Privada', 'tipo' => 'Sitio corporativo', 'detalle' => 'Traslado y custodia de valores con cobertura nacional: servicios, tecnología y contacto directo por WhatsApp.'],
        ['video' => 'web-maxispace', 'cliente' => 'Maxispace', 'tipo' => 'Sitio y captación de prospectos', 'detalle' => 'Mini bodegas en renta: tamaños, ubicaciones con mapa y formulario que llega directo a WhatsApp.'],
        ['video' => 'web-osorio', 'cliente' => 'Grupo Osorio', 'tipo' => 'Sitio corporativo', 'detalle' => 'Sitio para un grupo con más de 30 años de experiencia y varias líneas de negocio.'],
        ['video' => 'web-rewear', 'cliente' => 'Rewear', 'tipo' => 'Tienda en línea', 'detalle' => 'Catálogo de ropa con fichas de producto, tallas y colores.'],
        ['video' => 'web-nicxa1', 'cliente' => 'Grupo Nicxa', 'tipo' => 'Sitio para grupo de franquicias', 'detalle' => 'Un solo sitio para KFC, Burger King, Pizza Hut y Bisquets Obregón, con promociones por marca.'],
    ])->filter(fn ($s) => file_exists(public_path('img/inicio/' . $s['video'] . '.mp4')))->values();

    $trabajos = [
        ['clase' => 'ed-g-a', 'img' => 'tokyo-tyger-fachada.webp', 'cliente' => 'Tokyo Tyger', 'servicio' => 'Fotografía de restaurante', 'ruta' => 'audiovisuales'],
        ['clase' => 'ed-g-b', 'img' => 'foto-kuro.webp', 'cliente' => 'Kuro', 'servicio' => 'Fotografía de alimentos', 'ruta' => 'audiovisuales'],
        ['clase' => 'ed-g-c', 'video' => 'video-comida', 'cliente' => 'Reel para restaurante', 'servicio' => 'Video para redes', 'ruta' => 'audiovisuales'],
        ['clase' => 'ed-g-d', 'img' => 'antelar-cuarto.webp', 'cliente' => 'Antelar', 'servicio' => 'Fotografía de interiores', 'ruta' => 'audiovisuales'],
        ['clase' => 'ed-g-e', 'img' => 'campana-burger-king.webp', 'cliente' => 'Burger King', 'servicio' => 'Campaña Real Whopper Beach', 'ruta' => null],
        ['clase' => 'ed-g-f', 'img' => 'samma-obra.webp', 'cliente' => 'Samma', 'servicio' => 'Fotografía de obra', 'ruta' => 'audiovisuales'],
    ];

    $sistemas = [
        ['id' => 'whatsapp', 'titulo' => 'Ventas por WhatsApp', 'texto' => 'Cada mensaje se vuelve un prospecto o pedido en tu panel, con seguimiento y recordatorios.'],
        ['id' => 'proyectos', 'titulo' => 'Gestión de proyectos', 'texto' => 'Cotizaciones, avances por etapa y entregables en un portal para tu equipo y tus clientes.'],
        ['id' => 'inventario', 'titulo' => 'Inventario y stock', 'texto' => 'Entradas, salidas y existencias al momento, con aviso por WhatsApp cuando algo se acaba.'],
        ['id' => 'ventas', 'titulo' => 'Embudo de ventas', 'texto' => 'Todos tus prospectos por etapa, con valor en dinero, tareas e historial de cada contacto.'],
    ];

    $servicios = [
        ['titulo' => 'Sitios web y SEO', 'texto' => 'Sitios a la medida, tiendas en línea, SEO y GEO, hosting y mantenimiento.', 'ruta' => route('servicios')],
        ['titulo' => 'Sistemas y automatización', 'texto' => 'Paneles, portales y procesos conectados a WhatsApp: ventas, proyectos, inventario.', 'ruta' => '#sistemas'],
        ['titulo' => 'Video y video con IA', 'texto' => 'Reels, video corporativo, tomas con dron, comerciales y presentadores hechos con IA.', 'ruta' => '#contenido'],
        ['titulo' => 'Fotografía', 'texto' => 'Alimentos, interiores, arquitectura, retrato empresarial y eventos.', 'ruta' => route('audiovisuales')],
        ['titulo' => 'Redes sociales', 'texto' => 'Manejo mensual de Instagram, Facebook, TikTok y LinkedIn, con reporte de resultados.', 'ruta' => $whatsapp, 'servicio' => 'Redes sociales'],
        ['titulo' => 'Producción e impresos', 'texto' => 'Impresión, rotulación e instalación, bordado y artículos promocionales.', 'ruta' => $whatsapp, 'servicio' => 'Impresión y rotulación'],
        ['titulo' => 'Diseño digital', 'texto' => 'Campañas, promociones, presentaciones y material para punto de venta.', 'ruta' => $whatsapp, 'servicio' => 'Otro'],
    ];

    $pasos = [
        ['titulo' => 'Platicamos', 'texto' => 'Por WhatsApp o en llamada. Entendemos tu negocio y lo que necesitas.'],
        ['titulo' => 'Cotización en 24 h', 'texto' => 'Una cotización clara, por etapas, que revisas y aceptas desde tu portal.'],
        ['titulo' => 'Anticipo y producción', 'texto' => 'Con el anticipo arrancamos. En sitios web es el 50%; en foto y video el saldo se liquida antes de la sesión.'],
        ['titulo' => 'Entrega', 'texto' => 'Recibes tus archivos y avances en tu portal, listos para usar.'],
    ];

    $comparacion = [
        ['Equipo', 'Un proveedor para cada cosa', 'Un solo equipo: web, sistemas, video, foto e impresión'],
        ['Cotización', 'Días o semanas de espera', 'En menos de 24 horas'],
        ['Seguimiento', 'Mensajes sueltos y correos', 'Un portal con avances y entregables'],
        ['Contenido', 'Fotos de banco de imágenes', 'Foto y video propios, también hechos con IA'],
        ['Búsqueda', 'Solo pensar en Google', 'SEO y GEO: Google y respuestas de ChatGPT'],
        ['Después de entregar', 'Cada quien por su lado', 'Hosting, dominio y mantenimiento con nosotros'],
    ];

    $preguntas = [
        ['¿En cuánto tiempo recibo mi cotización?', 'En menos de 24 horas. Platicamos por WhatsApp o en llamada, entendemos lo que necesitas y te mandamos una cotización por etapas que puedes aceptar desde tu portal.'],
        ['¿Cómo se paga?', 'Con un anticipo arrancamos. En sitios web es el 50%; en fotografía y video el saldo se liquida antes de la sesión.'],
        ['¿Trabajan con negocios fuera de Mérida?', 'Sí. Nuestra base está en Mérida, Yucatán, pero trabajamos con negocios de todo México con una forma de trabajo pensada para hacerlo a distancia.'],
        ['¿Qué es SEO y GEO?', 'SEO es lo que hace que tu sitio aparezca en Google. GEO es lo mismo pero para las respuestas de inteligencia artificial como ChatGPT. Preparamos tu sitio para las dos.'],
        ['¿Ustedes se encargan del hosting?', 'Sí. Nos encargamos del dominio, el servidor y las actualizaciones para que tú no tengas que pensar en eso.'],
        ['¿Qué tipo de video hacen con IA?', 'Comerciales y anuncios, presentadores virtuales, animación de producto y fotos, y versiones de tus videos en otros idiomas.'],
    ];

    $testimonio = [
        'frase' => '¡Es un equipo maravilloso! Hemos trabajado juntos desde hace más de 4 años y siempre terminamos satisfechos. 100% recomendados.',
        'nombre' => 'Alejandro Canto', 'puesto' => 'Director', 'empresa' => 'Grupo Osorio',
    ];
    $n2 = fn ($i) => str_pad($i + 1, 2, '0', STR_PAD_LEFT);
@endphp
<x-layouts.guest titulo="Páginas web, sistemas y video con IA en Mérida" tema="editorial"
    meta-description="Agencia en Mérida para negocios de todo México: páginas web con SEO, sistemas conectados a WhatsApp, video con IA, fotografía e impresión. Cotiza en 24 h.">

    @push('estilos')
        <script type="application/ld+json">
        {!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn ($p) => ['@type' => 'Question', 'name' => $p[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $p[1]]], $preguntas)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    @endpush

    {{-- Portada --}}
    <section class="ed-oscuro ed-portada">
        <div class="ed-caja">
            <div class="ed-meta">
                <span>Agencia Vandu</span>
                <span>Mérida, Yucatán · para todo México</span>
                <span>Web · Sistemas · Video · Foto · Producción</span>
            </div>

            <h1 class="ed-titular">Que te encuentren.<br>Que te <em>elijan.</em></h1>

            <div class="ed-portada-pie">
                <div>
                    <p class="ed-entrada">Somos un equipo en Mérida que diseña, programa, graba y produce para negocios de todo México. De la primera idea a lo que tu cliente ve.</p>
                    <p class="ed-golpe">A tu negocio no le falta calidad.<br>Le falta que se note.</p>
                </div>
                <div class="ed-acciones">
                    <a class="ed-btn ed-btn-verde" href="{{ $whatsapp }}" target="_blank" rel="noopener">Cotiza tu proyecto <span aria-hidden="true">→</span></a>
                    <a class="ed-enlace" href="#trabajo">Ver el trabajo <span aria-hidden="true">↓</span></a>
                </div>
            </div>
        </div>

        <figure class="ed-reel">
            <video muted loop playsinline preload="none" poster="{{ asset('img/inicio/video-reel.webp') }}" data-src="{{ asset('img/inicio/video-reel.mp4') }}"></video>
            <figcaption><span>Demo reel</span><span>Video corporativo y dron</span></figcaption>
        </figure>
    </section>

    {{-- Triada --}}
    <section class="ed-oscuro ed-triada" aria-label="Lo que hacemos">
        <div class="ed-caja">
            <a href="#trabajo"><span class="ed-num">01</span><span>Lo diseñamos.</span></a>
            <a href="#sistemas"><span class="ed-num">02</span><span>Lo construimos.</span></a>
            <a href="#contenido"><span class="ed-num">03</span><span>Lo hacemos <em>crecer.</em></span></a>
        </div>
    </section>

    {{-- Marcas --}}
    <section class="ed-oscuro ed-marcas">
        <div class="ed-caja">
            <p class="ed-etiqueta">Marcas que trabajan con nosotros</p>
            <ul class="ed-muro">
                @foreach ($clientes as [$archivo, $nombre, $cuadrado])
                    <li><img @class(['ed-cuadrado' => $cuadrado]) src="{{ asset('img/logos-marcas/' . $archivo) }}" alt="{{ $nombre }}" loading="lazy"></li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Definición + índice --}}
    <section class="ed-claro ed-definicion">
        <div class="ed-caja ed-dos">
            <nav class="ed-indice" aria-label="En esta página">
                <p class="ed-etiqueta">En esta página</p>
                <ol>
                    @foreach ($indice as $i => [$ancla, $texto])
                        <li><a href="{{ $ancla }}"><span>{{ $n2($i) }}</span>{{ $texto }}</a></li>
                    @endforeach
                </ol>
            </nav>
            <div class="ed-diccionario">
                <p class="ed-palabra">marca <span>/már·ka/</span> <i>sustantivo femenino</i></p>
                <ol>
                    <li>Lo que la gente dice de tu negocio cuando tú no estás.</li>
                    <li>Lo que hace que te elijan aunque haya otro más barato a dos cuadras.</li>
                </ol>
                <p class="ed-texto">Nuestro trabajo es que las dos coincidan con lo que tú quieres que digan: en tu sitio, en tus fotos, en tus videos y en tu mostrador.</p>
            </div>
        </div>
    </section>

    {{-- Problema --}}
    <section class="ed-claro ed-problema" id="problema">
        <div class="ed-caja">
            <header class="ed-cabeza">
                <p class="ed-etiqueta">Lo que vemos</p>
                <h2>Casi nunca falta talento.<br>Falta <em>orden.</em></h2>
            </header>
            <ol class="ed-tres">
                @foreach ($problemas as $i => $p)
                    <li>
                        <span class="ed-num">{{ $n2($i) }}</span>
                        <h3>{{ $p['titulo'] }}</h3>
                        <p>{{ $p['texto'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Servicios --}}
    <section class="ed-claro ed-servicios" id="servicios">
        <div class="ed-caja">
            <header class="ed-cabeza">
                <p class="ed-etiqueta">Qué hacemos</p>
                <h2>Todo lo que tu marca necesita, <em>con un solo equipo.</em></h2>
            </header>
            <ol class="ed-filas">
                @foreach ($servicios as $i => $s)
                    <li>
                        <a href="{{ $s['ruta'] }}" @if (str_starts_with($s['ruta'], 'https://')) target="_blank" rel="noopener" @endif @isset($s['servicio']) data-servicio="{{ $s['servicio'] }}" @endisset>
                            <span class="ed-num">{{ $n2($i) }}</span>
                            <b>{{ $s['titulo'] }}</b>
                            <span class="ed-fila-texto">{{ $s['texto'] }}</span>
                            <span class="ed-flecha" aria-hidden="true">→</span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Sitios web --}}
    <section class="ed-claro ed-sitios" id="trabajo">
        <div class="ed-caja">
            <header class="ed-cabeza ed-cabeza-lado">
                <div>
                    <p class="ed-etiqueta">Sitios web</p>
                    <h2>Sitios que trabajan por tu negocio <em>todos los días.</em></h2>
                </div>
                <p>Diseñados y programados a la medida, sin plantillas. Rápidos, fáciles de administrar y preparados para que te encuentren en Google y en ChatGPT.</p>
            </header>

            @if ($sitios->isNotEmpty())
                <div class="ed-sitios-grid">
                    <div class="ed-pantalla vi-navegador">
                        <video class="vi-navegador-video" muted playsinline preload="none"
                            poster="{{ asset('img/inicio/' . $sitios[0]['video'] . '.webp') }}"
                            data-src="{{ asset('img/inicio/' . $sitios[0]['video'] . '.mp4') }}"></video>
                        <p class="ed-pantalla-pie"><span class="vi-navegador-url">{{ $sitios[0]['cliente'] }}</span><span>Grabación del sitio en vivo</span></p>
                    </div>
                    <ol class="ed-proyectos">
                        @foreach ($sitios as $i => $sitio)
                            <li>
                                <button type="button" @class(['vi-proyecto', 'activo' => $i === 0])
                                    data-video="{{ asset('img/inicio/' . $sitio['video'] . '.mp4') }}"
                                    data-poster="{{ asset('img/inicio/' . $sitio['video'] . '.webp') }}"
                                    data-cliente="{{ $sitio['cliente'] }}">
                                    <span class="ed-num">{{ $n2($i) }}</span>
                                    <span><b>{{ $sitio['cliente'] }}</b><small>{{ $sitio['tipo'] }}</small><span class="ed-proyecto-detalle">{{ $sitio['detalle'] }}</span></span>
                                </button>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif

            <ul class="ed-cuatro">
                <li><b>A la medida</b>Diseño propio para tu marca, sin plantillas.</li>
                <li><b>SEO y GEO</b>Listo para aparecer en Google y en respuestas de ChatGPT.</li>
                <li><b>Fácil de administrar</b>Panel para cambiar contenido y dar seguimiento a prospectos.</li>
                <li><b>Hosting y mantenimiento</b>Nos encargamos de dominio, servidor y actualizaciones.</li>
            </ul>
            <a class="ed-enlace" href="{{ route('servicios') }}">Más sobre sitios web <span aria-hidden="true">→</span></a>
        </div>
    </section>

    {{-- Sistemas --}}
    <section class="ed-oscuro ed-sistemas" id="sistemas">
        <div class="ed-caja">
            <header class="ed-cabeza ed-cabeza-lado">
                <div>
                    <p class="ed-etiqueta">Sistemas a la medida</p>
                    <h2>Procesos que trabajan solos <em>y hablan WhatsApp.</em></h2>
                </div>
                <p>Tus clientes siguen escribiendo por WhatsApp. Tú lo ves todo ordenado en un solo lugar, sin hojas de cálculo ni mensajes perdidos.</p>
            </header>

            <div class="ed-sistemas-grid">
                <div class="ed-pestanas" role="tablist" aria-label="Tipos de sistema">
                    @foreach ($sistemas as $i => $sis)
                        <button type="button" role="tab" id="tab-{{ $sis['id'] }}" aria-controls="vista-{{ $sis['id'] }}"
                            aria-selected="{{ $i === 0 ? 'true' : 'false' }}" @class(['vi-pestana', 'activo' => $i === 0])>
                            <span class="ed-num">{{ $n2($i) }}</span>
                            <span><b>{{ $sis['titulo'] }}</b><span>{{ $sis['texto'] }}</span></span>
                        </button>
                    @endforeach
                </div>

                <div class="ed-panel">
                    <div class="vi-vista ed-vista-chat" id="vista-whatsapp" role="tabpanel" aria-labelledby="tab-whatsapp">
                        <div class="ed-chat">
                            <p class="cliente">Hola, ¿tienen bodegas de 3×3 disponibles?</p>
                            <p class="negocio">¡Hola! Sí, tenemos en dos sucursales. ¿Para qué fecha la necesitas?</p>
                            <p class="cliente">Para el lunes</p>
                        </div>
                        <div class="ed-aviso"><small>Nuevo prospecto</small><b>Bodega 3×3 · lunes</b><span>Asignado a ventas · recordatorio mañana 10:00</span></div>
                    </div>
                    <div class="vi-vista" id="vista-proyectos" role="tabpanel" aria-labelledby="tab-proyectos" hidden>
                        <ol class="ed-etapas">
                            <li class="hecho">Definición</li><li class="hecho">Prototipado</li><li class="actual">Diseño</li><li>Desarrollo</li><li>Puesta en marcha</li>
                        </ol>
                        <div class="ed-aviso"><small>Para aprobar</small><b>Publicación del jueves</b><span>Aprobar · Comentar</span></div>
                    </div>
                    <div class="vi-vista" id="vista-inventario" role="tabpanel" aria-labelledby="tab-inventario" hidden>
                        <table class="ed-tabla-mini">
                            <thead><tr><th>Producto</th><th>Entradas</th><th>Salidas</th><th>Existencia</th></tr></thead>
                            <tbody>
                                <tr><td>Playera dama M</td><td>120</td><td>86</td><td>34</td></tr>
                                <tr><td>Gorra bordada</td><td>60</td><td>21</td><td>39</td></tr>
                                <tr class="bajo"><td>Lona 2×1 m</td><td>40</td><td>36</td><td>4 · bajo</td></tr>
                                <tr><td>Tarjetas (millar)</td><td>15</td><td>9</td><td>6</td></tr>
                            </tbody>
                        </table>
                        <p class="ed-nota">Aviso por WhatsApp: “Quedan 4 lonas de 2×1 m”.</p>
                    </div>
                    <div class="vi-vista" id="vista-ventas" role="tabpanel" aria-labelledby="tab-ventas" hidden>
                        <div class="ed-embudo">
                            <div><small>Nuevos</small><b>$48,000</b><span>Restaurante centro</span><span>Clínica dental</span></div>
                            <div><small>Cotizados</small><b>$65,500</b><span>Hotel boutique</span><span>Constructora</span></div>
                            <div><small>Ganados</small><b>$32,000</b><span>Franquicia</span></div>
                        </div>
                    </div>
                    <p class="ed-panel-pie">Vistas de ejemplo</p>
                </div>
            </div>

            <p class="ed-caso"><span class="ed-etiqueta">Caso real</span> En <b>Maxispace</b> cada solicitud del sitio llega por WhatsApp y queda registrada en su panel de prospectos, con filtros, estados e historial de contacto.</p>
        </div>
    </section>

    {{-- Video, foto y diseño --}}
    <section class="ed-claro ed-contenido" id="contenido">
        <div class="ed-caja">
            <header class="ed-cabeza ed-cabeza-lado">
                <div>
                    <p class="ed-etiqueta">Video, foto y diseño</p>
                    <h2>Imágenes que hacen que tu negocio <em>se antoje.</em></h2>
                </div>
                <div class="ed-columnas">
                    <div><h3>Producción real</h3><p>Video corporativo y testimonios · Reels y TikTok · Tomas con dron · Fotografía de alimentos, interiores y arquitectura</p></div>
                    <div><h3>Hecho con IA</h3><p>Comerciales y anuncios · Presentadores virtuales · Animación de producto y fotos · Versiones en otros idiomas</p></div>
                </div>
            </header>
            <div class="ed-galeria">
                @foreach ($trabajos as $t)
                    @php($etiqueta = $t['ruta'] ? 'a' : 'div')
                    <{{ $etiqueta }} class="ed-pieza {{ $t['clase'] }}" @if ($t['ruta']) href="{{ route($t['ruta']) }}" @endif>
                        @isset($t['video'])
                            <video muted loop playsinline preload="none" poster="{{ asset('img/inicio/' . $t['video'] . '.webp') }}" data-src="{{ asset('img/inicio/' . $t['video'] . '.mp4') }}"></video>
                        @else
                            <img src="{{ asset('img/inicio/' . $t['img']) }}" alt="{{ $t['servicio'] }} para {{ $t['cliente'] }}" loading="lazy">
                        @endisset
                        <span class="ed-pie"><b>{{ $t['cliente'] }}</b>{{ $t['servicio'] }}</span>
                    </{{ $etiqueta }}>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Método --}}
    <section class="ed-oscuro ed-metodo" id="metodo">
        <div class="ed-caja">
            <header class="ed-cabeza">
                <p class="ed-etiqueta">Cómo trabajamos</p>
                <h2>De la primera plática a la entrega, <em>en cuatro pasos.</em></h2>
            </header>
            <ol class="ed-pasos">
                @foreach ($pasos as $i => $p)
                    <li>
                        <span class="ed-fase">Paso {{ $n2($i) }}</span>
                        <h3>{{ $p['titulo'] }}</h3>
                        <p>{{ $p['texto'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Comparación --}}
    <section class="ed-claro ed-diferencia" id="diferencia">
        <div class="ed-caja">
            <header class="ed-cabeza">
                <p class="ed-etiqueta">La diferencia</p>
                <h2>Lo de siempre, <em>contra Vandu.</em></h2>
            </header>
            <div class="ed-tabla" role="table" aria-label="Comparación">
                <div class="ed-tabla-fila ed-tabla-cabeza" role="row">
                    <span role="columnheader"></span><span role="columnheader">Lo de siempre</span><span role="columnheader">Con Vandu</span>
                </div>
                @foreach ($comparacion as [$criterio, $antes, $vandu])
                    <div class="ed-tabla-fila" role="row">
                        <span role="rowheader">{{ $criterio }}</span><span role="cell">{{ $antes }}</span><span role="cell">{{ $vandu }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Testimonio --}}
    <section class="ed-oscuro ed-testimonio">
        <div class="ed-caja">
            <figure>
                <blockquote>“{{ $testimonio['frase'] }}”</blockquote>
                <figcaption><b>{{ $testimonio['nombre'] }}</b> · {{ $testimonio['puesto'] }}, {{ $testimonio['empresa'] }}</figcaption>
            </figure>
        </div>
    </section>

    {{-- Preguntas --}}
    <section class="ed-claro ed-preguntas" id="preguntas">
        <div class="ed-caja ed-dos">
            <header class="ed-cabeza">
                <p class="ed-etiqueta">Preguntas frecuentes</p>
                <h2>Lo que casi todos <em>nos preguntan.</em></h2>
            </header>
            <div class="ed-acordeon">
                @foreach ($preguntas as $i => [$p, $r])
                    <details @if ($i === 0) open @endif>
                        <summary>{{ $p }}<span aria-hidden="true"></span></summary>
                        <p>{{ $r }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Contacto --}}
    <section class="ed-oscuro ed-contacto" id="contacto">
        <div class="ed-caja ed-dos">
            <div>
                <p class="ed-etiqueta">Contacto</p>
                <h2 class="ed-grande">¿Platicamos?</h2>
                <p class="ed-entrada">Cuéntanos qué necesitas y en menos de 24 horas te decimos cuál es la mejor opción para tu negocio y tu presupuesto.</p>
                <a class="ed-btn ed-btn-verde" href="{{ $whatsapp }}" target="_blank" rel="noopener">Escríbenos por WhatsApp <span aria-hidden="true">→</span></a>
                <div class="ed-correo vi-correo">
                    <span>O escríbenos a</span>
                    <input type="text" class="correo" value="proyectos@agenciavandu.com" readonly aria-label="Correo de contacto">
                    <button type="button" aria-label="Copiar correo"><img src="{{ asset('img/copy.svg') }}" class="copiado" width="16" alt=""></button>
                    <span id="message" aria-live="polite"></span>
                </div>
            </div>
            <div class="ed-formulario">
                <p>¿Prefieres que te llamemos? Déjanos tus datos.</p>
                <x-form />
            </div>
        </div>
    </section>

</x-layouts.guest>
