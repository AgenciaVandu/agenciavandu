@push('estilos')
    @vite(['resources/scss/app.scss', 'resources/scss/global.scss', 'resources/scss/multimedia.scss', 'resources/js/app.js', 'resources/js/bguno.js', 'resources/js/observer.js'])
@endpush
<x-layouts.guest titulo="Diseñamos y desarrollamos sitios web y aplicaciones"
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
    <div class="marcas">
        <div class="logos-container">
            <div class="logos-slide">
                <img src="{{ asset('/img/logos-marcas/burger-king.svg') }}" width="40" alt="">
               <img src="{{ asset('/img/logos-marcas/KFC.svg') }}" width="40" alt="">
                <img src="{{ asset('/img/logos-marcas/pizza-hut.svg') }}" width="40" alt="">
                 <img src="{{ asset('/img/logos-marcas/saama-gray.svg') }}" width="60" alt="">
                <img src="{{ asset('/img/logos-marcas/gran-calzada.svg') }}" width="100" alt="">
                <img src="{{ asset('/img/logos-marcas/tokio07.svg') }}" width="40" alt="">
                 <img src="{{ asset('/img/logos-marcas/amavita.svg') }}" width="100" alt="">
                 <img src="{{ asset('/img/logos-marcas/osroca.svg') }}" width="100" alt="">
                <img src="{{ asset('/img/logos-marcas/turismaya.svg') }}" width="100" alt="">
                 <img src="{{ asset('/img/logos-marcas/casitas 1.svg') }}" width="50" alt="">
            </div>

            <div class="logos-slide">
                <img src="{{ asset('/img/logos-marcas/burger-king.svg') }}" width="40" alt="">
               <img src="{{ asset('/img/logos-marcas/KFC.svg') }}" width="40" alt="">
                <img src="{{ asset('/img/logos-marcas/pizza-hut.svg') }}" width="40" alt="">
                 <img src="{{ asset('/img/logos-marcas/saama-gray.svg') }}" width="60" alt="">
                <img src="{{ asset('/img/logos-marcas/gran-calzada.svg') }}" width="100" alt="">
                <img src="{{ asset('/img/logos-marcas/tokio07.svg') }}" width="40" alt="">
                 <img src="{{ asset('/img/logos-marcas/amavita.svg') }}" width="100" alt="">
                 <img src="{{ asset('/img/logos-marcas/osroca.svg') }}" width="100" alt="">
                <img src="{{ asset('/img/logos-marcas/turismaya.svg') }}" width="100" alt="">
                 <img src="{{ asset('/img/logos-marcas/casitas 1.svg') }}" width="50" alt="">
            </div>
        </div>
    </div>
    <section class="portafolio-visual">
        <div class="container">
            <div class="grid-contenido">
                <div class="foto1">
                    <img loading="lazy" src="{{ asset('/img/webp/2026/Sesión-Tokyo-Tyger-Mayo-37-1.webp') }}" alt="">
                </div>
                <div class="foto2">
                    <img loading="lazy" src="{{ asset('/img/webp/2026/Samma-25-Alberto-1.webp') }}" alt="">
                </div>
                <div class="foto3">
                    <video preload="metadata" src="{{ asset('/video/croc.webm') }}" muted autoplay loop alt="">
                </div>
                <div class="foto4">
                    <video preload="metadata" src="{{ asset('/video/ronqueo-kurouma.webm') }}" muted autoplay loop alt="">
                </div>
                <div class="foto5">
                    <video preload="metadata" src="{{ asset('/video/vuelo-drone.webm') }}" muted autoplay loop alt="">
                </div>
                <div class="foto6">
                    <img loading="lazy" src="{{ asset('/img/webp/2026/kuro-23.webp') }}" alt="">
                </div>
                <div class="foto7">
                    <img loading="lazy" src="{{ asset('/img/webp/2026/antelar-9.webp') }}" alt="">
                </div>
                <div class="foto8">
                    <img loading="lazy" src="{{ asset('/img/webp/2026/antelar-41.webp') }}" alt="">
                </div>
                <div class="foto9">
                    <img loading="lazy" src="{{ asset('/img/webp/2026/Samma-18-Alberto-1.webp') }}" alt="">
                </div>
                <div class="foto10">
                    <img loading="lazy" src="{{ asset('/img/webp/2026/Sesión-Tokyo-Tyger-Mayo-1-6.webp') }}" alt="">
                </div>
                <div class="foto11">
                    <video preload="metadata" src="{{ asset('/video/IMG_7614.webm') }}" autoplay muted loop alt="">
                </div>
                <div class="foto12">
                    <video preload="metadata" src="{{ asset('/video/Foto_Comida.webm') }}" autoplay muted loop alt="">
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
            <img loading="lazy" src="{{ asset('/img/2.svg') }}" class="middle mostrar" alt="Demo reel agencia Vandu">
            <video preload="metadata" src="{{ asset('/video/demo-reel.webm') }}" height="350" class="up-a mostrar" autoplay muted
                loop alt="Demo reel agencia Vandu"
                title="Video demostrativo de beneficios de producción audiovisual para empresas" type="video/webm">
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
                <a href="" class="btn-servicios">Cotizar proyecto</a>
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
        <x-slot name="clientes">
            <section id="formulario" class="formulario">
        <div class="container text-white" >
            <script src="https://js.hsforms.net/forms/embed/51078765.js" defer></script>
            <div class="hs-form-frame" data-region="na1" data-form-id="016947aa-4806-4b0e-87e5-921f88476d97"
                data-portal-id="51078765"></div>
        </div>
    </section>
        </x-slot>
    </x-validacion>
</x-layouts.guest>
