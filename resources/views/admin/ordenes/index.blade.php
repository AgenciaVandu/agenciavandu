@extends('admin.layout')
@section('titulo', 'Órdenes de compra')

@php
    $filtros = ['abiertas' => 'Abiertas', 'borrador' => 'Borradores', 'en_proceso' => 'En proceso', 'facturada' => 'Por pagar', 'pagada' => 'Pagadas', 'todas' => 'Todas'];
    $n = fn ($k) => match ($k) {
        'abiertas' => $cuentas->except(['pagada', 'cancelada'])->sum(),
        'todas'    => $cuentas->sum(),
        default    => $cuentas[$k] ?? 0,
    };
@endphp

@section('contenido')
<div class="migas"><a href="{{ route('admin.proveedores.index') }}">Proveedores</a> <i class="bi bi-chevron-right small"></i> <span>Órdenes de compra</span></div>
<div class="page-head">
    <div>
        <h1>Órdenes de compra</h1>
        <p class="sub">Lo que le pides a cada proveedor, sus facturas y lo que falta pagar.</p>
    </div>
    <a href="{{ route('admin.ordenes.create') }}" class="btn btn-primario"><i class="bi bi-plus-lg me-1"></i> Nueva orden</a>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <nav class="segmento" aria-label="Filtrar órdenes">
        @foreach($filtros as $k => $t)
            <a href="{{ route('admin.ordenes.index', ['ver' => $k, 'q' => $q ?: null]) }}" class="{{ $ver === $k ? 'activo' : '' }}">{{ $t }} @if($n($k))<span class="n num">{{ $n($k) }}</span>@endif</a>
        @endforeach
    </nav>
    <form class="buscador" method="get" role="search">
        <input type="hidden" name="ver" value="{{ $ver }}">
        <i class="bi bi-search"></i>
        <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Folio, pedido, proveedor o rubro" aria-label="Buscar órdenes">
    </form>
</div>

<div class="panel">
    @if($ordenes->isEmpty())
        <div class="vacio">
            <div class="ico"><i class="bi {{ $q ? 'bi-search' : 'bi-receipt' }}"></i></div>
            @if($q)
                <h3>Ninguna orden coincide con “{{ $q }}”</h3>
                <a href="{{ route('admin.ordenes.index', ['ver' => $ver]) }}" class="btn btn-borde">Limpiar búsqueda</a>
            @else
                <h3>No hay órdenes aquí</h3>
                <p>Crea una orden para pedirle algo a un proveedor y recibir su factura en un solo lugar.</p>
                <a href="{{ route('admin.ordenes.create') }}" class="btn btn-primario">Nueva orden de compra</a>
            @endif
        </div>
    @else
        <div class="table-responsive">
            <table class="tabla">
                <thead><tr><th>Folio</th><th>Proveedor</th><th>Pedido</th><th>Entrega</th><th class="text-end">Total</th><th>Estado</th></tr></thead>
                <tbody>
                @foreach($ordenes as $o)
                    <tr>
                        <td class="text-nowrap"><a href="{{ route('admin.ordenes.show', $o) }}" class="fila-link"><span class="principal num">{{ $o->folio }}</span></a>
                            <div class="secundario">{{ $o->fecha?->locale('es')->isoFormat('D MMM YYYY') }}</div></td>
                        <td style="min-width: 170px"><a href="{{ route('admin.proveedores.show', $o->proveedor) }}" class="text-reset text-decoration-none"><div class="text-truncate" style="max-width: 220px">{{ $o->proveedor->empresa }}</div></a>
                            <div class="secundario">{{ $o->proveedor->rubro }}</div></td>
                        <td style="min-width: 200px"><div class="text-truncate" style="max-width: 280px">{{ $o->titulo }}</div>
                            <div class="secundario text-truncate" style="max-width: 280px">{{ $o->proyecto ? $o->proyecto->nombre . ' · ' . ($o->proyecto->cliente?->empresa ?: $o->proyecto->cliente?->nombre) : $o->resumen }}</div></td>
                        <td class="text-nowrap {{ $o->retrasada ? 'text-danger' : 'secundario' }}">{{ $o->fecha_entrega ? \Illuminate\Support\Str::after($o->entrega_texto, 'Entrega ') : '—' }}</td>
                        <td class="text-end num fw-medium text-nowrap">{{ \App\Models\OrdenCompra::dinero($o->total) }}</td>
                        <td class="text-nowrap"><span class="estado estado-{{ $o->estado }}">{{ $o->estado_texto }}</span>
                            @if($o->archivos->where('tipo', 'factura')->count())<i class="bi bi-receipt ms-1" style="color: var(--green-ink)" title="Con factura"></i>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@if($ordenes->hasPages())
    <div class="mt-3">{{ $ordenes->links('pagination::bootstrap-5') }}</div>
@endif
@endsection
