@extends('admin.layout')
@section('titulo', 'Finanzas')

@php
    use App\Http\Controllers\Admin\FinanzasController as F;
    $d = fn ($n) => '$' . number_format($n ?? 0, 0);
    $corto = function ($n) {
        if ($n >= 1000000) return '$' . rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.') . ' M';
        if ($n >= 1000) return '$' . rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . ' k';
        return '$' . number_format($n, 0);
    };
    $q = fn (array $extra) => route('admin.finanzas', array_filter(array_merge(['seccion' => $seccion === 'resumen' ? null : $seccion, 'periodo' => $periodo === 'mes' ? null : $periodo,
        'desde' => $periodo === 'rango' ? $desde : null, 'hasta' => $periodo === 'rango' ? $hasta : null], $extra), fn ($v) => $v !== null));
    $base = $conIva ? 'con IVA' : 'antes de IVA';
    $f = fn ($s) => $s ? ucfirst(\Illuminate\Support\Carbon::parse($s)->locale('es')->isoFormat('D MMM YYYY')) : null;

    // Escala de la gráfica
    $max = max(1, $meses->max(fn ($m) => max($m['cotizado'], $m['ganado'], $m['cobrado'])));
    $paso = 10 ** floor(log10($max));
    $tope = ceil($max / $paso) * $paso;
    if ($tope / $paso <= 3) { $paso /= 2; $tope = ceil($max / $paso) * $paso; }
    $marcas = collect(range(0, 4))->map(fn ($i) => $tope * $i / 4)->reverse()->values();
    $colores = ['cotizado' => '#4A5BD4', 'ganado' => '#00A862', 'cobrado' => '#B8650A'];
    $nombres = ['cotizado' => 'Cotizado', 'ganado' => 'Ganado', 'cobrado' => 'Cobrado'];

    $variacion = null;
    if ($kpi['ganadoAnterior'] !== null) {
        $variacion = $kpi['ganadoAnterior'] > 0 ? round(($kpi['ganado'] - $kpi['ganadoAnterior']) / $kpi['ganadoAnterior'] * 100) : ($kpi['ganado'] > 0 ? 100 : 0);
    }
    $embudoMax = max(1, collect($embudo)->max('n'));
    $tipoMax = max(1, $porTipo->max('monto') ?? 1);
@endphp

