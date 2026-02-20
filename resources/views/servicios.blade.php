@push ('estilos')
@vite(['resources/scss/app.scss', 'resources/scss/global.scss', 'resources/scss/servicios.scss', 'resources/js/app.js', 'resources/js/observer.js', 'resources/js/portafolio.js', 'resources/js/diseno.js' ])
@endpush
<x-layouts.guest titulo="Diseñamos y desarrollamos sitios web y aplicaciones" meta-description="Somos una agencia de desarrollo que utiliza la tecnología a tu favor para elevar la experiencia de atención a clientes y obtener leads de manera sostenida y eficiente.">
    <x-header>
        <x-slot name="header">
            <h1>Conectamos tecnología y estrategia para generar leads de alto impacto</h1>
        </x-slot>
        <x-slot name="parrafo">
            <p class="darktxt">Somos una agencia de desarrollo web que utiliza la tecnología a tu favor para elevar la experiencia de atención a clientes y obtener leads de manera sostenida y eficiente.</p>
        </x-slot>
    </x-header>
    <section class="intro">
        <div class="container text-center">
            <div class="intro_contenido"></div>
        </div>
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
        <x-slot name="logos">
            <div class="row logos">
                <div class="col">
                    <img src="{{asset('img/black/mercadoblack.svg')}}" alt="Logotipo mercadopago">
                </div>
                <div class="col">
                    <img src="{{asset('img/black/hostgatorblack.svg')}}" alt="Logotipo mercadopago">
                </div>
                <div class="col">
                    <img src="{{asset('img/black/paypalblack.svg')}}" alt="Logotipo mercadopago">
                </div>
                <div class="col">
                    <img src="{{asset('img/black/stripeblack.svg')}}" alt="Logotipo mercadopago">
                </div>
            </div>
        </x-slot>
        <x-slot name="clientes">
            <div class="logos_clientes">
                <div class="primera-fila">
                    <img src="{{asset('img/black/burgerblack.svg')}}" width="80" alt="">
                    <img src="{{asset('img/black/kfcblack.svg')}}" width="80" alt="">
                    <img src="{{asset('img/black/phblack.svg')}}" width="80" alt="">
                </div>
                <div class="segunda-fila">
                    <img src="{{asset('img/black/kananfleetblack.svg')}}" width="150" alt="">
                    <img src="{{asset('img/black/turismayablack.svg')}}" width="150" alt="">
                </div>
                <img src="{{asset('img/black/nicxablack.svg')}}" width="200" alt="">

            </div>
        </x-slot>
    </x-validacion>
</x-layouts.guest>