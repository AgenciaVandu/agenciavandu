@extends('admin.layout')
@section('titulo', $c->exists ? 'Editar clase' : 'Programar clase')
@include('admin.fitness._estilos')

@php
    $nueva = ! $c->exists;
    $tz = config('vandu.zona_horaria');
    $inicio = $c->inicia_at?->copy()->setTimezone($tz);
    $dur = $c->exists ? (int) round($c->inicia_at->diffInMinutes($c->termina_at)) : ($tipos->firstWhere('id', $c->clase_tipo_id)?->duracion ?? 60);
    $tiposJs = $tipos->mapWithKeys(fn ($t) => [$t->id => ['duracion' => $t->duracion, 'cupo' => $t->cupo]]);
    $diaSemana = $inicio ? $inicio->dayOfWeekIso : 1;
@endphp

@section('contenido')
<div class="migas"><a href="{{ route('admin.clases.index') }}">Agenda</a> <i class="bi bi-chevron-right small"></i> <span>{{ $nueva ? 'Programar clase' : $c->nombre }}</span></div>
<div class="page-head"><div><h1>{{ $nueva ? 'Programar clase' : 'Editar clase' }}</h1>
    <p class="sub">{{ $nueva ? 'Una clase suelta o un horario fijo que se repite cada semana. El cupo lo decides tú.' : $c->fecha_texto . ' · ' . $c->horario }}</p></div></div>

@if($tipos->isEmpty())
    <div class="panel"><div class="vacio"><div class="ico"><i class="bi bi-tags"></i></div><h3>Primero agrega tus tipos de clase</h3>
        <a href="{{ route('admin.clase-tipos') }}" class="btn btn-primario">Agregar tipos de clase</a></div></div>
@else
<form method="post" action="{{ $nueva ? route('admin.clases.store') : route('admin.clases.update', $c) }}" style="max-width: 900px"
      x-data="{ tipos: {{ Js::from($tiposJs) }}, tipo: '{{ old('clase_tipo_id', $c->clase_tipo_id) }}', duracion: {{ (int) old('duracion', $dur) }}, cupo: {{ (int) old('cupo', $c->cupo) }}, repetir: {{ old('repetir') ? 'true' : 'false' }},
                cambiarTipo() { const t = this.tipos[this.tipo]; if (t) { this.duracion = t.duracion; this.cupo = t.cupo; } } }">
    @csrf @if(! $nueva) @method('put') @endif
    <section class="panel panel-body">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="tipo">Clase</label>
                <select id="tipo" name="clase_tipo_id" class="form-select" x-model="tipo" @change="cambiarTipo()" required>
                    @foreach($tipos as $t)<option value="{{ $t->id }}">{{ $t->nombre }}</option>@endforeach
                </select></div>
            <div class="col-md-3"><label class="form-label" for="cupo">Cupo</label>
                <div class="input-group"><input id="cupo" name="cupo" type="number" min="{{ max(1, $c->exists ? $c->ocupados : 1) }}" max="500" class="form-control num @error('cupo') is-invalid @enderror" x-model.number="cupo" required><span class="input-group-text">lugares</span></div>
                @error('cupo')<div class="text-danger small">{{ $message }}</div>@enderror</div>
            <div class="col-md-3"><label class="form-label" for="dur">Duración</label>
                <div class="input-group"><input id="dur" name="duracion" type="number" min="10" max="480" class="form-control num" x-model.number="duracion" required><span class="input-group-text">min</span></div></div>
            <div class="col-md-4"><label class="form-label" for="fecha" x-text="repetir ? 'Empieza el' : 'Fecha'">Fecha</label>
                <input id="fecha" name="fecha" type="date" class="form-control num" value="{{ old('fecha', $inicio?->toDateString()) }}" required></div>
            <div class="col-md-3"><label class="form-label" for="hora">Hora</label>
                <input id="hora" name="hora" type="time" class="form-control num @error('hora') is-invalid @enderror" value="{{ old('hora', $inicio?->format('H:i')) }}" required></div>
            <div class="col-md-5"><label class="form-label" for="coach">Entrenador</label>
                <select id="coach" name="coach_id" class="form-select"><option value="">Sin asignar</option>
                    @foreach($coaches as $co)<option value="{{ $co->id }}" @selected((int) old('coach_id', $c->coach_id) === $co->id)>{{ $co->name }}</option>@endforeach</select></div>
            @if($sucursales->isNotEmpty())
                <div class="col-md-6"><label class="form-label" for="suc">Sucursal</label>
                    <select id="suc" name="sucursal_id" class="form-select">@if($sucursales->count() > 1)<option value="">—</option>@endif
                        @foreach($sucursales as $s)<option value="{{ $s->id }}" @selected((int) old('sucursal_id', $c->sucursal_id) === $s->id)>{{ $s->nombre }}</option>@endforeach</select></div>
            @endif
        </div>

        @if($nueva)
            <div class="mt-3 p-3 rounded-3" style="background: var(--sunken)">
                <label class="form-check m-0"><input type="checkbox" name="repetir" value="1" class="form-check-input" x-model="repetir"> <span class="form-check-label fw-medium">Se repite cada semana (horario fijo)</span></label>
                <div x-show="repetir" x-cloak class="mt-3">
                    <span class="form-label small d-block">Los días</span>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach(\App\Models\ClaseSerie::DIAS as $n => $dia)
                            <label class="fx-pastilla" style="cursor:pointer; padding: 5px 12px"><input type="checkbox" name="dias[]" value="{{ $n }}" class="form-check-input m-0" @checked(in_array($n, (array) old('dias', [$diaSemana])))> {{ $dia }}</label>
                        @endforeach
                    </div>
                    @error('dias')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    <div class="mt-2" style="max-width: 240px"><label class="form-label small" for="hasta">Hasta <span class="secundario">(opcional)</span></label>
                        <input id="hasta" name="hasta" type="date" class="form-control num" value="{{ old('hasta') }}"></div>
                    <p class="secundario small mt-2 mb-0">Se programan las próximas {{ \App\Support\Fitness\Agenda::SEMANAS }} semanas y se van agregando solas.</p>
                </div>
            </div>
        @endif
    </section>

    <section class="panel panel-body mt-3">
        <h2 class="fs-6 fw-semibold mb-1">Entrenamiento</h2>
        <p class="secundario small mb-3">Opcional. Lo ven el entrenador y los atletas en su portal.</p>
        @include('admin.fitness._entrenamiento', ['bloques' => $c->entrenamiento ?? [], 'ejercicios' => $ejercicios])
        <div class="mt-3"><label class="form-label small" for="notas">Notas para el equipo</label>
            <textarea id="notas" name="notas" rows="2" class="form-control" maxlength="2000">{{ old('notas', $c->notas) }}</textarea></div>
    </section>

    <div class="d-flex gap-2 justify-content-end mt-3">
        <a href="{{ $nueva ? route('admin.clases.index') : route('admin.clases.show', $c) }}" class="btn btn-borde">Cancelar</a>
        <button class="btn btn-primario px-4" x-text="repetir ? 'Crear horario fijo' : '{{ $nueva ? 'Programar clase' : 'Guardar' }}'">{{ $nueva ? 'Programar clase' : 'Guardar' }}</button>
    </div>
</form>
@endif
@endsection
