@php
    /** @var \App\Models\Presupuesto $p */
    $vigente = $p->vigente;
    $secciones = $p->consideraciones_limpias;
    $vence = \App\Models\Presupuesto::fechaLarga($p->vigencia_local) . ' a las ' . $p->vigencia_local->format('H:i');
    $wa = config('vandu.whatsapp');
    $waMsg = rawurlencode($vigente
        ? "Hola, tengo una duda sobre la cotización {$p->folio}."
        : "Hola, la cotización {$p->folio} venció. ¿Me pueden enviar una actualizada?");
    $n = 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $p->titulo }} para {{ $p->cliente_empresa ?: $p->cliente_nombre }} | Agencia Vandu</title>
    <link rel="icon" href="/favi.svg" type="image/svg+xml">
    <link rel="preload" href="/font/Geist-Variable.woff2" as="font" type="font/woff2" crossorigin>
    <style>
        @font-face { font-family: 'Geist'; src: url('/font/Geist-Variable.woff2') format('woff2'); font-weight: 100 900; font-display: swap; }
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
        .tabla td.desc { white-space: pre-line; }
        .tot { margin-left: auto; width: min(100%, 340px); margin-top: 16px; }
        .tot div { display: flex; justify-content: space-between; padding: 10px 14px; background: var(--mist); }
        .tot div + div { margin-top: 2px; }
        .tot .final { font-weight: 700; font-size: 18px; }

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
            .tabla td.desc { font-weight: 500; margin-bottom: 8px; }
            .tabla td[data-k]::before { content: attr(data-k) ': '; color: var(--muted); }
            .banco .fila { grid-template-columns: 1fr auto; }
            .banco .k { grid-column: 1 / -1; }
            .vig .reloj span { font-size: 16px; }
        }
        @media print {
            .vig, .acciones, .copiar { display: none !important; }
            .doc { padding-top: 0; }
        }
    </style>
</head>
<body>

{{-- ================= Barra de vigencia con cuenta regresiva ================= --}}
<div class="vig {{ $vigente ? '' : 'vencida' }}" id="vig" role="status" data-fin="{{ $p->vigente_hasta->toIso8601String() }}">
    <div class="in">
        <span class="punto" aria-hidden="true"></span>
        @if($vigente)
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
    <header class="hdr">
        <x-logo-vandu width="112" height="36" />
        <div class="emisor">
            @if($p->emisor_nombre)<b>{{ $p->emisor_nombre }}</b><br>@endif
            @if($p->emisor_telefono)<b><a href="tel:{{ preg_replace('/\D/', '', $p->emisor_telefono) }}">{{ $p->emisor_telefono }}</a></b><br>@endif
            @if($p->emisor_sitio)<a href="https://{{ $p->emisor_sitio }}" target="_blank" rel="noopener">{{ $p->emisor_sitio }}</a><br>@endif
            @if($p->emisor_email)<a href="mailto:{{ $p->emisor_email }}">{{ $p->emisor_email }}</a>@endif
        </div>
    </header>

    <section class="partes">
        <div><b>{{ $p->cliente_nombre }}</b><span>{{ $p->cliente_empresa }}</span></div>
        <div class="der"><b>{{ $p->fecha_larga }}</b><span>{{ $p->titulo }}</span></div>
    </section>

    @if($vigente)
        <div class="acciones">
            <a class="btn btn-prim" href="{{ route('presupuesto.descargar', $p->token) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m0 0-5-5m5 5 5-5M4 21h16"/></svg>
                Descargar PDF
            </a>
            <a class="btn btn-sec" href="https://wa.me/{{ $wa }}?text={{ $waMsg }}" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.8 11.9 11.9 0 0 0 4.6 4c1.7.7 2.3.8 3.2.7.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.5-.3Z"/></svg>
                Tengo una duda
            </a>
        </div>

        <table class="tabla">
            <thead><tr><th>Concepto</th><th class="c" style="width:110px">Cantidad</th><th class="r" style="width:170px">Costo</th></tr></thead>
            <tbody>
            @foreach($p->conceptos as $c)
                <tr>
                    <td class="desc">{{ $c->descripcion }}</td>
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
            <a class="btn btn-prim" href="{{ route('presupuesto.descargar', $p->token) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m0 0-5-5m5 5 5-5M4 21h16"/></svg>
                Descargar PDF
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

    <footer class="pie">
        <span>Folio {{ $p->folio }}</span>
        <span>Agencia Vandu, Mérida, Yucatán</span>
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

    // Cuenta regresiva
    var bar = document.getElementById('vig'); if (!bar || bar.classList.contains('vencida')) return;
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
