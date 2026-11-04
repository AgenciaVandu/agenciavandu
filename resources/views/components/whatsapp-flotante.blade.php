{{--
    Botón flotante de WhatsApp: puntitos de "escribiendo…", "psst", y un panel con temas.
    Cada tema abre el formulario de WhatsApp del sitio con el servicio ya elegido.
    Se hace a un lado cuando se ve la sección #contacto. Trae sus propios estilos y JS.

    Uso en cualquier página:   <x-whatsapp-flotante />
--}}
@php $I = \App\Support\Inicio::class; @endphp
@once
    @vite(['resources/scss/whatsapp-flotante.scss', 'resources/js/whatsapp-flotante.js'])
@endonce

{{-- Solo uno por página aunque el componente esté en el layout y también en la vista --}}
@once
<div class="vx-wa" data-wa>
    <div class="vx-wa-panel" id="vx-wa-panel" data-wa-panel>
        <div class="vx-wa-cab">
            <p class="vx-wa-meta"><span>WhatsApp · Agencia Vandu</span><span>Mérida <time data-reloj>--:--</time></span></p>
            <p class="vx-wa-titulo">¿En qué te ayudamos?</p>
        </div>
        @foreach (config('inicio.whatsapp_temas') as $k => [$texto, $servicio, $mensaje])
            <a href="{{ $I::whatsapp() }}" data-servicio="{{ $servicio }}" @if ($mensaje) data-mensaje="{{ $mensaje }}" @endif><small>{{ $I::n2($k) }}</small><span>{{ $texto }}</span><i aria-hidden="true">↗</i></a>
        @endforeach
        <p class="vx-wa-nota">Se abre WhatsApp con tu mensaje listo para enviar.</p>
    </div>
    <button type="button" class="vx-wa-btn" data-wa-btn aria-expanded="false" aria-controls="vx-wa-panel" aria-label="Escríbenos por WhatsApp">
        <span class="vx-wa-ico">
            <svg class="vx-wa-w" width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91C21.95 6.45 17.5 2 12.04 2zm5.8 14.02c-.24.68-1.41 1.3-1.95 1.35-.5.05-1.13.07-1.82-.11-.42-.13-.96-.31-1.65-.61-2.9-1.25-4.8-4.17-4.94-4.36-.14-.19-1.18-1.57-1.18-3 0-1.43.75-2.13 1.02-2.42.27-.29.58-.36.78-.36h.56c.18 0 .42-.07.66.5.24.58.82 2.01.89 2.16.07.14.12.31.02.5-.1.19-.14.31-.29.48-.14.17-.3.38-.43.51-.14.14-.29.3-.13.59.17.29.74 1.22 1.59 1.98 1.09.97 2.01 1.27 2.3 1.42.29.14.46.12.63-.07.17-.19.72-.84.91-1.13.19-.29.38-.24.65-.14.26.1 1.68.79 1.97.94.29.14.48.22.55.34.07.12.07.7-.17 1.38z"/></svg>
            <svg class="vx-wa-x" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </span>
        <span class="vx-wa-txt"><b data-wa-texto>Escríbenos</b><small><span class="vx-cuadro" aria-hidden="true"></span>Respondemos por WhatsApp</small></span>
        <span class="vx-pts" aria-hidden="true"><i></i><i></i><i></i></span>
        <span class="vx-psst" aria-hidden="true">psst</span>
    </button>
</div>
@endonce
