<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Iniciar sesión · Vandu</title>
    <link rel="icon" href="/favi.svg" type="image/svg+xml">
    <link rel="preload" href="/font/Geist-Variable.woff2" as="font" type="font/woff2" crossorigin>
    <style>
        @font-face { font-family: 'Geist'; src: url('/font/Geist-Variable.woff2') format('woff2'); font-weight: 100 900; font-display: swap; }
        :root { --ink: #13161D; --ink-2: #1d212b; --green: #00F385; --line: #E3E4E8; --muted: #5d6270; --red: #b42318; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; font-family: 'Geist', system-ui, sans-serif; color: var(--ink);
               background: #fff; display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 6fr); }

        /* Lado de marca */
        .marca { background: var(--ink); color: #fff; padding: 48px; display: flex; flex-direction: column; justify-content: space-between;
                 position: relative; overflow: hidden; }
        .marca img { width: 128px; height: auto; }
        .marca h1 { font-size: clamp(32px, 3.4vw, 48px); line-height: 1.05; font-weight: 600; letter-spacing: -.02em; margin: 0; max-width: 11ch; }
        .marca p { color: #a9adb8; margin: 16px 0 0; max-width: 34ch; line-height: 1.5; }
        .marca .pie { color: #6f7482; font-size: 14px; }
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
        input:focus { outline: none; border-color: var(--ink); box-shadow: 0 0 0 4px rgba(0,243,133,.35); }
        .pass { position: relative; }
        .pass input { padding-right: 76px; }
        .ver { position: absolute; right: 6px; top: 6px; height: 36px; padding: 0 12px; border: 0; border-radius: 7px;
               background: transparent; font: inherit; font-size: 14px; font-weight: 500; color: var(--muted); cursor: pointer; }
        .ver:hover { background: #f3f3f3; color: var(--ink); }
        .error { color: var(--red); font-size: 14px; margin-top: 6px; display: flex; gap: 6px; }
        input.mal { border-color: var(--red); }
        .recordar { display: flex; align-items: center; gap: 10px; margin: 4px 0 28px; font-size: 15px; cursor: pointer; }
        .recordar input { width: 18px; height: 18px; accent-color: var(--ink); margin: 0; }
        button.entrar { width: 100%; height: 50px; border: 0; border-radius: 10px; background: var(--ink); color: #fff;
                        font: inherit; font-size: 16px; font-weight: 600; cursor: pointer; transition: background .15s, color .15s; }
        button.entrar:hover { background: #000; color: var(--green); }
        button.entrar:disabled { opacity: .7; cursor: wait; }
        :focus-visible { outline: 3px solid var(--green); outline-offset: 2px; }
        .aviso { background: #e7fbf1; color: #087a46; padding: 12px 14px; border-radius: 10px; font-size: 15px; margin-bottom: 24px; }
        .volver { display: inline-block; margin-top: 28px; color: var(--muted); font-size: 14px; text-decoration: none; }
        .volver:hover { color: var(--ink); }

        @media (max-width: 820px) {
            body { grid-template-columns: 1fr; grid-template-rows: auto 1fr; }
            .marca { padding: 28px 24px; gap: 20px; }
            .marca img { width: 104px; }
            .marca h1 { font-size: 28px; max-width: none; }
            .marca p, .marca .pie { display: none; }
            .marca .eco { width: 220px; right: -50px; bottom: -60px; }
            .form { align-items: flex-start; padding-top: 36px; }
        }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
    </style>
</head>
<body>

<aside class="marca">
    <img src="/img/logo-vandu-blanco.svg" alt="Agencia Vandu" width="128" height="41">
    <div>
        <h1>Clientes y cotizaciones</h1>
        <p>Da de alta clientes, arma sus cotizaciones y compárteles un enlace con vigencia.</p>
    </div>
    <span class="pie">agenciavandu.com</span>
    <img class="eco" src="/img/icono-vandu.svg" alt="" aria-hidden="true">
</aside>

<main class="form">
    <form method="post" action="{{ route('login.entrar') }}" novalidate
          onsubmit="this.querySelector('.entrar').disabled = true; this.querySelector('.entrar').textContent = 'Entrando…';">
        @csrf
        <h2>Inicia sesión</h2>
        <p class="sub">Entra con tu correo de Vandu.</p>

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

        <a href="/" class="volver">Ir al sitio web</a>
    </form>
</main>

</body>
</html>
