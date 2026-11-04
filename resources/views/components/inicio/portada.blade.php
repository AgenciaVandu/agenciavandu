{{-- Portada: cuadrícula con celdas que se encienden, titular en Geist Black que se descifra y, debajo, los planos. --}}
@php $I = \App\Support\Inicio::class; @endphp
<section class="vx-portada" id="inicio">
    <div class="vx-celdas" aria-hidden="true">
        <span style="left:960px;top:80px;animation-delay:0s"></span><span style="left:1200px;top:240px;animation-delay:1.4s"></span>
        <span style="left:1040px;top:400px;animation-delay:2.8s"></span><span style="left:1280px;top:80px;animation-delay:4.2s"></span>
        <span style="left:80px;top:640px;animation-delay:5.6s"></span><span style="left:1120px;top:560px;animation-delay:3.5s"></span>
    </div>
    <div class="vx-caja vx-portada-caja">
        <p class="vx-etiqueta vx-portada-etiqueta"><span data-descifra-carga>Agencia digital · Mérida, México</span><span class="vx-entra">Sitios · Sistemas · Video · Foto · Impresión</span></p>
        <h1 data-descifra>Que a tu negocio lo encuentren y lo elijan<span class="vx-verde">.</span></h1>
        <div class="vx-portada-pie vx-entra">
            <p>Diseñamos y programamos sitios y sistemas, y producimos el video, la foto y los impresos de tu marca. Un solo equipo para negocios de todo México.</p>
            <div class="vx-botones">
                <a href="#contacto" class="vx-btn vx-btn-verde">Cotiza tu proyecto <span class="vx-fl" aria-hidden="true">→</span></a>
                <a href="#trabajo" class="vx-btn vx-btn-linea">Ver el trabajo</a>
            </div>
        </div>
    </div>

    <x-inicio.planos />
</section>
