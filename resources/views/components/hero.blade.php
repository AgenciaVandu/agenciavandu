@php
    $whatsapp = 'https://wa.me/529991460310';
    $servicios = ['sitios web que venden', 'sistemas que funcionan con WhatsApp', 'videos con inteligencia artificial', 'fotografía que se antoja', 'rótulos, lonas y bordados'];
    $trabajos = [
        ['img' => 'antelar-41.webp', 'cliente' => 'Antelar', 'servicio' => 'Fotografía de interiores', 'alt' => 'Interior de hotel'],
        ['img' => 'kuro-23.webp', 'cliente' => 'Kuro', 'servicio' => 'Fotografía de alimentos', 'alt' => 'Platillo de sushi'],
        ['img' => 'sesion-tokyo-tyger-mayo-1-6.webp', 'cliente' => 'Tokyo Tyger', 'servicio' => 'Fotografía para restaurantes', 'alt' => 'Clienta en restaurante'],
        ['img' => 'samma-25-alberto-1.webp', 'cliente' => 'Samma', 'servicio' => 'Video corporativo', 'alt' => 'Grabación en obra'],
        ['img' => 'impresos-tarjetas.webp', 'cliente' => 'Impresos', 'servicio' => 'Papelería y producción', 'alt' => 'Tarjetas de presentación impresas'],
    ];
    $clientes = [
        ['KFC.svg', 'KFC', true], ['gpo-nicxa.svg', 'Grupo Nicxa', false], ['burger-king.svg', 'Burger King', true],
        ['gran-calzada.svg', 'Gran Calzada', false], ['pizza-hut.svg', 'Pizza Hut', true], ['amavita.svg', 'Amavita', false],
        ['osroca.svg', 'Osroca', false], ['tokio07.svg', 'Tokio 07', true], ['turismaya.svg', 'Turismaya', false],
        ['saama-gray.svg', 'Saama', false], ['casitas 1.svg', 'Casitas', false],
    ];
@endphp

<section class="vh-hero">
    <canvas class="vh-luces" aria-hidden="true"></canvas>
    <img class="vh-marca vh-m1" src="{{ asset('img/icono-vandu.svg') }}" alt="" aria-hidden="true">
    <img class="vh-marca vh-m2" src="{{ asset('img/icono-vandu.svg') }}" alt="" aria-hidden="true">

    <div class="vh-contenido">
        <div class="vh-insignia">
            <span class="vh-caras">
                <img src="{{ asset('img/hero/cara-sesion-tokyo-tyger-mayo-37-1.webp') }}" width="28" height="28" alt="">
                <img src="{{ asset('img/hero/cara-tokio07-mayo-1-1.webp') }}" width="28" height="28" alt="">
                <img src="{{ asset('img/hero/cara-samma-18-alberto-1.webp') }}" width="28" height="28" alt="">
            </span>
            <span>Más de <b>4 años</b> creando para marcas de todo México</span>
        </div>

        <h1 class="vh-titulo">Que a tu negocio <span class="vh-verde">lo encuentren y lo elijan</span>
            <span class="vh-sticker">HECHO EN MÉRIDA</span>
        </h1>

        <p class="vh-lead">
            Creamos <span class="vh-escribe" data-palabras='@json($servicios)'>{{ $servicios[0] }}</span><span class="vh-cursor" aria-hidden="true"></span>
            <small>Sitios web, sistemas, video con IA, fotografía y producción con un solo equipo. Desde Mérida para negocios de todo México que quieren vender más.</small>
        </p>

        <div class="vh-botones">
            <a class="vh-btn vh-btn-verde" href="{{ $whatsapp }}" target="_blank" rel="noopener">
                <img src="{{ asset('img/whatsapp.svg') }}" width="18" height="18" alt="">
                Cotiza tu proyecto por WhatsApp
            </a>
            <a class="vh-btn vh-btn-linea" href="#trabajo">Ver nuestro trabajo</a>
        </div>

        <div class="vh-abanico">
            @foreach ($trabajos as $i => $t)
                <figure class="vh-tarjeta vh-t{{ $i + 1 }}">
                    <img src="{{ asset('img/hero/' . $t['img']) }}" alt="{{ $t['alt'] }}" loading="{{ $i === 2 ? 'eager' : 'lazy' }}">
                    <figcaption><b>{{ $t['cliente'] }}</b><span>{{ $t['servicio'] }}</span></figcaption>
                </figure>
            @endforeach
        </div>
    </div>
</section>

<section class="vh-clientes">
    <p>Marcas que ya trabajan con nosotros</p>
    <div class="vh-marquesina">
        <div class="vh-pista">
            @foreach ([0, 1] as $copia)
                @foreach ($clientes as [$archivo, $nombre, $cuadrado])
                    <img @class(['vh-cuadrado' => $cuadrado]) src="{{ asset('img/logos-marcas/' . $archivo) }}"
                        alt="{{ $copia ? '' : 'Logo ' . $nombre }}" @if ($copia) aria-hidden="true" @endif>
                @endforeach
            @endforeach
        </div>
    </div>
</section>
