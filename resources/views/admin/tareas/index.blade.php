@extends('admin.layout')
@section('titulo', $gestiona ? 'Tareas' : 'Mis tareas')

@php
    $titulos = ['revision' => 'Por revisar', 'en_curso' => 'En curso', 'pendiente' => 'Pendientes'];
    $ayudas = ['revision' => $gestiona ? 'Entregadas: revísalas y dalas por terminadas o pide ajustes' : 'Entregadas: esperando revisión', 'en_curso' => 'Alguien ya está trabajando en ellas', 'pendiente' => 'Todavía no empiezan'];
    $filtro = fn ($extra) => route('admin.tareas.index', array_filter(array_merge(['ver' => $ver !== 'todas' ? $ver : null, 'estado' => $terminadas ? 'terminadas' : null], $extra), fn ($v) => $v !== null && $v !== ''));
@endphp

@push('head')
<style>
    .tr-filtros { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; margin-bottom: 18px; }
    .tr-filtros .izq { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
    .tr-grupo + .tr-grupo { margin-top: 20px; }
    .tr-lista { list-style: none; margin: 0; padding: 0; }
    .tr-lista li + li { border-top: 1px solid var(--line); }
    .tr-item { display: grid; grid-template-columns: 18px minmax(0, 1fr) auto; gap: 4px 12px; align-items: center; padding: 14px 18px; color: var(--text); text-decoration: none; }
    .tr-item:hover { background: #FAFBFC; color: var(--text); }
    .tr-item:hover .n { text-decoration: underline; text-underline-offset: 3px; }
    .tr-item .ico { font-size: 15px; }
    .tr-item .n { font-weight: 500; font-size: 15px; }
    .tr-item .s { grid-column: 2; font-size: 13px; color: var(--muted); display: flex; flex-wrap: wrap; gap: 4px 12px; }
    .tr-item .s span { display: inline-flex; align-items: center; gap: 5px; }
    .tr-item .der { grid-row: 1 / span 2; grid-column: 3; display: flex; align-items: center; gap: 12px; }
    .tr-item .fecha { font-size: 13px; color: var(--text-2); white-space: nowrap; }
    .tr-item .fecha.vencida { color: var(--red); font-weight: 500; }
    .tr-item .avatar { width: 28px; height: 28px; font-size: 11px; }
    .etq-urg { font-size: 11px; font-weight: 600; padding: 1px 7px; border-radius: 99px; background: var(--red-soft); color: var(--red); margin-left: 6px; vertical-align: 2px; }
    @media (max-width: 575.98px) {
        .tr-grupo .panel-head .ayuda { display: none; }
        .tr-item { grid-template-columns: 18px minmax(0, 1fr); padding: 13px 14px; }
        .tr-item .der { grid-row: auto; grid-column: 2; justify-content: space-between; }
    }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <h1>{{ $gestiona ? 'Tareas' : 'Mis tareas' }}</h1>
        <p class="sub">{{ $gestiona ? 'Asigna trabajo al equipo, elige dónde se entrega y revisa lo que suben.' : 'Lo que tienes que hacer. Sube tu trabajo desde cada tarea y entrégala cuando esté lista.' }}</p>
    </div>
    @if($gestiona)<a href="{{ route('admin.tareas.create') }}" class="btn btn-primario"><i class="bi bi-plus-lg me-1"></i> Nueva tarea</a>@endif
</div>

<div class="tr-filtros">
    <div class="izq">
        @if($gestiona)
            <nav class="segmento" aria-label="De quién">
                <a href="{{ $filtro(['ver' => null]) }}" class="{{ $ver === 'todas' ? 'activo' : '' }}">Todo el equipo</a>
                <a href="{{ $filtro(['ver' => 'mias']) }}" class="{{ $ver === 'mias' ? 'activo' : '' }}">Mías <span class="n num">{{ $mias ?: '' }}</span></a>
                <a href="{{ $filtro(['ver' => 'sin']) }}" class="{{ $ver === 'sin' ? 'activo' : '' }}">Sin asignar</a>
            </nav>
            <select class="form-select" style="width: auto; min-width: 190px" aria-label="Ver tareas de" onchange="if (this.value) location.href = this.value">
                <option value="">Ver de una persona…</option>
                @foreach($equipo as $p)<option value="{{ $filtro(['ver' => $p->id]) }}" @selected($ver === (string) $p->id)>{{ $p->name }}{{ $p->puesto ? ' · ' . $p->puesto : '' }}</option>@endforeach
            </select>
        @endif
    </div>
    <nav class="segmento" aria-label="Estado">
        <a href="{{ $filtro(['estado' => null]) }}" class="{{ $terminadas ? '' : 'activo' }}">Abiertas</a>
        <a href="{{ $filtro(['estado' => 'terminadas']) }}" class="{{ $terminadas ? 'activo' : '' }}">Terminadas</a>
    </nav>
</div>

@if($tareas->isEmpty())
    <div class="panel"><div class="vacio">
        <div class="ico"><i class="bi {{ $terminadas ? 'bi-archive' : 'bi-check2-all' }}"></i></div>
        @if($terminadas)
            <h3>Todavía no hay tareas terminadas</h3><p>Aquí quedarán las que se den por terminadas.</p>
        @elseif($gestiona && $ver === 'todas')
            <h3>No hay tareas abiertas</h3><p>Crea una y asígnala a alguien del equipo.</p>
            <a href="{{ route('admin.tareas.create') }}" class="btn btn-primario">Nueva tarea</a>
        @else
            <h3>Nada pendiente por aquí</h3><p>Cuando te asignen una tarea aparecerá aquí y te llegará un aviso.</p>
        @endif
    </div></div>
@elseif($terminadas)
    <section class="panel"><ul class="tr-lista">@foreach($tareas as $t)<li>@include('admin.tareas._fila')</li>@endforeach</ul></section>
@else
    @foreach($grupos as $estado => $lista)
        @continue($lista->isEmpty())
        <section class="panel tr-grupo">
            <div class="panel-head"><h2 class="d-flex align-items-center gap-2"><i class="bi {{ \App\Models\Tarea::ESTADOS[$estado]['icono'] }}" style="color: {{ \App\Models\Tarea::ESTADOS[$estado]['color'] }}"></i> {{ $titulos[$estado] }} <span class="secundario fw-normal num">{{ $lista->count() }}</span></h2><span class="ayuda">{{ $ayudas[$estado] }}</span></div>
            <ul class="tr-lista">@foreach($lista as $t)<li>@include('admin.tareas._fila')</li>@endforeach</ul>
        </section>
    @endforeach
@endif
@endsection
