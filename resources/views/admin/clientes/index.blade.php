@extends('admin.layout')
@section('titulo', 'Clientes')

@section('contenido')
<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
    <h1 class="h3 mb-0">Clientes</h1>
    <div class="d-flex gap-2">
        <form class="d-flex gap-2" method="get">
            <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Nombre, empresa, correo…" aria-label="Buscar cliente">
            <button class="btn btn-outline-dark"><i class="bi bi-search"></i></button>
        </form>
        <a href="{{ route('admin.clientes.create') }}" class="btn btn-v text-nowrap"><i class="bi bi-person-plus"></i> Nuevo cliente</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small">
                <tr><th>Cliente</th><th>Contacto</th><th class="text-center">Cotizaciones</th><th class="text-end">Acciones</th></tr>
            </thead>
            <tbody>
            @forelse($clientes as $c)
                <tr>
                    <td>
                        <a href="{{ route('admin.clientes.show', $c) }}" class="fw-semibold text-decoration-none">{{ $c->nombre }}</a>
                        <div class="small text-muted">{{ $c->empresa }}</div>
                    </td>
                    <td class="small">
                        @if($c->email)<div>{{ $c->email }}</div>@endif
                        @if($c->telefono)<div class="num">{{ $c->telefono }}</div>@endif
                    </td>
                    <td class="text-center num">{{ $c->presupuestos_count }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.presupuestos.create', ['cliente' => $c->id]) }}" class="btn btn-sm btn-verde"><i class="bi bi-plus-lg"></i> Cotizar</a>
                        <a href="{{ route('admin.clientes.edit', $c) }}" class="btn btn-sm btn-outline-dark">Editar</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center py-5">
                    <p class="text-muted mb-3">{{ $q ? "Ningún cliente coincide con “{$q}”." : 'Todavía no tienes clientes.' }}</p>
                    <a href="{{ route('admin.clientes.create') }}" class="btn btn-verde">Agregar cliente</a>
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $clientes->links('pagination::bootstrap-5') }}</div>
@endsection
