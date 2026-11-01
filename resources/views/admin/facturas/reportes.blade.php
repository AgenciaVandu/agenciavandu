@extends('admin.layout')
@section('titulo', 'Reportes de facturación')

@php
    $m = fn ($n) => '$' . number_format((float) $n, 2);
    $tz = config('vandu.zona_horaria');
    $tipos = \App\Support\Facturas\Reporte::TIPOS;
@endphp

@push('head')
<style>
    .rf { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 20px; }
    .rf > * { padding: 16px 20px; } .rf > * + * { border-left: 1px solid var(--line); }
    .rf .k { font-size: 13px; color: var(--muted); } .rf .v { font-size: 24px; font-weight: 700; } .rf .s { font-size: 12.5px; color: var(--muted); }
    @media (max-width: 767.98px) { .rf { grid-template-columns: repeat(2, minmax(0, 1fr)); } .rf > *:nth-child(3) { border-left: 0; } .rf > *:nth-child(n+3) { border-top: 1px solid var(--line); } }
    .rf-b { font-size: 12px; font-weight: 600; padding: 2px 8px; border-radius: 99px; background: var(--sunken); color: var(--muted); }
    .rf-b.mal { background: var(--red-soft); color: var(--red); }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div><h1>Reportes de facturación</h1><p class="sub">Todo lo que facturaste en el mes: facturas, notas de crédito, complementos y globales. Para tu contador, en Excel y con los XML.</p></div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.facturas.reportes.excel', ['mes' => $mes]) }}" class="btn btn-borde"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Excel</a>
        <a href="{{ route('admin.facturas.reportes.zip', ['mes' => $mes]) }}" class="btn btn-borde"><i class="bi bi-file-zip me-1"></i> XML del mes</a>
    </div>
</div>
@include('admin.facturas._tabs')

<form method="get" class="mb-3 d-flex gap-2 align-items-center">
    <label class="small secundario" for="rf-mes">Mes</label>
    <select id="rf-mes" name="mes" class="form-select" style="max-width: 220px" onchange="this.form.submit()">@foreach($meses as $k => $t)<option value="{{ $k }}" @selected($k === $mes)>{{ $t }}</option>@endforeach</select>
</form>

<div class="panel rf">
    <div><div class="k">Facturado</div><div class="v num">{{ $m($d['facturado']) }}</div><div class="s">{{ $d['n_facturas'] }} facturas{{ $d['globales'] > 0 ? ' (incluye ' . $m($d['globales']) . ' en globales)' : '' }}</div></div>
    <div><div class="k">Notas de crédito</div><div class="v num">{{ $m($d['notas']) }}</div><div class="s">Neto {{ $m($d['neto']) }}</div></div>
    <div><div class="k">IVA trasladado</div><div class="v num">{{ $m($d['iva']) }}</div><div class="s">Estimado, ya restando notas</div></div>
    <div><div class="k">Complementos</div><div class="v num">{{ $d['complementos'] }}</div><div class="s">{{ $m($d['cobrado_ppd']) }} cobrados · {{ $d['canceladas'] }} canceladas</div></div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <section class="panel">
            <div class="panel-head"><h2>Comprobantes</h2><span class="ayuda">{{ $d['filas']->count() }}</span></div>
            @if($d['filas']->isEmpty())
                <div class="vacio"><div class="ico"><i class="bi bi-receipt"></i></div><h3>Sin comprobantes este mes</h3><p>Aquí aparecen las facturas con folio fiscal, timbradas en Nerby o subidas.</p></div>
            @else
                <div class="table-responsive"><table class="tabla">
                    <thead><tr><th>Fecha</th><th>Tipo</th><th>Folio</th><th>Cliente</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                    @foreach($d['filas'] as $x)
                        <tr>
                            <td class="secundario text-nowrap">{{ $x['fecha']->setTimezone($tz)->locale('es')->isoFormat('D MMM') }}</td>
                            <td>{{ $tipos[$x['tipo']] }}@if($x['metodo']) <span class="rf-b">{{ $x['metodo'] }}</span>@endif @if($x['cancelada'])<span class="rf-b mal">Cancelada</span>@endif</td>
                            <td class="num">@if($x['venta'])<a href="{{ route('admin.facturas.venta', $x['venta']) }}">{{ $x['folio'] }}</a>@else{{ $x['folio'] }}@endif</td>
                            <td>{{ $x['cliente'] }}<div class="secundario small num">{{ $x['rfc'] }}</div></td>
                            <td class="text-end num {{ $x['cancelada'] ? 'secundario text-decoration-line-through' : 'fw-medium' }}">{{ $x['tipo'] === 'egreso' ? '−' : '' }}{{ $m($x['total']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </section>
    </div>
    <div class="col-lg-4 d-grid gap-4 align-content-start">
        <section class="panel">
            <div class="panel-head"><h2>Por cobrar (PPD)</h2><span class="ayuda">{{ $m($d['por_cobrar']->sum('saldo')) }}</span></div>
            @forelse($d['por_cobrar'] as $v)
                <a href="{{ route('admin.facturas.venta', $v) }}" class="d-flex justify-content-between px-4 py-2 border-top text-decoration-none" style="color: inherit">
                    <span>{{ $v->quien }}<span class="d-block secundario small">{{ $v->fecha->locale('es')->isoFormat('D MMM YYYY') }}{{ $v->vencida ? ' · vencida' : '' }}</span></span><b class="num">{{ $m($v->saldo) }}</b></a>
            @empty
                <div class="panel-body secundario small">Nada pendiente.</div>
            @endforelse
        </section>
        <section class="panel">
            <div class="panel-head"><h2>Por cliente</h2></div>
            @forelse($d['por_cliente']->take(12) as $c)
                <div class="d-flex justify-content-between px-4 py-2 border-top"><span>{{ $c['cliente'] }}<span class="d-block secundario small">{{ $c['n'] }} {{ $c['n'] === 1 ? 'factura' : 'facturas' }}</span></span><b class="num">{{ $m($c['total']) }}</b></div>
            @empty
                <div class="panel-body secundario small">Sin facturas este mes.</div>
            @endforelse
        </section>
    </div>
</div>
@endsection