@push('head')
<style>
    .controles { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 20px; }
    .controles .rango { color: var(--muted); font-size: 13.5px; margin-left: 4px; }
    .kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 14px; }
    .flujo { display: flex; flex-wrap: wrap; align-items: center; gap: 10px 14px; padding: 6px 20px 16px; }
    .flujo > div { display: grid; gap: 2px; } .flujo .k { font-size: 12.5px; color: var(--muted); } .flujo b { font-size: 20px; font-weight: 600; }
    .flujo .op { font-size: 20px; color: var(--muted); } .flujo .total { padding: 8px 14px; border-radius: 12px; background: #E3FBEF; } .flujo .total b { color: #047A4B; }
    .flujo .total.neg { background: #FDECEC; } .flujo .total.neg b { color: var(--red); }
    .kpi { padding: 16px 18px; }
    .kpi .k { font-size: 13.5px; color: var(--muted); display: flex; align-items: center; gap: 7px; }
    .kpi .v { font-size: 28px; font-weight: 600; letter-spacing: -.02em; line-height: 1.1; margin-top: 8px; }
    .kpi .s { font-size: 13px; color: var(--muted); margin-top: 6px; }
    .kpi.destacado { background: var(--ink); color: #fff; border-color: var(--ink); }
    .kpi.destacado .k, .kpi.destacado .s { color: #A9AEBA; }
    .delta { display: inline-flex; align-items: center; gap: 3px; font-weight: 600; }
    .delta.sube { color: var(--green); } .delta.baja { color: #FF8A80; }
    .kpi:not(.destacado) .delta.sube { color: var(--green-ink); } .kpi:not(.destacado) .delta.baja { color: var(--red); }
    .kpi.alerta .v { color: var(--amber); }
    .kpi.malo .v { color: var(--red); }

    .graf { position: relative; height: 260px; display: grid; grid-template-columns: 56px 1fr; }
    .graf .eje { position: relative; }
    .graf .eje span { position: absolute; right: 10px; transform: translateY(-50%); font-size: 12px; color: var(--muted); }
    .graf .area { position: relative; border-bottom: 1px solid var(--line-strong); }
    .graf .linea { position: absolute; left: 0; right: 0; border-top: 1px solid #EEF0F3; }
    .graf .cols { position: absolute; inset: 0; display: grid; grid-template-columns: repeat(12, 1fr); }
    .graf .col { position: relative; display: flex; align-items: flex-end; justify-content: center; gap: 2px; }
    .graf .col:hover, .graf .col:focus-visible { background: rgba(19,22,29,.035); outline: none; }
    .graf .barra { width: 9px; border-radius: 4px 4px 0 0; }
    .graf .tip { position: absolute; bottom: calc(100% + 8px); left: 50%; transform: translateX(-50%); z-index: 3; background: var(--ink); color: #fff; border-radius: 8px;
                 padding: 10px 12px; font-size: 13px; white-space: nowrap; box-shadow: 0 8px 24px rgba(16,24,40,.2); pointer-events: none; opacity: 0; transition: opacity .12s; }
    .graf .col:hover .tip, .graf .col:focus-visible .tip { opacity: 1; }
    .graf .col:nth-child(-n+2) .tip { left: 0; transform: none; }
    .graf .col:nth-last-child(-n+3) .tip { left: auto; right: 0; transform: none; }
    .graf .tip b { display: block; margin-bottom: 4px; }
    .graf .tip .fila { display: flex; justify-content: space-between; gap: 16px; }
    .graf .tip .fila span:first-child { display: inline-flex; gap: 6px; align-items: center; color: #C9CDD6; }
    .punto { width: 8px; height: 8px; border-radius: 2px; display: inline-block; }
    .meses { display: grid; grid-template-columns: 56px repeat(12, 1fr); margin-top: 8px; }
    .meses span { text-align: center; font-size: 12px; color: var(--muted); }
    .meses span.actual { color: var(--text); font-weight: 600; }
    .leyenda { display: flex; gap: 14px; font-size: 13px; color: var(--text-2); flex-wrap: wrap; }
    .leyenda span { display: inline-flex; align-items: center; gap: 6px; }

    .rejilla { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 20px; margin-top: 20px; align-items: start; }
    .hbar { display: grid; grid-template-columns: 170px minmax(0, 1fr) auto; gap: 10px 14px; align-items: center; }
    .hbar .et { font-size: 14px; color: var(--text-2); }
    .hbar .pista { height: 22px; background: #F1F2F4; border-radius: 6px; overflow: hidden; }
    .hbar .pista span { display: block; height: 100%; background: var(--ink); border-radius: 0 6px 6px 0; min-width: 2px; }
    .hbar .val { text-align: right; font-size: 14px; white-space: nowrap; }
    .hbar .val small { display: block; color: var(--muted); font-size: 12.5px; }
    .tasa-embudo { font-size: 12.5px; color: var(--muted); grid-column: 2 / 3; margin-top: -6px; }

    /* Submenú de Finanzas */
    .subnav { display: flex; gap: 4px; border-bottom: 1px solid var(--line); margin: -4px 0 20px; overflow-x: auto; scrollbar-width: none; }
    .subnav::-webkit-scrollbar { display: none; }
    .subnav a { display: inline-flex; align-items: center; gap: 7px; padding: 10px 14px; color: var(--muted); text-decoration: none; font-weight: 500; font-size: 14.5px;
                border-bottom: 2px solid transparent; margin-bottom: -1px; white-space: nowrap; }
    .subnav a:hover { color: var(--text); }
    .subnav a.activo { color: var(--text); border-bottom-color: var(--ink); }
    .subnav a.activo i { color: var(--green-ink); }
    .kpi.bueno .v { color: var(--green-ink); }
    .barra-rent { display: flex; height: 12px; border-radius: 6px; overflow: hidden; background: #F1F2F4; margin: 10px 0 8px; }
    .barra-rent span { display: block; height: 100%; }
    .leyenda-rent { display: flex; flex-wrap: wrap; gap: 6px 16px; font-size: 13px; color: var(--text-2); }
    .leyenda-rent span { display: inline-flex; gap: 6px; align-items: center; }
    .aviso-suave { background: var(--amber-soft); color: var(--amber); border-radius: 12px; padding: 12px 16px; font-size: 14px; margin-bottom: 14px; display: flex; gap: 10px; align-items: flex-start; }
    .aviso-suave a { color: inherit; font-weight: 600; }
    .margen-bajo { color: var(--red); } .margen-ok { color: var(--green-ink); }

    @media (max-width: 1199.98px) { .kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .rejilla { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 575.98px) { .kpi .v { font-size: 22px; } .hbar { grid-template-columns: 1fr auto; grid-auto-flow: row dense; gap: 4px 12px; } .hbar .pista { grid-column: 1 / -1; margin-bottom: 8px; }
        .subnav { flex-wrap: wrap; border-bottom: 0; gap: 6px; } .subnav a { border: 1px solid var(--line); border-radius: 99px; padding: 6px 12px; font-size: 13.5px; margin: 0; } .subnav a.activo { background: var(--ink); color: #fff; border-color: var(--ink); } .subnav a.activo i { color: var(--green); } .tasa-embudo { grid-column: 1 / -1; }
        .graf .barra { width: 5px; } .meses span { font-size: 10.5px; } }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <h1>Finanzas</h1>
        <p class="sub">{{ $seccion === 'resumen' ? 'Cómo va tu negocio y lo que pide atención.' : F::SECCIONES[$seccion]['pregunta'] . '.' }}</p>
    </div>
</div>

<div class="controles" x-data="{ elegir: {{ $periodo === 'rango' ? 'true' : 'false' }} }">
    <nav class="segmento" aria-label="Periodo">
        @foreach(F::PERIODOS_FINANZAS as $k => $label)
            <a href="{{ $q(['periodo' => $k === 'mes' ? null : $k, 'desde' => null, 'hasta' => null]) }}" class="{{ $periodo === $k ? 'activo' : '' }}">{{ $label }}</a>
        @endforeach
        <a href="#" @click.prevent="elegir = ! elegir" class="{{ $periodo === 'rango' || ! array_key_exists($periodo, F::PERIODOS_FINANZAS) ? 'activo' : '' }}"><i class="bi bi-calendar3 me-1"></i>Elegir fechas</a>
    </nav>
    <span class="rango num">{{ $desde ? $f($desde) . ' – ' . $f($hasta) : 'Todo lo registrado' }}</span>
    <div x-show="elegir" x-cloak class="w-100">
        <form method="get" action="{{ route('admin.finanzas', $seccion === 'resumen' ? null : $seccion) }}" class="d-flex flex-wrap gap-2 align-items-end">
            <input type="hidden" name="periodo" value="rango">
            <div><label class="form-label small mb-1" for="fz-desde">Desde</label><input type="date" id="fz-desde" name="desde" value="{{ $desde }}" class="form-control form-control-sm" required></div>
            <div><label class="form-label small mb-1" for="fz-hasta">Hasta</label><input type="date" id="fz-hasta" name="hasta" value="{{ $hasta }}" class="form-control form-control-sm" required></div>
            <button class="btn btn-primario btn-sm">Ver</button>
        </form>
    </div>
</div>

@include('admin.finanzas._submenu')

@if($seccion === 'resumen')
    @include('admin.finanzas._resumen')
@elseif($seccion === 'dinero')
    @include('admin.finanzas._cuentas')
@elseif($seccion === 'ventas')
    @include('admin.finanzas._ventas')
@elseif($seccion === 'gastos')
    @include('admin.finanzas._gastos')
@elseif($seccion === 'utilidad')
    @include('admin.finanzas._utilidad')
@endif
@endsection
