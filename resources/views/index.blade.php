@push('estilos')
    @vite(['resources/scss/app.scss', 'resources/scss/global.scss', 'resources/scss/hero.scss', 'resources/scss/inicio.scss', 'resources/js/app.js', 'resources/js/hero.js'])
@endpush
@php
    $whatsapp = 'https://wa.me/529991460310';

    // Franja de números. Solo datos comprobables; cambia o agrega los tuyos
    // (proyectos entregados, sesiones de fotografía, clientes activos).
    $numeros = [
        ['valor' => '+4', 'texto' => 'años trabajando con marcas de Yucatán'],
        ['valor' => '11', 'texto' => 'marcas que ya confían en nosotros'],
        ['valor' => '5', 'texto' => 'servicios con un solo equipo'],
        ['valor' => '24 h', 'texto' => 'para recibir tu cotización'],
    ];

    // Trabajo reciente: "video" reproduce un clip corto en silencio; "img" es una foto.
    $trabajos = [
        ['clase' => 'vi-grande', 'video' => 'web-nicxa1', 'cliente' => 'Grupo Nicxa', 'servicio' => 'Sitio web para franquicias', 'ruta' => 'servicios'],
        ['clase' => 'vi-alta', 'img' => 'foto-kuro.webp', 'cliente' => 'Kuro', 'servicio' => 'Fotografía de alimentos', 'ruta' => 'audiovisuales'],
        ['clase' => '', 'video' => 'web-osorio', 'cliente' => 'Grupo Osorio', 'servicio' => 'Sitio web corporativo', 'ruta' => 'servicios'],
        ['clase' => '', 'img' => 'antelar-cuarto.webp', 'cliente' => 'Antelar', 'servicio' => 'Fotografía de interiores', 'ruta' => 'audiovisuales'],
        ['clase' => '', 'video' => 'video-comida', 'cliente' => 'Reel para restaurante', 'servicio' => 'Video para redes', 'ruta' => 'audiovisuales'],
        ['clase' => '', 'video' => 'web-rewear', 'cliente' => 'Rewear', 'servicio' => 'Tienda en línea', 'ruta' => 'servicios'],
        ['clase' => 'vi-ancha', 'img' => 'campana-burger-king.webp', 'cliente' => 'Burger King', 'servicio' => 'Campaña Real Whopper Beach', 'ruta' => 'multimedia'],
    ];

    $servicios = [
        [
            'titulo' => 'Sitios web y SEO',
            'texto' => 'Sitios rápidos y fáciles de administrar, preparados para aparecer en Google y en respuestas de ChatGPT.',
            'lista' => ['Sitios corporativos', 'Tiendas en línea', 'SEO y GEO', 'Hosting y mantenimiento'],
            'ruta' => route('servicios'),
            'img' => 'img/inicio/web-nicxa1.webp',
        ],
        [
            'titulo' => 'Fotografía',
            'texto' => 'Fotos que abren el apetito o que venden un espacio. Producimos la sesión completa.',
            'lista' => ['Alimentos y bebidas', 'Interiores y arquitectura', 'Retrato empresarial', 'Eventos'],
            'ruta' => route('audiovisuales'),
            'img' => 'img/inicio/foto-kuro.webp',
        ],
        [
            'titulo' => 'Video',
            'texto' => 'Videos cortos para redes, video corporativo y tomas con dron, de la idea a la edición final.',
            'lista' => ['Reels y TikTok', 'Video corporativo', 'Tomas con dron', 'Edición'],
            'ruta' => route('audiovisuales'),
            'img' => 'img/inicio/video-comida.webp',
        ],
        [
            'titulo' => 'Redes sociales',
            'texto' => 'Manejamos tus redes cada mes: calendario, diseño, publicación y reporte de resultados.',
            'lista' => ['Instagram y Facebook', 'TikTok', 'LinkedIn', 'Reporte mensual'],
            'ruta' => route('marketing'),
            'img' => 'img/inicio/redes-tokio07.webp',
        ],
        [
            'titulo' => 'Producción e impresos',
            'texto' => 'Lo que tu marca necesita en físico, cotizado y entregado por nosotros.',
            'lista' => ['Impresión', 'Rotulación e instalación', 'Bordado', 'Artículos promocionales'],
            'ruta' => $whatsapp,
            'img' => 'img/inicio/impresos-tarjetas.webp',
        ],
        [
            'titulo' => 'Diseño digital',
            'texto' => 'Un equipo de diseño a tu disposición para campañas, promociones y piezas del día a día.',
            'lista' => ['Campañas', 'Promociones', 'Presentaciones', 'Material para punto de venta'],
            'ruta' => route('multimedia'),
            'img' => 'img/inicio/diseno-whopper.webp',
        ],
    ];

    $pasos = [
        ['titulo' => 'Platicamos', 'texto' => 'Por WhatsApp o en llamada. Entendemos tu negocio y lo que necesitas.'],
        ['titulo' => 'Cotización en 24 h', 'texto' => 'Te enviamos una cotización clara, por etapas, que puedes revisar y aceptar desde tu portal.'],
        ['titulo' => 'Anticipo y producción', 'texto' => 'Con el anticipo arrancamos. En sitios web es el 50%; en foto y video el saldo se liquida antes de la sesión.'],
        ['titulo' => 'Entrega', 'texto' => 'Recibes tus archivos y avances en tu portal, listos para usar.'],
    ];

    // Testimonios: agrega más con el mismo formato (nombre, puesto, empresa y frase).
    $testimonios = [
        [
            'frase' => '¡Es un equipo maravilloso! Hemos trabajado juntos desde hace más de 4 años y siempre terminamos satisfechos. 100% recomendados.',
            'nombre' => 'Alejandro Canto',
            'puesto' => 'Director',
            'empresa' => 'Grupo Osorio',
        ],
    ];
