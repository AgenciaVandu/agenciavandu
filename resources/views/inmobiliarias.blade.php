@push ('estilos')
@vite(['resources/scss/app.scss', 'resources/scss/global.scss', 'resources/scss/servicios.scss', 'resources/js/app.js', 'resources/js/observer.js', 'resources/js/portafolio.js', 'resources/js/diseno.js' ])
@endpush
<x-layouts.guest titulo="Diseño Web para Inmobiliarias y Bienes Raíces" meta-description="Desarrollo de páginas web para inmobiliarias y brokers. Sitios autoadministrables con filtros avanzados, mapas y optimización SEO en Mérida.">
    <x-header>
        <x-slot name="header">
            <h1>Páginas Web para Inmobiliarias: <br> Diseñadas para captar leads</h1>
        </x-slot>
        <x-slot name="parrafo">
            <p class="darktxt">Somos una agencia de desarrollo web que utiliza la tecnología a tu favor para elevar la experiencia de atención a clientes y obtener leads de manera sostenida y eficiente.</p>
        </x-slot>
    </x-header>
    <section class="mb-5">
        <x-clientes></x-clientes>
    </section>
   
    <section class="extracto">
        <div class="container">
            <p class="darktxt">Un sitio web debería ser más que un elemento estático, debe ser una entidad dinámica en constante evolución, perfectamente alineada a la identidad y los objetivos de tu marca. Creamos potentes sistemas diseñados para adaptarse y crecer.</p>
        </div>
    </section>
    <x-portafolio>

    </x-portafolio>
    <x-detalle>
        <x-slot name="portafolioImg">
            <img src="{{asset('/img/3.svg')}}" class="base mostrar" alt="portafolio de diseño web">
            <img src="{{asset('/img/2.svg')}}" class="middle mostrar" alt="portafolio de diseño web">
            <img src="{{asset('/img/port.svg')}}" class="up mostrar" alt="portafolio de diseño web">
        </x-slot>
        <div class="contenido_espacio">
            <x-slot name="titularServicios">
                <span class="darktxt"> Diseñamos y desarrollamos páginas web que atrapan</span>
            </x-slot>
            <x-slot name="parrafoServicios">
                El contenido, tiempo de retención y de carga son aspectos clave para el posicionamiento de tu empresa. Nos encargamos de usar las mejores prácticas para que puedas lograr tus objetivos mensuales.
            </x-slot>
            <x-slot name="botonServicios">
                <a href="{{route('cotizar')}}" class="btn-servicios">Cotizar proyecto</a>
            </x-slot>
        </div>
    </x-detalle>
    <x-quehacemos>
        <x-slot name="quehacemos">
            <h2>¿Qué hacemos por tu negocio de Bienes Raíces?</h2>
            <p>Llevamos tu plataforma inmobiliaria de 0 a 100. Nos encargamos de preparar absolutamente todo, creamos herramientas intuitivas tanto para los usuarios que buscan su próximo hogar como para tus asesores encargados de gestionar el inventario de preventas, departamentos y terrenos.</p>
        </x-slot>
        <x-slot name="listaUno">
            <li>
                <span>Research & Análisis de Mercado</span>
            </li>
            <li><span>Eustructura y Estrategia UX/UI</span></li>
            <li><span>earch Optimization (SEO Inmobiliario)</span></li>
            <li><span>Desarrollo Front-end Adaptable</span></li>
            <li><span>Desarrollo Back-end y Sistemas Dinámicos</span></li>
        </x-slot>
    </x-quehacemos>
    <x-cta>
        <x-slot name="titulocta">
            <h5>¿Quieres potenciar tu marca en internet?</h2>
        </x-slot>
    </x-cta>
    <x-validacion>
       
    </x-validacion>
</x-layouts.guest>