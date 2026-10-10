@php
    /** @var \App\Models\Cliente $c */
    $quien = $c->empresa ?: $c->nombre;
    $nombre = \App\Support\Correos::primerNombre((string) $c->nombre);
    $tz = config('vandu.zona_horaria');
    $dinero = fn ($v) => '$' . number_format($v, 2);
    $estadoCot = function ($p) {
        return match (true) {
            $p->estado === 'aceptada'    => ['Aceptada', 'ok'],
            $p->estado === 'rechazada'   => ['No aceptada', 'gris'],
            $p->estado === 'negociacion' => ['Revisando tus cambios', 'azul'],
            ! $p->vigente                => ['Vencida', 'gris'],
            default                      => ['Por responder', 'pend'],
        };
    };
    $porResponder = $cotizaciones->filter(fn ($p) => $p->abierta && $p->estado !== 'negociacion' && ! $p->proyecto);
    $activos = $proyectos->where('estado', '!=', 'terminado');
    $terminados = $proyectos->where('estado', 'terminado');
    $miniatura = fn ($a) => route('proyecto.archivo', [$proyectos->firstWhere('id', $a->proyecto_id)->token, $a->id]) . '?v=miniatura';
    $wa = config('vandu.whatsapp');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $quien }} · {{ config('vandu.marca.nombre') }}</title>
    <link rel="icon" href="/favi.svg" type="image/svg+xml">
    <link rel="preload" href="{{ route('vandu.fuente') }}" as="font" type="font/woff2" crossorigin>
    <style>
        @font-face { font-family: 'Geist'; src: url('{{ route('vandu.fuente') }}') format('woff2'); font-weight: 100 900; font-display: swap; }
        :root { --ink: #13161D; --hdr: #242424; --mist: #F3F3F3; --line: #E3E4E8; --muted: #5d6270; --green: #00F385; --green-ink: #047A4B;
                --amber: #9A5300; --amber-soft: #FFF3DD; --blue: #2557D6; --blue-soft: #E8EEFC; }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body { margin: 0; font-family: 'Geist', system-ui, sans-serif; color: var(--ink); background: #F6F7F8; font-size: 16px; line-height: 1.5; }
        .num { font-variant-numeric: tabular-nums; }
        a { color: inherit; }
        :focus-visible { outline: 3px solid var(--green); outline-offset: 2px; }
        .doc { max-width: 920px; margin: 0 auto; padding: 24px 16px 64px; }

        .hdr { background: var(--hdr); color: #fff; border-radius: 16px; padding: 26px 30px 30px; position: relative; overflow: hidden; }
        .hdr .top { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
        .hdr .top img { width: 104px; height: auto; display: block; }
        .hdr .top .contacto { font-size: 13px; color: #cfd2d8; text-align: right; }
        .hdr .top .contacto a { color: #cfd2d8; text-decoration: none; }
        .hdr h1 { font-size: clamp(26px, 4.4vw, 38px); line-height: 1.1; letter-spacing: -.02em; margin: 30px 0 6px; font-weight: 600; }
        .hdr .sub { color: #b9bdc6; margin: 0; }
        .resumen { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 22px; }
        .resumen span { background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12); border-radius: 99px; padding: 5px 13px; font-size: 14px; }
        .resumen b { color: var(--green); font-weight: 600; }

        h2 { font-size: 20px; font-weight: 600; letter-spacing: -.01em; margin: 40px 0 14px; display: flex; align-items: baseline; gap: 10px; }
        h2 small { font-size: 14px; font-weight: 400; color: var(--muted); }

        .aviso { display: flex; gap: 14px; align-items: center; background: #fff; border: 1.5px solid var(--amber); border-radius: 14px; padding: 16px 18px; margin-top: 18px; text-decoration: none; }
        .aviso .ic { width: 40px; height: 40px; border-radius: 10px; background: var(--amber-soft); color: var(--amber); display: grid; place-items: center; flex: none; }
        .aviso .ic svg { width: 20px; height: 20px; }
        .aviso .t { flex: 1; min-width: 0; }
        .aviso .t b { display: block; }
        .aviso .t span { font-size: 14px; color: var(--muted); }
        .aviso .ir { font-weight: 600; font-size: 14px; white-space: nowrap; }

        .proyectos { display: grid; gap: 14px; }
        .pry { background: #fff; border: 1px solid var(--line); border-radius: 16px; overflow: hidden; display: grid; grid-template-columns: minmax(0, 1fr) 240px; }
        .pry.sin-fotos { grid-template-columns: minmax(0, 1fr); }
        .pry .info { padding: 20px 22px; display: grid; gap: 10px; align-content: start; }
        .pry .tipo { font-size: 13px; color: var(--muted); display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .pry h3 { margin: 0; font-size: 19px; font-weight: 600; line-height: 1.25; }
        .pry .avance { display: grid; grid-template-columns: 1fr auto; gap: 6px 12px; align-items: center; }
        .pry .avance .pct { font-weight: 600; font-size: 15px; }
        .pry .barra { grid-column: 1 / -1; height: 8px; border-radius: 99px; background: var(--mist); overflow: hidden; }
        .pry .barra span { display: block; height: 100%; background: var(--ink); border-radius: 99px; }
        .pry .sig { color: var(--muted); font-size: 14.5px; }
        .pry .sig b { color: var(--ink); font-weight: 600; }
        .pry .acc { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-weight: 600; font-size: 14.5px; padding: 9px 16px; border-radius: 10px; border: 1.5px solid var(--line); background: #fff; color: var(--ink); }
        .btn:hover { border-color: var(--ink); }
        .btn.prim { background: var(--ink); color: #fff; border-color: var(--ink); }
        .btn.prim:hover { color: var(--green); }
        .btn svg { width: 16px; height: 16px; }
        .fotos { display: grid; grid-template-columns: 1fr 1fr; gap: 2px; background: var(--mist); min-height: 100%; text-decoration: none; position: relative; }
        .fotos img { width: 100%; height: 100%; aspect-ratio: 1; object-fit: cover; display: block; }
        .fotos.una { grid-template-columns: 1fr; }
        .fotos .mas { position: absolute; right: 8px; bottom: 8px; background: rgba(19,22,29,.78); color: #fff; font-size: 12.5px; font-weight: 600; padding: 3px 9px; border-radius: 99px; }

        .pill { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 500; padding: 2px 10px; border-radius: 99px; white-space: nowrap; }
        .pill::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .pill.ok { background: #E3FBEF; color: var(--green-ink); }
        .pill.pend { background: var(--amber-soft); color: var(--amber); }
        .pill.azul { background: var(--blue-soft); color: var(--blue); }
        .pill.gris { background: #EEF0F3; color: #4E5463; }
        .pill.curso { background: var(--ink); color: #fff; }

        .cots { background: #fff; border: 1px solid var(--line); border-radius: 16px; overflow: hidden; }
        .cots a { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 4px 16px; align-items: center; padding: 15px 20px; text-decoration: none; }
        .cots a + a { border-top: 1px solid var(--line); }
        .cots a:hover { background: #FAFBFC; }
        .cots .n { font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .cots .s { grid-column: 1; font-size: 13.5px; color: var(--muted); }
        .cots .monto { font-weight: 600; text-align: right; grid-row: 1 / span 2; grid-column: 2; }
        .cots .est { grid-row: 1 / span 2; grid-column: 3; text-align: right; }

        details.term summary { cursor: pointer; list-style: none; color: var(--muted); font-weight: 500; margin: 26px 0 12px; }
        details.term summary::-webkit-details-marker { display: none; }
        .extra { display: flex; gap: 14px; align-items: center; background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 18px 20px; text-decoration: none; margin-top: 14px; }
        .extra:hover { border-color: var(--ink); }
        .extra .ic { width: 44px; height: 44px; border-radius: 12px; background: var(--ink); color: var(--green); display: grid; place-items: center; flex: none; }
        .extra .ic svg { width: 22px; height: 22px; }
        .extra .t { flex: 1; }
        .extra .t b { display: block; }
        .extra .t span { font-size: 14px; color: var(--muted); }
        .vacio { background: #fff; border: 1px dashed var(--line); border-radius: 16px; padding: 28px; text-align: center; color: var(--muted); }
        .pie { margin-top: 48px; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 10px; color: var(--muted); font-size: 14px; }
        .pie a { color: var(--ink); font-weight: 600; text-decoration: none; }

        @media (max-width: 640px) {
            .doc { padding: 12px 12px 48px; }
            .hdr { padding: 20px 20px 24px; border-radius: 14px; }
            .hdr .top .contacto { display: none; }
            .hdr h1 { margin-top: 22px; }
            .pry { grid-template-columns: minmax(0, 1fr); }
            .pry .fotos { order: -1; grid-template-columns: repeat(4, 1fr); min-height: 0; }
            .pry .fotos.una { grid-template-columns: 1fr; }
            .pry .fotos.una img { aspect-ratio: 16 / 9; }
            .pry .info { padding: 16px 18px 18px; }
            .cots a { grid-template-columns: minmax(0, 1fr) auto; padding: 14px 16px; }
            .cots .monto { grid-row: auto; grid-column: 2; grid-row: 1; }
            .cots .est { grid-column: 2; grid-row: 2; }
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
        <h1>Hola, {{ $nombre ?: $quien }}</h1>
        <p class="sub">Aquí están todos tus proyectos y cotizaciones{{ $c->empresa ? ' de ' . $c->empresa : '' }} con {{ config('vandu.marca.nombre') }}. Guarda este enlace: siempre está al día.</p>
        <div class="resumen">
            @if($activos->count())<span><b>{{ $activos->count() }}</b> {{ $activos->count() === 1 ? 'proyecto en curso' : 'proyectos en curso' }}</span>@endif
            @if($terminados->count())<span><b>{{ $terminados->count() }}</b> {{ $terminados->count() === 1 ? 'terminado' : 'terminados' }}</span>@endif
            <span><b>{{ $cotizaciones->count() }}</b> {{ $cotizaciones->count() === 1 ? 'cotización' : 'cotizaciones' }}</span>
        </div>
    </header>

    @foreach($porResponder as $p)
        <a class="aviso" href="{{ $p->url_publica }}">
            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M9 14l2 2 4-4"/></svg></span>
            <span class="t"><b>Tienes una cotización por responder</b><span>{{ $p->folio }} · {{ \Illuminate\Support\Str::limit($p->conceptos->first()?->resumen ?: $p->titulo, 60) }} · vence el {{ $p->vigencia_local->locale('es')->isoFormat('D [de] MMMM') }}</span></span>
            <span class="ir">Revisar →</span>
        </a>
    @endforeach

    <h2>Tus proyectos @if($proyectos->count())<small class="num">{{ $proyectos->count() }}</small>@endif</h2>
    @if($proyectos->isEmpty())
        <div class="vacio">Cuando aceptes una cotización, aquí verás el avance de tu proyecto, sus entregas y pagos.</div>
    @endif
    @php
        $tarjeta = function ($pr) use ($fotos, $enGaleria, $miniatura, $dinero) {
            return view('clientes._proyecto', compact('pr', 'fotos', 'enGaleria', 'miniatura', 'dinero'))->render();
        };
    @endphp
    <div class="proyectos">
        @foreach($activos as $pr) {!! $tarjeta($pr) !!} @endforeach
    </div>
    @if($terminados->isNotEmpty())
        <details class="term" @if($activos->isEmpty()) open @endif>
            <summary>{{ $terminados->count() === 1 ? 'Ver 1 proyecto terminado' : 'Ver ' . $terminados->count() . ' proyectos terminados' }} ▾</summary>
            <div class="proyectos">
                @foreach($terminados as $pr) {!! $tarjeta($pr) !!} @endforeach
            </div>
        </details>
    @endif

    @if($redes)
        <a class="extra" href="{{ $redes }}">
            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></span>
            <span class="t"><b>Tu contenido de redes sociales</b><span>Revisa y aprueba las publicaciones del mes</span></span>
            <span class="ir">Abrir →</span>
        </a>
    @endif

    <h2>Tus cotizaciones @if($cotizaciones->count())<small class="num">{{ $cotizaciones->count() }}</small>@endif</h2>
    @if($cotizaciones->isEmpty())
        <div class="vacio">Todavía no hay cotizaciones.</div>
    @else
        <div class="cots">
            @foreach($cotizaciones as $p)
                @php [$txt, $cls] = $estadoCot($p); @endphp
                <a href="{{ $p->url_publica }}">
                    <span class="n">{{ $p->conceptos->first()?->resumen ?: $p->titulo }}</span>
                    <span class="s num">{{ $p->folio }} · {{ $p->fecha->locale('es')->isoFormat('D MMM YYYY') }}@if($p->proyecto) · proyecto en curso @endif</span>
                    <span class="monto num">{{ $dinero($p->total) }}</span>
                    <span class="est"><span class="pill {{ $cls }}">{{ $txt }}</span></span>
                </a>
            @endforeach
        </div>
    @endif

    <footer class="pie">
        <span>{{ config('vandu.marca.nombre') }}{{ config('vandu.marca.ciudad') ? ' · ' . config('vandu.marca.ciudad') : '' }}</span>
        @if($wa)<span>¿Dudas? <a href="https://wa.me/{{ $wa }}?text={{ rawurlencode('Hola, soy ' . $quien . ', tengo una duda.') }}" target="_blank" rel="noopener">Escríbenos por WhatsApp</a></span>@endif
    </footer>
</main>
</body>
</html>
