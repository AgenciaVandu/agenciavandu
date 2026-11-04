@push('estilos')
    <script>
        // Animaciones de entrada solo si el JS del inicio carga; si en 3 s no cargó, se muestra todo
        (function (d) {
            d.classList.add('vx-js');
            try { if (!sessionStorage.getItem('vx-telon') && !matchMedia('(prefers-reduced-motion: reduce)').matches) d.classList.add('vx-telon-on'); } catch (e) {}
            setTimeout(function () { if (!window.vxListo) d.classList.remove('vx-js', 'vx-telon-on'); }, 3000);
        })(document.documentElement);
    </script>
    @vite(['resources/scss/app.scss', 'resources/scss/global.scss', 'resources/scss/vandu-inicio.scss', 'resources/js/app.js', 'resources/js/vandu-inicio.js'])
@endpush
@php
    $whatsapp = 'https://wa.me/529991460310';

    $clientes = [
        ['KFC.svg', 'KFC', true], ['burger-king.svg', 'Burger King', true], ['pizza-hut.svg', 'Pizza Hut', true], ['gpo-nicxa.svg', 'Grupo Nicxa', false],
        ['gran-calzada.svg', 'Gran Calzada', false], ['amavita.svg', 'Amavita', false], ['osroca.svg', 'Osroca', false], ['tokio07.svg', 'Tokio 07', true],
        ['turismaya.svg', 'Turismaya', false], ['saama-gray.svg', 'Saama', false], ['casitas 1.svg', 'Casitas', false],
    ];

    // Servicios: tarjetas que se recorren de lado al hacer scroll
    $servicios = [
        ['titulo' => 'Sitios web y SEO', 'texto' => 'Sitios a la medida, tiendas en línea, SEO y GEO, hosting y mantenimiento.', 'img' => 'inicio/web-stev.webp', 'ruta' => route('servicios'), 'etiquetas' => ['A la medida', 'SEO y GEO']],
        ['titulo' => 'Sistemas y automatización', 'texto' => 'Paneles, portales y procesos conectados a WhatsApp: ventas, proyectos, inventario.', 'img' => 'inicio/web-maxispace.webp', 'ruta' => '#proceso', 'etiquetas' => ['WhatsApp', 'Paneles']],
        ['titulo' => 'Video y video con IA', 'texto' => 'Reels, video corporativo, tomas con dron, comerciales y presentadores hechos con IA.', 'img' => 'inicio/video-reel.webp', 'ruta' => route('audiovisuales'), 'etiquetas' => ['Dron', 'IA']],
        ['titulo' => 'Fotografía', 'texto' => 'Alimentos, interiores, arquitectura, retrato empresarial y eventos.', 'img' => 'hero/kuro-23.webp', 'ruta' => route('audiovisuales'), 'etiquetas' => ['Alimentos', 'Interiores']],
        ['titulo' => 'Redes sociales', 'texto' => 'Manejo mensual de Instagram, Facebook, TikTok y LinkedIn, con reporte de resultados.', 'img' => 'inicio/redes-tokio07.webp', 'ruta' => $whatsapp, 'etiquetas' => ['Contenido', 'Reportes']],
        ['titulo' => 'Producción e impresos', 'texto' => 'Impresión, rotulación e instalación, bordado y artículos promocionales.', 'img' => 'hero/impresos-tarjetas.webp', 'ruta' => $whatsapp, 'etiquetas' => ['Rotulación', 'Bordado']],
        ['titulo' => 'Diseño digital', 'texto' => 'Campañas, promociones, presentaciones y material para punto de venta.', 'img' => 'inicio/campana-burger-king.webp', 'ruta' => $whatsapp, 'etiquetas' => ['Campañas', 'Punto de venta']],
    ];

    // Proyectos web: cada clip vive en public/img/inicio/{video}.mp4 con su imagen fija {video}.webp
    $sitios = collect([
        ['video' => 'web-stev', 'cliente' => 'Stev Seguridad Privada', 'tipo' => 'Sitio corporativo', 'detalle' => 'Traslado y custodia de valores con cobertura nacional y contacto directo por WhatsApp.'],
        ['video' => 'web-maxispace', 'cliente' => 'Maxispace', 'tipo' => 'Sitio y captación de prospectos', 'detalle' => 'Mini bodegas en renta: tamaños, ubicaciones con mapa y formulario que llega a WhatsApp.'],
        ['video' => 'web-nicxa1', 'cliente' => 'Grupo Nicxa', 'tipo' => 'Sitio para franquicias', 'detalle' => 'Un solo sitio para KFC, Burger King, Pizza Hut y Bisquets Obregón.'],
        ['video' => 'web-osorio', 'cliente' => 'Grupo Osorio', 'tipo' => 'Sitio corporativo', 'detalle' => 'Un grupo con más de 30 años de experiencia y varias líneas de negocio.'],
        ['video' => 'web-rewear', 'cliente' => 'Rewear', 'tipo' => 'Tienda en línea', 'detalle' => 'Catálogo de ropa con fichas de producto, tallas y colores.'],
    ])->filter(fn ($s) => file_exists(public_path('img/inicio/' . $s['video'] . '.mp4')))->values();

    $fotos = [
        ['img' => 'inicio/tokyo-tyger-fachada.webp', 'cliente' => 'Tokyo Tyger', 'servicio' => 'Fotografía de restaurante'],
        ['img' => 'inicio/antelar-cuarto.webp', 'cliente' => 'Antelar', 'servicio' => 'Fotografía de interiores'],
        ['video' => 'video-comida', 'cliente' => 'Reel para restaurante', 'servicio' => 'Video para redes'],
        ['img' => 'inicio/samma-obra.webp', 'cliente' => 'Samma', 'servicio' => 'Fotografía de obra'],
    ];

    $numeros = [
        ['valor' => 4, 'antes' => '+', 'despues' => '', 'texto' => 'años creando para marcas de todo México'],
        ['valor' => 7, 'antes' => '', 'despues' => '', 'texto' => 'servicios con un solo equipo'],
        ['valor' => 24, 'antes' => '', 'despues' => ' h', 'texto' => 'para recibir tu cotización'],
    ];

    $pasos = [
        ['titulo' => 'Platicamos', 'texto' => 'Por WhatsApp o en llamada. Entendemos tu negocio y lo que necesitas.', 'img' => 'hero/sesion-tokyo-tyger-mayo-1-6.webp'],
        ['titulo' => 'Cotización en 24 h', 'texto' => 'Una cotización clara, por etapas, que revisas y aceptas desde tu portal.', 'img' => 'inicio/web-maxispace.webp'],
        ['titulo' => 'Anticipo y producción', 'texto' => 'Con el anticipo arrancamos. En sitios web es el 50%; en foto y video el saldo se liquida antes de la sesión.', 'img' => 'hero/samma-25-alberto-1.webp'],
        ['titulo' => 'Entrega', 'texto' => 'Recibes tus archivos y avances en tu portal, listos para usar.', 'img' => 'hero/antelar-41.webp'],
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
<x-layouts.guest titulo="Páginas web, sistemas y video con IA en Mérida" tema="vx"
    meta-description="Agencia en Mérida para negocios de todo México: páginas web con SEO, sistemas conectados a WhatsApp, video con IA, fotografía e impresión. Cotiza en 24 h.">

    @push('estilos')
        <script type="application/ld+json">
        {!! json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn ($p) => ['@type' => 'Question', 'name' => $p[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $p[1]]], $preguntas)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    @endpush

    {{-- Telón de entrada (una vez por visita) --}}
    <div class="vx-telon" aria-hidden="true"><img src="{{ asset('img/logo-vandu-blanco.svg') }}" alt=""></div>
    {{-- Cursor "Ver" sobre los proyectos --}}
    <div class="vx-cursor" aria-hidden="true"><span>Ver</span></div>

    {{-- Portada --}}
    <section class="vx-oscuro vx-portada">
        <div class="vx-caja">
            {{-- Dos columnas: la tarjeta queda en el hueco entre ellas, en los dos renglones --}}
            <h1 class="vx-titular" data-palabras>
                <span class="vx-col vx-izq" aria-hidden="true"><span class="vx-p"><i>Que a tu negocio</i></span><span class="vx-p">encuentren</span></span>
                <span class="vx-hueco" aria-hidden="true">
                    <span class="vx-cartas" data-cartas>
                        @foreach (['hero/sesion-tokyo-tyger-mayo-1-6.webp', 'inicio/web-maxispace.webp', 'hero/kuro-23.webp', 'inicio/web-stev.webp', 'hero/impresos-tarjetas.webp', 'inicio/redes-tokio07.webp'] as $k => $img)
                            <span @class(['vx-carta', 'vx-actual' => $k === 0])><img src="{{ asset('img/' . $img) }}" alt="" @if ($k > 1) loading="lazy" @endif></span>
                        @endforeach
                    </span>
                </span>
                <span class="vx-col vx-der" aria-hidden="true"><span class="vx-p">lo</span><span class="vx-p">y lo elijan.</span></span>
                <span class="vx-solo-lector">Que a tu negocio lo encuentren y lo elijan.</span>
            </h1>
            <p class="vx-entrada" data-aparece>Sitios web, sistemas que hablan WhatsApp, video con IA, fotografía y producción. Un solo equipo en Mérida para negocios de todo México.</p>
            <div class="vx-acciones" data-aparece>
                <a class="vx-btn vx-btn-verde" href="{{ $whatsapp }}" target="_blank" rel="noopener">Cotiza tu proyecto</a>
                <a class="vx-btn vx-btn-linea" href="#trabajo">Ver el trabajo</a>
            </div>
        </div>
    </section>

    {{-- El reel crece con el scroll --}}
    <section class="vx-oscuro vx-reel-zona" aria-label="Demo reel">
        <div class="vx-reel-fijo">
            <figure class="vx-reel" data-reel>
                <video muted loop playsinline preload="none" poster="{{ asset('img/inicio/video-reel.webp') }}" data-src="{{ asset('img/inicio/video-reel.mp4') }}"></video>
                <figcaption><span>Demo reel</span><span>Video corporativo y dron</span></figcaption>
            </figure>
        </div>
    </section>

    {{-- Servicios: se recorren de lado --}}
    <section class="vx-oscuro vx-servicios" id="servicios">
        <div class="vx-caja vx-cabeza-lado">
            <div>
                <p class="vx-etiqueta" data-aparece><span></span>Servicios</p>
                <h2 data-lineas>Diseñamos, <span class="vx-ranura" aria-hidden="true"><video muted loop playsinline preload="none" poster="{{ asset('img/inicio/video-reel.webp') }}" data-src="{{ asset('img/inicio/video-reel.mp4') }}"></video></span> programamos y producimos. Todo en un solo lugar.</h2>
            </div>
            <div data-aparece>
                <p>Desde tu sitio y tus sistemas hasta tus fotos, videos y lonas. Un equipo que entiende tu negocio y se encarga de todo.</p>
                <a class="vx-btn vx-btn-linea" href="{{ route('servicios') }}">Ver servicios</a>
            </div>
        </div>
        <div class="vx-carril" data-carril>
            <div class="vx-carril-fijo">
                <div class="vx-pista" data-pista>
                    @foreach ($servicios as $i => $s)
                        <a class="vx-tarjeta" href="{{ $s['ruta'] }}" @if (str_starts_with($s['ruta'], 'https://')) target="_blank" rel="noopener" @endif>
                            <img src="{{ asset('img/' . $s['img']) }}" alt="" loading="lazy">
                            <span class="vx-tarjeta-velo"></span>
                            <span class="vx-tarjeta-flecha" aria-hidden="true">↗</span>
                            <span class="vx-tarjeta-texto">
                                <span class="vx-chips">@foreach ($s['etiquetas'] as $e)<em>{{ $e }}</em>@endforeach</span>
                                <b>{{ $s['titulo'] }}</b>
                                <small>{{ $s['texto'] }}</small>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- Marcas en movimiento --}}
    <section class="vx-oscuro vx-marcas" aria-label="Marcas que trabajan con nosotros">
        <p class="vx-etiqueta-verde" data-aparece>Marcas que confían en nosotros</p>
        @foreach ([0, 1] as $fila)
            <div class="vx-marquesina {{ $fila ? 'vx-reversa' : '' }}">
                <div class="vx-cinta">
                    @foreach ([0, 1] as $copia)
                        @foreach ($fila ? array_reverse($clientes) : $clientes as [$archivo, $nombre, $cuadrado])
                            <img @class(['vx-cuadrado' => $cuadrado]) src="{{ asset('img/logos-marcas/' . $archivo) }}" alt="{{ $copia ? '' : $nombre }}" @if ($copia) aria-hidden="true" @endif loading="lazy">
                        @endforeach
                    @endforeach
                </div>
            </div>
        @endforeach
    </section>

    {{-- Trabajo --}}
    <section class="vx-claro vx-trabajo" id="trabajo">
        <div class="vx-caja">
            <h2 class="vx-centro" data-lineas>Nuestro trabajo</h2>
            <p class="vx-sub vx-centro" data-aparece>Sitios que trabajan por tu negocio todos los días. Pasa el cursor para verlos en movimiento.</p>
            <div class="vx-proyectos">
                @foreach ($sitios as $i => $s)
                    <article class="vx-proyecto {{ $i === 0 ? 'vx-grande' : '' }}" data-aparece data-ver>
                        <div class="vx-proyecto-media">
                            <video muted loop playsinline preload="none" poster="{{ asset('img/inicio/' . $s['video'] . '.webp') }}" data-hover-src="{{ asset('img/inicio/' . $s['video'] . '.mp4') }}"></video>
                        </div>
                        <div class="vx-proyecto-pie">
                            <span class="vx-chip-claro">{{ $s['tipo'] }}</span>
                            <h3>{{ $s['cliente'] }}</h3>
                            <p>{{ $s['detalle'] }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="vx-galeria">
                @foreach ($fotos as $f)
                    <a class="vx-foto-pieza" href="{{ route('audiovisuales') }}" data-aparece data-ver>
                        @isset($f['video'])
                            <video muted loop playsinline preload="none" poster="{{ asset('img/inicio/' . $f['video'] . '.webp') }}" data-src="{{ asset('img/inicio/' . $f['video'] . '.mp4') }}"></video>
                        @else
                            <img src="{{ asset('img/' . $f['img']) }}" alt="{{ $f['servicio'] }} para {{ $f['cliente'] }}" loading="lazy">
                        @endisset
                        <span><b>{{ $f['cliente'] }}</b>{{ $f['servicio'] }}</span>
                    </a>
                @endforeach
            </div>
            <div class="vx-centro" data-aparece><a class="vx-btn vx-btn-oscuro" href="{{ route('audiovisuales') }}">Ver foto y video</a></div>
        </div>
    </section>

    {{-- Números --}}
    <section class="vx-negro vx-numeros">
        <div class="vx-caja">
            <p class="vx-etiqueta vx-centro-flex" data-aparece><span></span>Por qué Vandu</p>
            <h2 class="vx-centro" data-lineas>Un solo equipo para que tu marca se vea y venda mejor.</h2>
            <div class="vx-cifras">
                @foreach ($numeros as $n)
                    <div class="vx-cifra" data-aparece>
                        <strong>{{ $n['antes'] }}<span data-cuenta="{{ $n['valor'] }}">{{ $n['valor'] }}</span>{{ $n['despues'] }}</strong>
                        <p>{{ $n['texto'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Proceso: se apilan --}}
    <section class="vx-negro vx-proceso" id="proceso">
        <div class="vx-caja vx-proceso-grid">
            <div class="vx-proceso-fijo">
                <p class="vx-etiqueta" data-aparece><span></span>Cómo trabajamos</p>
                <h2 data-lineas>De la primera plática a la entrega.</h2>
                <p class="vx-sub" data-aparece>Cada paso claro, sin sorpresas. Todo lo ves en tu portal.</p>
                <a class="vx-btn vx-btn-verde" href="{{ $whatsapp }}" target="_blank" rel="noopener" data-aparece>Empezar ahora</a>
            </div>
            <ol class="vx-pasos">
                @foreach ($pasos as $i => $p)
                    <li class="vx-paso" style="--i: {{ $i }}">
                        <div>
                            <span class="vx-paso-num">{{ $n2($i) }}</span>
                            <h3>{{ $p['titulo'] }}</h3>
                            <p>{{ $p['texto'] }}</p>
                        </div>
                        <img src="{{ asset('img/' . $p['img']) }}" alt="" loading="lazy">
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Testimonio --}}
    <section class="vx-gris vx-testimonio">
        <div class="vx-caja">
            <figure data-aparece>
                <svg width="44" height="34" viewBox="0 0 44 34" aria-hidden="true"><path d="M0 34V20C0 8 6 1 18 0l2 5c-6 2-9 6-9 12h8v17H0zm24 0V20C24 8 30 1 42 0l2 5c-6 2-9 6-9 12h8v17H24z" fill="currentColor"/></svg>
                <blockquote>{{ $testimonio['frase'] }}</blockquote>
                <figcaption><b>{{ $testimonio['nombre'] }}</b>{{ $testimonio['puesto'] }}, {{ $testimonio['empresa'] }}</figcaption>
            </figure>
        </div>
    </section>

    {{-- Preguntas --}}
    <section class="vx-claro vx-preguntas" id="preguntas">
        <div class="vx-caja vx-preguntas-grid">
            <div class="vx-preguntas-fijo">
                <p class="vx-etiqueta vx-etiqueta-clara" data-aparece><span></span>Preguntas</p>
                <h2 data-lineas>¿Dudas? Te las resolvemos.</h2>
                <a class="vx-btn vx-btn-oscuro" href="{{ $whatsapp }}" target="_blank" rel="noopener" data-aparece>Pregúntanos por WhatsApp</a>
            </div>
            <div class="vx-acordeon">
                @foreach ($preguntas as $i => [$p, $r])
                    <details data-aparece @if ($i === 0) open @endif>
                        <summary>{{ $p }}<span aria-hidden="true"></span></summary>
                        <p>{{ $r }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Contacto --}}
    <section class="vx-oscuro vx-contacto" id="contacto">
        <div class="vx-caja">
            <div class="vx-contacto-tarjeta" data-aparece>
                <div class="vx-contacto-lado">
                    <p class="vx-etiqueta"><span></span>Hablemos</p>
                    <h2>¿Platicamos sobre tu proyecto?</h2>
                    <p>Cuéntanos qué necesitas y en menos de 24 horas te decimos cuál es la mejor opción para tu negocio y tu presupuesto.</p>
                    <a class="vx-btn vx-btn-verde" href="{{ $whatsapp }}" target="_blank" rel="noopener">Escríbenos por WhatsApp</a>
                    <div class="vx-correo">
                        <span>O escríbenos a</span>
                        <input type="text" class="correo" value="proyectos@agenciavandu.com" readonly aria-label="Correo de contacto">
                        <button type="button" aria-label="Copiar correo"><img src="{{ asset('img/copy.svg') }}" class="copiado" width="16" alt=""></button>
                        <span id="message" aria-live="polite"></span>
                    </div>
                </div>
                <div class="vx-formulario">
                    <p>¿Prefieres que te llamemos? Déjanos tus datos.</p>
                    <x-form />
                </div>
            </div>
        </div>
    </section>

</x-layouts.guest>
