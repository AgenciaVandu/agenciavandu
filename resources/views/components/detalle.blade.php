<section class="detalles">
    <div class="container">
        <div class="contenido">
            <div class="row">
                <div class="col-lg-6 col-md-12 col-sm-12 contenido_portafolio ">
                    {{$portafolioImg}}
                </div>
                <div class="col-lg-6 col-md-12 col-sm-12 contenido_text m-auto">
                <h1 class="pb-2">{{$titularServicios ?? 'Escribe titulo servicios'}}</h1>
                    <p class="pb-2">{{$parrafoServicios ?? 'escribe servicios'}}</p>
                    {{$botonServicios ?? 'boton'}}
                </div>
            </div>
        </div>
    </div>
</section>