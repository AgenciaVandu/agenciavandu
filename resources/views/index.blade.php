@push('estilos')
    @vite(['resources/scss/app.scss', 'resources/scss/global.scss', 'resources/scss/hero.scss', 'resources/scss/inicio.scss', 'resources/js/app.js', 'resources/js/hero.js'])
@endpush
@php
    $whatsapp = 'https://wa.me/529991460310';

    // Franja de números. Solo datos comprobables; cambia o agrega los tuyos
    // (proyectos entregados, sesiones de fotografía, clientes activos).
    $numeros = [
        ['valor' => '+4', 'texto' => 'años creando para marcas de todo México'],
        ['valor' => '7', 'texto' => 'servicios con un solo equipo'],
        ['valor' => '24 h', 'texto' => 'para recibir tu cotización'],
    ];

    // Sitios web destacados. Cada clip vive en public/img/inicio/{video}.mp4 con su
    // imagen fija {video}.webp; si el clip todavía no existe, el proyecto no se muestra.
    $sitios = collect([
        ['video' => 'web-stev', 'cliente' => 'Stev Seguridad Privada', 'tipo' => 'Sitio corporativo', 'detalle' => 'Traslado y custodia de valores con cobertura nacional: servicios, tecnología y contacto directo por WhatsApp.'],
        ['video' => 'web-maxispace', 'cliente' => 'Maxispace', 'tipo' => 'Sitio y captación de prospectos', 'detalle' => 'Mini bodegas en renta: tamaños, ubicaciones con mapa y formulario que llega directo a WhatsApp.'],
        ['video' => 'web-osorio', 'cliente' => 'Grupo Osorio', 'tipo' => 'Sitio corporativo', 'detalle' => 'Sitio para un grupo con más de 30 años de experiencia y varias líneas de negocio.'],
        ['video' => 'web-rewear', 'cliente' => 'Rewear', 'tipo' => 'Tienda en línea', 'detalle' => 'Catálogo de ropa con fichas de producto, tallas y colores.'],
        ['video' => 'web-nicxa1', 'cliente' => 'Grupo Nicxa', 'tipo' => 'Sitio para grupo de franquicias', 'detalle' => 'Un solo sitio para KFC, Burger King, Pizza Hut y Bisquets Obregón, con promociones por marca.'],
    ])->filter(fn ($s) => file_exists(public_path('img/inicio/' . $s['video'] . '.mp4')))->values();

    // Galería de fotografía y diseño. "ruta" null = sin enlace (la página aún no existe).
    $trabajos = [
        ['clase' => 'vi-grande', 'img' => 'tokyo-tyger-fachada.webp', 'cliente' => 'Tokyo Tyger', 'servicio' => 'Fotografía de restaurante', 'ruta' => 'audiovisuales'],
        ['clase' => 'vi-alta', 'img' => 'foto-kuro.webp', 'cliente' => 'Kuro', 'servicio' => 'Fotografía de alimentos', 'ruta' => 'audiovisuales'],
        ['clase' => '', 'img' => 'antelar-cuarto.webp', 'cliente' => 'Antelar', 'servicio' => 'Fotografía de interiores', 'ruta' => 'audiovisuales'],
        ['clase' => '', 'video' => 'video-comida', 'cliente' => 'Reel para restaurante', 'servicio' => 'Video para redes', 'ruta' => 'audiovisuales'],
        ['clase' => 'vi-ancha', 'img' => 'campana-burger-king.webp', 'cliente' => 'Burger King', 'servicio' => 'Campaña Real Whopper Beach', 'ruta' => null],
        ['clase' => '', 'img' => 'redes-tokio07.webp', 'cliente' => 'Tokio 07', 'servicio' => 'Contenido para redes', 'ruta' => null],
        ['clase' => '', 'img' => 'samma-obra.webp', 'cliente' => 'Samma', 'servicio' => 'Fotografía de obra', 'ruta' => 'audiovisuales'],
    ];

    // Sistemas a la medida: cada pestaña muestra una vista de ejemplo.
    $sistemas = [
        ['id' => 'whatsapp', 'titulo' => 'Ventas por WhatsApp', 'texto' => 'Tus clientes escriben por WhatsApp y cada mensaje se vuelve un prospecto o pedido en tu panel, con seguimiento y recordatorios.'],
        ['id' => 'proyectos', 'titulo' => 'Gestión de proyectos', 'texto' => 'Cotizaciones, avances por etapa y entregables en un portal para tu equipo y tus clientes.'],
        ['id' => 'inventario', 'titulo' => 'Inventario y stock', 'texto' => 'Entradas, salidas y existencias en tiempo real, con aviso por WhatsApp cuando algo está por agotarse.'],
        ['id' => 'ventas', 'titulo' => 'Embudo de ventas', 'texto' => 'Todos tus prospectos por etapa, con valor en dinero, tareas y el historial de cada contacto.'],
    ];

    // Lista general de servicios (sin imágenes, para no repetir las de arriba).
    $servicios = [
        ['titulo' => 'Sitios web y SEO', 'texto' => 'Sitios a la medida, tiendas en línea, SEO y GEO, hosting y mantenimiento.', 'ruta' => route('servicios')],
        ['titulo' => 'Sistemas y automatización', 'texto' => 'Paneles, portales y procesos conectados a WhatsApp: ventas, proyectos, inventario.', 'ruta' => '#sistemas'],
        ['titulo' => 'Video y video con IA', 'texto' => 'Reels, video corporativo, tomas con dron, comerciales y avatares hechos con IA.', 'ruta' => '#video'],
        ['titulo' => 'Fotografía', 'texto' => 'Alimentos, interiores, arquitectura, retrato empresarial y eventos.', 'ruta' => route('audiovisuales')],
        ['titulo' => 'Redes sociales', 'texto' => 'Manejo mensual de Instagram, Facebook, TikTok y LinkedIn, con reporte de resultados.', 'ruta' => $whatsapp],
        ['titulo' => 'Producción e impresos', 'texto' => 'Impresión, rotulación e instalación, bordado y artículos promocionales.', 'ruta' => $whatsapp],
        ['titulo' => 'Diseño digital', 'texto' => 'Campañas, promociones, presentaciones y material para punto de venta.', 'ruta' => $whatsapp],
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
<x-layouts.guest titulo="Páginas web, sistemas y video con IA en Mérida"
    meta-description="Agencia en Mérida para negocios de todo México: páginas web con SEO, sistemas conectados a WhatsApp, video con IA, fotografía e impresión. Cotiza en 24 h.">

    <x-hero />

    {{-- Números --}}
    <section class="vi-numeros">
        <div class="vi-contenedor" style="--columnas: {{ count($numeros) }}">
            @foreach ($numeros as $n)
                <div class="vi-numero">
                    <strong>{{ $n['valor'] }}</strong>
                    <span>{{ $n['texto'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Sitios web --}}
    <section class="vi-seccion vi-webs" id="trabajo">
        <div class="vi-contenedor">
            <header class="vi-encabezado">
                <p class="vi-ceja">Sitios web</p>
                <h2>Sitios que trabajan por tu negocio todos los días</h2>
                <p>Diseñamos y programamos cada sitio a la medida, sin plantillas. Rápidos, fáciles de administrar y preparados para que te encuentren en Google y en ChatGPT.</p>
            </header>

            @if ($sitios->isNotEmpty())
                <div class="vi-webs-grid">
                    <div class="vi-navegador">
                        <div class="vi-navegador-barra">
                            <span></span><span></span><span></span>
                            <em class="vi-navegador-url">{{ $sitios[0]['cliente'] }}</em>
                        </div>
                        <video class="vi-navegador-video" muted playsinline preload="none"
                            poster="{{ asset('img/inicio/' . $sitios[0]['video'] . '.webp') }}"
                            data-src="{{ asset('img/inicio/' . $sitios[0]['video'] . '.mp4') }}"></video>
                    </div>

                    <ol class="vi-proyectos">
                        @foreach ($sitios as $i => $sitio)
                            <li>
                                <button type="button" @class(['vi-proyecto', 'activo' => $i === 0])
                                    data-video="{{ asset('img/inicio/' . $sitio['video'] . '.mp4') }}"
                                    data-poster="{{ asset('img/inicio/' . $sitio['video'] . '.webp') }}"
                                    data-cliente="{{ $sitio['cliente'] }}">
                                    <span class="vi-proyecto-tipo">{{ $sitio['tipo'] }}</span>
                                    <b>{{ $sitio['cliente'] }}</b>
                                    <span class="vi-proyecto-detalle">{{ $sitio['detalle'] }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif

            <ul class="vi-ventajas">
                <li><b>A la medida</b>Diseño propio para tu marca, sin plantillas.</li>
                <li><b>SEO y GEO</b>Listo para aparecer en Google y en respuestas de ChatGPT.</li>
                <li><b>Fácil de administrar</b>Panel para cambiar contenido y dar seguimiento a prospectos.</li>
                <li><b>Hosting y mantenimiento</b>Nos encargamos de dominio, servidor y actualizaciones.</li>
            </ul>

            <a class="vh-btn vh-btn-linea" href="{{ route('servicios') }}">Ver más sobre sitios web</a>
        </div>
    </section>

    {{-- Sistemas a la medida --}}
    <section class="vi-seccion vi-sistemas" id="sistemas">
        <div class="vi-contenedor">
            <header class="vi-encabezado">
                <p class="vi-ceja">Sistemas a la medida</p>
                <h2>Procesos que trabajan solos y hablan WhatsApp</h2>
                <p>Creamos paneles y sistemas para que tu negocio deje de depender de hojas de cálculo y mensajes perdidos. Tus clientes siguen escribiendo por WhatsApp; tú lo ves todo ordenado en un solo lugar.</p>
            </header>

            <div class="vi-sistemas-grid">
                <div class="vi-pestanas" role="tablist" aria-label="Tipos de sistema">
                    @foreach ($sistemas as $i => $sis)
                        <button type="button" role="tab" id="tab-{{ $sis['id'] }}" aria-controls="vista-{{ $sis['id'] }}"
                            aria-selected="{{ $i === 0 ? 'true' : 'false' }}" @class(['vi-pestana', 'activo' => $i === 0])>
                            <b>{{ $sis['titulo'] }}</b>
                            <span>{{ $sis['texto'] }}</span>
                        </button>
                    @endforeach
                </div>

                {{-- Vistas ilustrativas con datos de ejemplo --}}
                <div class="vi-panel">
                    <div class="vi-panel-barra">
                        <span></span><span></span><span></span>
                        <em>panel de tu negocio</em>
                    </div>

                    <div class="vi-vista vi-vista-whatsapp" id="vista-whatsapp" role="tabpanel" aria-labelledby="tab-whatsapp">
                        <div class="vi-chat">
                            <p class="vi-burbuja cliente">Hola, ¿tienen bodegas de 3×3 disponibles?</p>
                            <p class="vi-burbuja negocio">¡Hola! Sí, tenemos en dos sucursales. ¿Para qué fecha la necesitas?</p>
                            <p class="vi-burbuja cliente">Para el lunes 🙌</p>
                        </div>
                        <div class="vi-aviso">
                            <span class="vi-estado">Nuevo prospecto</span>
                            <strong>Bodega 3×3 · lunes</strong>
                            <small>Asignado a ventas · recordatorio mañana 10:00</small>
                        </div>
                    </div>

                    <div class="vi-vista" id="vista-proyectos" role="tabpanel" aria-labelledby="tab-proyectos" hidden>
                        <div class="vi-panel-cuerpo">
                            <div class="vi-tarjeta-panel">
                                <small>Cotización · Sitio web</small>
                                <strong>Proyecto de ejemplo</strong>
                                <span class="vi-estado">Aceptada</span>
                            </div>
                            <div class="vi-tarjeta-panel vi-avance">
                                <small>Avance</small>
                                <ol>
                                    <li class="hecho">Definición</li>
                                    <li class="hecho">Prototipado</li>
                                    <li class="actual">Diseño</li>
                                    <li>Desarrollo</li>
                                    <li>Puesta en marcha</li>
                                </ol>
                            </div>
                            <div class="vi-tarjeta-panel vi-aprobar">
                                <small>Para aprobar</small>
                                <strong>Publicación del jueves</strong>
                                <div><span class="si">Aprobar</span><span>Comentar</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="vi-vista" id="vista-inventario" role="tabpanel" aria-labelledby="tab-inventario" hidden>
                        <table class="vi-tabla">
                            <thead><tr><th>Producto</th><th>Entradas</th><th>Salidas</th><th>Existencia</th></tr></thead>
                            <tbody>
                                <tr><td>Playera dama M</td><td>120</td><td>86</td><td>34</td></tr>
                                <tr><td>Gorra bordada</td><td>60</td><td>21</td><td>39</td></tr>
                                <tr class="bajo"><td>Lona 2×1 m</td><td>40</td><td>36</td><td>4 <span class="vi-estado alerta">Bajo</span></td></tr>
                                <tr><td>Tarjetas (millar)</td><td>15</td><td>9</td><td>6</td></tr>
                            </tbody>
                        </table>
                        <p class="vi-nota">Aviso enviado por WhatsApp: "Quedan 4 lonas de 2×1 m".</p>
                    </div>

                    <div class="vi-vista" id="vista-ventas" role="tabpanel" aria-labelledby="tab-ventas" hidden>
                        <div class="vi-embudo">
                            <div><small>Nuevos</small><strong>$48,000</strong><span>Restaurante centro</span><span>Clínica dental</span></div>
                            <div><small>Cotizados</small><strong>$65,500</strong><span>Hotel boutique</span><span>Constructora</span></div>
                            <div><small>Ganados</small><strong>$32,000</strong><span>Franquicia</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <p class="vi-caso">Caso real: en <b>Maxispace</b> cada solicitud del sitio llega por WhatsApp y queda registrada en su panel de prospectos, con filtros, estados e historial de contacto.</p>
        </div>
    </section>

    {{-- Video --}}
    <section class="vi-seccion vi-video" id="video">
        <div class="vi-contenedor vi-video-grid">
            <div class="vi-video-texto">
                <p class="vi-ceja">Video e inteligencia artificial</p>
                <h2>Videos que se ven caros, en una fracción del tiempo</h2>
                <p>Grabamos con equipo profesional y dron, y también creamos videos con inteligencia artificial cuando necesitas más contenido, más rápido.</p>
                <div class="vi-video-columnas">
                    <div>
                        <h3>Producción real</h3>
                        <ul>
                            <li>Video corporativo y testimonios</li>
                            <li>Reels y TikTok</li>
                            <li>Tomas con dron</li>
                        </ul>
                    </div>
                    <div class="vi-ia">
                        <h3>Hecho con IA <span class="vi-estado">Nuevo</span></h3>
                        <ul>
                            <li>Comerciales y anuncios</li>
                            <li>Presentadores virtuales</li>
                            <li>Animación de producto y fotos</li>
                            <li>Versiones en otros idiomas</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="vi-reel">
                <video muted loop playsinline preload="none"
                    poster="{{ asset('img/inicio/video-reel.webp') }}"
                    data-src="{{ asset('img/inicio/video-reel.mp4') }}"></video>
                <span class="vi-etiqueta"><b>Demo reel</b>Video corporativo y dron</span>
            </div>
        </div>
    </section>

    {{-- Fotografía y diseño --}}
    <section class="vi-seccion" id="galeria">
        <div class="vi-contenedor">
            <header class="vi-encabezado">
                <p class="vi-ceja">Fotografía y diseño</p>
                <h2>Imágenes que hacen que tu negocio se antoje</h2>
            </header>
            <div class="vi-galeria">
                @foreach ($trabajos as $t)
                    @php($etiqueta = $t['ruta'] ? 'a' : 'div')
                    <{{ $etiqueta }} class="vi-pieza {{ $t['clase'] }}" @if ($t['ruta']) href="{{ route($t['ruta']) }}" @endif>
                        @isset($t['video'])
                            <video muted loop playsinline preload="none"
                                poster="{{ asset('img/inicio/' . $t['video'] . '.webp') }}"
                                data-src="{{ asset('img/inicio/' . $t['video'] . '.mp4') }}"></video>
                        @else
                            <img src="{{ asset('img/inicio/' . $t['img']) }}" alt="{{ $t['servicio'] }} para {{ $t['cliente'] }}" loading="lazy">
                        @endisset
                        <span class="vi-etiqueta"><b>{{ $t['cliente'] }}</b>{{ $t['servicio'] }}</span>
                    </{{ $etiqueta }}>
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
            <ul class="vi-lista-servicios">
                @foreach ($servicios as $i => $s)
                    <li>
                        <a href="{{ $s['ruta'] }}" @if (str_starts_with($s['ruta'], 'https://')) target="_blank" rel="noopener" @endif>
                            <span class="vi-lista-num">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            <b>{{ $s['titulo'] }}</b>
                            <span class="vi-lista-texto">{{ $s['texto'] }}</span>
                            <span class="vi-lista-flecha" aria-hidden="true">→</span>
                        </a>
                    </li>
                @endforeach
            </ul>
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
