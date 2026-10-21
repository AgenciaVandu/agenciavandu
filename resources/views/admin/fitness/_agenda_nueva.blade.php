{{-- Ventana para programar una clase desde la agenda (suelta o de horario fijo). Vive dentro de agendaFx --}}
@php $diasSemana = [1 => 'L', 2 => 'M', 3 => 'M', 4 => 'J', 5 => 'V', 6 => 'S', 7 => 'D']; $nombresDia = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo']; @endphp
<div class="ag-velo" x-show="nueva" x-cloak x-transition.opacity @click.self="nueva = false" role="dialog" aria-modal="true" aria-labelledby="ag-nueva-t">
    <form class="ag-modal" method="post" action="{{ route('admin.clases.store') }}" @keydown.escape.stop="nueva = false">
        @csrf
        <input type="hidden" name="volver" value="agenda">
        <input type="hidden" name="repetir" :value="f.repetir ? 1 : 0">
        <div class="cab">
            <h2 id="ag-nueva-t">Programar clase</h2>
            <button type="button" class="btn btn-fantasma btn-icono" @click="nueva = false" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="cuerpo">
            @if($errors->any() && old('volver') === 'agenda')
                <div class="aviso aviso-error m-0"><i class="bi bi-exclamation-triangle-fill"></i> {{ $errors->first() }}</div>
            @endif

            @if($tipos->isEmpty())
                <div class="secundario">Primero crea tus tipos de clase (HYROX, Fuerza, Mobility…). <a href="{{ route('admin.clase-tipos') }}">Crear tipos de clase</a></div>
            @else
                <div>
                    <div class="form-label small">Clase</div>
                    <div class="ag-tipos" role="radiogroup" aria-label="Tipo de clase">
                        @foreach($tipos as $t)
                            <label style="--c: {{ $t->color }}"><input type="radio" name="clase_tipo_id" value="{{ $t->id }}" :checked="f.tipo === '{{ $t->id }}'" @change="ponerTipo({{ $t->id }})" @if($loop->first) x-ref="primero" @endif>
                                <span><i></i>{{ $t->nombre }}</span></label>
                        @endforeach
                    </div>
                </div>

                <div class="ag-g4">
                    <div><label class="form-label small" for="ag-fecha">Día</label>
                        <input id="ag-fecha" type="date" name="fecha" class="form-control num" x-model="f.fecha" @change="cambiarFecha()" min="{{ now(config('vandu.zona_horaria'))->toDateString() }}" required></div>
                    <div><label class="form-label small" for="ag-hora">Hora</label>
                        <input id="ag-hora" type="time" name="hora" class="form-control num" x-model="f.hora" step="300" required></div>
                    <div><label class="form-label small" for="ag-dur">Duración</label>
                        <div class="input-group"><input id="ag-dur" type="number" name="duracion" class="form-control num" x-model.number="f.duracion" min="10" max="480" step="5" required><span class="input-group-text">min</span></div></div>
                    <div><label class="form-label small" for="ag-cupo">Cupo</label>
                        <input id="ag-cupo" type="number" name="cupo" class="form-control num" x-model.number="f.cupo" min="1" max="500" required></div>
                </div>

                <div class="row g-2">
                    <div class="col"><label class="form-label small" for="ag-coach">Entrenador</label>
                        <select id="ag-coach" name="coach_id" class="form-select"><option value="">Sin asignar</option>
                            @foreach($coaches as $co)<option value="{{ $co->id }}" @selected((int) old('coach_id', $coach) === $co->id)>{{ $co->name }}</option>@endforeach</select></div>
                    @if($sucursales->count() > 1)
                        <div class="col"><label class="form-label small" for="ag-suc">Sucursal</label>
                            <select id="ag-suc" name="sucursal_id" class="form-select">
                                @foreach($sucursales as $su)<option value="{{ $su->id }}" @selected((int) old('sucursal_id', $sucursal ?? auth()->user()->sucursal_id) === $su->id)>{{ $su->nombre }}</option>@endforeach</select></div>
                    @elseif($sucursales->count() === 1)
                        <input type="hidden" name="sucursal_id" value="{{ $sucursales->first()->id }}">
                    @endif
                </div>

                <div class="ag-repite">
                    <nav class="segmento" aria-label="¿Se repite?">
                        <a href="#" :class="{ activo: !f.repetir }" @click.prevent="f.repetir = false">Solo ese día</a>
                        <a href="#" :class="{ activo: f.repetir }" @click.prevent="f.repetir = true"><i class="bi bi-arrow-repeat"></i> Cada semana</a>
                    </nav>
                    <template x-if="f.repetir">
                        <div class="d-grid gap-2">
                            <div class="ag-semana" role="group" aria-label="Días que se repite">
                                @foreach($diasSemana as $n => $l)
                                    <label title="{{ ucfirst($nombresDia[$n]) }}"><input type="checkbox" name="dias[]" value="{{ $n }}" x-model="f.dias"><span>{{ $l }}</span></label>
                                @endforeach
                            </div>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <label class="form-label small m-0" for="ag-hasta">Hasta</label>
                                <input id="ag-hasta" type="date" name="hasta" class="form-control form-control-sm num w-auto" value="{{ old('hasta') }}">
                                <span class="secundario small">Vacío = sin fin. Se programa {{ \App\Support\Fitness\Agenda::SEMANAS }} semanas hacia adelante y se va completando solo.</span>
                            </div>
                        </div>
                    </template>
                </div>
                <p class="secundario small m-0">El entrenamiento y las notas se agregan después desde la clase.</p>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-borde" @click="nueva = false">Cancelar</button>
                    <button class="btn btn-primario" :disabled="!f.tipo || (f.repetir && !f.dias.length)"><i class="bi bi-check2 me-1"></i> <span x-text="f.repetir ? 'Crear horario fijo' : 'Programar clase'">Programar clase</span></button>
                </div>
            @endif
        </div>
    </form>
</div>
