@php
    /** @var \App\Models\Unidad $u */
    $marca = config('vandu.marca.nombre');
    $titulo = $u->marca . ' ' . $u->modelo . ($u->anio ? ' ' . $u->anio : '');
    $resumen = \Illuminate\Support\Str::limit(collect([$u->version, $u->kilometraje !== null ? number_format($u->kilometraje) . ' km' : null, $u->transmision, '$' . number_format($u->precio, 0)])->filter()->join(' · ') . '. ' . $u->descripcion, 155);
    $tel = config('vandu.emisor.telefono');
    $portada = $u->fotos->first();
    $waIcono = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.7-1.4.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.4.1-.7.3-.2.3-.9.9-.9 2.2s.9 2.5 1 2.7c.1.2 1.8 2.8 4.4 3.9 1.6.7 2.3.8 3.1.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.6-.3Z"/></svg>';
    $datos = array_filter(['Año' => $u->anio, 'Kilometraje' => $u->kilometraje !== null ? number_format($u->kilometraje) . ' km' : null, 'Transmisión' => $u->transmision,
        'Combustible' => $u->combustible, 'Tipo' => $u->tipo, 'Color' => $u->color]);
    $conV = fn ($url) => $url . ($v ? '?v=' . $v : '');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $titulo }} | {{ $marca }}</title>
    <meta name="description" content="{{ $resumen }}">
    @if($previa)<meta name="robots" content="noindex, nofollow">@endif
    <link rel="canonical" href="{{ $u->url }}">
    <meta property="og:type" content="product">
    <meta property="og:title" content="{{ $titulo }} · ${{ number_format($u->precio, 0) }}">
    <meta property="og:description" content="{{ $resumen }}">
    <meta property="og:url" content="{{ $u->url }}">
    @if($portada)<meta property="og:image" content="{{ $portada->url }}"><meta name="twitter:card" content="summary_large_image">@endif
    <link rel="preload" href="{{ route('vandu.fuente') }}" as="font" type="font/woff2" crossorigin>
    <script type="application/ld+json">{!! json_encode(array_filter([
        '@context' => 'https://schema.org', '@type' => 'Car', 'name' => $titulo, 'brand' => ['@type' => 'Brand', 'name' => $u->marca], 'model' => $u->modelo,
        'vehicleModelDate' => $u->anio ? (string) $u->anio : null, 'color' => $u->color, 'image' => $portada?->url,
        'mileageFromOdometer' => $u->kilometraje !== null ? ['@type' => 'QuantitativeValue', 'value' => $u->kilometraje, 'unitCode' => 'KMT'] : null,
        'offers' => ['@type' => 'Offer', 'price' => number_format($u->precio, 2, '.', ''), 'priceCurrency' => 'MXN', 'url' => $u->url,
                     'availability' => $u->estado === 'disponible' ? 'https://schema.org/InStock' : 'https://schema.org/LimitedAvailability'],
    ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    <style>
        @font-face { font-family: 'Geist'; src: url('{{ route('vandu.fuente') }}') format('woff2'); font-weight: 100 900; font-display: swap; }
        :root { --ink: #13161D; --muted: #5d6270; --line: #E3E4E8; --mist: #F4F5F7; --wa: #25D366; --wa-ink: #06301a; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Geist', system-ui, sans-serif; color: var(--ink); background: #fff; font-size: 16px; line-height: 1.55; }
        a { color: inherit; }
        :focus-visible { outline: 3px solid var(--wa); outline-offset: 2px; }
        .num { font-variant-numeric: tabular-nums; }
        .previa { background: #FFF4D6; color: #6b4e00; text-align: center; font-size: 14px; padding: 8px 16px; }
        .barra { background: var(--ink); color: #fff; }
        .barra .in { max-width: 1160px; margin: 0 auto; padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .barra img { max-height: 34px; width: auto; display: block; }
        .barra .contacto { font-size: 14px; color: #d8dbe0; text-decoration: none; }
        main { max-width: 1160px; margin: 0 auto; padding: 24px 20px 120px; }
        .ficha { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr); gap: 40px; align-items: start; }
        .principal { aspect-ratio: 4 / 3; border-radius: 18px; overflow: hidden; background: var(--mist); display: grid; place-items: center; }
        .principal img { width: 100%; height: 100%; object-fit: cover; }
        .principal svg { width: 80px; height: 80px; color: #b9bdc6; }
        .miniaturas { display: grid; grid-template-columns: repeat(auto-fill, minmax(84px, 1fr)); gap: 8px; margin-top: 10px; }
        .miniaturas button { padding: 0; border: 2px solid transparent; border-radius: 10px; overflow: hidden; aspect-ratio: 4 / 3; cursor: pointer; background: var(--mist); }
        .miniaturas button.activa { border-color: var(--ink); }
        .miniaturas img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .info { position: sticky; top: 20px; }
        h1 { font-size: clamp(28px, 4vw, 40px); line-height: 1.1; letter-spacing: -.02em; margin: 0 0 6px; font-weight: 650; }
        .sub { color: var(--muted); margin: 0 0 20px; }
        .precio { font-size: 36px; font-weight: 650; letter-spacing: -.01em; margin: 0; }
        .mensual { margin: 6px 0 0; font-size: 15px; color: var(--muted); }
        .mensual b { color: var(--ink); }
        .apartado { display: inline-block; margin-top: 12px; background: #FFF4D6; color: #6b4e00; font-size: 14px; font-weight: 500; padding: 4px 12px; border-radius: 99px; }
        .datos { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 24px 0 0; padding: 18px 0 0; border-top: 1px solid var(--line); }
        .datos div span { display: block; font-size: 12.5px; color: var(--muted); }
        .datos div b { font-weight: 500; }
        .acciones { margin-top: 24px; display: grid; gap: 10px; }
        .wa { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 16px 22px; border-radius: 999px; background: var(--wa); color: var(--wa-ink); font-weight: 600; font-size: 17px; text-decoration: none; }
        .wa svg { width: 22px; height: 22px; }
        .compartir { padding: 13px; border-radius: 999px; border: 1px solid var(--line); background: #fff; font: inherit; font-size: 15px; cursor: pointer; }
        .vendedor { text-align: center; font-size: 14px; color: var(--muted); margin: 0; }
        .bloque { margin-top: 40px; }
        .bloque h2 { font-size: 18px; font-weight: 600; margin: 0 0 12px; }
        .desc { white-space: pre-line; color: #343843; margin: 0; max-width: 70ch; }
        .equipo { columns: 2; padding-left: 20px; margin: 0; color: #343843; }
        .equipo li { margin-bottom: 4px; break-inside: avoid; }
        .otras { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
        .otras a { text-decoration: none; }
        .otras .f { aspect-ratio: 4 / 3; border-radius: 12px; overflow: hidden; background: var(--mist); margin-bottom: 8px; }
        .otras img { width: 100%; height: 100%; object-fit: cover; }
        .otras b { display: block; font-weight: 500; font-size: 15px; }
        .otras span { color: var(--muted); font-size: 14px; }
        footer { border-top: 1px solid var(--line); padding: 22px 20px 100px; text-align: center; font-size: 13px; color: var(--muted); }
        .fijo { display: none; }
        @media (max-width: 880px) {
            .ficha { grid-template-columns: minmax(0, 1fr); gap: 20px; }
            .info { position: static; }
            .otras { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .equipo { columns: 1; }
            .acciones .wa { display: none; }
            .fijo { display: block; position: fixed; inset: auto 0 0 0; z-index: 10; padding: 12px 16px calc(12px + env(safe-area-inset-bottom, 0px)); background: rgba(255,255,255,.95); backdrop-filter: blur(8px); border-top: 1px solid var(--line); }
            main { padding-top: 0; }
            .principal { border-radius: 0; margin: 0 -20px; }
        }
    </style>
</head>
<body>
@if($previa)<div class="previa">Vista previa: {{ $u->estado === 'vendido' ? 'esta unidad ya se vendió' : 'esta página no es pública' }}. Solo tu equipo la puede ver.</div>@endif
<header class="barra"><div class="in">
    <x-logo-vandu width="120" height="34" :alt="$marca" />
    @if($u->sucursal?->telefono || $tel)<a class="contacto num" href="tel:{{ preg_replace('/\D/', '', $u->sucursal?->telefono ?: $tel) }}">{{ $u->sucursal?->telefono ?: $tel }}</a>@endif
</div></header>

<main x-data>
    <div class="ficha">
        <div>
            <div class="principal">
                @if($portada)<img id="foto-grande" src="{{ $portada->url }}" alt="{{ $titulo }}" fetchpriority="high">
                @else<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M5 17h14M6 17l1.5-5h9L18 17M7.5 12 9 8h6l1.5 4"/><circle cx="8" cy="17" r="1.6"/><circle cx="16" cy="17" r="1.6"/></svg>@endif
            </div>
            @if($u->fotos->count() > 1)
                <div class="miniaturas" role="list" aria-label="Fotos">
                    @foreach($u->fotos as $f)
                        <button type="button" role="listitem" class="{{ $loop->first ? 'activa' : '' }}" data-grande="{{ $f->url }}" aria-label="Ver foto {{ $loop->iteration }}"><img src="{{ $f->miniatura_url }}" alt="" loading="lazy"></button>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="info">
            <h1>{{ $titulo }}</h1>
            <p class="sub">{{ collect([$u->version, $u->sucursal?->nombre])->filter()->join(' · ') }}</p>
            <p class="precio num">${{ number_format($u->precio, 0) }} <span style="font-size:15px; font-weight:400; color:var(--muted)">MXN</span></p>
            @if($estimada)<p class="mensual">Desde <b class="num">${{ number_format($estimada['mensualidad'], 0) }} al mes</b> a {{ $estimada['plazo'] }} meses, con ${{ number_format($estimada['enganche'], 0) }} de enganche.*</p>@endif
            @if($u->estado === 'apartado')<span class="apartado">Apartado · pregunta si sigue disponible</span>@endif

            @if($datos)
                <div class="datos">@foreach($datos as $k => $val)<div><span>{{ $k }}</span><b>{{ $val }}</b></div>@endforeach</div>
            @endif

            <div class="acciones">
                @if($whatsapp)<a class="wa" href="{{ $whatsapp }}" target="_blank" rel="noopener">{!! $waIcono !!} Me interesa, escribir por WhatsApp</a>@endif
                <button type="button" class="compartir" id="compartir" data-url="{{ $conV($u->url) }}" data-titulo="{{ $titulo }}">Compartir</button>
                @if($vendedor)<p class="vendedor">Te atiende {{ $vendedor->name }}</p>@endif
            </div>
        </div>
    </div>

    @if($u->descripcion)<section class="bloque"><h2>Descripción</h2><p class="desc">{{ $u->descripcion }}</p></section>@endif
    @if($u->ficha_lineas)<section class="bloque"><h2>Equipamiento y ficha técnica</h2><ul class="equipo">@foreach($u->ficha_lineas as $l)<li>{{ $l }}</li>@endforeach</ul></section>@endif

    @if($otras->isNotEmpty())
        <section class="bloque">
            <h2>Más autos disponibles</h2>
            <div class="otras">
                @foreach($otras as $o)
                    <a href="{{ $conV($o->url) }}">
                        <div class="f">@if($o->portada)<img src="{{ $o->portada->miniatura_url }}" alt="" loading="lazy">@endif</div>
                        <b>{{ $o->marca }} {{ $o->modelo }} {{ $o->anio }}</b><span class="num">${{ number_format($o->precio, 0) }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</main>

<footer>{{ $marca }} · @if($estimada)*Mensualidad estimada, sujeta a aprobación y a las condiciones del crédito. @endif Precios en pesos mexicanos.</footer>
@if($whatsapp)<div class="fijo"><a class="wa" href="{{ $whatsapp }}" target="_blank" rel="noopener" style="display:flex">{!! $waIcono !!} Escribir por WhatsApp</a></div>@endif

<script>
    document.querySelectorAll('[data-grande]').forEach((b) => b.addEventListener('click', () => {
        document.getElementById('foto-grande').src = b.dataset.grande;
        document.querySelectorAll('[data-grande]').forEach((x) => x.classList.toggle('activa', x === b));
    }));
    document.getElementById('compartir').addEventListener('click', async (e) => {
        const b = e.currentTarget;
        if (navigator.share) { try { await navigator.share({ title: b.dataset.titulo, url: b.dataset.url }); } catch (err) {} return; }
        try { await navigator.clipboard.writeText(b.dataset.url); b.textContent = 'Enlace copiado'; setTimeout(() => b.textContent = 'Compartir', 1800); } catch (err) { prompt('Copia el enlace', b.dataset.url); }
    });
</script>
</body>
</html>
