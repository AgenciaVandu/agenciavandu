@push ('estilos')
@vite(['resources/scss/app.scss', 'resources/scss/global.scss', 'resources/scss/servicios.scss', 'resources/js/app.js' ])
@endpush
<x-layouts.guest>
    <x-header>
        <x-slot name="header">
            <h1>Conectamos tecnología y estrategia para generar leads de alto impacto</h1>
        </x-slot>
        <x-slot name="parrafo">
            <p>Somos una agencia de marketing digital que utiliza la tecnología a tu favor para elevar la experiencia de atención a clientes y obtener leads de manera sostenida y eficiente</p>
        </x-slot>
    </x-header>
    <section class="intro">
        <div class="container text-center">
            <img src="{{asset('img/fondo.svg')}}" class="w-100" alt="">
        </div>
    </section>
    <section class="extracto">
        <div class="container">
            <p>Un sitio web debería ser más que un elemento estático, debe ser una entidad dinámica en constante evolución, perfectamente alineada a la identidad y los objetivos de tu marca. Creamos potentes sistemas diseñados para adaptarse y crecer.</p>
        </div>
    </section>
    <x-portafolio>

    </x-portafolio>
    <x-detalle>
        <x-slot name="portafolioImg">
            <div class="muestra">
                <img src="{{asset('/img/port.svg')}}" class="base mostrar" alt="portafolio de diseño web">
                <img src="{{asset('/img/port.svg')}}" class="middle" alt="portafolio de diseño web">
                <img src="{{asset('/img/port.svg')}}" class="up" alt="portafolio de diseño web">

            </div>
            <x-slot name="titularServicios">
                Diseñamos y desarrollamos páginas web que atrapan
            </x-slot>
            <x-slot name="parrafoServicios">
                El contenido, tiempo de retención y de carga son aspectos clave para el posicionamiento de tu empresa. Nos encargamos de usar las mejores prácticas para que puedas lograr tus objetivos mensuales.
            </x-slot>
            <x-slot name="botonServicios">
                <a href="" class="btn-servicios">Cotizar proyecto</a>
            </x-slot>
        </x-slot>
    </x-detalle>
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