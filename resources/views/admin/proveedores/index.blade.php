@extends('admin.layout')
@section('titulo', 'Proveedores')

@section('contenido')
<div class="page-head">
    <div>
        <h1>Proveedores</h1>
        <p class="sub">Quienes imprimen, bordan, fabrican o fotografían para tus proyectos.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.ordenes.index') }}" class="btn btn-borde"><i class="bi bi-receipt me-1"></i> Órdenes de compra</a>
        <a href="{{ route('admin.proveedores.create') }}" class="btn btn-primario"><i class="bi bi-plus-lg me-1"></i> Nuevo proveedor</a>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <nav class="segmento" aria-label="Filtrar por rubro">
        <a href="{{ route('admin.proveedores.index', ['q' => $q ?: null]) }}" class="{{ $rubro ? '' : 'activo' }}">Todos</a>
        @foreach($rubros as $r => $n)
            <a href="{{ route('admin.proveedores.index', ['rubro' => $r, 'q' => $q ?: null]) }}" class="{{ $rubro === $r ? 'activo' : '' }}">{{ $r }} <span class="n num">{{ $n }}</span></a>
        @endforeach
    </nav>
    <form class="buscador" method="get" role="search">
        @if($rubro)<input type="hidden" name="rubro" value="{{ $rubro }}">@endif
        <i class="bi bi-search"></i>
        <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Empresa, contacto, rubro o teléfono" aria-label="Buscar proveedores">
    </form>
</div>

<div class="panel">
    @if($proveedores->isEmpty())
        <div class="vacio">
            <div class="ico"><i class="bi {{ $q ? 'bi-search' : 'bi-truck' }}"></i></div>
            @if($q || $rubro)
                <h3>Ningún proveedor coincide</h3>
                <p>Prueba con otro nombre o quita el filtro.</p>
                <a href="{{ route('admin.proveedores.index') }}" class="btn btn-borde">Ver todos</a>
            @else
                <h3>Todavía no tienes proveedores</h3>
                <p>Agrega a tu imprenta, bordador o fotógrafo para mandarles órdenes de compra y recibir sus facturas en un solo lugar.</p>
                <a href="{{ route('admin.proveedores.create') }}" class="btn btn-primario">Agregar proveedor</a>
            @endif
        </div>
    @else
        <div class="table-responsive">
            <table class="tabla">
                <thead>
                    <tr><th>Proveedor</th><th>Contacto</th><th class="text-center">Órdenes</th><th>Última orden</th><th class="text-end"><span class="visually-hidden">Acciones</span></th></tr>
                </thead>
                <tbody>
                @foreach($proveedores as $p)
                    <tr>
                        <td style="min-width: 240px">
                            <a href="{{ route('admin.proveedores.show', $p) }}" class="fila-link persona">
                                @include('admin._avatar', ['nombre' => $p->empresa])
                                <div>
                                    <div class="principal text-truncate">{{ $p->empresa }}</div>
                                    <div class="secundario text-truncate">{{ $p->rubro ?: 'Sin rubro' }}</div>
                                </div>
                            </a>
                        </td>
                        <td>
                            @if($p->nombre)<div class="text-truncate" style="max-width: 220px">{{ $p->nombre }}</div>@endif
                            @if($p->telefono)<div class="secundario num">{{ $p->telefono }}</div>@elseif($p->email)<div class="secundario text-truncate" style="max-width: 220px">{{ $p->email }}</div>@endif
                            @if(! $p->nombre && ! $p->telefono && ! $p->email)<span class="secundario">Sin datos de contacto</span>@endif
                        </td>
                        <td class="text-center num">
                            <span class="fw-medium">{{ $p->ordenes_count }}</span>
                            @if($p->abiertas_count)<div class="secundario">{{ $p->abiertas_count }} {{ $p->abiertas_count === 1 ? 'abierta' : 'abiertas' }}</div>@endif
                        </td>
                        <td class="secundario text-nowrap">
                            {{ $p->ordenes_max_fecha ? \Illuminate\Support\Carbon::parse($p->ordenes_max_fecha)->locale('es')->isoFormat('D MMM YYYY') : '—' }}
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.ordenes.create', ['proveedor' => $p->id]) }}" class="btn btn-borde btn-sm"><i class="bi bi-plus-lg"></i> Orden</a>
                            <a href="{{ route('admin.proveedores.edit', $p) }}" class="btn btn-fantasma btn-icono" title="Editar" aria-label="Editar {{ $p->empresa }}"><i class="bi bi-pencil"></i></a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@if($proveedores->hasPages())
    <div class="mt-3">{{ $proveedores->links('pagination::bootstrap-5') }}</div>
@endif
@endsection
