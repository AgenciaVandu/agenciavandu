@php
    /** @var \App\Models\Proyecto $p */
    $c = $p->cliente;
    $quien = $c?->empresa ?: $c?->nombre;
    $ruta = fn ($a, $v = null, $d = false) => route('proyecto.archivo', [$p->token, $a]) . '?' . http_build_query(array_filter(['v' => $v, 'descargar' => $d ? 1 : null]));
    $items = $galeria->map(fn ($a) => [
        'tipo'   => $a->es_imagen ? 'img' : ($a->es_video ? 'video' : 'archivo'),
        'vista'  => $a->es_imagen ? $ruta($a, 'vista') : $ruta($a),
        'bajar'  => $ruta($a, null, true),
        'poster' => $a->es_video && $a->vista ? $ruta($a, 'vista') : null,
        'nombre' => $a->nombre,
        'peso'   => $a->peso_texto,
    ])->values();
    $fechaEntrega = $galeria->max('created_at') ?? now();
    $wa = config('vandu.whatsapp');
    $waMsg = rawurlencode("Hola, vi la entrega de {$p->nombre}.");
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Entrega · {{ $p->nombre }} | {{ config('vandu.marca.nombre') }}</title>
    <link rel="icon" href="/favi.svg" type="image/svg+xml">
    <link rel="preload" href="{{ route('vandu.fuente') }}" as="font" type="font/woff2" crossorigin>
    <style>
        @font-face { font-family: 'Geist'; src: url('{{ route('vandu.fuente') }}') format('woff2'); font-weight: 100 900; font-display: swap; }
        :root { --ink: #13161D; --ink-2: #1C2029; --line: #E3E4E8; --mist: #F3F4F6; --muted: #5d6270; --green: #00F385; --paper: #fff; }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body { margin: 0; font-family: 'Geist', system-ui, sans-serif; color: var(--ink); background: var(--paper); font-size: 16px; line-height: 1.5; }
        a { color: inherit; }
        :focus-visible { outline: 3px solid var(--green); outline-offset: 2px; }
        .num { font-variant-numeric: tabular-nums; }
        .ancho { max-width: 1240px; margin: 0 auto; padding: 0 24px; }

        /* Portada */
        .portada { background: var(--ink); color: #fff; padding: 22px 0 56px; border-bottom: 4px solid var(--green); }
        .portada .top { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
        .portada .top img { width: 112px; height: auto; display: block; }
        .portada .top a { color: #9BA1AE; text-decoration: none; font-size: 14px; }
        .portada .top a:hover { color: #fff; }
        .portada .etq { margin-top: 64px; display: inline-flex; align-items: center; gap: 10px; font-size: 13px; letter-spacing: .12em; text-transform: uppercase; color: var(--green); font-weight: 600; }
        .portada .etq::before { content: ''; width: 28px; height: 2px; background: var(--green); }
        .portada h1 { font-size: clamp(34px, 6vw, 64px); line-height: 1.02; letter-spacing: -.035em; font-weight: 600; margin: 14px 0 0; max-width: 16ch; }
        .portada .sub { margin-top: 16px; color: #C3C8D2; font-size: 18px; }
        .portada .sub b { color: #fff; font-weight: 500; }
        .acciones { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 32px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 9px; min-height: 50px; padding: 0 24px; border-radius: 12px; font: inherit; font-weight: 600;
               text-decoration: none; cursor: pointer; border: 1.5px solid transparent; transition: background .15s, color .15s, border-color .15s; }
        .btn svg { width: 19px; height: 19px; flex: none; }
        .btn-verde { background: var(--green); color: var(--ink); }
        .btn-verde:hover { background: #fff; }
        .btn-fant { border-color: rgba(255,255,255,.28); color: #fff; }
        .btn-fant:hover { border-color: #fff; }

        /* Galería tipo mosaico */
        .seccion { padding: 48px 0 8px; }
        .seccion-top { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; }
        .seccion-top h2 { margin: 0; font-size: 24px; letter-spacing: -.015em; font-weight: 600; }
        .seccion-top span { color: var(--muted); }
        .mosaico { columns: 3 300px; column-gap: 12px; }
        .mosaico button { display: block; width: 100%; margin: 0 0 12px; padding: 0; border: 0; border-radius: 12px; overflow: hidden; background: var(--mist);
                          cursor: zoom-in; position: relative; break-inside: avoid; }
        .mosaico img, .mosaico video { display: block; width: 100%; height: auto; transition: transform .35s ease; }
        .mosaico button:hover img, .mosaico button:hover video { transform: scale(1.025); }
        .mosaico .play { position: absolute; inset: 0; display: grid; place-items: center; pointer-events: none; }
        .mosaico .play i { width: 58px; height: 58px; border-radius: 50%; background: rgba(19,22,29,.72); display: grid; place-items: center; backdrop-filter: blur(4px); }
        .mosaico .play svg { width: 22px; height: 22px; fill: #fff; margin-left: 3px; }
        .mosaico .arch { padding: 36px 16px; display: grid; gap: 6px; place-items: center; color: var(--muted); font-size: 14px; }
        .mosaico .duracion { position: absolute; left: 10px; bottom: 10px; font-size: 12px; font-weight: 600; color: #fff; background: rgba(19,22,29,.72); padding: 3px 8px; border-radius: 6px; }

        .docs { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 10px; }
        .docs a { display: flex; align-items: center; gap: 14px; padding: 14px 16px; border: 1.5px solid var(--line); border-radius: 12px; text-decoration: none; }
        .docs a:hover { border-color: var(--ink); }
        .docs .ic { width: 40px; height: 40px; border-radius: 10px; background: var(--mist); display: grid; place-items: center; flex: none; }
        .docs .ic svg { width: 19px; height: 19px; }
        .docs .n { min-width: 0; flex: 1; }
        .docs .n b { display: block; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .docs .n span { font-size: 13px; color: var(--muted); }

        .vacio { text-align: center; padding: 80px 16px; color: var(--muted); }
        .pie { margin: 56px auto 0; padding: 24px; border-top: 1px solid var(--line); color: var(--muted); font-size: 14px; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .pie a { color: var(--ink); font-weight: 500; text-decoration: none; }

        /* Visor */
        .visor { position: fixed; inset: 0; z-index: 50; background: rgba(10,11,14,.97); display: none; flex-direction: column; color: #fff; }
        .visor.abierto { display: flex; }
        .visor .barra-v { display: flex; align-items: center; gap: 12px; padding: 12px 16px; }
        .visor .barra-v .n { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 14px; color: #C9CDD6; }
        .visor .ctl { width: 44px; height: 44px; border-radius: 10px; border: 0; background: rgba(255,255,255,.08); color: #fff; display: grid; place-items: center; cursor: pointer; text-decoration: none; }
        .visor .ctl:hover { background: rgba(255,255,255,.16); }
        .visor .ctl svg { width: 20px; height: 20px; }
        .visor .lienzo { flex: 1; min-height: 0; display: grid; grid-template-columns: 64px minmax(0, 1fr) 64px; grid-template-rows: minmax(0, 1fr); align-items: center; padding: 0 8px 8px; }
        .visor .medio { height: 100%; min-height: 0; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .visor .medio img, .visor .medio video { max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 6px; }
        .visor .medio .btn { background: #fff; color: var(--ink); }
        .visor .contador { text-align: center; font-size: 13px; color: #9AA0AC; padding-bottom: 14px; }

        @media (max-width: 640px) {
            .ancho { padding: 0 16px; }
            .portada { padding-bottom: 40px; }
            .portada .etq { margin-top: 44px; }
            .portada .sub { font-size: 16px; }
            .acciones .btn { flex: 1 1 100%; }
            .mosaico { columns: 2; column-gap: 6px; }
            .mosaico button { margin-bottom: 6px; border-radius: 8px; }
            .visor .lienzo { grid-template-columns: minmax(0, 1fr); padding: 0 0 8px; } .visor .lienzo .ctl { display: none; }
        }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
            .bajar-sec { color: inherit; font-weight: 600; text-underline-offset: 3px; }
    </style>
</head>
<body>

<header class="portada">
    <div class="ancho">
        <div class="top">
            <x-logo-vandu width="112" height="36" />
            <a href="https://{{ config('vandu.emisor.sitio') }}" target="_blank" rel="noopener">{{ config('vandu.emisor.sitio') }}</a>
        </div>
        <div class="etq">Entrega · {{ ucfirst($fechaEntrega->timezone(config('vandu.zona_horaria'))->locale('es')->isoFormat('D [de] MMMM YYYY')) }}</div>
        <h1>{{ $p->nombre }}</h1>
        <div class="sub">Para <b>{{ $quien }}</b> · {{ $p->entregables_texto }}</div>
        <div class="acciones">
            @if($items->isNotEmpty())
                <a class="btn btn-verde" href="{{ route('proyecto.zip', $p->token) }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m0 0-5-5m5 5 5-5M4 21h16"/></svg>
                    Descargar todo
                </a>
            @endif
            <a class="btn btn-fant" href="{{ $p->url_publica }}">
                Ver mi proyecto
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
            </a>
        </div>
    </div>
</header>

<main class="ancho">
    @if($items->isNotEmpty())
        @php $n = 0; $varios = $grupos->count() > 1; @endphp
        @foreach($grupos as $g)
        <section class="seccion">
            <div class="seccion-top">
                <h2>{{ $varios ? $g['nombre'] : 'Galería' }}</h2>
                <span>@if($varios){{ $g['archivos']->count() }} {{ $g['archivos']->count() === 1 ? 'archivo' : 'archivos' }} · {{ ucfirst($g['fecha']->locale('es')->isoFormat('D [de] MMMM')) }}@if($g['seccion']) · <a href="{{ route('proyecto.zip', [$p->token, 'seccion' => $g['seccion']->id]) }}" class="bajar-sec">Descargar esta sección</a>@endif @else Toca cualquier archivo para verlo en grande y descargarlo.@endif</span>
            </div>
            <div class="mosaico">
                @foreach($g['archivos'] as $a)
                    @php $i = $n++; @endphp
                    <button type="button" data-i="{{ $i }}" aria-label="Ver {{ $a->nombre }}">
                        @if($a->es_imagen)
                            <img src="{{ $ruta($a, 'miniatura') }}" alt="" loading="lazy" @if($a->ancho) width="{{ $a->ancho }}" height="{{ $a->alto }}" @endif>
                        @elseif($a->es_video && $a->miniatura)
                            <img src="{{ $ruta($a, 'miniatura') }}" alt="" loading="lazy" @if($a->ancho) width="{{ $a->ancho }}" height="{{ $a->alto }}" @endif>
                            <span class="play"><i><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></i></span>
                        @elseif($a->es_video)
                            <video src="{{ $ruta($a) }}#t=0.5" preload="metadata" muted playsinline></video>
                            <span class="play"><i><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></i></span>
                        @else
                            <span class="arch"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/></svg>{{ \Illuminate\Support\Str::limit($a->nombre, 34) }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </section>
        @endforeach
    @endif

    @if($documentos->isNotEmpty())
        <section class="seccion">
            <div class="seccion-top"><h2>Documentos</h2><span class="num">{{ $documentos->count() }} {{ $documentos->count() === 1 ? 'archivo' : 'archivos' }}</span></div>
            <ul class="docs">
                @foreach($documentos as $a)
                    <li><a href="{{ $ruta($a, null, true) }}">
                        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/></svg></span>
                        <span class="n"><b>{{ $a->nombre }}</b><span class="num">{{ strtoupper(pathinfo($a->nombre, PATHINFO_EXTENSION)) }} · {{ $a->peso_texto }}</span></span>
                    </a></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if($items->isEmpty() && $documentos->isEmpty())
        <div class="vacio">Tu material aparecerá aquí en cuanto esté listo.</div>
    @endif
</main>

<footer class="pie ancho">
    <span>¿Algún ajuste? <a href="https://wa.me/{{ $wa }}?text={{ $waMsg }}" target="_blank" rel="noopener">Escríbenos por WhatsApp</a></span>
    <span>{{ config('vandu.marca.nombre') }}{{ config('vandu.marca.ciudad') ? ', ' . config('vandu.marca.ciudad') : '' }}</span>
</footer>

@if($items->isNotEmpty())
    <div class="visor" id="visor" role="dialog" aria-modal="true" aria-label="Visor de entregables">
        <div class="barra-v">
            <span class="n" id="v-nombre"></span>
            <a class="ctl" id="v-bajar" href="#" aria-label="Descargar original" title="Descargar original"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12m0 0-5-5m5 5 5-5M4 21h16"/></svg></a>
            <button class="ctl" id="v-cerrar" type="button" aria-label="Cerrar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
        </div>
        <div class="lienzo">
            <button class="ctl" id="v-prev" type="button" aria-label="Anterior"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m15 18-6-6 6-6"/></svg></button>
            <div class="medio" id="v-medio"></div>
            <button class="ctl" id="v-next" type="button" aria-label="Siguiente"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m9 18 6-6-6-6"/></svg></button>
        </div>
        <div class="contador num" id="v-cont"></div>
    </div>
    <script>
    (function () {
        var items = @json($items);
        var visor = document.getElementById('visor'), medio = document.getElementById('v-medio'), actual = 0, origen = null;
        function mostrar(i) {
            actual = (i + items.length) % items.length;
            var it = items[actual], el;
            medio.innerHTML = '';
            if (it.tipo === 'img') { el = new Image(); el.src = it.vista; el.alt = it.nombre; }
            else if (it.tipo === 'video') { el = document.createElement('video'); if (it.poster) el.poster = it.poster; el.src = it.vista; el.controls = true; el.autoplay = true; el.playsInline = true; }
            else { el = document.createElement('a'); el.href = it.bajar; el.className = 'btn'; el.textContent = 'Descargar ' + it.nombre; }
            medio.appendChild(el);
            document.getElementById('v-nombre').textContent = it.nombre + ' · ' + it.peso;
            document.getElementById('v-bajar').href = it.bajar;
            document.getElementById('v-cont').textContent = (actual + 1) + ' de ' + items.length;
            var sig = items[(actual + 1) % items.length]; if (sig && sig.tipo === 'img') { new Image().src = sig.vista; }
        }
        function abrir(i, desde) { origen = desde; visor.classList.add('abierto'); document.body.style.overflow = 'hidden'; mostrar(i); document.getElementById('v-cerrar').focus(); }
        function cerrar() { visor.classList.remove('abierto'); medio.innerHTML = ''; document.body.style.overflow = ''; if (origen) origen.focus(); }
        document.querySelectorAll('.mosaico [data-i]').forEach(function (b) { b.addEventListener('click', function () { abrir(+b.dataset.i, b); }); });
        document.getElementById('v-cerrar').onclick = cerrar;
        document.getElementById('v-prev').onclick = function () { mostrar(actual - 1); };
        document.getElementById('v-next').onclick = function () { mostrar(actual + 1); };
        visor.addEventListener('click', function (e) { if (e.target === visor || e.target.classList.contains('medio')) cerrar(); });
        document.addEventListener('keydown', function (e) {
            if (!visor.classList.contains('abierto')) return;
            if (e.key === 'Escape') cerrar(); if (e.key === 'ArrowLeft') mostrar(actual - 1); if (e.key === 'ArrowRight') mostrar(actual + 1);
        });
        var x0 = null;
        medio.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
        medio.addEventListener('touchend', function (e) { if (x0 === null) return; var dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 50) mostrar(actual + (dx < 0 ? 1 : -1)); x0 = null; });
    })();
    </script>
@endif
</body>
</html>
