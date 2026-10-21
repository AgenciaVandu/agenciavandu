@extends('admin.layout')
@section('titulo', 'Horario fijo')
@include('admin.fitness._estilos')

@section('contenido')
<div class="page-head">
    <div><h1>Horario fijo</h1><p class="sub">Las clases que se repiten cada semana. Se programan solas {{ \App\Support\Fitness\Agenda::SEMANAS }} semanas hacia adelante.</p></div>
    <a href="{{ route('admin.clases.create') }}" class="btn btn-primario"><i class="bi bi-plus-lg me-1"></i> Nuevo horario</a>
</div>
@include('admin.fitness._submenu')

<div class="d-grid gap-3" style="max-width: 900px">
    @forelse($series as $s)
        <details class="panel fx-editar">
            <summary class="fx-fila">
                <span class="fx-color" style="background: {{ $s->tipo?->color }}; width: 14px; height: 14px"></span>
                <div class="flex-grow-1 min-w-0">
                    <div class="principal">{{ $s->tipo?->nombre }} · {{ $s->hora }} @unless($s->activa)<span class="fx-pastilla ms-1">Ya no se repite</span>@endunless</div>
                    <div class="secundario small">{{ $s->dias_texto }} · {{ $s->duracion }} min · cupo {{ $s->cupo }}{{ $s->coach ? ' · ' . $s->coach->name : '' }}{{ $s->sucursal ? ' · ' . $s->sucursal->nombre : '' }}{{ $s->hasta ? ' · hasta el ' . $s->hasta->locale('es')->isoFormat('D MMM') : '' }}</div>
                </div>
                <span class="secundario small d-none d-md-inline">{{ $s->futuras_count }} próximas</span>
                @if($s->activa)<span class="btn btn-fantasma btn-sm ver"><i class="bi bi-pencil"></i></span>@endif
            </summary>
            @if($s->activa)
                <form method="post" action="{{ route('admin.clases.series.update', $s) }}" class="panel-body pt-0">
                    @csrf @method('put')
                    <div class="fx-grid-form">
                        <div><label class="form-label small" for="co{{ $s->id }}">Entrenador</label>
                            <select id="co{{ $s->id }}" name="coach_id" class="form-select"><option value="">Sin asignar</option>
                                @foreach($coaches as $co)<option value="{{ $co->id }}" @selected($s->coach_id === $co->id)>{{ $co->name }}</option>@endforeach</select></div>
                        @if($sucursales->isNotEmpty())
                        <div><label class="form-label small" for="su{{ $s->id }}">Sucursal</label>
                            <select id="su{{ $s->id }}" name="sucursal_id" class="form-select">@if($sucursales->count() > 1)<option value="">—</option>@endif
                                @foreach($sucursales as $su)<option value="{{ $su->id }}" @selected($s->sucursal_id === $su->id)>{{ $su->nombre }}</option>@endforeach</select></div>
                        @endif
                        <div><label class="form-label small" for="cu{{ $s->id }}">Cupo</label><input id="cu{{ $s->id }}" name="cupo" type="number" min="1" max="500" class="form-control num" value="{{ $s->cupo }}" required></div>
                        <div><label class="form-label small" for="ha{{ $s->id }}">Hasta</label><input id="ha{{ $s->id }}" name="hasta" type="date" class="form-control num" value="{{ $s->hasta?->toDateString() }}"></div>
                    </div>
                    <p class="secundario small mt-2 mb-0">Los cambios se aplican a sus próximas clases. El cupo nunca baja de los que ya reservaron.</p>
                    <div class="d-flex gap-2 mt-3"><button class="btn btn-primario btn-sm">Guardar</button>
                        <button class="btn btn-fantasma btn-sm text-danger ms-auto" form="terminar-{{ $s->id }}">Ya no repetir</button></div>
                </form>
                <form id="terminar-{{ $s->id }}" method="post" action="{{ route('admin.clases.series.destroy', $s) }}" onsubmit="return confirm('¿Dejar de repetir esta clase? Se quitan las próximas que no tengan reservaciones.')">@csrf @method('delete')</form>
            @endif
        </details>
    @empty
        <div class="panel"><div class="vacio"><div class="ico"><i class="bi bi-arrow-repeat"></i></div><h3>Aún no tienes horario fijo</h3>
            <p>Programa una clase y marca "Se repite cada semana".</p><a href="{{ route('admin.clases.create') }}" class="btn btn-primario">Programar</a></div></div>
    @endforelse
</div>
@endsection
