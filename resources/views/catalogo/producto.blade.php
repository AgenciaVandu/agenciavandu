@php
    /** @var \App\Models\Producto $p */
    $marca = config('vandu.marca.nombre') ?: $cuenta->nombre;
    $iva = (float) config('vandu.iva_porcentaje', 16);
    $conIva = round($p->precio * (1 + $iva / 100), 2);
    $resumen = \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', (string) $p->descripcion), 155) ?: $p->nombre . ' en ' . $marca . '.';
    $tel = config('vandu.emisor.telefono');
    $sitio = config('vandu.emisor.sitio') ?: config('vandu.marca.sitio');
    $waIcono = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.7-1.4.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.4.1-.7.3-.2.3-.9.9-.9 2.2s.9 2.5 1 2.7c.1.2 1.8 2.8 4.4 3.9 1.6.7 2.3.8 3.1.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.6-.3Z"/></svg>';
    $conV = fn ($url) => $url . ($v ? '?v=' . $v : '');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $p->nombre }} | {{ $marca }}</title>
    <meta name="description" content="{{ $resumen }}">
    @if($vistaPrevia)<meta name="robots" content="noindex, nofollow">@endif
    <link rel="canonical" href="{{ $p->url }}">
    <meta property="og:type" content="product">
    <meta property="og:title" content="{{ $p->nombre }}">
    <meta property="og:description" content="{{ $resumen }}">
    <meta property="og:url" content="{{ $p->url }}">
    <meta property="og:site_name" content="{{ $marca }}">
    @if($p->foto_url)<meta property="og:image" content="{{ $p->foto_url }}"><meta name="twitter:card" content="summary_large_image">@endif
    <link rel="preload" href="{{ route('vandu.fuente') }}" as="font" type="font/woff2" crossorigin>
    <script type="application/ld+json">{!! json_encode(array_filter([
        '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $p->nombre, 'sku' => $p->clave,
        'description' => $p->descripcion ?: null, 'image' => $p->foto_url, 'category' => $p->categoria?->nombre,
        'brand' => ['@type' => 'Brand', 'name' => $marca],
        'offers' => $p->muestra_precio && $p->precio > 0 ? ['@type' => 'Offer', 'price' => number_format($conIva, 2, '.', ''), 'priceCurrency' => 'MXN', 'url' => $p->url,
            'availability' => ! $p->controla_existencia || $p->existencia > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'] : null,
    ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    <style>
        @font-face { font-family: 'Geist'; src: url('{{ route('vandu.fuente') }}') format('woff2'); font-weight: 100 900; font-display: swap; }
        :root { --ink: #13161D; --muted: #5d6270; --line: #E3E4E8; --mist: #F4F5F7; --paper: #fff; --wa: #25D366; --wa-ink: #06301a; --ok: #0a7d4a; --mal: #c2372f; }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body { margin: 0; font-family: 'Geist', system-ui, sans-serif; color: var(--ink); background: var(--paper); font-size: 16px; line-height: 1.55; }
        a { color: inherit; }
        :focus-visible { outline: 3px solid var(--wa); outline-offset: 2px; }
        .num { font-variant-numeric: tabular-nums; }
        .previa { background: #FFF4D6; color: #6b4e00; text-align: center; font-size: 14px; padding: 8px 16px; }
        .barra { background: var(--ink); color: #fff; }
        .barra .in { max-width: 1120px; margin: 0 auto; padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .barra img { max-height: 34px; width: auto; display: block; }
        .barra .contacto { font-size: 14px; color: #d8dbe0; text-decoration: none; white-space: nowrap; }
        .barra .contacto:hover { color: #fff; }
        main { max-width: 1120px; margin: 0 auto; padding: 28px 20px 120px; }
        .migas { font-size: 14px; color: var(--muted); margin-bottom: 18px; }
        .ficha { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); gap: 48px; align-items: start; }
        .foto { aspect-ratio: 1; border-radius: 20px; background: var(--mist); overflow: hidden; display: grid; place-items: center; }
        .foto img { width: 100%; height: 100%; object-fit: contain; }
        .foto svg { width: 72px; height: 72px; color: #b9bdc6; }
        .info { position: sticky; top: 24px; }
        .cat { display: inline-block; font-size: 13px; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin-bottom: 8px; }
        h1 { font-size: clamp(28px, 4vw, 40px); line-height: 1.12; letter-spacing: -.02em; margin: 0 0 10px; font-weight: 650; }
        .clave { font-size: 14px; color: var(--muted); margin: 0 0 22px; }
        .precio { font-size: 34px; font-weight: 650; letter-spacing: -.01em; margin: 0; }
        .precio small { display: inline-block; font-size: 15px; font-weight: 400; color: var(--muted); letter-spacing: 0; line-height: 1.3; }
        .sin-iva { font-size: 14px; color: var(--muted); margin: 2px 0 0; }
        .exis { display: inline-flex; align-items: center; gap: 8px; margin: 18px 0 0; font-size: 14.5px; font-weight: 500; color: var(--ok); }
        .exis::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
        .exis.mal { color: var(--mal); }
        .acciones { margin: 26px 0 0; display: grid; gap: 10px; }
        .wa { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 16px 22px; border-radius: 999px; background: var(--wa); color: var(--wa-ink);
              font-weight: 600; font-size: 17px; text-decoration: none; transition: transform .15s ease, filter .15s ease; }
        .wa:hover { transform: translateY(-1px); filter: brightness(1.04); }
        .wa svg { width: 22px; height: 22px; }
        .compartir { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 13px 22px; border-radius: 999px; border: 1px solid var(--line); background: #fff; color: var(--ink); font: inherit; font-size: 15px; cursor: pointer; }
        .compartir:hover { border-color: var(--ink); }
        .vendedor { font-size: 14px; color: var(--muted); text-align: center; margin: 2px 0 0; }
        .desc { margin-top: 30px; padding-top: 26px; border-top: 1px solid var(--line); white-space: pre-line; color: #343843; }
        .desc h2, .mas h2 { font-size: 15px; font-weight: 600; margin: 0 0 8px; color: var(--ink); white-space: normal; }
        .mas { margin-top: 64px; }
        .rejilla { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
        .rejilla a { text-decoration: none; display: block; }
        .rejilla .f { aspect-ratio: 1; border-radius: 14px; background: var(--mist); overflow: hidden; margin-bottom: 10px; }
        .rejilla .f img { width: 100%; height: 100%; object-fit: cover; transition: transform .3s ease; }
        .rejilla a:hover img { transform: scale(1.04); }
        .rejilla .n { font-weight: 500; font-size: 15px; line-height: 1.3; }
        .rejilla .pr { font-size: 14px; color: var(--muted); }
        footer { border-top: 1px solid var(--line); padding: 22px 20px 100px; text-align: center; font-size: 13px; color: var(--muted); }
        .fijo { display: none; }
        @media (max-width: 860px) {
            .ficha { grid-template-columns: minmax(0, 1fr); gap: 24px; }
            .info { position: static; }
            .rejilla { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            main { padding-top: 16px; }
            .fijo { display: block; position: fixed; left: 0; right: 0; bottom: 0; z-index: 10; padding: 12px 16px calc(12px + env(safe-area-inset-bottom, 0px));
                    background: rgba(255,255,255,.94); backdrop-filter: blur(8px); border-top: 1px solid var(--line); }
            .acciones .wa { display: none; }
            .precio small { display: block; margin-top: 4px; }
        }
        @media (prefers-reduced-motion: reduce) { .wa, .rejilla .f img { transition: none; } }
    </style>
</head>
<body>
@if($vistaPrevia)
    <div class="previa">Vista previa: esta página no es pública{{ $p->activo ? '' : ' porque el producto está desactivado' }}. Solo tu equipo la puede ver.</div>
@endif
<header class="barra">
    <div class="in">
        <x-logo-vandu width="120" height="34" :alt="$marca" />
        @if($tel)<a class="contacto num" href="tel:{{ preg_replace('/\D/', '', $tel) }}">{{ $tel }}</a>@elseif($sitio)<a class="contacto" href="https://{{ $sitio }}" rel="noopener">{{ $sitio }}</a>@endif
    </div>
</header>

<main>
    @if($p->categoria)<div class="migas">{{ $p->categoria->nombre }}</div>@endif
    <div class="ficha">
        <div class="foto">
            @if($p->foto_url)
                <img src="{{ $p->foto_url }}" alt="{{ $p->nombre }}" width="1600" height="1600" fetchpriority="high">
            @else
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5M12 13v8"/></svg>
            @endif
        </div>

        <div class="info">
            <h1>{{ $p->nombre }}</h1>
            @if($p->clave)<p class="clave num">Clave {{ $p->clave }}</p>@endif

            @if($p->muestra_precio && $p->precio > 0)
                <p class="precio num">${{ number_format($conIva, 2) }} <small>MXN por {{ $p->unidad }}, IVA incluido</small></p>
                <p class="sin-iva num">${{ number_format($p->precio, 2) }} más IVA</p>
            @else
                <p class="precio" style="font-size: 22px">Pide tu cotización</p>
                <p class="sin-iva">Te respondemos por WhatsApp con precio y tiempo de entrega.</p>
            @endif

            @if($p->controla_existencia)
                @if($p->existencia > 0)<p class="exis">Disponible para entrega</p>
                @else<p class="exis mal">Agotado por ahora · pregunta por la fecha de llegada</p>@endif
            @endif

            <div class="acciones">
                @if($whatsapp)
                    <a class="wa" href="{{ $whatsapp }}" target="_blank" rel="noopener">
                        {!! $waIcono !!}
                        {{ $p->muestra_precio ? 'Lo quiero, escribir por WhatsApp' : 'Pedir precio por WhatsApp' }}
                    </a>
                @endif
                <button type="button" class="compartir" id="compartir" data-url="{{ $conV($p->url) }}" data-titulo="{{ $p->nombre }}">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8M16 6l-4-4-4 4M12 2v13"/></svg>
                    <span>Compartir</span>
                </button>
                @if($vendedor)<p class="vendedor">Te atiende {{ $vendedor->name }}</p>@endif
            </div>

            @if($p->descripcion)
                <div class="desc"><h2>Descripción</h2>{{ $p->descripcion }}</div>
            @endif
        </div>
    </div>

    @if($relacionados->isNotEmpty())
        <section class="mas">
            <h2>{{ $p->categoria ? 'Más en ' . $p->categoria->nombre : 'También te puede interesar' }}</h2>
            <div class="rejilla">
                @foreach($relacionados as $r)
                    <a href="{{ $conV($r->url) }}">
                        <div class="f">@if($r->miniatura_url)<img src="{{ $r->miniatura_url }}" alt="" loading="lazy">@endif</div>
                        <div class="n">{{ $r->nombre }}</div>
                        @if($r->muestra_precio && $r->precio > 0)<div class="pr num">${{ number_format($r->precio * (1 + $iva / 100), 2) }}</div>@endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</main>

<footer>{{ $marca }}@if(config('vandu.marca.ciudad')) · {{ config('vandu.marca.ciudad') }}@endif · Precios en pesos mexicanos, sujetos a cambio sin previo aviso.</footer>

@if($whatsapp)
    <div class="fijo"><a class="wa" href="{{ $whatsapp }}" target="_blank" rel="noopener" style="display:flex">
        {!! $waIcono !!}
        {{ $p->muestra_precio ? 'Escribir por WhatsApp' : 'Pedir precio por WhatsApp' }}</a></div>
@endif

<script>
    document.getElementById('compartir').addEventListener('click', async (e) => {
        const b = e.currentTarget, url = b.dataset.url, t = b.querySelector('span');
        if (navigator.share) { try { await navigator.share({ title: b.dataset.titulo, url }); } catch (err) {} return; }
        try { await navigator.clipboard.writeText(url); t.textContent = 'Enlace copiado'; setTimeout(() => t.textContent = 'Compartir', 1800); }
        catch (err) { window.prompt('Copia el enlace', url); }
    });
</script>
</body>
</html>
