{{--
    Barra flotante del sitio: menú con luz que se desliza, menú de servicios, buscador (tecla /),
    hora de Mérida, botón Cotizar y menú en celular. Trae sus propios estilos y JS.

    Uso en cualquier página:   <x-barra />
    En el inicio va encima de la portada, sin espacio arriba:   <x-barra :espacio="false" />
--}}
@props(['espacio' => true])
@php
    $I = \App\Support\Inicio::class;
    $flecha = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
    $servicios = config('inicio.servicios');
@endphp
@once
    @vite(['resources/scss/barra.scss', 'resources/js/barra.js'])
@endonce

{{-- Solo uno por página aunque el componente esté en el layout y también en la vista --}}
@once
@if ($espacio)
    <div class="vx-cab-espacio" aria-hidden="true"></div>
@endif
<header class="vx-cab" data-cab>
    <div class="vx-capsula">
        <a href="{{ request()->routeIs('home') ? '#inicio' : route('home') }}" class="vx-cab-logo"><img src="{{ asset('img/logo-vandu-blanco.svg') }}" alt="Agencia Vandu" width="96" height="24"></a>
        <nav class="vx-menu" aria-label="Principal" data-menu>
            <span class="vx-menu-luz" aria-hidden="true"></span>
            @foreach (config('inicio.menu') as $k => [$texto, $id])
                <a href="{{ $I::ancla($id) }}" data-menu-item="{{ $k }}" @if ($k === 0) aria-haspopup="true" aria-expanded="false" aria-controls="vx-mega" @endif><small>{{ $I::n2($k) }}</small><span data-descifra-hover>{{ $texto }}</span></a>
            @endforeach
        </nav>
        <button type="button" class="vx-buscar" data-paleta-abrir aria-label="Buscar en el sitio (tecla /)">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
            <span class="vx-kbd">/</span>
        </button>
        <span class="vx-reloj"><span class="vx-cuadro" aria-hidden="true"></span>Mérida <time data-reloj="segundos">--:--:--</time></span>
        <a href="{{ $I::ancla('contacto') }}" class="vx-cotizar">Cotizar <span class="vx-cotizar-caja">{!! $flecha !!}</span></a>
        <button type="button" class="vx-hamburguesa" data-movil-abrir aria-expanded="false" aria-controls="vx-movil" aria-label="Abrir menú">
            <span></span><span></span>
        </button>
        <span class="vx-avance-pag" data-avance-pag aria-hidden="true"></span>
    </div>

    <div class="vx-mega" id="vx-mega" data-mega>
        @foreach (array_chunk($servicios, 4, true) as $columna)
            <div class="vx-mega-col">
                @foreach ($columna as $k => $s)
                    @php $e = $I::enlace($s['destino']); @endphp
                    <a href="{{ $e['href'] }}" @if ($e['servicio']) data-servicio="{{ $e['servicio'] }}" @endif><small>{{ $I::n2($k) }}</small><b>{{ $s['titulo'] }}</b><i aria-hidden="true">↗</i><span>{{ $s['corto'] }}</span></a>
                @endforeach
            </div>
        @endforeach
        <div class="vx-mega-cta">
            <p class="vx-mega-gris">¿No sabes por dónde empezar?</p>
            <p class="vx-mega-frase">Cuéntanos tu negocio y te decimos qué conviene primero.</p>
            <a href="{{ $I::ancla('contacto') }}">Platiquemos →</a>
        </div>
    </div>

    <div class="vx-movil" id="vx-movil" data-movil>
        @foreach (config('inicio.secciones') as $k => [$texto, $id])
            <a href="{{ $I::ancla($id) }}"><small>{{ $I::n2($k) }}</small>{{ $texto }}</a>
        @endforeach
    </div>
</header>

{{-- Buscador rápido (tecla /) --}}
<div class="vx-paleta" data-paleta hidden>
    <div class="vx-paleta-caja" role="dialog" aria-modal="true" aria-label="Buscar en el sitio">
        <input type="text" placeholder="Busca: sitio web, Nerby, fotos, cotizar…" data-paleta-q aria-label="Buscar" autocomplete="off">
        <div class="vx-paleta-res" role="listbox">
            @foreach ($I::buscable() as $b)
                <a href="{{ $b['href'] }}" role="option" data-paleta-op data-texto="{{ $b['buscar'] }}" @if ($b['servicio']) data-servicio="{{ $b['servicio'] }}" @endif><span>{{ $b['texto'] }}</span><small>{{ $b['tipo'] }}</small></a>
            @endforeach
            <p class="vx-paleta-nada" data-paleta-nada hidden>Nada con eso. ¿Lo platicamos por WhatsApp?</p>
        </div>
        <p class="vx-paleta-pie"><span><span class="vx-kbd">↑</span> <span class="vx-kbd">↓</span> moverte</span><span><span class="vx-kbd">Enter</span> abrir</span><span><span class="vx-kbd">Esc</span> cerrar</span></p>
    </div>
</div>
@endonce
