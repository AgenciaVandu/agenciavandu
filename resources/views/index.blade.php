@push ('estilos')
@vite(['resources/scss/app.scss', 'resources/scss/global.scss', 'resources/scss/index.scss', 'resources/js/app.js', 'resources/js/bg.js' ])
@endpush
<x-layouts.guest titulo="Construimos el futuro de tu empresa en el mundo digital" meta-description="Somos una agencia de diseño y desarrollo web estratégico que ayuda a las empresas en momentos clave, nos enfocamos en lo esencial para desbloquear oportunidades significativas. Nuestras soluciones eficientes generan resultados tangibles.">

    <x-header>
        <x-slot name="header">
            <h1>Diseñamos y construimos el futuro de tu empresa en el mundo digital</h1>
        </x-slot>

    </x-header>
    <section class="intro">
        <div class="container">
            <div class="contenido">
                <p>Somos una agencia de diseño y desarrollo web estratégico que ayuda a las empresas en momentos clave, nos enfocamos en lo esencial para desbloquear oportunidades significativas. Nuestras soluciones eficientes generan resultados tangibles.</p>
                <p>¿Quieres ver un poco de nuestro trabajo?</p>
                <div class="botones">
                    <a href="#">Diseño web <span><img src="{{asset('img/arrow-2.svg')}}" class="arrow1" width="10" alt=""></span></a>
                    <a href="#">Diseño digital e impreso <span><img src="{{asset('img/arrow-2.svg')}}" class="arrow1" width="10" alt=""></a>
                    <a href="#">Fotografía <span><img src="{{asset('img/arrow-2.svg')}}" class="arrow1" width="10" alt=""></a>
                    <a href="#">Marcas<span><img src="{{asset('img/arrow-2.svg')}}" class="arrow1" width="10" alt=""></a>
                </div>
                <div class="cta">
                    <a href="#">Contáctanos <span><img src="{{asset('img/arrow-3.svg')}}" class="arrow1" width="10" alt=""></a>
                </div>
            </div>
        </div>
    </section>
    <section class="servicios">
        <div class="container">
            <div class="grid-contenido">
                <div class="web">
                    <div class="lista_contenido">
                        <li>CRM</li>
                        <li>Ventas</li>
                        <li>Desarrollo web</li>
                        <li>Agencia de viajes</li>
                    </div>
                    <div class="web__contenido">
                        <h3>Diseño web</h3>
                        <a href="">Ver más información <span><img src="{{asset ('img/arrow-3.svg') }}" class="arrow" width="10" alt=""></span></a>
                    </div>
                </div>
                <div class="fotografia">
                    <div class="lista_contenido">
                        <li>Alimentos</li>
                        <li>Restaurantes</li>
                        <li>Arquitectura</li>
                        <li>Eventos</li>
                    </div>
                    <div class="foto__contenido">
                        <h2>Fotografía</h2>
                        <a href="">Ver más información <span><img src="{{asset ('img/arrow-3.svg') }}" class="arrow" width="10" alt=""></span></a>
                    </div>
                </div>
                <div class="digital">
                    <div class="lista_contenido">
                        <li>Redes sociales</li>
                        <li>Marketing</li>
                        <li>Restaurantes</li>
                        <li>Inmobiliarias</li>
                    </div>
                    <div class="digital__contenido">
                        <h3>Diseño digital e impreso</h3>

                        <a href="">Ver más información <span><img src="{{asset ('img/arrow-3.svg') }}" class="arrow" width="10" alt=""></span></a>
                    </div>
                </div>
                <div class="impresos">
                    <div class="lista_contenido">
                        <li>Logotipo</li>
                        <li>Comunicación</li>
                        <li>Conceptualización</li>
                    </div>
                    <div class="impresos__contenido">
                        <h3>Identidad de marca</h3>
                        <a href="">Ver más información <span><img src="{{asset ('img/arrow-3.svg') }}" class="arrow" width="10" alt=""></span></a>
                    </div>
                </div>
                <div class="contacto">
                    <h3>¿Podemos hacer algo por ti?</h3>
                    <a href="">Contáctanos</a>
                </div>
            </div>
        </div>
    </section>
    <section class="herramientas">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 col-md-12 col-sm-12 m-auto titular">
                    <h1>Creamos tus siguientes <span>herramientas favoritas</span></h1>
                    <a href="">Ver productos</a>
                </div>
                <div class="col-lg-6 col-md-12 col-sm-12 contenido darktxt">
                    <div class="mensaje1">
                        <small>Reyna agendó una nueva cita para el 14 de noviembre en tu calendario.</small>
                    </div>
                    <div class="nacho">
                        <div class="contenido_nacho">
                            <img src="{{asset('img/nacho.svg')}}" width="20" class="" alt="">
                            <small>Nacho</small>
                        </div>
                    </div>
                    <div class="lucia">
                        <div class="contenido_lucia">
                            <img src="{{asset('img/lucia.svg')}}" width="20" class="" alt="">
                            <small>Lucia</small>
                        </div>
                    </div>
                    <div class="mensaje2">
                        <small>Recibiste un pago de GM.</small>
                    </div>
                    <div class="mensaje3">
                        <small>¡Hamburguesa con queso cargada con éxito! ¿Quieres cargar alguna promoción?</small>
                    </div>
                </div>
            </div>
            <div class="row detalles">
                <div class="col-lg-4  col">
                    <p>
                        <span>Fáciles de usar.</span>
                        Nuestras herramientas son útiles e intuitivas para que cualquier persona pueda usarlas.
                    </p>
                </div>
                <div class="col-lg-4  col">
                    <p>
                        <span>Optimizamos recursos. </span>
                        Ayudamos a que tu negocio use sus recursos de manera efectiva, transparente, ágil y contundente.
                    </p>
                </div>
                <div class="col-lg-4 col-12">
                    <p>
                        <span>Operación segura. </span>
                        Priorizamos la seguridad de tus movimientos, tu paso por el mundo digital será totalmente placentero.
                    </p>
                </div>
            </div>
        </div>
    </section>
    <x-validacion>
        <x-slot name="logos">
            <div class="row logos">
                <div class="col">
                    <img src="{{asset('img/mercadopago.svg')}}" alt="Logotipo mercadopago">
                </div>
                <div class="col">
                    <img src="{{asset('img/hostgator.svg')}}" alt="Logotipo mercadopago">
                </div>
                <div class="col">
                    <img src="{{asset('img/paypal.svg')}}" alt="Logotipo mercadopago">
                </div>
                <div class="col">
                    <img src="{{asset('img/stripe.svg')}}" alt="Logotipo mercadopago">
                </div>
            </div>
        </x-slot>
        <x-slot name="clientes">
            <div class="logos_clientes">
                <div class="primera-fila">
                    <img src="{{asset('img/burger.svg')}}" width="80" alt="Logo burger king">
                    <img src="{{asset('img/kfc.svg')}}" width="80" alt="Logo KFC">
                    <img src="{{asset('img/ph.svg')}}" width="80" alt="Logo Pizza hut">
                </div>
                <div class="segunda-fila">
                    <img src="{{asset('img/kanan.svg')}}" width="150" alt="Logo Kananfleet">
                    <img src="{{asset('img/turismaya.svg')}}" width="150" alt="Logo turismaya">
                </div>
                <img src="{{asset('img/nicxa.svg')}}" width="200" alt="Logo grupo nicxa">

            </div>
        </x-slot>
    </x-validacion>

</x-layouts.guest>