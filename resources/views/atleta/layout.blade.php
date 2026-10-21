@php
    $cuenta = \App\Support\Cuentas::actual();
    $logo = $cuenta?->logo(true);
    $a = auth('atleta')->user();
    $tabs = [
        ['atleta.inicio', 'bi-house-door', 'bi-house-door-fill', 'Inicio'],
        ['atleta.clases', 'bi-calendar-week', 'bi-calendar-week-fill', 'Clases'],
        ['atleta.credencial', 'bi-qr-code', 'bi-qr-code', 'Mi QR'],
        ['atleta.perfil', 'bi-person', 'bi-person-fill', 'Perfil'],
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titulo', 'Mis clases') · {{ $cuenta?->nombre }}</title>
    <link rel="manifest" href="{{ route('atleta.manifiesto', ['n' => $cuenta?->slug]) }}">
    <meta name="theme-color" content="#0D1124">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ \Illuminate\Support\Str::limit((string) $cuenta?->nombre, 14, '') }}">
    <link rel="apple-touch-icon" href="{{ route('app.icono', 'apple-touch-icon.png') }}">
    <link rel="preload" href="{{ route('vandu.fuente') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,600;0,700;1,600;1,700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @include('atleta._estilos')
    @stack('head')
</head>
<body class="{{ $a ? 'con-tabs' : 'sin-tabs' }}">
@if($a)
<header class="cab">
    <div class="in">
        @if($logo)<img src="{{ $logo }}" alt="{{ $cuenta?->nombre }}" class="logo">@else<b class="marca">{{ $cuenta?->nombre }}</b>@endif
        @if($a)<a href="{{ route('atleta.perfil') }}" class="yo" aria-label="Mi perfil">
            @if($a->foto)<img src="{{ route('atleta.foto', ['v' => substr(md5((string) $a->foto), 0, 8)]) }}" alt="">@else<span>{{ $a->iniciales }}</span>@endif</a>@endif
    </div>
</header>
@endif
<main class="doc">
    @if(session('ok'))<div class="aviso ok" role="status"><i class="bi bi-check-circle-fill"></i> {{ session('ok') }}</div>@endif
    @if($errors->any())<div class="aviso error" role="alert"><i class="bi bi-exclamation-triangle-fill"></i> {{ $errors->first() }}</div>@endif
    @yield('contenido')
</main>
@if($a)
<nav class="tabs" aria-label="Portal">
    @foreach($tabs as [$ruta, $ico, $lleno, $texto])
        <a href="{{ route($ruta) }}" class="{{ request()->routeIs($ruta . '*') ? 'on' : '' }}" @if(request()->routeIs($ruta . '*')) aria-current="page" @endif><i class="bi {{ request()->routeIs($ruta . '*') ? $lleno : $ico }}"></i>{{ $texto }}</a>
    @endforeach
</nav>
@endif
<script>
if ('serviceWorker' in navigator) window.addEventListener('load', () => navigator.serviceWorker.register(@json(route('atleta.sw')), { scope: '/atleta' }).catch(() => {}));
</script>
@stack('scripts')
</body>
</html>
