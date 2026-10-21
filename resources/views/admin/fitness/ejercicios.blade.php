@extends('admin.layout')
@section('titulo', 'Ejercicios')
@include('admin.fitness._estilos')

@php $unidades = \App\Models\Ejercicio::UNIDADES; @endphp

@section('contenido')
<div class="page-head">
    <div><h1>Ejercicios</h1><p class="sub">Tu biblioteca para armar los entrenamientos de cada clase.</p></div>
</div>
@include('admin.fitness._submenu')

<datalist id="categorias-ej">@foreach($categorias as $c)<option value="{{ $c }}">@endforeach</datalist>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="d-flex flex-wrap gap-2 mb-3">
            <form class="buscador flex-grow-1" method="get" role="search"><i class="bi bi-search"></i>
                <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Buscar ejercicio" aria-label="Buscar ejercicio"></form>
            @if($categorias)
                <nav class="segmento" aria-label="Categorías">
                    <a href="{{ route('admin.ejercicios') }}" class="{{ ! $categoria ? 'activo' : '' }}">Todos</a>
                    @foreach($categorias as $c)<a href="{{ route('admin.ejercicios', ['categoria' => $c]) }}" class="{{ $categoria === $c ? 'activo' : '' }}">{{ $c }}</a>@endforeach
                </nav>
            @endif
        </div>
        <div class="panel fx-lista">
            @forelse($ejercicios as $e)
                <details class="fx-editar">
                    <summary class="fx-fila">
                        <div class="flex-grow-1 min-w-0">
                            <div class="principal">{{ $e->nombre }}</div>
                            <div class="secundario small text-truncate">{{ $e->categoria ?: 'Sin categoría' }} · {{ $unidades[$e->unidad] ?? $e->unidad }}{{ $e->instrucciones ? ' · ' . \Illuminate\Support\Str::limit($e->instrucciones, 60) : '' }}</div>
                        </div>
                        <span class="btn btn-fantasma btn-sm ver"><i class="bi bi-pencil"></i></span>
                    </summary>
                    <form method="post" action="{{ route('admin.ejercicios.update', $e) }}" class="px-3 pb-3">
                        @csrf @method('put')
                        @include('admin.fitness._ejercicio_campos', ['e' => $e, 'pre' => 'e' . $e->id])
                        <div class="d-flex gap-2 mt-2"><button class="btn btn-primario btn-sm">Guardar</button>
                            <button class="btn btn-fantasma btn-sm text-danger ms-auto" form="borrar-ej-{{ $e->id }}">Eliminar</button></div>
                    </form>
                    <form id="borrar-ej-{{ $e->id }}" method="post" action="{{ route('admin.ejercicios.destroy', $e) }}" onsubmit="return confirm('¿Eliminar este ejercicio?')">@csrf @method('delete')</form>
                </details>
            @empty
                <div class="vacio"><div class="ico"><i class="bi bi-list-check"></i></div><h3>{{ $q || $categoria ? 'Nada coincide' : 'Sin ejercicios todavía' }}</h3></div>
            @endforelse
        </div>
    </div>
    <div class="col-lg-5">
        <form method="post" action="{{ route('admin.ejercicios.store') }}" class="panel panel-body" style="position: sticky; top: 24px">
            @csrf
            <h2 class="fs-6 fw-semibold mb-3">Nuevo ejercicio</h2>
            @include('admin.fitness._ejercicio_campos', ['e' => null, 'pre' => 'en'])
            <button class="btn btn-primario mt-3"><i class="bi bi-plus-lg me-1"></i> Agregar</button>
        </form>
    </div>
</div>
@endsection
