@extends('admin.layout')
@section('titulo', 'Clientes')

@section('contenido')
<div class="page-head">
    <div>
        <h1>Clientes</h1>
        <p class="sub">Tus contactos y sus datos fiscales, listos para cotizar.</p>
    </div>
    <a href="{{ route('admin.clientes.create') }}" class="btn btn-primario"><i class="bi bi-person-plus me-1"></i> Nuevo cliente</a>
</div>

@push('head')
<style>
    .etq-nuevo { display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; font-weight: 600; padding: 1px 8px; border-radius: 99px; background: var(--green-soft); color: var(--green-ink); vertical-align: 2px; margin-left: 6px; }
    .etq-nuevo::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--green); }
    tr.es-nuevo td:first-child { box-shadow: inset 3px 0 0 var(--green); }
</style>
@endpush

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <nav class="segmento" aria-label="Filtrar clientes">
        <a href="{{ route('admin.clientes.index', ['q' => $q ?: null]) }}" class="{{ $soloNuevos ? '' : 'activo' }}">Todos</a>
        <a href="{{ route('admin.clientes.index', ['ver' => 'nuevos', 'q' => $q ?: null]) }}" class="{{ $soloNuevos ? 'activo' : '' }}">Nuevos <span class="n num">{{ $nuevos ?: '' }}</span></a>
    </nav>
    <form class="buscador" method="get" role="search">
        @if($soloNuevos)<input type="hidden" name="ver" value="nuevos">@endif
        <i class="bi bi-search"></i>
        <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Nombre, empresa, correo o teléfono" aria-label="Buscar clientes">
    </form>
</div>

<div class="panel">
    @if($clientes->isEmpty())
        <div class="vacio">
            <div class="ico"><i class="bi {{ $q ? 'bi-search' : ($soloNuevos ? 'bi-inbox' : 'bi-people') }}"></i></div>
            @if($soloNuevos && ! $q)
                <h3>No hay contactos nuevos</h3>
                <p>Cuando alguien llene el formulario de agenciavandu.com aparecerá aquí.</p>
                <a href="{{ route('admin.clientes.index') }}" class="btn btn-borde">Ver todos</a>
            @elseif($q)
                <h3>Ningún cliente coincide con “{{ $q }}”</h3>
                <p>Revisa la ortografía o busca por correo o teléfono.</p>
                <a href="{{ route('admin.clientes.index') }}" class="btn btn-borde">Limpiar búsqueda</a>
            @else
                <h3>Todavía no tienes clientes</h3>
                <p>Agrega el primero para empezar a cotizar.</p>
                <a href="{{ route('admin.clientes.create') }}" class="btn btn-primario">Agregar cliente</a>
            @endif
        </div>
    @else
        <div class="table-responsive">
            <table class="tabla">
                <thead>
                    <tr><th>Cliente</th><th>Contacto</th><th class="text-center">Cotizaciones</th><th>Última cotización</th><th class="text-end"><span class="visually-hidden">Acciones</span></th></tr>
                </thead>
                <tbody>
                @foreach($clientes as $c)
                    <tr class="{{ $c->nuevo ? 'es-nuevo' : '' }}">
                        <td style="min-width: 240px">
                            <a href="{{ route('admin.clientes.show', $c) }}" class="fila-link persona">
                                @include('admin._avatar', ['nombre' => $c->empresa ?: $c->nombre])
                                <div>
                                    <div class="principal text-truncate">{{ $c->empresa ?: $c->nombre }}@if($c->nuevo)<span class="etq-nuevo">Nuevo</span>@endif</div>
                                    @if($c->nuevo)
                                        <div class="secundario text-truncate">{{ $c->interes ?: 'Desde el sitio' }} · {{ ($c->contacto_at ?? $c->created_at)->locale('es')->diffForHumans() }}</div>
                                    @else
                                        <div class="secundario text-truncate">{{ $c->empresa ? $c->nombre : 'Persona física' }}</div>
                                    @endif
                                </div>
                            </a>
                        </td>
                        <td>
                            @if($c->email)<div class="text-truncate" style="max-width: 240px">{{ $c->email }}</div>@endif
                            @if($c->telefono)<div class="secundario num">{{ $c->telefono }}</div>@endif
                            @if(! $c->email && ! $c->telefono)<span class="secundario">Sin datos de contacto</span>@endif
                        </td>
                        <td class="text-center num">
                            <span class="fw-medium">{{ $c->presupuestos_count }}</span>
                            @if($c->vigentes_count)<div class="secundario">{{ $c->vigentes_count }} {{ $c->vigentes_count === 1 ? 'vigente' : 'vigentes' }}</div>@endif
                        </td>
                        <td class="secundario text-nowrap">
                            {{ $c->presupuestos_max_fecha ? \Illuminate\Support\Carbon::parse($c->presupuestos_max_fecha)->locale('es')->isoFormat('D MMM YYYY') : '—' }}
                        </td>
                        <td class="text-end text-nowrap">
                            @if($c->nuevo)
                                <form method="post" action="{{ route('admin.clientes.atendido', $c) }}" class="d-inline">@csrf @method('patch')
                                    <button class="btn btn-fantasma btn-icono" title="Marcar como atendido" aria-label="Marcar a {{ $c->nombre }} como atendido"><i class="bi bi-check2-circle"></i></button>
                                </form>
                            @endif
                            <a href="{{ route('admin.presupuestos.create', ['cliente' => $c->id]) }}" class="btn btn-borde btn-sm"><i class="bi bi-plus-lg"></i> Cotizar</a>
                            <a href="{{ route('admin.clientes.edit', $c) }}" class="btn btn-fantasma btn-icono" title="Editar" aria-label="Editar {{ $c->nombre }}"><i class="bi bi-pencil"></i></a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@if($clientes->hasPages())
    <div class="mt-3">{{ $clientes->links('pagination::bootstrap-5') }}</div>
@endif
@endsection