@endphp
<x-layouts.guest titulo="Sitios web, fotografía y video en Mérida"
    meta-description="Agencia en Mérida, Yucatán: sitios web con SEO y GEO, fotografía de alimentos e interiores, video, redes sociales, impresión y rotulación para negocios locales.">

    <x-hero />

    {{-- Números --}}
    <section class="vi-numeros">
        <div class="vi-contenedor">
            @foreach ($numeros as $n)
                <div class="vi-numero">
                    <strong>{{ $n['valor'] }}</strong>
                    <span>{{ $n['texto'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Trabajo reciente --}}
    <section class="vi-seccion" id="trabajo">
        <div class="vi-contenedor">
            <header class="vi-encabezado">
                <p class="vi-ceja">Trabajo reciente</p>
                <h2>Lo que hemos hecho para negocios como el tuyo</h2>
            </header>
            <div class="vi-galeria">
                @foreach ($trabajos as $t)
                    <a class="vi-pieza {{ $t['clase'] }}" href="{{ route($t['ruta']) }}">
                        @isset($t['video'])
                            <video muted loop playsinline preload="none"
                                poster="{{ asset('img/inicio/' . $t['video'] . '.webp') }}"
                                data-src="{{ asset('img/inicio/' . $t['video'] . '.mp4') }}"></video>
                        @else
                            <img src="{{ asset('img/inicio/' . $t['img']) }}" alt="{{ $t['servicio'] }} para {{ $t['cliente'] }}" loading="lazy">
                        @endisset
                        <span class="vi-etiqueta"><b>{{ $t['cliente'] }}</b>{{ $t['servicio'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Servicios --}}
    <section class="vi-seccion">
        <div class="vi-contenedor">
            <header class="vi-encabezado">
                <p class="vi-ceja">Servicios</p>
                <h2>Todo lo que tu marca necesita, con un solo equipo</h2>
            </header>
            <div class="vi-servicios">
                @foreach ($servicios as $s)
                    <a class="vi-servicio" href="{{ $s['ruta'] }}" @if (str_starts_with($s['ruta'], 'https://wa.me')) target="_blank" rel="noopener" @endif>
                        <div class="vi-servicio-foto"><img src="{{ asset($s['img']) }}" alt="" loading="lazy"></div>
                        <h3>{{ $s['titulo'] }}</h3>
                        <p>{{ $s['texto'] }}</p>
                        <ul>
                            @foreach ($s['lista'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                        <span class="vi-mas">Ver más <span aria-hidden="true">→</span></span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA a mitad de página --}}
    <section class="vi-banda">
        <div class="vi-contenedor">
            <h2>¿Tienes un proyecto en mente?</h2>
            <p>Escríbenos y en menos de 24 horas tienes tu cotización.</p>
            <a class="vh-btn vh-btn-verde" href="{{ $whatsapp }}" target="_blank" rel="noopener">
                <img src="{{ asset('img/whatsapp.svg') }}" width="18" height="18" alt="">
                Cotiza tu proyecto por WhatsApp
            </a>
        </div>
    </section>

    {{-- Portal de clientes --}}
    <section class="vi-seccion">
        <div class="vi-contenedor vi-portal">
            <div class="vi-portal-texto">
                <p class="vi-ceja">Tu portal de cliente</p>
                <h2>Todo tu proyecto en un solo lugar</h2>
                <p>Cada cliente tiene su propio portal para ver su cotización, seguir los avances, descargar entregables y aprobar contenido. Sin correos perdidos ni archivos regados.</p>
                <ul class="vi-checks">
                    <li>Cotizaciones con vigencia que aceptas en línea</li>
                    <li>Línea del tiempo con el avance de cada etapa</li>
                    <li>Fotos, videos y archivos listos para descargar</li>
                    <li>Aprobación de publicaciones antes de salir</li>
                </ul>
            </div>

            {{-- Vista ilustrativa del portal (datos de ejemplo) --}}
            <div class="vi-panel" aria-hidden="true">
                <div class="vi-panel-barra">
                    <span></span><span></span><span></span>
                    <em>portal.agenciavandu.com</em>
                </div>
                <div class="vi-panel-cuerpo">
                    <div class="vi-tarjeta-panel vi-cotizacion">
                        <small>Cotización · Sitio web</small>
                        <strong>Restaurante de ejemplo</strong>
                        <span class="vi-estado">Aceptada</span>
                    </div>
                    <div class="vi-tarjeta-panel vi-avance">
                        <small>Avance del proyecto</small>
                        <ol>
                            <li class="hecho">Definición</li>
                            <li class="hecho">Prototipado</li>
                            <li class="actual">Diseño del sitio</li>
                            <li>Desarrollo</li>
                            <li>Puesta en marcha</li>
                        </ol>
                    </div>
                    <div class="vi-tarjeta-panel vi-entregables">
                        <small>Entregables</small>
                        <div class="vi-miniaturas">
                            <img src="{{ asset('img/hero/kuro-23.webp') }}" alt="">
                            <img src="{{ asset('img/hero/antelar-41.webp') }}" alt="">
                            <img src="{{ asset('img/hero/sesion-tokyo-tyger-mayo-1-6.webp') }}" alt="">
                            <span>+21</span>
                        </div>
                    </div>
                    <div class="vi-tarjeta-panel vi-aprobar">
                        <small>Publicación del jueves</small>
                        <strong>¿Apruebas este post?</strong>
                        <div><span class="si">Aprobar</span><span>Comentar</span></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Proceso --}}
    <section class="vi-seccion">
        <div class="vi-contenedor">
            <header class="vi-encabezado">
                <p class="vi-ceja">Cómo trabajamos</p>
                <h2>De la primera plática a la entrega, en 4 pasos</h2>
            </header>
            <ol class="vi-pasos">
                @foreach ($pasos as $i => $p)
                    <li>
                        <span class="vi-paso-num">{{ $i + 1 }}</span>
                        <h3>{{ $p['titulo'] }}</h3>
                        <p>{{ $p['texto'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Testimonios --}}
    <section class="vi-seccion">
        <div class="vi-contenedor">
            <header class="vi-encabezado">
                <p class="vi-ceja">Lo que dicen nuestros clientes</p>
            </header>
            <div @class(['vi-testimonios', 'vi-uno' => count($testimonios) === 1])>
                @foreach ($testimonios as $t)
                    <figure class="vi-testimonio">
                        <blockquote>“{{ $t['frase'] }}”</blockquote>
                        <figcaption>
                            <span class="vi-iniciales">{{ implode('', array_map(fn ($p) => mb_substr($p, 0, 1), array_slice(explode(' ', $t['nombre']), 0, 2))) }}</span>
                            <span><b>{{ $t['nombre'] }}</b>{{ $t['puesto'] }}, {{ $t['empresa'] }}</span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Contacto final --}}
    <section class="vi-seccion vi-contacto">
        <div class="vi-contenedor vi-contacto-grid">
            <div>
                <p class="vi-ceja">Contacto</p>
                <h2>¿Platicamos sobre tu proyecto?</h2>
                <p>Cada proyecto es diferente. Cuéntanos qué necesitas y te decimos cuál es la mejor opción para tu negocio y tu presupuesto.</p>
                <a class="vh-btn vh-btn-verde" href="{{ $whatsapp }}" target="_blank" rel="noopener">
                    <img src="{{ asset('img/whatsapp.svg') }}" width="18" height="18" alt="">
                    Cotiza tu proyecto por WhatsApp
                </a>
                <div class="vi-correo">
                    <span>O escríbenos a</span>
                    <input type="text" class="correo" value="proyectos@agenciavandu.com" readonly aria-label="Correo de contacto">
                    <button type="button" aria-label="Copiar correo">
                        <img src="{{ asset('img/copy.svg') }}" class="copiado" width="18" alt="">
                    </button>
                    <span id="message" aria-live="polite"></span>
                </div>
            </div>
            <div class="vi-formulario">
                <p>¿Prefieres que te llamemos? Déjanos tus datos.</p>
                <x-form />
            </div>
        </div>
    </section>

</x-layouts.guest>
