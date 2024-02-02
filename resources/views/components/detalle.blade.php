<section class="detalles">
    <div class="container">
        <div class="contenido">
            <div class="contenido_portafolio text-center">
                {{$portafolioImg}}
            </div>
            <div class="contenido_texto">
                <h1 class="pb-2">{{$titularServicios ?? 'Escribe titulo servicios'}}</h1>
                <p class="pb-2">{{$parrafoServicios ?? 'escribe servicios'}}</p>
                {{$botonServicios ?? 'boton'}}
                
            </div>
        </div>
    </div>
</section>