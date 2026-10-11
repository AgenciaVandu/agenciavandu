<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titulo') · {{ config('vandu.marca.nombre') }}</title>
    <link rel="icon" href="/favi.svg" type="image/svg+xml">
    <link rel="preload" href="{{ route('vandu.fuente') }}" as="font" type="font/woff2" crossorigin>
    <style>
        @font-face { font-family: 'Geist'; src: url('{{ route('vandu.fuente') }}') format('woff2'); font-weight: 100 900; font-display: swap; }
        :root { --ink: #13161D; --hdr: #242424; --mist: #F3F3F3; --line: #E3E4E8; --muted: #5d6270; --green: #00F385; --green-ink: #047A4B; --green-soft: #E3FBEF;
                --amber: #9A5300; --amber-soft: #FFF3DD; --blue: #2557D6; --blue-soft: #E8EEFC; --red: #B42318; }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body { margin: 0; font-family: 'Geist', system-ui, sans-serif; color: var(--ink); background: #F6F7F8; font-size: 16px; line-height: 1.5; }
        .num { font-variant-numeric: tabular-nums; }
        a { color: inherit; }
        :focus-visible { outline: 3px solid var(--green); outline-offset: 2px; }
        .doc { max-width: 920px; margin: 0 auto; padding: 24px 16px 64px; }

        .hdr { background: var(--hdr); color: #fff; border-radius: 16px; padding: 26px 30px 30px; }
        .hdr .top { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
        .hdr .top .contacto { font-size: 13px; color: #cfd2d8; text-align: right; }
        .hdr .top .contacto a { color: #cfd2d8; text-decoration: none; }
        .hdr h1 { font-size: clamp(24px, 4.2vw, 36px); line-height: 1.1; letter-spacing: -.02em; margin: 28px 0 6px; font-weight: 600; }
        .hdr .sub { color: #b9bdc6; margin: 0; }
        .hdr .volver { display: inline-flex; gap: 6px; align-items: center; color: #cfd2d8; text-decoration: none; font-size: 14px; margin-top: 22px; }
        .resumen { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px; }
        .resumen span { background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12); border-radius: 99px; padding: 5px 13px; font-size: 14px; }
        .resumen b { color: var(--green); font-weight: 600; }

        h2 { font-size: 20px; font-weight: 600; letter-spacing: -.01em; margin: 36px 0 12px; display: flex; align-items: baseline; gap: 10px; }
        h2 small { font-size: 14px; font-weight: 400; color: var(--muted); }
        .caja { background: #fff; border: 1px solid var(--line); border-radius: 16px; overflow: hidden; }
        .caja .cuerpo { padding: 18px 20px; }
        .caja + .caja { margin-top: 14px; }

        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; font: inherit; font-weight: 600; font-size: 14.5px; padding: 9px 16px; border-radius: 10px; border: 1.5px solid var(--line); background: #fff; color: var(--ink); cursor: pointer; }
        .btn:hover { border-color: var(--ink); }
        .btn.prim { background: var(--ink); color: #fff; border-color: var(--ink); }
        .btn.prim:hover { color: var(--green); }
        .btn.chico { padding: 6px 12px; font-size: 13.5px; }
        .btn svg { width: 16px; height: 16px; }

        .pill { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 500; padding: 2px 10px; border-radius: 99px; white-space: nowrap; }
        .pill::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .pill.ok { background: var(--green-soft); color: var(--green-ink); }
        .pill.pend { background: var(--amber-soft); color: var(--amber); }
        .pill.azul { background: var(--blue-soft); color: var(--blue); }
        .pill.gris { background: #EEF0F3; color: #4E5463; }

        .aviso-ok { background: var(--green-soft); color: var(--green-ink); border: 1px solid #BDF2D6; border-radius: 12px; padding: 12px 16px; margin-top: 16px; font-weight: 500; }
        .aviso-error { background: #FEF3F2; color: var(--red); border: 1px solid #FECDCA; border-radius: 12px; padding: 12px 16px; margin-top: 16px; }
        .aviso-error ul { margin: 4px 0 0; padding-left: 18px; }
        .vacio { background: #fff; border: 1px dashed var(--line); border-radius: 16px; padding: 28px; text-align: center; color: var(--muted); }
        .pie { margin-top: 48px; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 10px; color: var(--muted); font-size: 14px; }
        .pie a { color: var(--ink); font-weight: 600; text-decoration: none; }

        label { font-size: 14px; font-weight: 500; display: block; margin-bottom: 6px; }
        select, textarea, input[type=file] { width: 100%; font: inherit; font-size: 15px; border: 1.5px solid var(--line); border-radius: 10px; padding: 9px 12px; background: #fff; color: var(--ink); }
        textarea { resize: vertical; min-height: 84px; }
        select:focus, textarea:focus, input[type=file]:focus { outline: none; border-color: var(--ink); }
        @yield('estilos')

        @media (max-width: 640px) {
            .doc { padding: 12px 12px 48px; }
            .hdr { padding: 20px 20px 24px; border-radius: 14px; }
            .hdr .top .contacto { display: none; }
            .hdr h1 { margin-top: 22px; }
        }
    </style>
</head>
<body>
<main class="doc">
    <header class="hdr">
        <div class="top">
            <x-logo-vandu width="104" height="34" />
            <div class="contacto">
                @if(config('vandu.emisor.telefono'))<a href="tel:{{ preg_replace('/\D/', '', config('vandu.emisor.telefono')) }}">{{ config('vandu.emisor.telefono') }}</a><br>@endif
                @if(config('vandu.emisor.email'))<a href="mailto:{{ config('vandu.emisor.email') }}">{{ config('vandu.emisor.email') }}</a>@endif
            </div>
        </div>
        @yield('encabezado')
    </header>

    @if(session('ok'))<div class="aviso-ok" role="status">{{ session('ok') }}</div>@endif
    @if($errors->any())
        <div class="aviso-error" role="alert"><b>Revisa esto:</b><ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    @yield('contenido')

    <footer class="pie">
        <span>{{ config('vandu.marca.nombre') }}{{ config('vandu.marca.ciudad') ? ' · ' . config('vandu.marca.ciudad') : '' }}</span>
        @if($wa = config('vandu.whatsapp'))<span>¿Dudas? <a href="https://wa.me/{{ $wa }}?text={{ rawurlencode('Hola, soy ' . $p->empresa . ', tengo una duda.') }}" target="_blank" rel="noopener">Escríbenos por WhatsApp</a></span>@endif
    </footer>
</main>
</body>
</html>
