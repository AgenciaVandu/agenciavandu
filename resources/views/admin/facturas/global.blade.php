@extends('admin.layout')
@section('titulo', 'Factura global')

@php
    $d = fn ($n) => '$' . number_format((float) $n, 2);
    $tz = config('vandu.zona_horaria');
    $total = $ventas->sum('total');
    $per = \App\Models\FacturaGlobal::PERIODICIDADES;
    $dias = \Illuminate\Support\Carbon::parse($desde)->diffInDays(\Illuminate\Support\Carbon::parse($hasta)) + 1;
    $sugerida = $dias <= 1 ? 'day' : ($dias <= 7 ? 'week' : ($dias <= 16 ? 'fortnight' : ($dias <= 31 ? 'month' : 'two_months')));
@endphp

@section('contenido')
<div class="page-head">
    <div><h1>Factura global</h1><p class="sub">Las ventas de mostrador ya cobradas que nadie pidió facturar, en una sola factura al público en general. El SAT pide hacerla a más tardar el mes siguiente.</p></div>
    <a href="{{ route('admin.facturas.crear') }}" class="btn btn-primario"><i class="bi bi-plus-lg me-1"></i> Nueva factura</a>
</div>
@include('admin.facturas._tabs')

<section class="panel mb-4">
    <form method="get" action="{{ route('admin.facturas.global') }}" class="panel-body d-flex flex-wrap gap-2 align-items-end">
        <div><label class="form-label small" for="g-desde">Desde</label><input id="g-desde" type="date" name="desde" value="{{ $desde }}" class="form-control"></div>
        <div><label class="form-label small" for="g-hasta">Hasta</label><input id="g-hasta" type="date" name="hasta" value="{{ $hasta }}" class="form-control"></div>
        <button class="btn btn-borde">Ver ventas</button>
    </form>
    <div class="panel-body border-top">
        <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center">
            <div><b class="num" style="font-size: 22px">{{ $d($total) }}</b> <span class="secundario">en {{ $ventas->count() }} {{ $ventas->count() === 1 ? 'venta' : 'ventas' }} del {{ \Illuminate\Support\Carbon::parse($desde)->locale('es')->isoFormat('D MMM') }} al {{ \Illuminate\Support\Carbon::parse($hasta)->locale('es')->isoFormat('D MMM YYYY') }}</span></div>
            @if($ventas->isNotEmpty() && $timbrar)
                <form method="post" action="{{ route('admin.facturas.global.emitir') }}" class="d-flex flex-wrap gap-2 align-items-end" onsubmit="return confirm('Se timbrará una factura global por {{ $d($total) }}. ¿Continuar?')">
                    @csrf
                    <input type="hidden" name="desde" value="{{ $desde }}"><input type="hidden" name="hasta" value="{{ $hasta }}">
                    <div><label class="form-label small" for="g-per">Periodicidad</label><select id="g-per" name="periodicidad" class="form-select">@foreach($per as $k => $t)<option value="{{ $k }}" @selected($k === $sugerida)>{{ $t }}</option>@endforeach</select></div>
                    @if(! $cp)<div><label class="form-label small" for="g-cp">C.P. de expedición</label><input id="g-cp" name="cp" maxlength="5" inputmode="numeric" class="form-control num" style="width: 100px" required></div>@endif
                    @if($series->count() > 1)<div><label class="form-label small" for="g-serie">Serie</label><select id="g-serie" name="serie_id" class="form-select">@foreach($series as $s)<option value="{{ $s->id }}">{{ $s->serie }}</option>@endforeach</select></div>@endif
                    <button class="btn btn-primario"><i class="bi bi-receipt me-1"></i> Timbrar factura global</button>
                </form>
            @elseif(! $timbrar)
                <a href="{{ route('admin.facturacion') }}" class="btn btn-borde">Activar la facturación</a>
            @endif
        </div>
    </div>
    @if($ventas->isNotEmpty())
        <div class="table-responsive" style="max-height: 360px; overflow: auto"><table class="tabla">
            <thead><tr><th>Venta</th><th>Fecha</th><th>Cliente</th><th class="text-end">IVA</th><th class="text-end">Total</th></tr></thead>
            <tbody>
            @foreach($ventas as $v)
                <tr><td class="num">{{ $v->folio }}</td><td class="secundario">{{ $v->fecha->locale('es')->isoFormat('D MMM') }}</td><td>{{ $v->quien }}</td><td class="text-end num">{{ $d($v->iva) }}</td><td class="text-end num fw-medium">{{ $d($v->total) }}</td></tr>
            @endforeach
            </tbody>
        </table></div>
    @else
        <div class="vacio"><div class="ico"><i class="bi bi-check2-circle"></i></div><h3>No hay ventas para la global</h3><p>Aquí aparecen las ventas cobradas de mostrador (sin RFC) que no tienen factura.</p></div>
    @endif
</section>

<section class="panel">
    <div class="panel-head"><h2>Globales timbradas</h2></div>
    @forelse($globales as $g)
        <div class="d-flex flex-wrap gap-2 align-items-center px-4 py-3 border-top" x-data="{ cancelar: false }">
            <div style="flex: 1 1 260px"><b>{{ $g->folio_texto }}</b> · {{ $per[$g->periodicidad] ?? $g->periodicidad }} · {{ $g->desde->locale('es')->isoFormat('D MMM') }} al {{ $g->hasta->locale('es')->isoFormat('D MMM YYYY') }}
                <div class="small secundario">{{ $g->ventas }} ventas · {{ $d($g->total) }}@if($g->cancelada_at) · <span style="color: var(--red)">cancelada</span>@elseif($g->cancelacion === 'pendiente') · cancelación pendiente @endif</div></div>
            @if($g->pdf)<a href="{{ route('admin.facturas.global.archivo', [$g, 'pdf']) }}" class="btn btn-fantasma btn-sm"><i class="bi bi-file-earmark-pdf"></i> PDF</a>@endif
            @if($g->xml)<a href="{{ route('admin.facturas.global.archivo', [$g, 'xml']) }}" class="btn btn-fantasma btn-sm"><i class="bi bi-filetype-xml"></i> XML</a>@endif
            @unless($g->cancelada_at)<button type="button" class="btn btn-fantasma btn-sm text-danger" @click="cancelar = ! cancelar">Cancelar</button>@endunless
            <form method="post" action="{{ route('admin.facturas.global.cancelar', $g) }}" class="w-100 d-flex gap-2" x-show="cancelar" x-cloak onsubmit="return confirm('¿Cancelar esta factura global ante el SAT? Sus ventas quedarán libres para otra global.')">
                @csrf<select name="motivo" class="form-select form-select-sm" style="max-width: 320px" aria-label="Motivo">@foreach(\App\Support\Facturas\Timbrado::MOTIVOS as $k => $t)<option value="{{ $k }}">{{ $k }} · {{ $t }}</option>@endforeach</select>
                <button class="btn btn-sm" style="background: var(--red); color: #fff">Cancelar ante el SAT</button></form>
        </div>
    @empty
        <div class="panel-body secundario">Todavía no has timbrado facturas globales.</div>
    @endforelse
</section>
@endsection
