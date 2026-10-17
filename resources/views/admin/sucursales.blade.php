@extends('admin.layout')
@section('titulo', 'Sucursales')

@section('contenido')
<div class="page-head">
    <div>
        <h1>Sucursales</h1>
        <p class="sub">Cada unidad y cada venta se asignan a una sucursal. Tu plan incluye {{ $limite }} {{ $limite === 1 ? 'sucursal' : 'sucursales' }}.</p>
    </div>
</div>

<div class="d-grid gap-3" style="max-width: 760px">
    @foreach($sucursales as $s)
        <form method="post" action="{{ route('admin.sucursales.update', $s) }}" class="panel panel-body">
            @csrf @method('put')
            <div class="row g-2 align-items-end">
                <div class="col-md-4"><label class="form-label small" for="n{{ $s->id }}">Nombre</label><input id="n{{ $s->id }}" name="nombre" class="form-control" value="{{ $s->nombre }}" required maxlength="100"></div>
                <div class="col-md-5"><label class="form-label small" for="d{{ $s->id }}">Dirección</label><input id="d{{ $s->id }}" name="direccion" class="form-control" value="{{ $s->direccion }}" maxlength="255"></div>
                <div class="col-md-3"><label class="form-label small" for="t{{ $s->id }}">Teléfono</label><input id="t{{ $s->id }}" name="telefono" class="form-control num" value="{{ $s->telefono }}" maxlength="30"></div>
            </div>
            <div class="d-flex align-items-center gap-2 mt-3">
                <span class="secundario small me-auto">{{ $s->disponibles_count }} {{ $s->disponibles_count === 1 ? 'unidad' : 'unidades' }} en venta</span>
                <button class="btn btn-borde btn-sm">Guardar</button>
                <button class="btn btn-fantasma btn-sm text-danger" form="borrar-{{ $s->id }}">Eliminar</button>
            </div>
        </form>
        <form id="borrar-{{ $s->id }}" method="post" action="{{ route('admin.sucursales.destroy', $s) }}" onsubmit="return confirm('¿Eliminar la sucursal {{ addslashes($s->nombre) }}?')">@csrf @method('delete')</form>
    @endforeach

    @if($sucursales->count() < $limite)
        <form method="post" action="{{ route('admin.sucursales.store') }}" class="panel panel-body">
            @csrf
            <h2 class="fs-6 fw-semibold mb-3">{{ $sucursales->isEmpty() ? 'Agrega tu sucursal' : 'Nueva sucursal' }}</h2>
            <div class="row g-2 align-items-end">
                <div class="col-md-4"><label class="form-label small" for="nn">Nombre</label><input id="nn" name="nombre" class="form-control" required maxlength="100" placeholder="Matriz, Norte…"></div>
                <div class="col-md-5"><label class="form-label small" for="nd">Dirección</label><input id="nd" name="direccion" class="form-control" maxlength="255"></div>
                <div class="col-md-3"><label class="form-label small" for="nt">Teléfono</label><input id="nt" name="telefono" class="form-control num" maxlength="30"></div>
            </div>
            <button class="btn btn-primario mt-3"><i class="bi bi-plus-lg me-1"></i> Agregar sucursal</button>
        </form>
    @else
        <p class="secundario small">Ya tienes las {{ $limite }} {{ $limite === 1 ? 'sucursal' : 'sucursales' }} de tu plan. Para abrir otra, pide que te la activen.</p>
    @endif
</div>
@endsection
