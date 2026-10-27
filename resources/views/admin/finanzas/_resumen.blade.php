{{-- Resumen: cómo voy en el periodo y lo que pide atención. Cada aviso lleva a su detalle. --}}
@php
    $r = $cont['resultados'];
    $iv = $cont['iva'];
    $cp = $cont['comprobantes'];
    $pr = $flujo['proyeccion'];
    $sem = $pr['semanas'][0] ?? null;
    $gasto = round($r['costo'] + $r['gastos'], 2);
    $unMes = $desde && $hasta && substr($desde, 0, 7) === substr($hasta, 0, 7);
    $mesSig = $hasta ? \Illuminate\Support\Carbon::parse($hasta)->addMonthNoOverflow()->locale('es')->isoFormat('MMMM') : null;
    $avisos = [];
    if ($pr['alerta']) $avisos[] = ['rojo', 'bi-exclamation-triangle', 'Tu dinero quedaría en ' . $d($pr['alerta']['saldo']) . ' ' . (mb_strtolower($pr['alerta']['etiqueta']) === 'esta semana' ? 'esta semana' : 'la semana del ' . $pr['alerta']['rango']), 'si todo se cobra y se paga a tiempo', $q(['seccion' => 'dinero']) . '#lo-que-viene'];
    if ($kpi['porCobrar'] > 0) $avisos[] = [($sem['vencido'] ?? 0) > 0 ? 'rojo' : 'ambar', 'bi-hourglass-split', 'Te deben ' . $d($kpi['porCobrar']), $kpi['pendientes'] . ' ' . ($kpi['pendientes'] === 1 ? 'pago pendiente' : 'pagos pendientes') . (($sem['vencido'] ?? 0) > 0 ? ' · ' . $d($sem['vencido']) . ' ya vencido' : ''), $q(['seccion' => 'ventas']) . '#por-cobrar'];
    if (($sem['sale'] ?? 0) > 0) $avisos[] = ['ambar', 'bi-truck', 'Esta semana pagas ' . $d($sem['sale']) . ' a proveedores', 'Órdenes entregadas o facturadas', $q(['seccion' => 'dinero']) . '#lo-que-viene'];
    elseif (($prov['porPagar'] ?? 0) > 0) $avisos[] = ['gris', 'bi-truck', 'Debes ' . $d($prov['porPagar']) . ' a proveedores', $prov['nPorPagar'] . ' ' . ($prov['nPorPagar'] === 1 ? 'orden' : 'órdenes') . ' por pagar', $q(['seccion' => 'gastos']) . '#proveedores'];
    if ($cp && $cp['nSin'] > 0) $avisos[] = ['ambar', 'bi-receipt-cutoff', $d($cp['sin']) . ' en gastos sin factura', $cp['nSin'] . ' ' . ($cp['nSin'] === 1 ? 'gasto' : 'gastos') . ': pídelas para deducirlos', route('admin.gastos', ['sin_factura' => 1, 'periodo' => $periodo === 'mes' ? null : (in_array($periodo, array_keys(\App\Http\Controllers\Admin\FinanzasController::PERIODOS), true) ? $periodo : null)])];
    if ($unMes && abs($iv['saldo']) >= 1) $avisos[] = ['gris', 'bi-bank', 'IVA estimado ' . ($iv['saldo'] > 0 ? 'a pagar: ' : 'a favor: ') . $d(abs($iv['saldo'])), $iv['saldo'] > 0 && $mesSig ? 'Se paga a más tardar el 17 de ' . $mesSig : 'Sobre lo cobrado y lo pagado con factura', $q(['seccion' => 'utilidad'])];
    if ($r['sinCosto'] > 0) $avisos[] = ['gris', 'bi-calculator', $r['sinCosto'] . ' ' . ($r['sinCosto'] === 1 ? 'venta sin costo capturado' : 'ventas sin costo capturado'), 'Tu utilidad real es menor a la que ves', $q(['seccion' => 'utilidad'])];
    if ($kpi['abiertas'] > 0) $avisos[] = ['gris', 'bi-send', $kpi['abiertas'] . ' ' . ($kpi['abiertas'] === 1 ? 'cotización abierta' : 'cotizaciones abiertas') . ' por ' . $d($kpi['enJuego']), $kpi['negociacion'] ? $kpi['negociacion'] . ' en negociación' : 'Dales seguimiento', route('admin.presupuestos.index', ['filtro' => 'vigentes'])];
@endphp

