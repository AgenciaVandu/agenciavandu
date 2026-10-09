<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#13161D">
    <title>Sin conexión · Vandu</title>
    <style>
        @font-face { font-family: 'Geist'; src: url('{{ route('vandu.fuente') }}') format('woff2'); font-weight: 100 900; font-display: swap; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #13161D; color: #fff; font-family: 'Geist', system-ui, sans-serif; text-align: center; padding: 24px; }
        .c { max-width: 360px; }
        .ico { width: 72px; height: 72px; border-radius: 20px; background: #1B1F28; display: grid; place-items: center; margin: 0 auto 22px; }
        h1 { font-size: 24px; letter-spacing: -.02em; margin: 0 0 8px; font-weight: 600; }
        p { color: #B9BEC9; line-height: 1.55; margin: 0 0 24px; }
        button { font: inherit; font-weight: 600; background: #00F385; color: #13161D; border: 0; border-radius: 12px; padding: 13px 22px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="c">
        <div class="ico"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#00F385" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 2l20 20M8.5 16.5a5 5 0 0 1 7 0M2 8.8a15 15 0 0 1 4.2-2.6M10.7 5.1A15 15 0 0 1 22 8.8M5 12.9a10 10 0 0 1 5.2-2.7M12 20h.01"/></svg></div>
        <h1>Sin conexión</h1>
        <p>No hay internet en este momento. En cuanto vuelva la señal, tu panel carga como siempre.</p>
        <button type="button" onclick="location.reload()">Reintentar</button>
    </div>
    <script>window.addEventListener('online', () => location.reload());</script>
</body>
</html>
