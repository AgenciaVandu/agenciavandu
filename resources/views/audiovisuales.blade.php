@push('estilos')
    @vite(['resources/scss/app.scss', 'resources/scss/global.scss', 'resources/scss/multimedia.scss', 'resources/js/app.js', 'resources/js/bguno.js', 'resources/js/observer.js'])
@endpush
<x-layouts.guest titulo="Vídeo y Fotografía para Marcas: Optimización Visual para Google y AI"
    meta-description="Agencia de marketing digital especializada en optimización y estrategias de IA en 2026 para sitios web, fotografía empresarial, fotografía de alimentos y desarrollo web.">
    <x-header>
        <x-slot name="header">
            <h1 class="mt-5">¿Por qué invertir en producción audiovisual profesional para tu empresa?</h1>
        </x-slot>
        <x-slot name="parrafo">
            <p>Porque el contenido de alta gama no es un gasto, es un activo. Creamos piezas de video y
                fotografía diseñadas para captar la atención en segundos, construir autoridad inmediata y multiplicar
                tus tasas de conversión.</p>
        </x-slot>
    </x-header>
    <x-clientes></x-clientes>
    <section class="portafolio-visual">
        <div class="container">
            <div class="grid-contenido">
                <!-- FOTOS 1 y 2 (Imágenes OK) -->
                <div class="foto1">
                    <img loading="lazy" src="{{ asset('/img/webp/2026/Sesion-Tokyo-Tyger-Mayo-37-1.webp') }}"
                        alt="Sesion Tokyo Tyger">
                </div>
                <div class="foto2">
                    <img loading="lazy" src="{{ asset('/img/webp/2026/Samma-25-Alberto-1.webp') }}"
                        alt="Samma Alberto">
                </div>

                <!-- VIDEOS 3, 4 y 5 (Corregidos) -->
                <div class="foto3">
                    <video class="lazy-video" playsinline loop muted webkit-playsinline preload="none"
                        data-src="{{ asset('/video/croc.webm') }}"></video>
                </div>
                <div class="foto4">
                    <video class="lazy-video" playsinline loop muted webkit-playsinline preload="none"
                        data-src="{{ asset('/video/ronqueo-kurouma.webm') }}"></video>
                </div>
                <div class="foto5">
                     <img loading="lazy" src="{{ asset('/img/webp/2026/behing-the-escenes.webp') }}"
                        alt="Behind the escenes">
                </div>

                <!-- FOTOS 6 a 10 (Imágenes OK) -->
                <div class="foto6"><img loading="lazy" src="{{ asset('/img/webp/2026/kuro-23.webp') }}"
                        alt=""></div>
                <div class="foto7"><img loading="lazy" src="{{ asset('/img/webp/2026/antelar-9.webp') }}"
                        alt=""></div>
                <div class="foto8"><img loading="lazy" src="{{ asset('/img/webp/2026/antelar-41.webp') }}"
                        alt=""></div>
                <div class="foto9"><img loading="lazy" src="{{ asset('/img/webp/2026/Samma-18-Alberto-1.webp') }}"
                        alt=""></div>
                <div class="foto10"><img loading="lazy"
                        src="{{ asset('/img/webp/2026/Sesion-Tokyo-Tyger-Mayo-1-6.webp') }}" alt=""></div>

                <!-- VIDEOS 11 y 12 (Corregidos) -->
                <div class="foto11">
                    <video class="lazy-video" playsinline loop muted webkit-playsinline preload="none"
                        data-src="{{ asset('/video/IMG_7614.webm') }}"></video>
                </div>
                <div class="foto12">
                    <video class="lazy-video" playsinline loop muted webkit-playsinline preload="none"
                        data-src="{{ asset('/video/Foto_Comida.webm') }}"></video>
                </div>
                <div class="contacto">
                    <h3>¿Podemos hacer algo por ti?</h3>
                    <a href="{{ route('cotizar') }}">Contáctanos</a>
                </div>

            </div>
        </div>
    </section>
    <x-detalle>
        <x-slot name="portafolioImg">
            <img loading="lazy" src="{{ asset('/img/3.svg') }}" class="base mostrar" alt="Demo reel agencia Vandu">
            <img loading="lazy" src="{{ asset('/img/2.svg') }}" class="middle mostrar"
                alt="Demo reel agencia Vandu">
            <video id="lazy-video" controls playsinline loop muted webkit-playsinline preload="none"
                data-src="{{ asset('/video/demo-reel.webm') }}" height="350" class="up-a mostrar"
                alt="Demo reel agencia Vandu"
                title="Video demostrativo de beneficios de producción audiovisual para empresas">
                <!-- Es mejor poner el type aquí adentro -->
                <source type="video/webm">
            </video>
        </x-slot>
        <div class="contenido_espacio">
            <x-slot name="titularServicios">
                <span>Ingeniería Visual: Nuestra Metodología de Alto Impacto.</span>
            </x-slot>
            <x-slot name="parrafoServicios">
                No dejamos nada al azar. Nuestro proceso integra estrategia de marca, ejecución técnica de vanguardia y
                postproducción de precisión para asegurar que cada fotograma trabaje en favor de tus objetivos
                comerciales.
            </x-slot>
            <x-slot name="botonServicios">
                <a href="{{ route('cotizar') }}" class="btn-servicios">Hablar por whatsapp</a>
            </x-slot>
        </div>
    </x-detalle>
    <x-quehacemos>
        <x-slot name="quehacemos">
            <h4>Qué hacemos: Producción Audiovisual con Propósito</h4>
            <p>Somos una productora de video y fotografía enfocada en elevar el estándar visual de las marcas.</p>
        </x-slot>
        <x-slot name="listaUno">
            <li>
                <span><b>Aumento de Conversión:</b>
                    El video profesional incrementa la confianza del cliente y las ventas hasta en un 80%.</span>
            </li>
            <li>
                <span><b>Posicionamiento SEO y Retención:</b>
                    Los algoritmos de Google y las redes sociales priorizan el video porque mantiene a los usuarios más
                    tiempo en tu sitio, reduciendo la tasa de rebote y mejorando orgánicamente tu ranking en los
                    buscadores.</span>
            </li>
        </x-slot>
        <x-slot name="listados">
            <li>
                <span><b>Conexión Emocional y Engagement:</b>
                    Fotografía de alta calidad que te separa de la competencia y proyecta autoridad.</span>
            </li>
            <li>
                <span><b>Optimización de la Comunicación Interna y Externa:</b>
                    Sustituimos textos densos por contenido visual directo y efectivo. Ya sea para explicar un servicio
                    complejo o presentar un informe anual, el video garantiza que tu mensaje se entienda rápido, sin
                    errores y de forma memorable.</span>
            </li>
        </x-slot>
    </x-quehacemos>

    <x-cta>
        <x-slot name="titulocta">
            <h5>¿Quieres una marca que enamore?</h2>
        </x-slot>
    </x-cta>
    <x-validacion>
        <x-slot name="logos">
            <div class="row logos">
                <div class="col">
                    <img loading="lazy" src="{{ asset('img/mercadopago.svg') }}" alt="Logotipo mercadopago">
                </div>
                <div class="col">
                    <img loading="lazy" src="{{ asset('img/hostgator.svg') }}" alt="Logotipo mercadopago">
                </div>
                <div class="col">
                    <img loading="lazy" src="{{ asset('img/paypal.svg') }}" alt="Logotipo mercadopago">
                </div>
                <div class="col">
                    <img loading="lazy" src="{{ asset('img/stripe.svg') }}" alt="Logotipo mercadopago">
                </div>
            </div>
        </x-slot>
       
    </x-validacion>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
    // Buscamos el video principal (ID) y los del portafolio (Clase)
    const lazyVideos = document.querySelectorAll("#lazy-video, .lazy-video");

    const videoObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const video = entry.target;
                
                // Como en tu HTML el data-src está en el <video>, 
                // lo pasamos directamente al src del elemento
                if (video.dataset.src) {
                    video.src = video.dataset.src;
                    video.load();
                    
                    // Reproducimos. El .catch evita errores si el usuario 
                    // tiene bloqueado el autoplay
                    video.play().catch(e => console.log("Carga en espera:", e));
                }

                // Una vez que empieza a cargar, dejamos de vigilarlo
                observer.unobserve(video);
            }
        });
    }, {
        // Empieza a cargar 200px antes de que el usuario llegue
        rootMargin: "0px 0px 200px 0px", 
        threshold: 0.1 
    });

    lazyVideos.forEach(v => videoObserver.observe(v));
});
    </script>
</x-layouts.guest>
