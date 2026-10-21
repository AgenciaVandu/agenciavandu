<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>Iniciar sesión · {{ request()->attributes->get('cuenta_dominio')?->nombre ?? config('vandu.plataforma.nombre') }}</title>
    <link rel="icon" href="/favi.svg" type="image/svg+xml">
    <link rel="manifest" href="{{ route('app.manifiesto', ['v' => \App\Support\IconoApp::huella(request()->attributes->get('cuenta_dominio'))]) }}">
    <meta name="theme-color" content="#1A103D">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ request()->attributes->get('cuenta_dominio')?->nombre ?? config('vandu.plataforma.nombre') }}">
    <link rel="apple-touch-icon" href="{{ \App\Support\IconoApp::url(request()->attributes->get('cuenta_dominio'), 'apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ \App\Support\IconoApp::url(request()->attributes->get('cuenta_dominio'), 'favicon-32.png') }}">
    <link rel="preload" href="{{ route('vandu.fuente') }}" as="font" type="font/woff2" crossorigin>
    <style>
        @font-face { font-family: 'Geist'; src: url('{{ route('vandu.fuente') }}') format('woff2'); font-weight: 100 900; font-display: swap; }
        :root { --ink: #1A103D; --ink-2: #241750; --marca: #6B3BFF; --marca-2: #5A2BEE; --cian: #41C9FF; --line: #E6E3EE; --muted: #6D6884; --red: #b42318; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; font-family: 'Geist', system-ui, sans-serif; color: var(--ink);
               background: #fff; display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 6fr); }

        /* Lado de marca */
        .marca { background: radial-gradient(90% 70% at 85% 100%, rgba(107,59,255,.55) 0%, transparent 60%), var(--ink); color: #fff; padding: 48px; display: flex; flex-direction: column; justify-content: space-between;
                 position: relative; overflow: hidden; }
        .marca > img, .marca .logo img { width: 128px; height: auto; }
        .marca img.logo-completo { width: 230px; height: auto; }
        .marca .personaje { position: absolute; right: -14px; bottom: -10px; width: min(52%, 300px); height: auto; pointer-events: none; filter: drop-shadow(0 24px 40px rgba(0,0,0,.35)); }
        .marca .texto { position: relative; z-index: 1; max-width: 60%; }
        .marca h1 { font-size: clamp(32px, 3.4vw, 50px); line-height: 1.02; font-weight: 800; letter-spacing: -.035em; margin: 0; max-width: 11ch; }
        .marca p { color: #BDB5DC; margin: 16px 0 0; max-width: 34ch; line-height: 1.5; }
        .marca .pie { color: #8E86B0; font-size: 14px; position: relative; z-index: 1; }
        /* La "A" de Vandu, grande y recortada, como textura */
        .marca .eco { position: absolute; right: -90px; bottom: -40px; width: 420px; opacity: .06; pointer-events: none; }

        /* Formulario */
        .form { display: flex; align-items: center; justify-content: center; padding: 48px 24px; }
        form { width: 100%; max-width: 380px; }
        form h2 { font-size: 26px; font-weight: 600; letter-spacing: -.01em; margin: 0 0 6px; }
        form .sub { color: var(--muted); margin: 0 0 32px; }
        label { display: block; font-size: 14px; font-weight: 500; margin-bottom: 6px; }
        .campo { margin-bottom: 20px; }
        input[type=email], input[type=password], input[type=text] {
            width: 100%; height: 48px; padding: 0 14px; font: inherit; font-size: 16px; color: var(--ink);
            border: 1.5px solid var(--line); border-radius: 10px; background: #fff; transition: border-color .15s, box-shadow .15s; }
        input:focus { outline: none; border-color: var(--marca); box-shadow: 0 0 0 4px rgba(107,59,255,.18); }
        .pass { position: relative; }
        .pass input { padding-right: 76px; }
        .ver { position: absolute; right: 6px; top: 6px; height: 36px; padding: 0 12px; border: 0; border-radius: 7px;
               background: transparent; font: inherit; font-size: 14px; font-weight: 500; color: var(--muted); cursor: pointer; }
        .ver:hover { background: #f3f3f3; color: var(--ink); }
        .error { color: var(--red); font-size: 14px; margin-top: 6px; display: flex; gap: 6px; }
        input.mal { border-color: var(--red); }
        .recordar { display: flex; align-items: center; gap: 10px; margin: 4px 0 28px; font-size: 15px; cursor: pointer; }
        .recordar input { width: 18px; height: 18px; accent-color: var(--marca); margin: 0; }
        button.entrar { width: 100%; height: 50px; border: 0; border-radius: 10px; background: var(--marca); color: #fff;
                        font: inherit; font-size: 16px; font-weight: 600; cursor: pointer; transition: background .15s, color .15s; }
        button.entrar:hover { background: var(--marca-2); }
        button.entrar:disabled { opacity: .7; cursor: wait; }
        :focus-visible { outline: 3px solid var(--marca); outline-offset: 2px; }
        .aviso { background: #e7fbf1; color: #087a46; padding: 12px 14px; border-radius: 10px; font-size: 15px; margin-bottom: 24px; }
        .volver { display: inline-block; margin-top: 28px; color: var(--muted); font-size: 14px; text-decoration: none; }
        .volver:hover { color: var(--ink); }

        @media (max-width: 820px) {
            body { grid-template-columns: minmax(0, 1fr); grid-template-rows: auto 1fr; }
            .marca { padding: 28px 24px; gap: 20px; }
            .marca > img, .marca .logo img { width: 104px; }
            .marca img.logo-completo { width: 168px; }
            .marca .personaje { width: 128px; right: 6px; bottom: -6px; }
            .marca .texto { max-width: 66%; }
            .marca h1 { font-size: 28px; max-width: none; }
            .marca p, .marca .pie { display: none; }
            .marca .eco { width: 220px; right: -50px; bottom: -60px; }
            .form { align-items: flex-start; padding-top: 36px; }
        }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
    </style>
</head>
<body>

@php $dom = request()->attributes->get('cuenta_dominio'); @endphp
<aside class="marca">
    @if($dom)
        <x-logo-vandu width="128" height="41" />
    @else
        <img class="logo-completo" src="{{ route('plataforma.img', 'logo-blanco.svg') }}" alt="{{ config('vandu.plataforma.nombre') }}. {{ config('vandu.plataforma.lema') }}">
    @endif
    <div class="texto">
        <h1>@if($dom){{ config('vandu.plataforma.lema') }}@else Menos herramientas. Más resultados.@endif</h1>
        <p>Clientes, ventas, agenda y finanzas de tu negocio en un solo lugar.</p>
    </div>
    <span class="pie">{{ $dom?->dominio ?? config('vandu.plataforma.nombre') . ' · hecho por ' . config('vandu.marca.sitio') }}</span>
    <img class="personaje" src="{{ route('plataforma.img', 'mascota.webp') }}" alt="" aria-hidden="true">
</aside>

<main class="form">
    <form method="post" action="{{ route('login.entrar') }}" novalidate
          onsubmit="this.querySelector('.entrar').disabled = true; this.querySelector('.entrar').textContent = 'Entrando…';">
        @csrf
        <h2>Inicia sesión</h2>
        <p class="sub">{{ $dom ? 'Entra con tu correo de ' . $dom->nombre . '.' : 'Entra con tu correo para ver tu negocio.' }}</p>

        @if(session('ok'))
            <div class="aviso" role="status">{{ session('ok') }}</div>
        @endif

        <div class="campo">
            <label for="email">Correo</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username" autofocus required
                   class="{{ $errors->has('email') ? 'mal' : '' }}" aria-describedby="{{ $errors->has('email') ? 'err-email' : '' }}">
            @error('email')<div class="error" id="err-email" role="alert">{{ $message }}</div>@enderror
        </div>

        <div class="campo">
            <label for="password">Contraseña</label>
            <div class="pass">
                <input type="password" id="password" name="password" autocomplete="current-password" required
                       class="{{ $errors->has('password') ? 'mal' : '' }}">
                <button type="button" class="ver" aria-controls="password"
                        onclick="const i = document.getElementById('password'); const v = i.type === 'password'; i.type = v ? 'text' : 'password'; this.textContent = v ? 'Ocultar' : 'Mostrar';">Mostrar</button>
            </div>
            @error('password')<div class="error" role="alert">{{ $message }}</div>@enderror
        </div>

        <label class="recordar"><input type="checkbox" name="recordar" value="1" @checked(old('recordar'))> Mantener la sesión iniciada</label>

        <button type="submit" class="entrar">Entrar</button>

        <p style="font-size:13.5px;color:var(--muted);margin:18px 0 0">¿Olvidaste tu contraseña? Pídele a quien administra el panel un enlace para crear una nueva.</p>
        @php $sitio = $dom ? $dom->ajuste('marca.sitio') : null; @endphp
        @if(! $dom)
            <a href="/" class="volver">Ir al sitio web</a>
        @elseif($sitio)
            <a href="https://{{ preg_replace('#^https?://#', '', $sitio) }}" class="volver" rel="noopener">Ir a {{ preg_replace('#^https?://#', '', $sitio) }}</a>
        @endif
    </form>
</main>

<script>if ('serviceWorker' in navigator) navigator.serviceWorker.register(@json(route('app.sw')), { scope: '/admin' }).catch(() => {});</script>
</body>
</html>
