@extends('admin.layout')
@section('titulo', 'Tipos de clase')
@include('admin.fitness._estilos')

@section('contenido')
<div class="page-head">
    <div><h1>Tipos de clase</h1><p class="sub">Los entrenamientos que ofreces. El cupo de aquí se propone al programar; en cada clase lo puedes cambiar.</p></div>
</div>
@include('admin.fitness._submenu')

<div class="d-grid gap-3" style="max-width: 860px">
    @forelse($tipos as $t)
        <details class="panel fx-editar" @if($errors->any() && old('_tipo') == $t->id) open @endif>
            <summary class="fx-fila">
                <span class="fx-color" style="background: {{ $t->color }}; width: 14px; height: 14px"></span>
                <div class="flex-grow-1 min-w-0">
                    <div class="principal">{{ $t->nombre }} @unless($t->activo)<span class="fx-pastilla ms-1">Desactivado</span>@endunless</div>
                    <div class="secundario small text-truncate">{{ $t->duracion }} min · cupo {{ $t->cupo }}{{ $t->descripcion ? ' · ' . $t->descripcion : '' }}</div>
                </div>
                <span class="secundario small d-none d-md-inline">{{ $t->futuras_count }} {{ $t->futuras_count === 1 ? 'clase próxima' : 'clases próximas' }}</span>
                <span class="btn btn-fantasma btn-sm ver"><i class="bi bi-pencil"></i> Editar</span>
            </summary>
            <form method="post" action="{{ route('admin.clase-tipos.update', $t) }}" class="panel-body pt-0">
                @csrf @method('put') <input type="hidden" name="_tipo" value="{{ $t->id }}">
                @include('admin.fitness._tipo_campos', ['t' => $t, 'pre' => 't' . $t->id])
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-primario btn-sm">Guardar</button>
                    <button class="btn btn-fantasma btn-sm text-danger ms-auto" form="borrar-tipo-{{ $t->id }}">{{ $t->futuras_count ? 'Desactivar' : 'Eliminar' }}</button>
                </div>
            </form>
            <form id="borrar-tipo-{{ $t->id }}" method="post" action="{{ route('admin.clase-tipos.destroy', $t) }}" onsubmit="return confirm({{ Js::from('¿Quitar ' . $t->nombre . '?') }})">@csrf @method('delete')</form>
        </details>
    @empty
        <div class="panel"><div class="vacio"><div class="ico"><i class="bi bi-tags"></i></div><h3>Aún no hay tipos de clase</h3><p>Agrega los entrenamientos que das: HYROX Engine, Mobility, Fuerza…</p></div></div>
    @endforelse

    <form method="post" action="{{ route('admin.clase-tipos.store') }}" class="panel panel-body">
        @csrf <input type="hidden" name="_tipo" value="nuevo">
        <h2 class="fs-6 fw-semibold mb-3">Nuevo tipo de clase</h2>
        @include('admin.fitness._tipo_campos', ['t' => null, 'pre' => 'tn'])
        <button class="btn btn-primario mt-3"><i class="bi bi-plus-lg me-1"></i> Agregar</button>
    </form>
</div>
@endsection
