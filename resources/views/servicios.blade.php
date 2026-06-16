@push('estilos')
    @vite(['resources/scss/app.scss', 'resources/scss/global.scss', 'resources/scss/servicios.scss', 'resources/js/app.js', 'resources/js/observer.js', 'resources/js/portafolio.js', 'resources/js/diseno.js'])
@endpush
<x-layouts.guest titulo="Diseño Web Profesional y Desarrollo a la Medida"
    meta-description="Somos una agencia de desarrollo que utiliza la tecnología a tu favor para elevar la experiencia de atención a clientes y obtener leads de manera sostenida y eficiente.">
    <x-header>
        <x-slot name="header">
            <h1>Conectamos tecnología y estrategia para generar leads de alto impacto</h1>
        </x-slot>
        <x-slot name="parrafo">
            <p class="darktxt">Creamos sitios web desde cero, con código limpio y veloces. Nada de plantillas lentas e
                idénticas que ahuyentan a tus clientes.</p>
        </x-slot>
    </x-header>
    <section class="mb-5">
        <x-clientes></x-clientes>
    </section>
    <section class="extracto">
        <div class="container">
            <p class="darktxt">Un sitio web debería ser más que un elemento estático, debe ser una entidad dinámica en
                constante evolución, perfectamente alineada a la identidad y los objetivos de tu marca. Creamos potentes
                sistemas diseñados para adaptarse y crecer.</p>
        </div>
    </section>
    <x-portafolio>

    </x-portafolio>

    <div class="contenido-visual">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 col-md-12 col-sm-12 m-auto pb-3 contenido-visual__txt">
                    <h2><span>Contenido visual</span> que enamora</h2>
                    <p class="pb-3">Tu página no llevará fotos falsas de internet. Incluimos producción de fotografía de marca y
                        video profesional para que tus clientes vean tu negocio real desde el primer segundo.</p>
                     <a href="https://wa.me/+529991460310" class="btn-visuales">Cotizar por WhatsApp</a>
                </div>
                <div class="col-lg-6 col-md-12 col-sm-12 text-center pt-5 pb-3">
                    <video id="lazy-video" controls playsinline loop muted webkit-playsinline preload="none"
                        data-src="{{ asset('/video/demo-reel1.webm') }}" height="auto" width="100%" class="up-a mostrar"
                        alt="Demo reel agencia Vandu"
                        title="Video demostrativo de beneficios de producción audiovisual para empresas">
                        <!-- Es mejor poner el type aquí adentro -->
                        <source type="video/webm">
                    </video>
                </div>
            </div>
        </div>
    </div>


    <x-detalle>
        <x-slot name="portafolioImg">
            <img src="{{ asset('/img/3.svg') }}" class="base mostrar" alt="portafolio de diseño web">
            <img src="{{ asset('/img/2.svg') }}" class="middle mostrar" alt="portafolio de diseño web">
            <img src="{{ asset('/img/port.svg') }}" class="up mostrar" alt="portafolio de diseño web">
        </x-slot>
        <div class="contenido_espacio">
            <x-slot name="titularServicios">
                <span class="darktxt"> Diseñamos y desarrollamos páginas web que atrapan</span>
            </x-slot>
            <x-slot name="parrafoServicios">
                El contenido, tiempo de retención y de carga son aspectos clave para el posicionamiento de tu empresa.
                Nos encargamos de usar las mejores prácticas para que puedas lograr tus objetivos mensuales.
            </x-slot>
            <x-slot name="botonServicios">
                <a href="https://wa.me/+529991460310" class="btn-servicios">Cotizar mi proyecto por WhatsApp</a>
            </x-slot>
        </div>
    </x-detalle>
    <x-quehacemos>
        <x-slot name="quehacemos">
            <h1>¿Qué hacemos?</h1>
            <p>Llevamos tu proyecto de 0 a 100, preparamos todo desde la estrategia inicial hasta el post - lanzamiento
                y mantenimiento.</p>
        </x-slot>
        <x-slot name="listaUno">
            <li>
                <span>Research & Análisis</span>
            </li>
            <li><span>Estrategia web</span></li>
            <li><span>Search Optimization (SEO)</span></li>
            <li><span>Desarrollo Front-end</span></li>
            <li><span>Desarrollo Back-end</span></li>
        </x-slot>
    </x-quehacemos>
    <x-cta>
        <x-slot name="titulocta">
            <h5>¿Quieres potenciar tu marca en internet?</h2>
        </x-slot>
    </x-cta>
    <x-validacion>

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
