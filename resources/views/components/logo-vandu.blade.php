@props(['archivo' => 'logo-vandu-blanco.svg', 'alt' => 'Agencia Vandu'])
@php
    // En otra cuenta (negocio) va su logo o su nombre; el ícono decorativo de Vandu no se muestra
    $cuentaLogo = \App\Support\Cuentas::esPrincipal() ? null : \App\Support\Cuentas::actual();
@endphp
@if($cuentaLogo)
    @if(! str_contains($archivo, 'icono'))
        @php $src = $cuentaLogo->logo(true); @endphp
        @if($src)
            <img src="{{ $src }}" alt="{{ $cuentaLogo->nombre }}" {{ $attributes->except(['width', 'height'])->merge(['style' => 'max-height:' . ($attributes->get('height') ?: 36) . 'px; width:auto; max-width:180px; object-fit:contain']) }}>
        @else
            <span {{ $attributes->except(['width', 'height', 'alt'])->merge(['style' => 'display:inline-block; font-weight:650; letter-spacing:-.01em; color:#fff; font-size:' . max(15, min(22, (int) (($attributes->get('height') ?: 30) * .62))) . 'px; line-height:1.1; max-width:200px']) }}>{{ $cuentaLogo->nombre }}</span>
        @endif
    @endif
@else
{{-- El logo va incrustado en la página: no depende de que public/img esté copiado en el servidor --}}
<img src="data:image/svg+xml;base64,{{ base64_encode(file_get_contents(resource_path('img/' . $archivo))) }}" alt="{{ $alt }}" {{ $attributes }}>
@endif
