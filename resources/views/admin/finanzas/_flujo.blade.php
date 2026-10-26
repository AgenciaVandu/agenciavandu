@php
    $fl = $flujo;
    $pr = $fl['proyeccion'];
    $d0 = fn ($n) => ($n < 0 ? '−' : '') . '$' . number_format(abs($n ?? 0), 0);
    $maxMes = max(1, $fl['meses']->max(fn ($m) => max($m['entradas'], $m['salidas'])));
    $maxE = max(1, $fl['porClaseE']->max('monto') ?? 1);
    $maxS = max(1, $fl['porClaseS']->max('monto') ?? 1);
@endphp

@push('head')
<style>
    .fl-meses { display: grid; gap: 2px; padding: 8px 20px 16px; }
    .fl-mes { display: grid; grid-template-columns: 54px minmax(0, 1fr) 96px; gap: 12px; align-items: center; padding: 5px 0; }
    .fl-mes .et { font-size: 13px; color: var(--muted); } .fl-mes.actual .et { color: var(--text); font-weight: 600; }
    .fl-mes .barras { display: grid; gap: 3px; }
    .fl-mes .barras span { display: block; height: 8px; border-radius: 0 4px 4px 0; }
    .fl-mes .neto { text-align: right; font-size: 13.5px; font-weight: 600; }
    .fl-pos { color: var(--green-ink); } .fl-neg { color: var(--red); }
    .fl-tabla td, .fl-tabla th { white-space: nowrap; }
    .fl-tabla .venc { display: block; font-size: 12px; color: var(--amber); font-weight: 400; }
    .fl-lista { display: grid; }
    .fl-lista a, .fl-lista div.fila { display: flex; justify-content: space-between; gap: 12px; padding: 10px 20px; border-top: 1px solid #F1F2F4; color: inherit; text-decoration: none; font-size: 14px; }
    .fl-lista a:hover { background: #F8F9FA; }
    .fl-lista .q { min-width: 0; } .fl-lista .q span { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
@endpush

<div class="kpis">
    <div class="panel kpi">
        <div class="k"><i class="bi bi-box-arrow-in-down"></i> Entró</div>
        <div class="v num">{{ $d0($fl['entradas']) }}</div>
        <div class="s">Cobros, ventas y aportaciones</div>
    </div>
    <div class="panel kpi">
        <div class="k"><i class="bi bi-box-arrow-up"></i> Salió</div>
        <div class="v num">{{ $d0($fl['salidas']) }}</div>
        <div class="s">Gastos, proveedores y retiros</div>
    </div>
    <div class="panel kpi {{ $fl['neto'] < 0 ? 'malo' : 'bueno' }}">
        <div class="k"><i class="bi bi-arrow-left-right"></i> {{ $fl['neto'] < 0 ? 'Salió más de lo que entró' : 'Te quedó' }}</div>
        <div class="v num">{{ $d0($fl['neto']) }}</div>
        <div class="s">{{ \App\Http\Controllers\Admin\FinanzasController::PERIODOS[$periodo] }}</div>
    </div>
    @if($fl['saldoHoy'] !== null)
        <a href="{{ $q(['seccion' => 'cuentas']) }}" class="panel kpi destacado text-decoration-none">
            <div class="k"><i class="bi bi-safe2"></i> Dinero disponible hoy</div>
            <div class="v num">{{ $d0($fl['saldoHoy']) }}</div>
            <div class="s">{{ $fl['sinCuenta'] ? $fl['sinCuenta'] . ' ' . ($fl['sinCuenta'] === 1 ? 'movimiento sin cuenta no se ha restado' : 'movimientos sin cuenta no se han restado') : 'En tus cuentas · ver detalle' }}</div>
        </a>
    @else
        <a href="{{ $q(['seccion' => 'cuentas']) }}" class="panel kpi text-decoration-none text-reset">
            <div class="k"><i class="bi bi-safe2"></i> Dinero disponible</div>
            <div class="v num">—</div>
            <div class="s">Da de alta tu caja y tu banco para verlo <i class="bi bi-arrow-right"></i></div>
        </a>
    @endif
</div>

{{-- Lo que viene --}}
<section class="panel" style="margin-bottom: 20px">
    <div class="panel-head"><div><h2>Próximas 8 semanas</h2><span class="ayuda">Lo que te deben pagar contra lo que debes pagar a proveedores, por su fecha</span></div></div>
    @if($pr['alerta'])
        <div class="panel-body pb-0">
            <div class="aviso-suave m-0"><i class="bi bi-exclamation-triangle"></i><div><b>Ojo:</b> {{ mb_strtolower($pr['alerta']['etiqueta']) === 'esta semana' ? 'esta semana' : 'la semana del ' . $pr['alerta']['rango'] }} tu dinero quedaría en <b>{{ $d0($pr['alerta']['saldo']) }}</b> si todo se cobra y se paga a tiempo. Adelanta cobros o mueve pagos.</div></div>
        </div>
    @endif
    <div class="table-responsive">
        <table class="tabla fl-tabla">
            <thead><tr><th>Semana</th><th class="text-end">Entra</th><th class="text-end">Sale</th><th class="text-end">Diferencia</th>@if($pr['conSaldo'])<th class="text-end">Dinero al final</th>@endif</tr></thead>
            <tbody>
            @foreach($pr['semanas'] as $s)
                <tr>
                    <td><span class="principal">{{ $s['etiqueta'] }}</span><div class="secundario">{{ $s['rango'] }}</div></td>
                    <td class="text-end num">{{ $s['entra'] > 0 ? $d0($s['entra']) : '—' }}@if($s['vencido'] > 0)<span class="venc">{{ $d0($s['vencido']) }} ya vencido</span>@endif</td>
                    <td class="text-end num">{{ $s['sale'] > 0 ? $d0($s['sale']) : '—' }}</td>
                    <td class="text-end num {{ $s['neto'] < 0 ? 'fl-neg' : ($s['neto'] > 0 ? 'fl-pos' : 'secundario') }}">{{ $s['neto'] == 0 ? '—' : ($s['neto'] > 0 ? '+' : '') . $d0($s['neto']) }}</td>
                    @if($pr['conSaldo'])<td class="text-end num fw-semibold {{ $s['saldo'] < 0 ? 'fl-neg' : '' }}">{{ $d0($s['saldo']) }}</td>@endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($pr['cobrarSin'] > 0 || $pr['cobrarDespues'] > 0 || $pr['pagarDespues'] > 0)
        <div class="panel-body pt-2 secundario small">
            @if($pr['cobrarSin'] > 0){{ $pr['nCobrarSin'] }} {{ $pr['nCobrarSin'] === 1 ? 'cobro pendiente no tiene' : 'cobros pendientes no tienen' }} fecha ({{ $d0($pr['cobrarSin']) }}); ponles fecha de vencimiento para que entren aquí. @endif
            @if($pr['cobrarDespues'] > 0 || $pr['pagarDespues'] > 0)Después de estas 8 semanas: {{ $d0($pr['cobrarDespues']) }} por cobrar y {{ $d0($pr['pagarDespues']) }} por pagar.@endif
        </div>
    @endif
</section>

<div class="rejilla" style="margin-top: 0; margin-bottom: 20px">
    <section class="panel">
        <div class="panel-head"><h2>Próximos cobros</h2></div>
        <div class="fl-lista">
            @forelse($pr['proximosCobros'] as $x)
                <a href="{{ $x->url }}"><span class="q"><span class="principal">{{ $x->quien }}</span><span class="secundario">{{ $x->concepto }} · {{ $f($x->fecha) }}{{ $x->fecha < now(config('vandu.zona_horaria'))->toDateString() ? ' · vencido' : '' }}</span></span><span class="num fw-semibold">{{ $d0($x->monto) }}</span></a>
            @empty
                <div class="fila secundario">Nada por cobrar con fecha en estas semanas.</div>
            @endforelse
        </div>
    </section>
    <section class="panel">
        <div class="panel-head"><h2>Próximos pagos a proveedores</h2></div>
        <div class="fl-lista">
            @forelse($pr['proximosPagos'] as $x)
                <a href="{{ $x->url }}"><span class="q"><span class="principal">{{ $x->quien }}</span><span class="secundario">{{ $x->concepto }} · {{ $f($x->fecha) }}</span></span><span class="num fw-semibold">{{ $d0($x->monto) }}</span></a>
            @empty
                <div class="fila secundario">Nada por pagar en estas semanas.</div>
            @endforelse
        </div>
    </section>
</div>

<div class="rejilla" style="margin-top: 0">
    <section class="panel">
        <div class="panel-head"><div><h2>De dónde entró</h2><span class="ayuda">{{ \App\Http\Controllers\Admin\FinanzasController::PERIODOS[$periodo] }}</span></div></div>
        @if($fl['porClaseE']->isEmpty())
            <div class="panel-body secundario">No entró dinero en este periodo.</div>
        @else
            <div class="panel-body hbar">
                @foreach($fl['porClaseE'] as $c)
                    <span class="et"><i class="bi {{ $c['icono'] }} me-1"></i>{{ $c['texto'] }}</span>
                    <span class="pista"><span style="width: {{ $c['monto'] / $maxE * 100 }}%; background: #00A862"></span></span>
                    <span class="val num">{{ $d0($c['monto']) }}<small>{{ $c['n'] }}</small></span>
                @endforeach
            </div>
        @endif
    </section>
    <section class="panel">
        <div class="panel-head"><div><h2>En qué salió</h2><span class="ayuda">{{ \App\Http\Controllers\Admin\FinanzasController::PERIODOS[$periodo] }}</span></div></div>
        @if($fl['porClaseS']->isEmpty())
            <div class="panel-body secundario">No salió dinero en este periodo.</div>
        @else
            <div class="panel-body hbar">
                @foreach($fl['porClaseS'] as $c)
                    <span class="et"><i class="bi {{ $c['icono'] }} me-1"></i>{{ $c['texto'] }}</span>
                    <span class="pista"><span style="width: {{ $c['monto'] / $maxS * 100 }}%; background: #B8650A"></span></span>
                    <span class="val num">{{ $d0($c['monto']) }}<small>{{ $c['n'] }}</small></span>
                @endforeach
            </div>
        @endif
    </section>
</div>

<section class="panel" style="margin-top: 20px">
    <div class="panel-head"><div><h2>Últimos 12 meses</h2><span class="ayuda">Lo que entró y salió de verdad cada mes</span></div>
        <div class="leyenda"><span><span class="punto" style="background:#00A862"></span> Entró</span><span><span class="punto" style="background:#B8650A"></span> Salió</span></div></div>
    <div class="fl-meses">
        @foreach($fl['meses'] as $m)
            <div class="fl-mes {{ $m['actual'] ? 'actual' : '' }}" title="{{ $m['completa'] }}: entró {{ $d0($m['entradas']) }}, salió {{ $d0($m['salidas']) }}">
                <span class="et">{{ $m['etiqueta'] }}</span>
                <span class="barras">
                    <span style="width: {{ $m['entradas'] / $maxMes * 100 }}%; background: #00A862"></span>
                    <span style="width: {{ $m['salidas'] / $maxMes * 100 }}%; background: #B8650A"></span>
                </span>
                <span class="neto num {{ $m['neto'] < 0 ? 'fl-neg' : ($m['neto'] > 0 ? 'fl-pos' : 'secundario') }}">{{ $m['neto'] == 0 && $m['entradas'] == 0 ? '—' : ($m['neto'] > 0 ? '+' : '') . $d0($m['neto']) }}</span>
            </div>
        @endforeach
    </div>
</section>
