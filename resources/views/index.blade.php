@push ('estilos')
@vite(['resources/scss/app.scss', 'resources/scss/global.scss', 'resources/scss/index.scss', 'resources/js/app.js' ])
@endpush
<x-layouts.guest titulo="Construimos el futuro de tu empresa en el mundo digital" meta-description="Somos una agencia de diseño y desarrollo web estratégico que ayuda a las empresas en momentos clave, nos enfocamos en lo esencial para desbloquear oportunidades significativas. Nuestras soluciones eficientes generan resultados tangibles.">

    <header class="header">
        <div class="container">
            <h1>Diseñamos y construimos el futuro de tu empresa en el mundo digital</h1>
        </div>
    </header>
    <section class="intro">
        <div class="container-fluid">
            <div class="contenido">
                <p>Somos una agencia de diseño y desarrollo web estratégico que ayuda a las empresas en momentos clave, nos enfocamos en lo esencial para desbloquear oportunidades significativas. Nuestras soluciones eficientes generan resultados tangibles.</p>
                <p>¿Quieres ver un poco de nuestro trabajo?</p>
                <div class="botones">
                    <a href="#" class="btn btn-primary">Diseño web <span><img src="{{asset('img/arrow-2.svg')}}" class="arrow1" width="10" alt=""></span></a>
                    <a href="#" class="btn btn-primary">Diseño gráfico <span><img src="{{asset('img/arrow-2.svg')}}" class="arrow1" width="10" alt=""></a>
                    <a href="#" class="btn btn-primary">Fotografía <span><img src="{{asset('img/arrow-2.svg')}}" class="arrow1" width="10" alt=""></a>
                </div>
                <div class="cta">
                    <a href="" class="btn btn-secondary">Contáctanos <span><img src="{{asset('img/arrow-3.svg')}}" class="arrow1" width="10" alt=""></a>
                </div>
            </div>
        </div>
    </section>
    <section class="servicios">
        <div class="container">
            <div class="grid-contenido">
                <div class="web">
                    <div class="web__contenido">
                        <h3>Diseño web</h3>
                        <p>Lorem ipsum dolor sit, amet consectetur adipisicing elit. Expedita recusandae iste officia libero minus quaerat saepe quasi praesentium hic et!</p>
                        <a href="">Ver más información <span><img src="{{asset ('img/arrow-3.svg') }}" class="arrow" width="10" alt=""></span></a>
                    </div>
                </div>
                <div class="fotografia">
                    <div class="foto__contenido">
                        <h2>Fotografia</h2>
                        <p>Lorem ipsum dolor sit, amet consectetur adipisicing elit. Expedita recusandae iste officia libero </p>
                        <a href="">Ver más información <span><img src="{{asset ('img/arrow-3.svg') }}" class="arrow" width="10" alt=""></span></a>
                    </div>
                </div>
                <div class="digital">
                    <div class="digital__contenido">
                        <h3>Diseño digital</h3>
                        <p>Lorem ipsum dolor sit, amet consectetur adipisicing elit. Expedita recusandae iste officia libero minus quaerat saepe quasi praesentium hic et!</p>
                        <a href="">Ver más información <span><img src="{{asset ('img/arrow-3.svg') }}" class="arrow" width="10" alt=""></span></a>
                    </div>
                </div>
                <div class="impresos">
                    <div class="impresos__contenido">
                        <h3>Diseño de impresos y empaque</h3>
                        <p>Lorem ipsum dolor sit, amet consectetur adipisicing elit. Expedita recusandae iste officia libero minus quaerat saepe quasi praesentium hic et!</p>
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
                <div class="col-lg-6 col-md-12 col-sm-12 m-auto">
                    <h1>Creamos tus siguientes <span>herramientas favoritas</span></h1>
                    <a href="">Ver productos</a>
                </div>
                <div class="col-lg-6 col-md-12 col-sm-12 contenido">
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
                    <div>
                        <small class="mensaje2">Recibiste un pago de GM.</small>
                    </div>
                    <div>
                        <small class="mensaje3">¡Hamburguesa con queso cargada con éxito! ¿Quieres cargar alguna promoción?</small>
                    </div>
                </div>
            </div>
        </div>
    </section>

</x-layouts.guest>