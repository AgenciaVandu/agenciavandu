@php
    $r = $cont['resultados'];
    $iv = $cont['iva'];
    $cp = $cont['comprobantes'];
    $d2 = fn ($n) => ($n < 0 ? '−' : '') . '$' . number_format(abs($n ?? 0), 2);
    $d0 = fn ($n) => ($n < 0 ? '−' : '') . '$' . number_format(abs($n ?? 0), 0);
    $variosMeses = ! $desde || substr($desde, 0, 7) !== substr($hasta, 0, 7);
    $mesSig = $hasta ? \Illuminate\Support\Carbon::parse($hasta)->addMonthNoOverflow()->locale('es')->isoFormat('MMMM') : null;
    $urlGastos = fn (array $x = []) => route('admin.gastos', array_merge(['periodo' => $periodo === 'mes' ? null : $periodo], $x));
@endphp

@push('head')
<style>
    .er { display: grid; }
    .er .fila { display: flex; justify-content: space-between; gap: 16px; padding: 10px 20px; font-size: 14.5px; border-top: 1px solid #F1F2F4; }
    .er .fila:first-child { border-top: 0; }
    .er .fila .secundario { font-size: 13px; }
    .er .fila > span:last-child { white-space: nowrap; }
    .er .fila.sub { padding-left: 38px; color: var(--text-2); font-size: 14px; }
    .er .fila.menos span:last-child { color: var(--text-2); }
    .er .fila.total { font-weight: 600; background: #F8F9FA; }
    .er .fila.final { font-weight: 700; font-size: 16px; background: #E3FBEF; color: #065F46; }
    .er .fila.final.neg { background: #FDECEC; color: var(--red); }
    .iva-cifra { font-size: 30px; font-weight: 700; letter-spacing: -.02em; }
    .iva-cifra.pagar { color: var(--text); } .iva-cifra.favor { color: var(--green-ink); }
    .barra-comp { display: flex; height: 12px; border-radius: 6px; overflow: hidden; background: #F1F2F4; margin: 6px 0 10px; }
    .barra-comp span { display: block; height: 100%; }
    .paq-cab { display: flex; gap: 16px; align-items: center; padding: 18px 20px; flex-wrap: wrap; }
    .paq-ico { width: 44px; height: 44px; border-radius: 12px; background: var(--green-soft); color: var(--green-ink); display: grid; place-items: center; font-size: 21px; flex: none; }
    .paq-txt { flex: 1 1 280px; min-width: 0; } .paq-txt h2 { font-size: 16px; font-weight: 600; margin: 0 0 2px; } .paq-txt p { margin: 0; font-size: 13.5px; color: var(--muted); }
    .paq-acc { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; } .paq-acc .form-select { width: auto; min-width: 160px; }
    .paq-form { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr) minmax(0, 1.6fr) auto; gap: 12px; align-items: end; padding: 0 20px 12px; border-top: 1px solid var(--line); padding-top: 16px; }
    @media (max-width: 767.98px) { .paq-form { grid-template-columns: 1fr; } .paq-acc { width: 100%; } .paq-acc > * { flex: 1 1 auto; } }
    .nota-cont { font-size: 13px; color: var(--muted); margin-top: 18px; display: flex; gap: 8px; }
</style>
@endpush

@if($cp && $cp['nSin'] > 0)
    <div class="aviso-suave" role="status">
        <i class="bi bi-receipt-cutoff"></i>
        <div>Tienes <b>{{ $d0($cp['sin']) }}</b> en {{ $cp['nSin'] }} {{ $cp['nSin'] === 1 ? 'gasto' : 'gastos' }} sin factura en este periodo.
            Pídela al proveedor cuanto antes: sin factura no la puedes deducir ni acreditar su IVA, y muchos comercios solo facturan dentro del mismo mes.
            <a href="{{ $urlGastos(['sin_factura' => 1]) }}">Ver cuáles</a></div>
    </div>
@endif

<div class="kpis">
    <div class="panel kpi">
        <div class="k"><i class="bi bi-graph-up-arrow"></i> Ventas</div>
        <div class="v num">{{ $d0($r['ventas']) }}</div>
        @php $nV = $r['nCot'] + $r['nDir'] + ($r['nLic'] ?? 0); @endphp
        <div class="s num">{{ $nV }} {{ $nV === 1 ? 'venta' : 'ventas' }} · antes de IVA</div>
    </div>
    <div class="panel kpi destacado">
        <div class="k"><i class="bi bi-cash-stack"></i> {{ $r['utilidad'] < 0 ? 'Pérdida' : 'Utilidad' }}</div>
        <div class="v num">{{ $d0($r['utilidad']) }}</div>
        <div class="s num">{{ $r['utilidad'] < 0 ? 'Los gastos superaron lo que dejó vender' : ($r['margen'] !== null ? $r['margen'] . '% de lo vendido' : 'Sin ventas en el periodo') }}</div>
    </div>
    <div class="panel kpi {{ $iv['saldo'] > 0 ? 'alerta' : 'bueno' }}">
        <div class="k"><i class="bi bi-bank"></i> IVA {{ $iv['saldo'] > 0 ? 'a pagar' : 'a favor' }}</div>
        <div class="v num">{{ $d0(abs($iv['saldo'])) }}</div>
        <div class="s">Estimado{{ $mesSig && ! $variosMeses ? ' · se paga a más tardar el 17 de ' . $mesSig : '' }}</div>
    </div>
    <div class="panel kpi {{ $cp && $cp['nSin'] > 0 ? 'alerta' : '' }}">
        <div class="k"><i class="bi bi-file-earmark-check"></i> Gastos con factura</div>
        @if($cp)
            <div class="v num">{{ $cp['pct'] !== null ? $cp['pct'] . '%' : '—' }}</div>
            <div class="s num">{{ $cp['nSin'] ? $cp['nSin'] . ' sin factura' : ($cp['nCon'] ? 'Todo comprobado' : 'Sin gastos en el periodo') }}</div>
        @else
            <div class="v num">—</div>
            <div class="s">El módulo de Gastos no está activo</div>
        @endif
    </div>
</div>

@php
    $paqMeses = \App\Support\Finanzas\PaqueteContador::meses();
    $paqMes = $desde && ! $variosMeses && isset($paqMeses[substr($desde, 0, 7)]) ? substr($desde, 0, 7) : \App\Support\Finanzas\PaqueteContador::mesSugerido();
    $contador = (array) (\App\Support\Cuentas::actual()?->ajuste('contador') ?? []);
@endphp
<section class="panel paq" style="margin-bottom: 20px" x-data="{ enviar: {{ $errors->has('email') ? 'true' : 'false' }}, mes: '{{ $paqMes }}' }">
    <div class="paq-cab">
        <div class="paq-ico"><i class="bi bi-file-earmark-zip"></i></div>
        <div class="paq-txt"><h2>Paquete para el contador</h2>
            <p>Un ZIP con el Excel del mes (ingresos, gastos, compras y facturas emitidas), los XML, las fotos de los tickets y lo que falta.</p></div>
        <div class="paq-acc">
            <select class="form-select" x-model="mes" aria-label="Mes del paquete">@foreach($paqMeses as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select>
            <a :href="'{{ route('admin.finanzas.paquete') }}?mes=' + mes" class="btn btn-primario" data-recargar><i class="bi bi-download me-1"></i> Descargar</a>
            <button type="button" class="btn btn-borde" @click="enviar = ! enviar"><i class="bi bi-envelope me-1"></i> Enviar al contador</button>
        </div>
    </div>
    <div x-show="enviar" x-cloak>
        <form method="post" action="{{ route('admin.finanzas.paquete.enviar') }}" class="paq-form">
            @csrf
            <input type="hidden" name="mes" :value="mes">
            <div><label class="form-label small" for="paq-email">Correo del contador</label>
                <input type="email" id="paq-email" name="email" value="{{ old('email', $contador['email'] ?? '') }}" class="form-control @error('email') is-invalid @enderror" required maxlength="160">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div><label class="form-label small" for="paq-nombre">Nombre (opcional)</label>
                <input id="paq-nombre" name="nombre" value="{{ old('nombre', $contador['nombre'] ?? '') }}" class="form-control" maxlength="80"></div>
            <div class="paq-nota"><label class="form-label small" for="paq-nota">Mensaje (opcional)</label>
                <input id="paq-nota" name="nota" class="form-control" maxlength="600" placeholder="Te faltan las facturas de la gasolina, ya las pedí."></div>
            <div class="paq-btn"><button class="btn btn-primario">Mandar enlace</button></div>
        </form>
        <p class="secundario small px-4 pb-3 m-0">Le llega un correo con un enlace para descargar el paquete; funciona 15 días. El correo se guarda para la próxima vez.</p>
    </div>
</section>

<div class="rejilla" style="margin-top: 0">
    <section class="panel">
        <div class="panel-head"><div><h2>Estado de resultados</h2><span class="ayuda">{{ \App\Http\Controllers\Admin\FinanzasController::PERIODOS[$periodo] }} · antes de IVA</span></div></div>
        <div class="er num">
            @if($r['nCot'])
                <div class="fila sub"><span>Cotizaciones aceptadas <span class="secundario">({{ $r['nCot'] }})</span></span><span>{{ $d2($r['ventasCot']) }}</span></div>
            @endif
            @if($r['nDir'])
                <div class="fila sub"><span>Ventas de mostrador y pedidos <span class="secundario">({{ $r['nDir'] }})</span></span><span>{{ $d2($r['ventasDir']) }}</span></div>
            @endif
            @if($r['nLic'] ?? 0)
                <div class="fila sub"><span>Licencias de la plataforma <span class="secundario">({{ $r['nLic'] }})</span></span><span>{{ $d2($r['ventasLic']) }}</span></div>
            @endif
            <div class="fila total"><span>Ventas</span><span>{{ $d2($r['ventas']) }}</span></div>
            <div class="fila menos"><span>− Costo de lo vendido</span><span>{{ $d2($r['costo']) }}</span></div>
            <div class="fila total"><span>Utilidad bruta @if($r['margenBruto'] !== null)<span class="secundario fw-normal">· {{ $r['margenBruto'] }}%</span>@endif</span><span>{{ $d2($r['bruta']) }}</span></div>
            @if($r['conGastos'])
                <div class="fila menos"><span>− Gastos de operación</span><span>{{ $d2($r['gastos']) }}</span></div>
                @foreach($r['porCategoria'] as $c)
                    <div class="fila sub"><span><i class="bi {{ $c['icono'] }} me-1"></i>{{ $c['texto'] }} <span class="secundario">({{ $c['n'] }})</span></span><span>{{ $d2($c['monto']) }}</span></div>
                @endforeach
            @endif
            <div class="fila final {{ $r['utilidad'] < 0 ? 'neg' : '' }}"><span>{{ $r['utilidad'] < 0 ? 'Pérdida' : 'Utilidad' }} {{ $r['conGastos'] ? 'de operación' : 'bruta' }} @if($r['margen'] !== null && $r['utilidad'] >= 0)<span class="fw-normal">· {{ $r['margen'] }}%</span>@endif</span><span>{{ $d2($r['utilidad']) }}</span></div>
        </div>
        @if($r['sinCosto'] || ! $r['conGastos'])
            <div class="panel-body pt-2">
                @if($r['sinCosto'])
                    <div class="aviso-suave m-0 mb-2"><i class="bi bi-exclamation-circle"></i><div>{{ $r['sinCosto'] }} {{ $r['sinCosto'] === 1 ? 'venta no tiene' : 'ventas no tienen' }} costo capturado ({{ $d0($r['sinCostoMonto']) }}), así que la utilidad real es menor. Captura el costo en tus productos o usa el costeo en la cotización.</div></div>
                @endif
                @unless($r['conGastos'])
                    <div class="secundario small">No se están restando gastos sueltos (renta, luz, gasolina…) porque el módulo de Gastos no está activo en este negocio.</div>
                @endunless
            </div>
        @endif
    </section>

    <section class="panel">
        <div class="panel-head"><div><h2>IVA {{ $variosMeses ? 'del periodo' : 'del mes' }}</h2><span class="ayuda">Sobre lo que cobraste y lo que pagaste con factura</span></div></div>
        <div class="panel-body">
            <div class="secundario small">{{ $iv['saldo'] > 0 ? 'Estimado a pagar' : 'Estimado a favor' }}</div>
            <div class="iva-cifra num {{ $iv['saldo'] > 0 ? 'pagar' : 'favor' }}">{{ $d2(abs($iv['saldo'])) }}</div>
            @if($variosMeses)<div class="secundario small mt-1">El IVA se declara mes por mes; elige "Este mes" o "Mes pasado" para ver el de cada declaración.</div>@endif
        </div>
        <div class="er num">
            <div class="fila"><span>IVA que cobraste <span class="secundario">(de {{ $d0($iv['cobrado']) }} cobrados)</span></span><span>{{ $d2($iv['trasladado']) }}</span></div>
            @if($iv['conGastos'])
                <div class="fila menos"><span>− IVA de gastos con factura</span><span>{{ $d2($iv['gastos']) }}</span></div>
            @endif
            <div class="fila menos"><span>− IVA de compras a proveedores <span class="secundario">({{ $iv['nOrdenes'] }} con XML)</span></span><span>{{ $d2($iv['ordenes']) }}</span></div>
            <div class="fila total"><span>{{ $iv['saldo'] > 0 ? 'A pagar' : 'A favor' }}</span><span>{{ $d2(abs($iv['saldo'])) }}</span></div>
        </div>
        @if($iv['perdido'] > 0)
            <div class="panel-body pt-2">
                <div class="aviso-suave m-0"><i class="bi bi-piggy-bank"></i><div>{{ $d2($iv['perdido']) }} de IVA en tickets sin factura no se puede acreditar. Si consigues esas facturas, tu IVA a pagar baja esa cantidad.</div></div>
            </div>
        @endif
    </section>
</div>

@if($cp)
<section class="panel" style="margin-top: 20px">
    <div class="panel-head"><div><h2>Gastos deducibles y sin factura</h2><span class="ayuda">Un gasto con factura (XML) se puede deducir; un ticket no</span></div>
        <a href="{{ $urlGastos() }}" class="btn btn-borde btn-sm">Ir a Gastos</a></div>
    <div class="panel-body">
        @php $tot = max(0.01, $cp['con'] + $cp['sin']); @endphp
        <div class="barra-comp" aria-hidden="true">
            <span style="width: {{ $cp['con'] / $tot * 100 }}%; background: #00A862"></span>
            <span style="width: {{ $cp['sin'] / $tot * 100 }}%; background: #E8A33D"></span>
        </div>
        <div class="leyenda-rent num">
            <span><span class="punto" style="background:#00A862"></span> Con factura: <b>{{ $d0($cp['con']) }}</b> ({{ $cp['nCon'] }})</span>
            <span><span class="punto" style="background:#E8A33D"></span> Sin factura: <b>{{ $d0($cp['sin']) }}</b> ({{ $cp['nSin'] }})</span>
        </div>
    </div>
    @if($cp['pendientes']->isNotEmpty())
        <div class="table-responsive">
            <table class="tabla">
                <thead><tr><th>Gasto sin factura</th><th>Fecha</th><th class="text-end">Total</th><th></th></tr></thead>
                <tbody>
                @foreach($cp['pendientes'] as $g)
                    <tr>
                        <td><span class="principal">{{ $g->concepto }}</span><div class="secundario">{{ $g->proveedor ?: 'Sin proveedor' }}{{ $g->foto ? ' · ticket con foto' : '' }}</div></td>
                        <td class="secundario">{{ $f($g->fecha->toDateString()) }}</td>
                        <td class="text-end num">{{ $d2($g->total) }}</td>
                        <td class="text-end"><a href="{{ route('admin.gastos.edit', $g) }}" class="btn btn-borde btn-sm">Subir factura</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($cp['nSin'] > $cp['pendientes']->count())
            <div class="panel-body pt-2"><a href="{{ $urlGastos(['sin_factura' => 1]) }}" class="small">Ver los {{ $cp['nSin'] }} gastos sin factura</a></div>
        @endif
    @endif
</section>
@endif

<p class="nota-cont"><i class="bi bi-info-circle"></i><span>Estos números son una estimación para planear: no consideran retenciones, productos con tasa 0 o exentos, ni pagos provisionales de ISR. La declaración la confirma tu contador.</span></p>
