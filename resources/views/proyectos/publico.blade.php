@php
    /** @var \App\Models\Proyecto $p */
    $c = $p->cliente;
    $quien = $c?->empresa ?: $c?->nombre;
    $emisor = $p->presupuesto ?? null;
    $pago = [
        'clabe'        => $emisor?->clabe ?: config('vandu.pago.clabe'),
        'banco'        => $emisor?->banco ?: config('vandu.pago.banco'),
        'beneficiario' => $emisor?->beneficiario ?: config('vandu.pago.beneficiario'),
        'nota'         => $emisor?->nota_comprobante ?: config('vandu.pago.nota_comprobante'),
    ];
    $pendientes = $p->pagos->whereNull('pagado_el');
    $wa = config('vandu.whatsapp');
    $waMsg = rawurlencode("Hola, tengo una duda sobre mi proyecto: {$p->nombre}.");
    $ruta = fn ($a, $v = null, $d = false) => route('proyecto.archivo', [$p->token, $a]) . '?' . http_build_query(array_filter(['v' => $v, 'descargar' => $d ? 1 : null]));
    $items = $galeria->map(fn ($a) => [
        'tipo'   => $a->es_imagen ? 'img' : ($a->es_video ? 'video' : 'archivo'),
        'vista'  => $a->es_imagen ? $ruta($a, 'vista') : $ruta($a),
        'bajar'  => $ruta($a, null, true),
        'nombre' => $a->nombre,
        'peso'   => $a->peso_texto,
    ])->values();
    $linea = $p->lineaDelTiempo();
    $f = fn ($d) => ucfirst($d->locale('es')->isoFormat('D [de] MMMM'));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $p->nombre }} · {{ $quien }} | Agencia Vandu</title>
    <link rel="icon" href="/favi.svg" type="image/svg+xml">
    <link rel="preload" href="{{ route('vandu.fuente') }}" as="font" type="font/woff2" crossorigin>
    <style>
        @font-face { font-family: 'Geist'; src: url('{{ route('vandu.fuente') }}') format('woff2'); font-weight: 100 900; font-display: swap; }
        :root { --ink: #13161D; --hdr: #242424; --mist: #F3F3F3; --line: #E3E4E8; --muted: #5d6270; --green: #00F385; --green-ink: #047A4B;
                --amber: #9A5300; --amber-soft: #FFF3DD; --paper: #fff; }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body { margin: 0; font-family: 'Geist', system-ui, sans-serif; color: var(--ink); background: var(--paper); font-size: 16px; line-height: 1.5; }
        .num { font-variant-numeric: tabular-nums; }
        a { color: inherit; }
        :focus-visible { outline: 3px solid var(--green); outline-offset: 2px; }
        .doc { max-width: 820px; margin: 0 auto; padding: 24px 16px 64px; }

        .hdr { background: var(--hdr); color: #fff; border-radius: 14px; padding: 28px 32px; display: flex; justify-content: space-between; align-items: center; gap: 20px; flex-wrap: wrap; }
        .hdr img { width: 112px; height: auto; display: block; }
        .hdr .emisor { text-align: right; font-size: 13px; line-height: 1.55; }
        .hdr .emisor a { color: #e6e6e6; text-decoration: none; }

        .titulo { margin: 36px 0 8px; }
        .titulo .cli { color: var(--muted); }
        .titulo h1 { font-size: clamp(26px, 4vw, 34px); line-height: 1.15; letter-spacing: -.02em; margin: 4px 0 0; font-weight: 600; }
        .avance { display: grid; grid-template-columns: 1fr auto; gap: 8px 16px; align-items: end; margin: 24px 0 8px; }
        .avance .pct { font-size: 32px; font-weight: 600; letter-spacing: -.02em; line-height: 1; }
        .avance .sig { color: var(--muted); }
        .avance .sig b { color: var(--ink); font-weight: 600; }
        .barra { grid-column: 1 / -1; height: 10px; border-radius: 99px; background: var(--mist); overflow: hidden; }
        .barra span { display: block; height: 100%; border-radius: 99px; background: var(--ink); }
        .mensaje { margin: 24px 0 0; padding: 16px 18px; border-left: 3px solid var(--green); background: #F4FEF9; border-radius: 0 10px 10px 0; white-space: pre-line; }

        h2 { font-size: 22px; font-weight: 600; letter-spacing: -.01em; margin: 48px 0 12px; }

        /* Línea del tiempo */
        .tl { list-style: none; margin: 0; padding: 0; }
        .tl > li { position: relative; display: grid; grid-template-columns: 44px minmax(0, 1fr); }
        .tl > li::before { content: ''; position: absolute; left: 15px; top: 0; bottom: 0; width: 2px; background: var(--line); }
        .tl > li:first-child::before { top: 20px; }
        .tl > li:last-child::before { bottom: calc(100% - 20px); }
        .tl > li.hecho::before { background: var(--ink); }
        .nodo { position: relative; z-index: 1; margin-top: 6px; width: 32px; height: 32px; border-radius: 50%; display: grid; place-items: center; background: #fff; border: 2px solid #CDD0D6; color: var(--muted); font-size: 14px; }
        .hecho .nodo { background: var(--ink); border-color: var(--ink); color: var(--green); }
        .curso .nodo { border-color: var(--ink); box-shadow: 0 0 0 5px rgba(19,22,29,.08); }
        .curso .nodo::after { content: ''; width: 12px; height: 12px; border-radius: 50%; background: var(--ink); animation: latido 1.8s ease-in-out infinite; }
        @keyframes latido { 50% { transform: scale(.7); opacity: .6; } }
        .pago .nodo { border-radius: 9px; }
        .pago.pend .nodo { border-color: var(--amber); color: var(--amber); background: var(--amber-soft); }
        .cuerpo { padding: 6px 0 28px; min-width: 0; }
        .cuerpo h3 { margin: 4px 0 0; font-size: 17px; font-weight: 600; }
        .meta { display: flex; flex-wrap: wrap; gap: 6px 14px; color: var(--muted); font-size: 14.5px; margin-top: 2px; }
        .pill { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 500; padding: 2px 10px; border-radius: 99px; }
        .pill.ok { background: #E3FBEF; color: var(--green-ink); }
        .pill.curso { background: var(--ink); color: #fff; }
        .pill.pend { background: var(--amber-soft); color: var(--amber); }
        .cuerpo p { margin: 6px 0 0; color: #3f4450; }
        .descargas { list-style: none; padding: 0; margin: 12px 0 0; display: grid; gap: 8px; }
        .descargas a { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1.5px solid var(--line); border-radius: 12px; text-decoration: none; }
        .descargas a:hover { border-color: var(--ink); }
        .descargas .ic { width: 36px; height: 36px; border-radius: 9px; background: var(--mist); display: grid; place-items: center; flex: none; }
        .descargas .ic svg { width: 18px; height: 18px; }
        .descargas .n { min-width: 0; flex: 1; }
        .descargas .n b { display: block; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .descargas .n span { font-size: 13px; color: var(--muted); }
        .descargas .flecha { font-size: 14px; font-weight: 600; flex: none; }

        .banco { margin-top: 14px; border: 1.5px solid var(--ink); border-radius: 12px; overflow: hidden; }
        .banco .t { background: var(--ink); color: #fff; font-weight: 600; text-align: center; padding: 9px; font-size: 14px; }
        .banco .fila { display: grid; grid-template-columns: 120px 1fr auto; align-items: center; gap: 8px; padding: 11px 14px; }
        .banco .fila + .fila { border-top: 1px solid var(--line); }
        .banco .k { color: var(--muted); font-size: 14px; }
        .banco .v { font-weight: 600; overflow-wrap: anywhere; }
        .copiar { font: inherit; font-size: 13px; font-weight: 600; background: var(--mist); border: 0; border-radius: 6px; padding: 6px 10px; cursor: pointer; }
        .copiar:hover { background: var(--green); }
        .nota-pago { font-size: 14.5px; color: #3f4450; margin-top: 10px; }

        /* Galería */
        .gal-top { display: flex; justify-content: space-between; align-items: end; gap: 12px; flex-wrap: wrap; margin: 48px 0 14px; }
        .gal-top h2 { margin: 0; }
        .gal-top span { color: var(--muted); }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 46px; padding: 0 20px; border-radius: 10px;
               font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; border: 1.5px solid var(--ink); background: var(--ink); color: #fff; }
        .btn:hover { background: #000; color: var(--green); }
        .btn.sec { background: #fff; color: var(--ink); }
        .btn.sec:hover { background: var(--mist); }
        .btn svg { width: 18px; height: 18px; }
        .galeria { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px; }
        .galeria button { position: relative; border: 0; padding: 0; aspect-ratio: 1; border-radius: 10px; overflow: hidden; background: var(--mist); cursor: zoom-in; }
        .galeria img, .galeria video { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .25s; }
        .galeria button:hover img { transform: scale(1.03); }
        .galeria .play { position: absolute; inset: 0; display: grid; place-items: center; }
        .galeria .play i { width: 48px; height: 48px; border-radius: 50%; background: rgba(19,22,29,.7); display: grid; place-items: center; }
        .galeria .play svg { width: 20px; height: 20px; fill: #fff; margin-left: 3px; }
        .galeria .arch { height: 100%; display: grid; place-items: center; padding: 12px; font-size: 13px; color: var(--muted); }

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
        .visor .contador { text-align: center; font-size: 13px; color: #9AA0AC; padding-bottom: 14px; }

        .pie { margin-top: 56px; padding-top: 18px; border-top: 1px solid var(--line); color: var(--muted); font-size: 14px; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .pie a { text-decoration: none; font-weight: 500; color: var(--ink); }

        @media (max-width: 600px) {
            .hdr { padding: 22px; } .hdr .emisor { text-align: left; }
            .galeria { grid-template-columns: repeat(3, 1fr); gap: 4px; } .galeria button { border-radius: 6px; }
            .banco .fila { grid-template-columns: 1fr auto; } .banco .k { grid-column: 1 / -1; }
            .visor .lienzo { grid-template-columns: minmax(0, 1fr); padding: 0 0 8px; } .visor .lienzo .ctl { display: none; }
            .gal-top .btn { width: 100%; }
        }
        @media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } }
    </style>
</head>
<body>
<main class="doc">
    <header class="hdr">
        <x-logo-vandu width="112" height="36" />
        <div class="emisor">
            <b>{{ $emisor?->emisor_nombre ?: config('vandu.emisor.nombre') }}</b><br>
            <b><a href="tel:{{ preg_replace('/\D/', '', $emisor?->emisor_telefono ?: config('vandu.emisor.telefono')) }}">{{ $emisor?->emisor_telefono ?: config('vandu.emisor.telefono') }}</a></b><br>
            <a href="https://{{ config('vandu.emisor.sitio') }}" target="_blank" rel="noopener">{{ config('vandu.emisor.sitio') }}</a><br>
            <a href="mailto:{{ $emisor?->emisor_email ?: config('vandu.emisor.email') }}">{{ $emisor?->emisor_email ?: config('vandu.emisor.email') }}</a>
        </div>
    </header>

    <section class="titulo">
        <div class="cli">{{ $quien }} · {{ $p->tipo_nombre }}@if($p->presupuesto) · <a href="{{ $p->presupuesto->url_publica }}" style="color:inherit">Ver cotización {{ $p->presupuesto->folio }}</a>@endif</div>
        <h1>{{ $p->nombre }}</h1>
    </section>

    <section class="avance" aria-label="Avance del proyecto">
        <div class="pct num">{{ $p->progreso }}%</div>
        <div class="sig" style="text-align:right">@if($p->estado === 'terminado')<b>Proyecto terminado</b>@else Siguiente: <b>{{ $p->siguiente_paso }}</b>@endif</div>
        <div class="barra" role="progressbar" aria-valuenow="{{ $p->progreso }}" aria-valuemin="0" aria-valuemax="100"><span style="width: {{ $p->progreso }}%"></span></div>
    </section>

    @if($p->mensaje_cliente)
        <div class="mensaje">{{ $p->mensaje_cliente }}</div>
    @endif

    <h2>Cómo vamos</h2>
    <ol class="tl">
        @foreach($linea as $t)
            @if($t['tipo'] === 'pago')
                @php $pg = $t['item']; $antes = $p->etapas->firstWhere('clave', $pg->antes_de); @endphp
                <li class="pago {{ $pg->pagado ? 'hecho' : 'pend' }}">
                    <span class="nodo" aria-hidden="true">
                        @if($pg->pagado)<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m5 12 5 5L20 7"/></svg>
                        @else<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></svg>@endif
                    </span>
                    <div class="cuerpo">
                        <h3>{{ $pg->concepto }} <span class="num" style="font-weight:500">{{ $pg->monto_texto }}</span></h3>
                        <div class="meta">
                            @if($pg->pagado)
                                <span class="pill ok">Recibido el {{ $f($pg->pagado_el) }}</span>
                            @else
                                <span class="pill pend">Pendiente</span>
                                @if($antes)<span>Necesario para iniciar {{ mb_strtolower($antes->nombre) }}</span>@endif
                            @endif
                        </div>
                    </div>
                </li>
            @else
                @php $e = $t['item']; $cls = ['completada' => 'hecho', 'en_curso' => 'curso', 'pendiente' => ''][$e->estado]; @endphp
                <li class="{{ $cls }}">
                    <span class="nodo" aria-hidden="true">@if($e->estado === 'completada')<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m5 12 5 5L20 7"/></svg>@endif</span>
                    <div class="cuerpo">
                        <h3>{{ $e->nombre }}</h3>
                        <div class="meta">
                            @if($e->estado === 'completada')<span class="pill ok">Completada</span>
                            @elseif($e->estado === 'en_curso')<span class="pill curso">En curso</span>@endif
                            @if($e->fechas_texto)<span class="num">{{ $e->fechas_texto }}</span>
                            @elseif($e->es_fecha)<span>Fecha por confirmar</span>@endif
                        </div>
                        @if($e->descripcion)<p>{{ $e->descripcion }}</p>@endif
                        @if($e->archivos->isNotEmpty())
                            <ul class="descargas">
                                @foreach($e->archivos as $a)
                                    <li><a href="{{ $ruta($a, null, true) }}">
                                        <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/></svg></span>
                                        <span class="n"><b>{{ $a->nombre }}</b><span class="num">{{ strtoupper(pathinfo($a->nombre, PATHINFO_EXTENSION)) }} · {{ $a->peso_texto }}</span></span>
                                        <span class="flecha">Descargar</span>
                                    </a></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </li>
            @endif
        @endforeach
    </ol>

    @if($pendientes->isNotEmpty() && $p->estado !== 'terminado')
        <h2>Datos para tu pago</h2>
        <div class="banco">
            <div class="t">Datos bancarios</div>
            @foreach(['CLABE' => $pago['clabe'], 'Banco' => $pago['banco'], 'Beneficiario' => $pago['beneficiario']] as $k => $v)
                @continue(! $v)
                <div class="fila"><span class="k">{{ $k }}</span><span class="v num">{{ $v }}</span>
                    @if($k !== 'Banco')<button type="button" class="copiar" data-copiar="{{ $v }}" aria-label="Copiar {{ $k }}">Copiar</button>@else<span></span>@endif</div>
            @endforeach
        </div>
        @if($pago['nota'])<p class="nota-pago">{{ $pago['nota'] }}</p>@endif
    @endif

    @if($items->isNotEmpty())
        <div class="gal-top">
            <div><h2>Tus entregables</h2><span class="num">{{ $items->count() }} {{ $items->count() === 1 ? 'archivo' : 'archivos' }}</span></div>
            <a class="btn" href="{{ route('proyecto.zip', $p->token) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m0 0-5-5m5 5 5-5M4 21h16"/></svg> Descargar todo
            </a>
        </div>
        <div class="galeria">
            @foreach($galeria as $i => $a)
                <button type="button" data-i="{{ $i }}" aria-label="Ver {{ $a->nombre }}">
                    @if($a->es_imagen)
                        <img src="{{ $ruta($a, 'miniatura') }}" alt="" loading="lazy" @if($a->ancho) width="{{ $a->ancho }}" height="{{ $a->alto }}" @endif>
                    @elseif($a->es_video)
                        <video src="{{ $ruta($a) }}#t=0.5" preload="metadata" muted playsinline></video>
                        <span class="play"><i><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></i></span>
                    @else
                        <span class="arch">{{ \Illuminate\Support\Str::limit($a->nombre, 30) }}</span>
                    @endif
                </button>
            @endforeach
        </div>

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
    @endif

    <footer class="pie">
        <span>¿Dudas? <a href="https://wa.me/{{ $wa }}?text={{ $waMsg }}" target="_blank" rel="noopener">Escríbenos por WhatsApp</a></span>
        <span>Agencia Vandu, Mérida, Yucatán</span>
    </footer>
</main>

<script>
(function () {
    document.addEventListener('click', async function (e) {
        var b = e.target.closest('[data-copiar]'); if (!b) return;
        try { await navigator.clipboard.writeText(b.dataset.copiar); b.textContent = 'Copiado'; } catch (err) { window.prompt('Copia el dato:', b.dataset.copiar); return; }
        setTimeout(function () { b.textContent = 'Copiar'; }, 1600);
    });

    var items = @json($items);
    var visor = document.getElementById('visor'); if (!visor) return;
    var medio = document.getElementById('v-medio'), actual = 0, origen = null;
    function mostrar(i) {
        actual = (i + items.length) % items.length;
        var it = items[actual];
        medio.innerHTML = '';
        var el;
        if (it.tipo === 'img') { el = new Image(); el.src = it.vista; el.alt = it.nombre; }
        else if (it.tipo === 'video') { el = document.createElement('video'); el.src = it.vista; el.controls = true; el.autoplay = true; el.playsInline = true; }
        else { el = document.createElement('a'); el.href = it.bajar; el.className = 'btn sec'; el.textContent = 'Descargar ' + it.nombre; }
        medio.appendChild(el);
        document.getElementById('v-nombre').textContent = it.nombre + ' · ' + it.peso;
        document.getElementById('v-bajar').href = it.bajar;
        document.getElementById('v-cont').textContent = (actual + 1) + ' de ' + items.length;
        // Precarga la siguiente foto
        var sig = items[(actual + 1) % items.length]; if (sig && sig.tipo === 'img') { new Image().src = sig.vista; }
    }
    function abrir(i, desde) { origen = desde; visor.classList.add('abierto'); document.body.style.overflow = 'hidden'; mostrar(i); document.getElementById('v-cerrar').focus(); }
    function cerrar() { visor.classList.remove('abierto'); medio.innerHTML = ''; document.body.style.overflow = ''; if (origen) origen.focus(); }
    document.querySelectorAll('.galeria [data-i]').forEach(function (b) { b.addEventListener('click', function () { abrir(+b.dataset.i, b); }); });
    document.getElementById('v-cerrar').onclick = cerrar;
    document.getElementById('v-prev').onclick = function () { mostrar(actual - 1); };
    document.getElementById('v-next').onclick = function () { mostrar(actual + 1); };
    visor.addEventListener('click', function (e) { if (e.target === visor || e.target.classList.contains('medio')) cerrar(); });
    document.addEventListener('keydown', function (e) {
        if (!visor.classList.contains('abierto')) return;
        if (e.key === 'Escape') cerrar(); if (e.key === 'ArrowLeft') mostrar(actual - 1); if (e.key === 'ArrowRight') mostrar(actual + 1);
    });
    // Deslizar en celular
    var x0 = null;
    medio.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
    medio.addEventListener('touchend', function (e) { if (x0 === null) return; var dx = e.changedTouches[0].clientX - x0; if (Math.abs(dx) > 50) mostrar(actual + (dx < 0 ? 1 : -1)); x0 = null; });
})();
</script>
</body>
</html>
