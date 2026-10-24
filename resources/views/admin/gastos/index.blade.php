@extends('admin.layout')
@section('titulo', 'Gastos')

@php
    $d = fn ($n) => '$' . number_format((float) $n, 0);
    $d2 = fn ($n) => '$' . number_format((float) $n, 2);
    $u = fn (array $extra = []) => route('admin.gastos', array_filter(array_merge(['periodo' => $periodo === 'mes' ? null : $periodo, 'categoria' => $categoria, 'q' => $q ?: null, 'sin_factura' => $sinFactura ? 1 : null], $extra), fn ($v) => $v !== null));
    $maxCat = max(1, $rep['porCategoria']->max('monto') ?? 0);
    $maxMes = max(1, $rep['meses']->max('monto'));
@endphp

@push('head')
<style>
    .gx-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 20px; }
    .gx-kpis > * { padding: 16px 20px; } .gx-kpis > * + * { border-left: 1px solid var(--line); }
    .gx-kpis .k { font-size: 13px; color: var(--muted); display: flex; gap: 6px; align-items: center; }
    .gx-kpis .v { font-size: 26px; font-weight: 700; letter-spacing: -.01em; } .gx-kpis .s { font-size: 12.5px; color: var(--muted); }
    @media (max-width: 767.98px) { .gx-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .gx-kpis > *:nth-child(3) { border-left: 0; } .gx-kpis > *:nth-child(n+3) { border-top: 1px solid var(--line); } }
    .gx-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 20px; margin-bottom: 20px; }
    @media (max-width: 991.98px) { .gx-grid { grid-template-columns: minmax(0, 1fr); } }
    .gx-cat { display: grid; grid-template-columns: 30px minmax(0, 1fr) auto; gap: 4px 12px; align-items: center; padding: 9px 20px; color: inherit; text-decoration: none; }
    .gx-cat:hover { background: var(--sunken); } .gx-cat.on { background: var(--marca-soft); }
    .gx-cat i { color: var(--marca); font-size: 17px; grid-row: span 2; }
    .gx-cat .barra { grid-column: 2 / 4; height: 5px; border-radius: 99px; background: var(--sunken); overflow: hidden; }
    .gx-cat .barra span { display: block; height: 100%; background: var(--marca); border-radius: 99px; }
    .gx-meses { display: flex; align-items: flex-end; gap: 8px; height: 150px; padding: 16px 20px 10px; }
    .gx-meses > div { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; height: 100%; justify-content: flex-end; min-width: 0; }
    .gx-meses .b { width: 70%; max-width: 22px; background: var(--marca); opacity: .75; border-radius: 4px 4px 1px 1px; min-height: 2px; }
    .gx-meses .actual .b { opacity: 1; } .gx-meses .e { font-size: 11.5px; color: var(--muted); } .gx-meses .actual .e { color: var(--ink); font-weight: 600; }
    .gx-lista > * + * { border-top: 1px solid var(--line); }
    .gx-fila { display: grid; grid-template-columns: 40px minmax(0, 1fr) auto; gap: 12px; align-items: center; padding: 12px 20px; color: inherit; text-decoration: none; }
    .gx-fila:hover { background: var(--sunken); }
    .gx-fila .ico { width: 40px; height: 40px; border-radius: 11px; display: grid; place-items: center; background: var(--marca-soft); color: var(--marca); font-size: 17px; }
    .gx-fila .t { font-weight: 600; } .gx-fila .s { font-size: 13px; color: var(--muted); }
    .gx-fila .m { text-align: right; font-weight: 600; white-space: nowrap; }
    .gx-comp { font-size: 11.5px; font-weight: 600; padding: 1px 8px; border-radius: 99px; background: var(--sunken); color: var(--muted); }
    .gx-comp.xml { background: #E3FBEF; color: #047A4B; } .gx-comp.foto { background: var(--marca-soft); color: var(--marca); }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <h1>{{ $todos ? 'Gastos' : 'Mis gastos' }}</h1>
        <p class="sub">{{ $todos ? 'Lo que el equipo pagó fuera de las órdenes de compra. Se resta en Finanzas.' : 'Los gastos que has registrado. Toma foto del ticket en cuanto pagues.' }} Montos {{ $conIva ? 'con IVA' : 'antes de IVA' }}.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <nav class="segmento" aria-label="Periodo">
            @foreach(\App\Http\Controllers\Admin\FinanzasController::PERIODOS as $k => $t)<a href="{{ $u(['periodo' => $k === 'mes' ? null : $k]) }}" class="{{ $periodo === $k ? 'activo' : '' }}">{{ $t }}</a>@endforeach
        </nav>
        @if($todos)<a href="{{ route('admin.gastos.exportar', ['periodo' => $periodo]) }}" class="btn btn-borde" data-recargar><i class="bi bi-download me-1"></i> Excel</a>@endif
        <a href="{{ route('admin.gastos.create') }}" class="btn btn-primario"><i class="bi bi-{{ $ia ? 'camera' : 'plus-lg' }} me-1"></i> Nuevo gasto</a>
    </div>
</div>

<div class="panel gx-kpis">
    <div><div class="k"><i class="bi bi-receipt"></i> {{ $todos ? 'Gastado' : 'Registraste' }}</div><div class="v num">{{ $d($rep['total']) }}</div><div class="s">{{ $rep['n'] }} {{ $rep['n'] === 1 ? 'gasto' : 'gastos' }} · {{ \App\Http\Controllers\Admin\FinanzasController::PERIODOS[$periodo] }}</div></div>
    <div><div class="k"><i class="bi bi-filetype-xml"></i> Con factura</div><div class="v num">{{ $rep['conFactura'] }}</div><div class="s">IVA acreditable {{ $d($rep['iva']) }}</div></div>
    <div><div class="k"><i class="bi bi-camera"></i> Con foto del ticket</div><div class="v num">{{ $rep['conFoto'] }}</div><div class="s">{{ $ia ? 'leídos con IA' : 'como comprobante' }}</div></div>
    <div><div class="k"><i class="bi bi-exclamation-circle"></i> Sin comprobante</div><div class="v num" style="{{ $rep['sinComprobante'] ? 'color: var(--amber)' : '' }}">{{ $rep['sinComprobante'] }}</div><div class="s">capturados a mano</div></div>
</div>

@if($rep['n'] > 0)
<div class="gx-grid">
    <section class="panel">
        <div class="panel-head"><div><h2>Por categoría</h2><span class="ayuda">Toca una para ver solo esos gastos</span></div></div>
        <div class="pb-2">
            @foreach($rep['porCategoria'] as $c)
                <a href="{{ $u(['categoria' => $categoria === $c['clave'] ? null : $c['clave'], 'page' => null]) }}" class="gx-cat {{ $categoria === $c['clave'] ? 'on' : '' }}">
                    <i class="bi {{ $c['icono'] }}"></i>
                    <span class="text-truncate">{{ $c['texto'] }} <span class="secundario small">· {{ $c['n'] }}</span></span>
                    <b class="num">{{ $d($c['monto']) }}</b>
                    <span class="barra"><span style="width: {{ round($c['monto'] / $maxCat * 100, 1) }}%"></span></span>
                </a>
            @endforeach
        </div>
    </section>
    <section class="panel">
        <div class="panel-head"><div><h2>Últimos 12 meses</h2><span class="ayuda">{{ $todos && $rep['porPersona']->count() > 1 ? 'Quién registró más: ' . $rep['porPersona']->first()['nombre'] . ' (' . $d($rep['porPersona']->first()['monto']) . ')' : 'Gasto por mes' }}</span></div></div>
        <div class="gx-meses" role="img" aria-label="Gasto por mes en los últimos 12 meses">
            @foreach($rep['meses'] as $m)
                <div class="{{ $m['actual'] ? 'actual' : '' }}" title="{{ $m['etiqueta'] }}: {{ $d($m['monto']) }}"><span class="b" style="height: {{ round($m['monto'] / $maxMes * 100, 1) }}%"></span><span class="e">{{ $m['etiqueta'] }}</span></div>
            @endforeach
        </div>
    </section>
</div>
@endif

<section class="panel">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 pt-3 pb-2">
        <h2 class="fs-6 fw-semibold m-0">{{ $categoria ? \App\Models\Gasto::CATEGORIAS[$categoria]['texto'] : 'Todos los gastos' }}{{ $sinFactura ? ' sin factura' : '' }} @if($categoria || $sinFactura)<a href="{{ $u(['categoria' => null, 'sin_factura' => null, 'page' => null]) }}" class="small fw-normal ms-1">Quitar filtro</a>@endif</h2>
        <form class="buscador" method="get" role="search">
            @if($periodo !== 'mes')<input type="hidden" name="periodo" value="{{ $periodo }}">@endif
            @if($categoria)<input type="hidden" name="categoria" value="{{ $categoria }}">@endif
            @if($sinFactura)<input type="hidden" name="sin_factura" value="1">@endif
            <i class="bi bi-search"></i><input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Concepto o proveedor" aria-label="Buscar gastos">
        </form>
    </div>
    @if($gastos->isEmpty())
        <div class="vacio"><div class="ico"><i class="bi bi-receipt"></i></div><h3>{{ $q || $categoria || $sinFactura ? 'Nada con ese filtro' : 'Sin gastos en este periodo' }}</h3>
            <p>{{ $ia ? 'Toma foto del ticket y llenamos los datos por ti, o sube el XML de la factura.' : 'Registra tus tickets y facturas para ver en qué se va el dinero.' }}</p>
            <a href="{{ route('admin.gastos.create') }}" class="btn btn-primario mt-2"><i class="bi bi-plus-lg me-1"></i> Nuevo gasto</a></div>
    @else
        <div class="gx-lista">
            @foreach($gastos as $g)
                <a href="{{ route('admin.gastos.edit', $g) }}" class="gx-fila">
                    <span class="ico"><i class="bi {{ $g->categoria_icono }}"></i></span>
                    <span class="min-w-0">
                        <span class="t d-block text-truncate">{{ $g->concepto }}</span>
                        <span class="s d-block text-truncate">{{ $g->fecha->locale('es')->isoFormat('D MMM YYYY') }}@if($g->proveedor) · {{ $g->proveedor }}@endif @if($g->proyecto) · {{ $g->proyecto->nombre }}@endif @if($todos && $g->usuario) · {{ $g->usuario->name }}@endif</span>
                    </span>
                    <span class="m">
                        <span class="num d-block">{{ $d2($g->monto($conIva)) }}</span>
                        <span class="gx-comp {{ $g->xml ? 'xml' : ($g->foto ? 'foto' : '') }}">{{ $g->xml ? 'Factura' : ($g->foto ? 'Ticket' : 'Sin comprobante') }}</span>
                    </span>
                </a>
            @endforeach
        </div>
        @if($gastos->hasPages())<div class="px-3 py-2">{{ $gastos->links() }}</div>@endif
    @endif
</section>
@endsection