@push('head')
<style>
    .rs-kpis { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 14px; margin-bottom: 22px; }
    .rs-kpi { padding: 16px 18px; text-decoration: none; color: inherit; display: block; }
    .rs-kpi .k { font-size: 13.5px; color: var(--muted); display: flex; gap: 7px; align-items: center; }
    .rs-kpi .v { font-size: 26px; font-weight: 600; letter-spacing: -.02em; margin-top: 8px; line-height: 1.1; }
    .rs-kpi .s { font-size: 12.5px; color: var(--muted); margin-top: 6px; }
    .rs-kpi.bueno .v { color: var(--green-ink); } .rs-kpi.malo .v { color: var(--red); }
    .rs-kpi.dinero { background: var(--ink); color: #fff; border-color: var(--ink); } .rs-kpi.dinero .k, .rs-kpi.dinero .s { color: #A9AEBA; }
    .rs-op { display: none; }
    .rs-avisos { display: grid; }
    .rs-aviso { display: flex; gap: 14px; align-items: center; padding: 14px 20px; border-top: 1px solid #F1F2F4; text-decoration: none; color: inherit; }
    .rs-aviso:first-child { border-top: 0; }
    .rs-aviso:hover { background: #F8F9FA; }
    .rs-aviso .ico { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; font-size: 17px; flex: none; background: var(--sunken); color: var(--text-2); }
    .rs-aviso.rojo .ico { background: var(--red-soft); color: var(--red); }
    .rs-aviso.ambar .ico { background: var(--amber-soft); color: var(--amber); }
    .rs-aviso .t { font-weight: 600; font-size: 15px; } .rs-aviso .d { font-size: 13px; color: var(--muted); }
    .rs-aviso .bi-chevron-right { margin-left: auto; color: var(--muted); }
    .rs-ir { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-top: 22px; }
    .rs-ir a { padding: 14px 16px; text-decoration: none; color: inherit; display: grid; gap: 2px; }
    .rs-ir a:hover { border-color: var(--line-strong); }
    .rs-ir b { font-size: 14.5px; } .rs-ir span { font-size: 12.5px; color: var(--muted); }
    @media (max-width: 1199.98px) { .rs-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .rs-kpi.dinero { grid-column: 1 / -1; } .rs-ir { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 575.98px) { .rs-kpi .v { font-size: 21px; } }
</style>
@endpush

<div class="rs-kpis">
    <a href="{{ $q(['seccion' => 'ventas']) }}" class="panel rs-kpi">
        <div class="k"><i class="bi bi-bag-check"></i> Vendiste</div>
        <div class="v num">{{ $d($r['ventas']) }}</div>
        <div class="s">antes de IVA</div>
    </a>
    <a href="{{ $q(['seccion' => 'ventas']) }}" class="panel rs-kpi">
        <div class="k"><i class="bi bi-box-arrow-in-down"></i> Cobraste</div>
        <div class="v num">{{ $d($iv['cobrado']) }}</div>
        <div class="s">dinero que entró, con IVA</div>
    </a>
    <a href="{{ $q(['seccion' => 'gastos']) }}" class="panel rs-kpi">
        <div class="k"><i class="bi bi-box-arrow-up"></i> Gastaste</div>
        <div class="v num">{{ $d($gasto) }}</div>
        <div class="s">costo de lo vendido y gastos, antes de IVA</div>
    </a>
    <a href="{{ $q(['seccion' => 'utilidad']) }}" class="panel rs-kpi {{ $r['utilidad'] < 0 ? 'malo' : ($r['utilidad'] > 0 ? 'bueno' : '') }}">
        <div class="k"><i class="bi bi-cash-stack"></i> {{ $r['utilidad'] < 0 ? 'Perdiste' : 'Te quedó' }}</div>
        <div class="v num">{{ $r['utilidad'] < 0 ? '−' : '' }}{{ $d(abs($r['utilidad'])) }}</div>
        <div class="s">{{ $r['margen'] !== null && $r['utilidad'] >= 0 ? $r['margen'] . '% de lo vendido' : 'vendiste menos de lo que gastaste' }}</div>
    </a>
    <a href="{{ $q(['seccion' => 'dinero']) }}" class="panel rs-kpi dinero">
        <div class="k"><i class="bi bi-safe2"></i> Dinero disponible</div>
        <div class="v num">{{ $flujo['saldoHoy'] !== null ? ($flujo['saldoHoy'] < 0 ? '−' : '') . $d(abs($flujo['saldoHoy'])) : '—' }}</div>
        <div class="s">{{ $flujo['saldoHoy'] !== null ? 'hoy, en caja y banco' : 'Da de alta tu caja y tu banco' }}</div>
    </a>
</div>

<section class="panel">
    <div class="panel-head"><div><h2>Lo que pide tu atención</h2><span class="ayuda">Toca cada uno para ver el detalle</span></div></div>
    <div class="rs-avisos">
        @forelse($avisos as [$color, $icono, $titulo, $detalle, $url])
            <a href="{{ $url }}" class="rs-aviso {{ $color }}"><span class="ico"><i class="bi {{ $icono }}"></i></span>
                <span><span class="t d-block">{{ $titulo }}</span><span class="d">{{ $detalle }}</span></span><i class="bi bi-chevron-right"></i></a>
        @empty
            <div class="rs-aviso"><span class="ico" style="background: var(--green-soft); color: var(--green-ink)"><i class="bi bi-check2"></i></span>
                <span><span class="t d-block">Todo en orden</span><span class="d">Nadie te debe, no hay pagos urgentes ni facturas pendientes.</span></span></div>
        @endforelse
    </div>
</section>

<div class="rs-ir">
    @foreach(array_slice(\App\Http\Controllers\Admin\FinanzasController::SECCIONES, 1, null, true) as $k => $s)
        <a href="{{ $q(['seccion' => $k]) }}" class="panel"><b><i class="bi {{ $s['icono'] }} me-1"></i> {{ $s['texto'] }}</b><span>{{ $s['pregunta'] }}</span></a>
    @endforeach
</div>
