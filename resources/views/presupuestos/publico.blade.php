@php
    /** @var \App\Models\Presupuesto $p */
    // Una cotización aceptada sigue disponible aunque pase su vigencia
    $aceptada = $p->estado === 'aceptada';
    $proyecto = $p->proyecto;
    $negociando = $p->estado === 'negociacion' && ! $p->vigente;
    $cancelada = $p->estado === 'cancelada';
    $vigente = ! $cancelada && ($p->vigente || $aceptada || $p->estado === 'negociacion');
    $secciones = $p->consideraciones_limpias;
    $vence = \App\Models\Presupuesto::fechaLarga($p->vigencia_local) . ' a las ' . $p->vigencia_local->format('H:i');
    $wa = config('vandu.whatsapp');
    $waMsg = rawurlencode($vigente
        ? "Hola, tengo una duda sobre la cotización {$p->folio}."
        : ($cancelada ? "Hola, quisiera retomar la cotización {$p->folio}." : "Hola, la cotización {$p->folio} venció. ¿Me pueden enviar una actualizada?"));
    $n = 0;
    $puedeResponder = \App\Support\Aceptacion::puedeResponder($p);
    $eventos = $p->eventos; // solo para el aviso de aceptación o cambios; el historial completo vive en el panel
    $ultimaAceptacion = $eventos->firstWhere('tipo', 'aceptada');
    $ultimosCambios = $p->estado === 'negociacion' ? $eventos->firstWhere('tipo', 'cambios') : null;
    $hayCorreo = (bool) $p->contacto?->email;
    // Diseño del negocio (plantilla, color, letra y franjas de su hoja membretada)
    $dd = \App\Support\DisenoDocumento::de();
    $ddOscuro = \App\Support\DisenoDocumento::encabezadoOscuro($dd);
    $ddTinta = \App\Support\DisenoDocumento::tintaSobre($dd['color']);
    $ddSuave = $dd['color'] === '#242424' ? '#F3F3F3' : \App\Support\DisenoDocumento::tinte($dd['color'], .88);
    $franjaSup = \App\Support\DisenoDocumento::dataUri($dd['franja_sup']);
    $franjaInf = \App\Support\DisenoDocumento::dataUri($dd['franja_inf']);
    $conHdr = ! $franjaSup || $dd['con_encabezado'];
    $fechaEv = fn ($d) => $d->copy()->setTimezone(config('vandu.zona_horaria'))->locale('es')->isoFormat('D MMM YYYY, h:mm a');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $p->titulo }} para {{ $p->cliente_empresa ?: $p->cliente_nombre }} | {{ config('vandu.marca.nombre') }}</title>
    <link rel="icon" href="/favi.svg" type="image/svg+xml">
    <link rel="preload" href="{{ route('vandu.fuente') }}" as="font" type="font/woff2" crossorigin>
    <style>
        @font-face { font-family: 'Geist'; src: url('{{ route('vandu.fuente') }}') format('woff2'); font-weight: 100 900; font-display: swap; }
        :root {
            --ink: #13161D; --hdr: #242424; --mist: #F3F3F3; --line: #E3E4E8; --muted: #5d6270;
            --green: #00F385; --amber: #FFB020; --red: #E5484D; --paper: #fff;
        }
        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body { margin: 0; font-family: 'Geist', system-ui, sans-serif; color: var(--ink); background: var(--paper);
               font-size: 16px; line-height: 1.5; }
        .num { font-variant-numeric: tabular-nums; }
        a { color: inherit; }
        :focus-visible { outline: 3px solid var(--green); outline-offset: 2px; }

        /* ---------- Barra de vigencia ---------- */
        .vig { position: sticky; top: 0; z-index: 10; background: var(--ink); color: #fff; }
        .vig .in { max-width: 760px; margin: 0 auto; padding: 10px 16px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .vig .punto { width: 8px; height: 8px; border-radius: 50%; background: var(--green); flex: none; }
        .vig .txt { font-size: 14px; flex: 1 1 200px; }
        .vig .reloj { display: flex; gap: 6px; font-weight: 600; }
        .vig .reloj span { display: inline-flex; align-items: baseline; gap: 2px; background: rgba(255,255,255,.1); padding: 2px 8px; border-radius: 6px; font-size: 18px; }
        .vig .reloj small { font-size: 11px; font-weight: 400; opacity: .7; }
        .vig.pronto { background: var(--amber); color: var(--ink); }
        .vig.pronto .punto { background: var(--ink); }
        .vig.pronto .reloj span { background: rgba(0,0,0,.08); }
        .vig.vencida { background: var(--red); }
        .vig.aceptada { background: var(--ink); }
        .vig.aceptada .punto { background: var(--green); }
        .vig .ir { color: var(--green); font-weight: 600; text-decoration: none; white-space: nowrap; }
        .vig .ir:hover { text-decoration: underline; }
        .proyecto-card { display: flex; align-items: center; gap: 18px; flex-wrap: wrap; padding: 18px 20px; border: 1.5px solid var(--ink); border-radius: 14px; margin-bottom: 28px; }
        .proyecto-card .info { flex: 1 1 260px; min-width: 0; }
        .proyecto-card .k { font-size: 13px; color: var(--muted); }
        .proyecto-card .n { font-weight: 600; font-size: 17px; margin: 2px 0 8px; }
        .proyecto-card .barra { height: 8px; border-radius: 99px; background: var(--mist); overflow: hidden; }
        .proyecto-card .barra span { display: block; height: 100%; background: var(--ink); border-radius: 99px; }
        .proyecto-card .sig { font-size: 14px; color: var(--muted); margin-top: 6px; }
        .proyecto-card .sig b { color: var(--ink); font-weight: 600; }
        @media (max-width: 600px) { .proyecto-card .btn { flex: 1 1 100%; } }
        .vig.vencida .punto { background: #fff; }

        /* ---------- Documento ---------- */
        .doc { max-width: 760px; margin: 0 auto; padding: 24px 16px 64px; }
        .hdr { background: var(--hdr); color: #fff; border-radius: 14px; padding: 28px 32px;
               display: flex; justify-content: space-between; align-items: center; gap: 20px; flex-wrap: wrap; }
        .hdr img { width: 112px; height: auto; display: block; }
        .hdr .emisor { text-align: right; font-size: 13px; line-height: 1.55; }
        .hdr .emisor b { font-weight: 600; }
        .hdr .emisor a { color: #e6e6e6; text-decoration: none; }
        .hdr .emisor a:hover { color: var(--green); }

        .partes { display: flex; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin: 32px 0 28px; }
        .partes .der { text-align: right; }
        .partes b { font-weight: 600; font-size: 18px; display: block; }
        .partes span { color: var(--muted); }

        .acciones { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 36px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 48px; padding: 0 22px;
               border-radius: 10px; font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; border: 1.5px solid var(--ink); }
        .btn-prim { background: var(--ink); color: #fff; }
        .btn-prim:hover { background: #000; color: var(--green); }
        .btn-sec { background: #fff; color: var(--ink); }
        .btn-sec:hover { background: var(--mist); }
        .btn svg { width: 18px; height: 18px; flex: none; }

        /* Conceptos */
        .tabla { width: 100%; border-collapse: collapse; }
        .tabla th { background: var(--mist); text-align: left; font-weight: 600; padding: 12px 14px; font-size: 15px; }
        .tabla th.c, .tabla td.c { text-align: center; }
        .tabla th.r, .tabla td.r { text-align: right; }
        .tabla td { padding: 20px 14px; vertical-align: middle; border-bottom: 1px solid var(--line); }
        .tabla td.desc .c-tit { display: block; font-weight: 600; }
        .tabla td.desc .c-desc { display: block; white-space: pre-line; color: #3f4450; }
        .tabla td.desc .c-tit + .c-desc { margin-top: 2px; }
        .tot { margin-left: auto; width: min(100%, 340px); margin-top: 16px; }
        .tot div { display: flex; justify-content: space-between; padding: 10px 14px; background: var(--mist); }
        .tot div + div { margin-top: 2px; }
        .tot .final { font-weight: 700; font-size: 18px; }

        .obs { margin-top: 28px; padding: 18px 20px; border-radius: 14px; background: #F6F7F9; border: 1px solid #E7E9EE; }
        .obs h3 { font-size: 15px; font-weight: 600; margin: 0 0 6px; }
        .obs p { margin: 6px 0 0; color: #4B5160; font-size: 15px; line-height: 1.5; }
        h2 { font-size: 22px; font-weight: 600; margin: 48px 0 8px; letter-spacing: -.01em; }
        .sec { padding: 18px 0; border-top: 1px solid var(--line); display: grid; grid-template-columns: 32px 1fr; gap: 0 8px; }
        .sec .n { font-weight: 600; color: var(--muted); }
        .sec h3 { margin: 0; font-size: 17px; font-weight: 600; }
        .sec ul { margin: 6px 0 0; padding-left: 18px; }
        .sec li + li { margin-top: 4px; }
        .sec p { margin: 6px 0 0; }
        .sec .cuerpo { min-width: 0; }

        .banco { margin-top: 16px; border: 1.5px solid var(--ink); border-radius: 10px; overflow: hidden; }
        .banco .t { background: var(--ink); color: #fff; font-weight: 600; text-align: center; padding: 10px; font-size: 14px; }
        .banco .fila { display: grid; grid-template-columns: 130px 1fr auto; align-items: center; gap: 8px; padding: 12px 14px; }
        .banco .fila + .fila { border-top: 1px solid var(--line); }
        .banco .k { color: var(--muted); font-size: 14px; }
        .banco .v { font-weight: 600; overflow-wrap: anywhere; }
        .copiar { font: inherit; font-size: 13px; font-weight: 600; background: var(--mist); border: 0; border-radius: 6px; padding: 6px 10px; cursor: pointer; }
        .copiar:hover { background: var(--green); }
        .nota { font-weight: 600; margin-top: 16px !important; }

        .pie { margin-top: 48px; padding-top: 18px; border-top: 1px solid var(--line); color: var(--muted); font-size: 14px;
               display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }

        /* Responder: aceptar o pedir cambios */
        .resp { border: 1.5px solid var(--ink); border-radius: 14px; padding: 20px 22px; margin: 0 0 32px; }
        .resp h2 { margin: 0 0 4px; font-size: 19px; }
        .resp p { margin: 0 0 14px; color: var(--muted); font-size: 15px; }
        .resp .acciones { margin: 0; }
        .resp.ok { border-color: #00C46A; background: #F1FFF8; }
        .resp .cita { margin: 10px 0 14px; padding: 12px 14px; border-left: 3px solid var(--ink); background: var(--mist); border-radius: 0 10px 10px 0; white-space: pre-line; color: var(--ink); font-size: 15px; }
        .aviso-flash { border-radius: 12px; padding: 14px 16px; margin: 0 0 20px; font-weight: 500; }
        .aviso-flash.ok { background: #E3FBEF; color: #047A4B; }
        .aviso-flash.error { background: #FDECEC; color: #B4232A; }

        dialog.modal { border: 0; border-radius: 16px; padding: 0; width: min(520px, calc(100vw - 24px)); max-height: calc(100dvh - 24px); box-shadow: 0 24px 60px rgba(0,0,0,.25); color: var(--ink); }
        dialog.modal::backdrop { background: rgba(15,18,25,.55); }
        .modal form { padding: 22px; display: grid; gap: 14px; }
        .modal h3 { margin: 0; font-size: 20px; }
        .modal .sub { margin: -8px 0 0; color: var(--muted); font-size: 14.5px; }
        .modal label { font-weight: 600; font-size: 14.5px; display: block; margin-bottom: 6px; }
        .modal input[type=text], .modal textarea { width: 100%; font: inherit; border: 1.5px solid var(--line); border-radius: 10px; padding: 11px 13px; }
        .modal input:focus, .modal textarea:focus { border-color: var(--ink); outline: none; }
        .modal textarea { min-height: 130px; resize: vertical; }
        .modal .ayuda { font-size: 13.5px; color: var(--muted); margin-top: 6px; }
        .modal .ayuda button { font: inherit; font-weight: 600; color: var(--ink); background: none; border: 0; padding: 0; text-decoration: underline; cursor: pointer; }
        .modal .check { display: flex; gap: 10px; align-items: flex-start; font-weight: 400; font-size: 14.5px; }
        .modal .check input { width: 20px; height: 20px; margin-top: 2px; flex: none; accent-color: var(--ink); }
        .modal .error { color: #B4232A; font-size: 14px; font-weight: 500; }
        .modal .exito { color: #047A4B; font-size: 14px; font-weight: 500; }
        .modal .pie-modal { display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
        .modal .btn[disabled] { opacity: .6; cursor: wait; }
        .modal [hidden] { display: none !important; }


        /* Vencida */
        .vencida-card { margin-top: 32px; background: var(--mist); border-radius: 14px; padding: 32px; }
        .vencida-card h1 { font-size: 26px; margin: 0 0 8px; letter-spacing: -.01em; }
        .vencida-card p { margin: 0 0 20px; color: var(--muted); max-width: 52ch; }

        /* Móvil */
        @media (max-width: 600px) {
            .hdr { padding: 22px; }
            .hdr .emisor { text-align: left; }
            .partes .der { text-align: left; }
            .acciones .btn { flex: 1 1 100%; }
            .tabla thead { display: none; }
            .tabla, .tabla tbody, .tabla tr, .tabla td { display: block; width: 100%; }
            .tabla tr { border-bottom: 1px solid var(--line); padding: 16px 0; }
            .tabla td { border: 0; padding: 2px 0; text-align: left !important; }
            .tabla td.desc { margin-bottom: 8px; }
            .tabla td[data-k]::before { content: attr(data-k) ': '; color: var(--muted); }
            .banco .fila { grid-template-columns: 1fr auto; }
            .banco .k { grid-column: 1 / -1; }
            .vig .reloj span { font-size: 16px; }
        }
        @media print {
            .vig, .acciones, .copiar, .resp, dialog { display: none !important; }
            .doc { padding-top: 0; }
        }
            .mi-espacio { display: inline-flex; align-items: center; gap: 8px; margin: 18px 0 0; padding: 8px 14px; border: 1.5px solid #E3E4E8; border-radius: 10px; text-decoration: none; font-weight: 500; font-size: 15px; color: #13161D; background: #fff; }
        .mi-espacio:hover { border-color: #13161D; }
        .mi-espacio svg { width: 17px; height: 17px; }
        @media print { .mi-espacio { display: none; } }

        /* Diseño del negocio */
        :root { --hdr: {{ $dd['color'] }}; --mist: {{ $ddSuave }}; }
        body { font-family: {!! \App\Support\DisenoDocumento::LETRAS[$dd['letra']]['web'] !!}; }
        .hdr { color: {{ $ddTinta }}; }
        .hdr .emisor a { color: inherit; opacity: .85; }
        .franja { display: block; width: 100%; height: auto; border-radius: 10px; }
        .franja.inf { margin-top: 40px; }
        @if(! $dd['membrete'] || $dd['con_encabezado'])
        @if($dd['plantilla'] === 'moderna')
            .hdr { border-radius: 0; }
            .tabla th { background: var(--hdr); color: {{ $ddTinta }}; }
        @elseif($dd['plantilla'] === 'minimal')
            .hdr { background: none; color: var(--ink); border-radius: 0; padding: 8px 0 18px; border-bottom: 2px solid var(--hdr); }
        @elseif($dd['plantilla'] === 'elegante')
            .hdr { background: none; color: var(--ink); border-radius: 0; padding: 4px 0 18px; border-bottom: 4px double var(--hdr); flex-direction: column; justify-content: center; text-align: center; }
            .hdr .emisor { text-align: center !important; }
            .tabla th { background: none; border-top: 1px solid var(--hdr); border-bottom: 1px solid var(--hdr); text-transform: uppercase; letter-spacing: .06em; font-size: 13px; }
        @endif
        @endif
        @if($dd['plantilla'] === 'minimal')
            .tabla th { background: none; border-bottom: 2px solid var(--hdr); }
        @endif
        @if($dd['color'] !== '#242424')
            .tot .final { background: var(--hdr); color: {{ $ddTinta }}; }
        @endif
    </style>
</head>
<body>

{{-- ================= Barra de vigencia con cuenta regresiva ================= --}}
<div class="vig {{ $aceptada || $negociando ? 'aceptada' : ($vigente ? '' : 'vencida') }}" id="vig" role="status" data-fin="{{ $p->vigente_hasta->toIso8601String() }}">
    <div class="in">
        <span class="punto" aria-hidden="true"></span>
        @if($aceptada)
            <span class="txt">Cotización aceptada{{ $p->aceptada_el ? ' el ' . \App\Models\Presupuesto::fechaLarga($p->aceptada_el) : '' }}. ¡Gracias por tu confianza!</span>
            @if($proyecto)<a class="ir" href="{{ $proyecto->url_publica }}">Ver proyecto →</a>@endif
        @elseif($cancelada)
            <span class="txt">Esta cotización se canceló{{ $p->cancelada_at ? ' el ' . \App\Models\Presupuesto::fechaLarga($p->cancelada_at->copy()->setTimezone(config('vandu.zona_horaria'))) : '' }}. Si quieres retomarla, escríbenos.</span>
        @elseif($negociando)
            <span class="txt">Estamos afinando los detalles de esta cotización contigo.</span>
        @elseif($vigente)
            <span class="txt">Esta cotización es válida hasta el {{ $vence }}</span>
            <span class="reloj num" aria-label="Tiempo restante">
                <span><b id="d">--</b><small>d</small></span>
                <span><b id="h">--</b><small>h</small></span>
                <span><b id="m">--</b><small>m</small></span>
                <span><b id="s">--</b><small>s</small></span>
            </span>
        @else
            <span class="txt">Esta cotización venció el {{ $vence }}</span>
        @endif
    </div>
</div>

<main class="doc">
    @if($franjaSup)<img class="franja" src="{{ $franjaSup }}" alt="{{ config('vandu.marca.nombre') }}">@endif
    @if($conHdr)
    <header class="hdr">
        @php $ddLogo = \App\Support\DisenoDocumento::logo($ddOscuro); @endphp
        @if($ddOscuro && \App\Support\Cuentas::esPrincipal())<x-logo-vandu width="112" height="36" />
        @elseif($ddLogo)<img src="{{ $ddLogo }}" alt="{{ config('vandu.marca.nombre') }}" style="max-height:40px; width:auto; max-width:180px; object-fit:contain">@else<b style="font-size:20px">{{ config('vandu.marca.nombre') }}</b>@endif
        <div class="emisor">
            @if($p->emisor_nombre)<b>{{ $p->emisor_nombre }}</b><br>@endif
            @if($p->emisor_telefono)<b><a href="tel:{{ preg_replace('/\D/', '', $p->emisor_telefono) }}">{{ $p->emisor_telefono }}</a></b><br>@endif
            @if($p->emisor_sitio)<a href="https://{{ $p->emisor_sitio }}" target="_blank" rel="noopener">{{ $p->emisor_sitio }}</a><br>@endif
            @if($p->emisor_email)<a href="mailto:{{ $p->emisor_email }}">{{ $p->emisor_email }}</a>@endif
        </div>
    </header>
    @endif

    @if($p->cliente && ! request()->boolean('pdf'))
        <a class="mi-espacio" href="{{ $p->cliente->portal_url }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg> Ver todos mis proyectos y cotizaciones</a>
    @endif

    <section class="partes">
        <div><b>{{ $p->cliente_nombre }}</b><span>{{ $p->cliente_empresa }}</span></div>
        <div class="der"><b>{{ $p->fecha_larga }}</b><span>{{ $p->titulo }}</span></div>
    </section>

    @if($vigente)
        @if($proyecto)
            <section class="proyecto-card" aria-label="Tu proyecto">
                <div class="info">
                    <div class="k">Tu proyecto · {{ $proyecto->tipo_nombre }}</div>
                    <div class="n">{{ $proyecto->nombre }}</div>
                    <div class="barra" role="progressbar" aria-valuenow="{{ $proyecto->progreso }}" aria-valuemin="0" aria-valuemax="100" aria-label="Avance"><span style="width: {{ $proyecto->progreso }}%"></span></div>
                    <div class="sig num">{{ $proyecto->progreso }}% · @if($proyecto->estado === 'terminado')<b>Terminado</b>@else Siguiente: <b>{{ $proyecto->siguiente_paso }}</b>@endif</div>
                </div>
                <a class="btn btn-prim" href="{{ $proyecto->url_publica }}">
                    Ver proyecto
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </a>
            </section>
        @endif
        <div class="acciones">
            <a class="btn {{ $proyecto ? 'btn-sec' : 'btn-prim' }}" href="{{ route('presupuesto.descargar', $p->token) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m0 0-5-5m5 5 5-5M4 21h16"/></svg>
                Descargar cotización
            </a>
            @foreach($p->facturas_ordenadas as $fa)
            <a class="btn btn-sec" href="{{ route('presupuesto.factura', [$p->token, $fa->id]) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h6M9 17h6"/></svg>
                {{ $fa->etiqueta }}
            </a>
            @endforeach
            @foreach($p->complementos_lotes->flatMap(fn ($c) => $c['archivos']) as $fa)
            <a class="btn btn-sec" href="{{ route('presupuesto.factura', [$p->token, $fa->id]) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h6M9 17h6"/></svg>
                {{ $fa->etiqueta }}
            </a>
            @endforeach
            @unless($proyecto)
            <a class="btn btn-sec" href="https://wa.me/{{ $wa }}?text={{ $waMsg }}" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.8 11.9 11.9 0 0 0 4.6 4c1.7.7 2.3.8 3.2.7.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.5-.3Z"/></svg>
                Tengo una duda
            </a>
            @endunless
        </div>

        @if(session('respuesta_ok'))<div class="aviso-flash ok" role="status">{{ session('respuesta_ok') }}</div>@endif
        @if(session('respuesta_error'))<div class="aviso-flash error" role="alert">{{ session('respuesta_error') }}</div>@endif

        @if($aceptada)
            <section class="resp ok" aria-label="Cotización aceptada">
                <h2>Cotización aceptada</h2>
                <p style="margin:0">@if($ultimaAceptacion)Aceptada por <b>{{ $ultimaAceptacion->autor }}</b> el {{ $fechaEv($ultimaAceptacion->created_at) }}.@else Aceptada{{ $p->aceptada_el ? ' el ' . \App\Models\Presupuesto::fechaLarga($p->aceptada_el) : '' }}.@endif
                    Si necesitas algún ajuste, escríbenos.</p>
            </section>
        @elseif($puedeResponder)
            <section class="resp" aria-label="Responder a la cotización">
                @if($ultimosCambios)
                    <h2>Recibimos tus comentarios</h2>
                    <p style="margin-bottom:0">{{ $ultimosCambios->autor }} · {{ $fechaEv($ultimosCambios->created_at) }}</p>
                    <div class="cita">{{ $ultimosCambios->detalle }}</div>
                    <p>Estamos preparando la versión actualizada. Si ya estás de acuerdo con esta, puedes aceptarla.</p>
                @else
                    <h2>¿Todo listo?</h2>
                    <p>Acepta la cotización para apartar fechas, o dinos qué te gustaría ajustar.</p>
                @endif
                <div class="acciones">
                    <button type="button" class="btn btn-prim" data-responder="aceptar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5L20 7"/></svg>
                        Aceptar cotización
                    </button>
                    <button type="button" class="btn btn-sec" data-responder="cambios">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4Z"/></svg>
                        Solicitar cambios
                    </button>
                </div>
            </section>
        @endif

        <table class="tabla">
            <thead><tr><th>Concepto</th><th class="c" style="width:110px">Cantidad</th><th class="r" style="width:170px">Costo</th></tr></thead>
            <tbody>
            @foreach($p->conceptos as $c)
                <tr>
                    <td class="desc">@if($c->titulo)<span class="c-tit">{{ $c->titulo }}</span>@endif @if(trim($c->descripcion))<span class="c-desc">{{ trim($c->descripcion) }}</span>@endif</td>
                    <td class="c num" data-k="Cantidad">{{ $c->cantidad_texto }}</td>
                    <td class="r num" data-k="Costo">{{ $p->monto($c->importe) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <div class="tot num">
            <div><span>Subtotal</span><span>{{ $p->monto($p->subtotal) }}</span></div>
            @if($p->modo_iva === 'desglosado')
                <div><span>IVA {{ rtrim(rtrim(number_format($p->iva_porcentaje, 2), '0'), '.') }}%</span><span>{{ $p->monto($p->iva) }}</span></div>
                <div class="final"><span>Total</span><span>{{ $p->monto($p->total) }}</span></div>
            @endif
        </div>

        @if($obs = $p->observaciones_lineas)
            <section class="obs" aria-label="Observaciones">
                <h3>Observaciones</h3>
                @foreach($obs as $linea)<p>{{ $linea }}</p>@endforeach
            </section>
        @endif

        @if(count($secciones) || $p->mostrar_pago)
            <h2>Consideraciones</h2>
        @endif

        @foreach($secciones as $s)
            <section class="sec">
                <span class="n num">{{ ++$n }}.</span>
                <div class="cuerpo">
                    <h3>{{ $s['titulo'] }}</h3>
                    @if(count($s['items']))
                        <ul>@foreach($s['items'] as $item)<li>{{ $item }}</li>@endforeach</ul>
                    @endif
                </div>
            </section>
        @endforeach

        @if($p->mostrar_pago)
            <section class="sec">
                <span class="n num">{{ ++$n }}.</span>
                <div class="cuerpo">
                    <h3>Confirmación de servicios</h3>
                    @if($p->pago_intro)<p>{{ $p->pago_intro }}</p>@endif
                    <div class="banco">
                        <div class="t">Datos bancarios</div>
                        @foreach(['CLABE' => $p->clabe, 'Banco' => $p->banco, 'Beneficiario' => $p->beneficiario] as $k => $v)
                            @continue(! $v)
                            <div class="fila">
                                <span class="k">{{ $k }}</span>
                                <span class="v num">{{ $v }}</span>
                                @if($k !== 'Banco')<button type="button" class="copiar" data-copiar="{{ $v }}" aria-label="Copiar {{ $k }}">Copiar</button>@else<span></span>@endif
                            </div>
                        @endforeach
                    </div>
                    @if($p->nota_comprobante)<p class="nota">{{ $p->nota_comprobante }}</p>@endif
                    @if($p->nota_factura)<p>{{ $p->nota_factura }}</p>@endif
                </div>
            </section>
        @endif

        <div class="acciones" style="margin: 40px 0 0">
            @if($puedeResponder)
                <button type="button" class="btn btn-prim" data-responder="aceptar">Aceptar cotización</button>
                <button type="button" class="btn btn-sec" data-responder="cambios">Solicitar cambios</button>
            @endif
            <a class="btn {{ $puedeResponder ? 'btn-sec' : 'btn-prim' }}" href="{{ route('presupuesto.descargar', $p->token) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m0 0-5-5m5 5 5-5M4 21h16"/></svg>
                Descargar cotización
            </a>
        </div>
    @else
        <section class="vencida-card">
            <h1>Esta cotización ya no está vigente</h1>
            <p>Los precios y condiciones eran válidos hasta el {{ $vence }}. Escríbenos y te enviamos una cotización actualizada.</p>
            <div class="acciones" style="margin:0">
                <a class="btn btn-prim" href="https://wa.me/{{ $wa }}?text={{ $waMsg }}" target="_blank" rel="noopener">Pedir cotización actualizada</a>
                @if($p->emisor_email)<a class="btn btn-sec" href="mailto:{{ $p->emisor_email }}?subject={{ rawurlencode('Cotización ' . $p->folio) }}">Enviar correo</a>@endif
            </div>
        </section>
    @endif


    @if($vigente && $puedeResponder)
        <dialog class="modal" id="responder" aria-labelledby="resp-titulo">
            <form method="post" action="{{ route('presupuesto.responder', $p->token) }}" novalidate>
                @csrf
                <input type="hidden" name="accion" value="aceptar">
                <h3 id="resp-titulo">Aceptar cotización</h3>
                <p class="sub" data-sub-aceptar>Confirma que estás de acuerdo con la cotización {{ $p->folio }} por <b>{{ $p->monto($p->modo_iva === 'desglosado' ? $p->total : $p->subtotal) }}</b>.</p>
                <p class="sub" data-sub-cambios hidden>Cuéntanos qué te gustaría ajustar y te enviaremos una versión actualizada.</p>

                <div data-solo-cambios hidden>
                    <label for="r-mensaje">¿Qué cambios necesitas?</label>
                    <textarea id="r-mensaje" name="mensaje" maxlength="3000" placeholder="Por ejemplo: agregar 5 fotos más, cambiar la fecha de grabación, ajustar el presupuesto a…"></textarea>
                </div>

                <div>
                    <label for="r-nombre">Tu nombre</label>
                    <input type="text" id="r-nombre" name="nombre" value="{{ $p->cliente_nombre }}" maxlength="120" autocomplete="name" required>
                </div>

                <label class="check" data-solo-aceptar><input type="checkbox" name="conforme" value="1"> <span>Acepto los conceptos, precios y condiciones de esta cotización.</span></label>

                <div class="error" data-error role="alert" hidden></div>

                <div class="pie-modal">
                    <button type="button" class="btn btn-sec" data-cerrar>Cancelar</button>
                    <button type="submit" class="btn btn-prim" data-enviar>Aceptar cotización</button>
                </div>
            </form>
        </dialog>
    @endif

    @if($franjaInf)<img class="franja inf" src="{{ $franjaInf }}" alt="">@endif
    <footer class="pie">
        <span>Folio {{ $p->folio }}</span>
        <span>{{ config('vandu.marca.nombre') }}{{ config('vandu.marca.ciudad') ? ', ' . config('vandu.marca.ciudad') : '' }}</span>
    </footer>
</main>

<script>
(function () {
    // Copiar datos bancarios
    document.addEventListener('click', async function (e) {
        var b = e.target.closest('[data-copiar]'); if (!b) return;
        try { await navigator.clipboard.writeText(b.dataset.copiar); b.textContent = 'Copiado'; }
        catch (err) { window.prompt('Copia el dato:', b.dataset.copiar); return; }
        setTimeout(function () { b.textContent = 'Copiar'; }, 1600);
    });

    // Aceptar o pedir cambios
    var dlg = document.getElementById('responder');
    if (dlg) {
        var f = dlg.querySelector('form'), err = f.querySelector('[data-error]'), enviar = f.querySelector('[data-enviar]');
        var mostrar = function (sel, si) { f.querySelectorAll(sel).forEach(function (e) { e.hidden = !si; }); };
        var abrir = function (accion) {
            f.accion.value = accion; err.hidden = true;
            var cambios = accion === 'cambios';
            dlg.querySelector('#resp-titulo').textContent = cambios ? 'Solicitar cambios' : 'Aceptar cotización';
            enviar.textContent = cambios ? 'Enviar comentarios' : 'Aceptar cotización';
            mostrar('[data-sub-aceptar], [data-solo-aceptar]', !cambios);
            mostrar('[data-sub-cambios], [data-solo-cambios]', cambios);
            dlg.showModal ? dlg.showModal() : dlg.setAttribute('open', '');
            setTimeout(function () { (cambios ? f.mensaje : f.nombre).focus(); }, 50);
        };
        document.querySelectorAll('[data-responder]').forEach(function (b) { b.addEventListener('click', function () { abrir(b.dataset.responder); }); });
        f.querySelector('[data-cerrar]').addEventListener('click', function () { dlg.close(); });
        var fallo = function (m, campo) { err.textContent = m; err.hidden = false; if (campo && f[campo]) f[campo].focus(); };
        f.addEventListener('submit', async function (e) {
            e.preventDefault(); err.hidden = true;
            var cambios = f.accion.value === 'cambios';
            if (cambios && f.mensaje.value.trim().length < 5) return fallo('Cuéntanos qué cambios necesitas.', 'mensaje');
            if (!f.nombre.value.trim()) return fallo('Escribe tu nombre.', 'nombre');
            if (!cambios && !f.conforme.checked) return fallo('Marca la casilla para confirmar que estás de acuerdo.');
            enviar.disabled = true;
            try {
                var r = await fetch(f.action, { method: 'POST', body: new FormData(f), headers: { 'Accept': 'application/json' } });
                var d = await r.json().catch(function () { return {}; });
                if (r.ok && d.ok) {
                    f.innerHTML = '<h3>' + (cambios ? '¡Gracias por tus comentarios!' : '¡Cotización aceptada!') + '</h3><p class="sub" style="margin:0">' + d.mensaje + '</p>';
                    setTimeout(function () { location.reload(); }, 2200);
                    return;
                }
                var m = d.mensaje || (d.errors ? Object.values(d.errors)[0][0] : 'No se pudo enviar. Intenta de nuevo.');
                fallo(m, d.campo || (d.errors ? Object.keys(d.errors)[0] : null));
            } catch (e2) { fallo('Revisa tu conexión e intenta de nuevo.'); }
            enviar.disabled = false;
        });
    }

    // Cuenta regresiva
    var bar = document.getElementById('vig'); if (!bar || bar.classList.contains('vencida') || bar.classList.contains('aceptada')) return;
    var fin = new Date(bar.dataset.fin).getTime();
    var el = { d: document.getElementById('d'), h: document.getElementById('h'), m: document.getElementById('m'), s: document.getElementById('s') };
    var pad = function (n) { return String(n).padStart(2, '0'); };
    function tick() {
        var ms = fin - Date.now();
        if (ms <= 0) { window.location.reload(); return; }
        var t = Math.floor(ms / 1000);
        el.d.textContent = Math.floor(t / 86400);
        el.h.textContent = pad(Math.floor(t % 86400 / 3600));
        el.m.textContent = pad(Math.floor(t % 3600 / 60));
        el.s.textContent = pad(t % 60);
        bar.classList.toggle('pronto', ms <= 48 * 3600 * 1000);
        setTimeout(tick, 1000 - (Date.now() % 1000));
    }
    tick();
})();
</script>
</body>
</html>
