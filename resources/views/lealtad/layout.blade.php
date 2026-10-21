@php
    $yo = auth('comensal')->user();
    $marca = config('vandu.plataforma.nombre');
    $tabs = [['lealtad.inicio', 'bi-house-heart', 'Inicio'], ['lealtad.qr', 'bi-qr-code', 'Mi QR'], ['lealtad.perfil', 'bi-person', 'Perfil']];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    {{-- App en el celular: sin zoom por doble toque (pellizcar sigue funcionando) y sin el zoom de iPhone al escribir en un campo --}}
    <style>html { touch-action: manipulation; -webkit-text-size-adjust: 100%; } @media (pointer: coarse) { input, select, textarea { font-size: max(16px, 1em) !important; } }</style>
    <title>@yield('titulo', 'Mis premios') · {{ $marca }}</title>
    <link rel="manifest" href="{{ route('lealtad.manifiesto') }}">
    <meta name="theme-color" content="#FFF4EC">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ $marca }}">
    <link rel="apple-touch-icon" href="{{ \App\Support\IconoApp::url(null, 'apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ \App\Support\IconoApp::url(null, 'favicon-32.png') }}">
    <link rel="preload" href="{{ route('vandu.fuente') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @include('lealtad._estilos')
    @stack('head')
</head>
<body class="{{ $yo ? 'con-tabs' : '' }} @yield('cuerpo')">
<header class="cab">
    <a href="{{ route('lealtad.inicio') }}" class="marca"><img src="{{ route('plataforma.img', 'logo-palabra-oscuro.svg') }}" alt="{{ $marca }}"></a>
    @if($yo)<a href="{{ route('lealtad.perfil') }}" class="yo" aria-label="Mi perfil">{{ $yo->iniciales }}</a>
    @elseif(! request()->routeIs('lealtad.entrar', 'lealtad.registro'))<a href="{{ route('lealtad.entrar') }}" class="entrar">Entrar</a>@endif
</header>
<main class="doc">
    @if(session('ok'))<div class="aviso ok" role="status"><i class="bi bi-check-circle-fill"></i> {{ session('ok') }}</div>@endif
    @if($errors->any())<div class="aviso error" role="alert"><i class="bi bi-exclamation-triangle-fill"></i> {{ $errors->first() }}</div>@endif
    @yield('contenido')
</main>
@if($yo)
<nav class="tabs" aria-label="{{ $marca }}">
    @foreach($tabs as [$ruta, $ico, $texto])
        <a href="{{ route($ruta) }}" class="{{ request()->routeIs($ruta) ? 'on' : '' }}" @if(request()->routeIs($ruta)) aria-current="page" @endif><i class="bi {{ $ico }}"></i>{{ $texto }}</a>
    @endforeach
</nav>
@endif
@stack('scripts')
</body>
</html>
