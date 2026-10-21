{{-- Panel lateral de la agenda con una clase: lista, inscribir, pasar lista, horario fijo y cancelar. $c, $atletas, $puedeEditar --}}
@php
    $activas = $c->reservaciones->where('estado', '!=', 'cancelada');
    $canceladas = $c->reservaciones->where('estado', 'cancelada');
    $u = auth()->user();
    $pasarLista = $u->puede('asistencia') || $u->puede('clases');
    $gestionar = $u->puede('clases') || $u->puede('membresias');
    $llena = $activas->count() >= $c->cupo;
    $s = $c->serie;
    $sinAcento = fn ($t) => mb_strtolower(\Illuminate\Support\Str::ascii((string) $t));
    $lista = $atletas->map(fn ($a) => [
        'id' => $a->id, 'n' => $a->nombre, 'b' => $sinAcento($a->nombre . ' ' . $a->telefono),
        'p' => ($m = $a->membresias->sortBy(fn ($x) => [$x->con_creditos ? 0 : 1])->first()) ? $m->restantes_texto : '',
    ])->values();
    $diasSemana = [1 => 'L', 2 => 'M', 3 => 'M', 4 => 'J', 5 => 'V', 6 => 'S', 7 => 'D'];
@endphp
<div x-data="agendaClaseFx({{ Js::from(['atletas' => $lista]) }})" @keydown.escape.window="if (!nueva) cerrar()">
    <div class="ag-velo-lado" x-show="abierto" @click="cerrar()" aria-hidden="true"></div>
    <aside class="ag-lado" x-show="abierto" x-transition:enter.duration.150ms style="--c: {{ $c->color }}" role="dialog" aria-modal="false" aria-labelledby="ag-clase-t">
        <div class="top">
            <div class="d-flex align-items-start gap-2">
                <div class="flex-grow-1 min-w-0">
                    <h2 id="ag-clase-t">{{ $c->nombre }}
                        @if($c->cancelada)<span class="estado estado-cancelada ms-1">Cancelada</span>@elseif($c->en_curso)<span class="estado estado-aceptada ms-1">En curso</span>@elseif($c->pasada)<span class="estado estado-borrador ms-1">Terminó</span>@endif</h2>
                    <div class="secundario small mt-1">{{ ucfirst($c->fecha_texto) }} · <span class="num">{{ $c->horario }}</span></div>
                    <div class="secundario small">{{ $c->coach?->name ?? 'Sin entrenador' }}{{ $c->sucursal ? ' · ' . $c->sucursal->nombre : '' }}@if($s) · <i class="bi bi-arrow-repeat"></i> {{ $s->dias_texto }}@endif</div>
                </div>
                <button type="button" class="btn btn-fantasma btn-icono" @click="cerrar()" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
            </div>
            @unless($c->cancelada)<x-fitness.ocupacion :ocupados="$activas->count()" :cupo="$c->cupo" class="mt-2" />@endunless
        </div>

        <div class="cuerpo">
            @if($c->cancelada)
                <div class="sec small"><i class="bi bi-x-octagon me-1"></i> Se canceló{{ $c->motivo_cancelacion ? ': ' . $c->motivo_cancelacion : '' }}</div>
            @endif
            @error('reservacion')<div class="sec"><div class="aviso aviso-error m-0"><i class="bi bi-exclamation-triangle-fill"></i> {{ $message }}</div></div>@enderror

            {{-- Inscribir --}}
            @if($gestionar && ! $c->cancelada && ! $c->pasada)
                <div class="sec">
                    <div class="tt">Inscribir</div>
                    @if($llena)
                        <div class="secundario small">La clase está llena.</div>
                    @elseif($lista->isEmpty())
                        <div class="secundario small">No hay más atletas con paquete vigente ese día. <a href="{{ route('admin.clientes.index', ['ver' => 'sin_paquete']) }}">Asigna un paquete</a> para inscribirlos.</div>
                    @else
                        <div class="ag-busca">
                            <input type="search" class="form-control" x-model="buscar" placeholder="Busca por nombre o teléfono" aria-label="Buscar atleta para inscribir" autocomplete="off">
                            <div class="res" x-show="resultados.length">
                                <template x-for="a in resultados" :key="a.id">
                                    <button type="button" @click="inscribir(a.id)"><i class="bi bi-person-plus"></i><span class="flex-grow-1" x-text="a.n"></span><span class="secundario small" x-text="a.p"></span></button>
                                </template>
                            </div>
                            <div class="secundario small mt-1" x-show="buscar && !resultados.length">Nadie con paquete vigente se llama así.</div>
                        </div>
                        <form x-ref="inscribir" method="post" action="{{ route('admin.reservaciones.store', $c) }}" class="d-none">@csrf<input type="hidden" name="cliente_id"></form>
                    @endif
                </div>
            @endif

            {{-- Lista --}}
            <div class="sec">
                <div class="tt"><span>Lista · {{ $activas->count() }} de {{ $c->cupo }}</span><span class="text-lowercase fw-normal">{{ $activas->where('estado', 'asistio')->count() }} entraron</span></div>
                @forelse($activas as $r)
                    <div class="persona">
                        <x-fitness.avatar :atleta="$r->cliente" />
                        <div class="min-w-0 flex-grow-1">
                            <a href="{{ route('admin.clientes.show', [$r->cliente, 'ver' => 'atleta']) }}" class="principal text-reset d-block text-truncate">{{ $r->cliente->nombre }}</a>
                            <div class="secundario small text-truncate">{{ $r->membresia ? $r->membresia->restantes_texto : 'Sin paquete' }}@if($r->checkin_at) · entró {{ $r->checkin_at->setTimezone(config('vandu.zona_horaria'))->format('H:i') }}@endif @if($r->origen === 'atleta') · desde su portal @endif</div>
                        </div>
                        <div class="acc">
                            @if($pasarLista && ! $c->cancelada)
                                @foreach(['asistio' => ['Asistió', 'bi-check-lg', 'on-si'], 'falta' => ['Faltó', 'bi-x-lg', 'on-no']] as $k => [$t, $i, $on])
                                    <form method="post" action="{{ route('admin.reservaciones.asistencia', $r) }}">@csrf @method('patch')
                                        <input type="hidden" name="estado" value="{{ $r->estado === $k ? 'reservada' : $k }}">
                                        <button class="btn btn-borde btn-sm {{ $r->estado === $k ? $on : '' }}" title="{{ $r->estado === $k ? 'Quitar marca' : $t }}" aria-label="{{ $t }}: {{ $r->cliente->nombre }}"><i class="bi {{ $i }}"></i></button></form>
                                @endforeach
                            @endif
                            @if($gestionar && $r->estado === 'reservada' && ! $c->pasada && ! $c->cancelada)
                                <form method="post" action="{{ route('admin.reservaciones.cancelar', $r) }}" onsubmit="return confirm({{ Js::from('¿Quitar a ' . $r->cliente->nombre . ' de la clase? Se le regresa su clase.') }})">@csrf
                                    <input type="hidden" name="devolver" value="1">
                                    <button class="btn btn-fantasma btn-sm text-danger" title="Quitar de la clase" aria-label="Quitar a {{ $r->cliente->nombre }}"><i class="bi bi-person-dash"></i></button></form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="secundario small">Nadie ha reservado. Los atletas reservan desde su portal, o inscríbelos aquí.</div>
                @endforelse
                @if($canceladas->isNotEmpty())
                    <details class="mt-2 small"><summary class="secundario" style="cursor: pointer">{{ $canceladas->count() }} {{ $canceladas->count() === 1 ? 'cancelación' : 'cancelaciones' }}</summary>
                        @foreach($canceladas as $r)<div class="secundario mt-1">{{ $r->cliente->nombre }} · {{ $r->credito_devuelto ? 'se le regresó su clase' : ($r->credito_usado ? 'canceló tarde' : 'ilimitada') }}</div>@endforeach
                    </details>
                @endif
            </div>

            {{-- Entrenamiento --}}
            <div class="sec">
                <div class="tt"><span>Entrenamiento</span>@if($puedeEditar && ! $c->cancelada)<a href="{{ route('admin.clases.edit', $c) }}" class="text-none small fw-normal text-decoration-none">Editar clase</a>@endif</div>
                @forelse($c->bloques as $b)
                    <div class="small mb-2"><b>{{ $b['nombre'] ?: 'Bloque' }}</b>@if(! empty($b['formato']))<span class="secundario"> · {{ $b['formato'] }}</span>@endif
                        <div class="secundario">{{ collect($b['ejercicios'])->map(fn ($e) => trim(($e['cantidad'] ? $e['cantidad'] . ' ' : '') . $e['nombre']))->join(' · ') }}</div></div>
                @empty
                    <div class="secundario small">Sin entrenamiento. @if($puedeEditar && ! $c->cancelada)<a href="{{ route('admin.clases.edit', $c) }}">Agregar</a>@endif</div>
                @endforelse
                @if($c->notas)<div class="small mt-1"><b>Notas:</b> {{ $c->notas }}</div>@endif
            </div>

            {{-- Horario fijo --}}
            @if($puedeEditar && $s && $s->activa && ! $c->cancelada)
                <details class="sec" @if($errors->hasAny(['dias', 'hora', 'duracion', 'cupo'])) open @endif>
                    <summary class="tt mb-0" style="cursor: pointer"><span><i class="bi bi-arrow-repeat me-1"></i>Cambiar horario fijo</span><i class="bi bi-chevron-down"></i></summary>
                    <form method="post" action="{{ route('admin.clases.series.update', $s) }}" class="d-grid gap-2 mt-2">
                        @csrf @method('put')
                        <div class="ag-semana" role="group" aria-label="Días">
                            @foreach($diasSemana as $n => $l)
                                <label><input type="checkbox" name="dias[]" value="{{ $n }}" @checked(in_array($n, array_map('intval', $s->dias ?? []), true))><span>{{ $l }}</span></label>
                            @endforeach
                        </div>
                        @error('dias')<div class="text-danger small">{{ $message }}</div>@enderror
                        <div class="row g-2">
                            <div class="col-4"><label class="form-label small" for="sh">Hora</label><input id="sh" type="time" name="hora" class="form-control form-control-sm num" value="{{ $s->hora }}" required></div>
                            <div class="col-4"><label class="form-label small" for="sd">Minutos</label><input id="sd" type="number" name="duracion" class="form-control form-control-sm num" value="{{ $s->duracion }}" min="10" max="480" required></div>
                            <div class="col-4"><label class="form-label small" for="sc">Cupo</label><input id="sc" type="number" name="cupo" class="form-control form-control-sm num" value="{{ $s->cupo }}" min="1" max="500" required></div>
                            <div class="col-7"><label class="form-label small" for="sco">Entrenador</label>
                                <select id="sco" name="coach_id" class="form-select form-select-sm"><option value="">Sin asignar</option>
                                    @foreach($coaches as $co)<option value="{{ $co->id }}" @selected($s->coach_id === $co->id)>{{ $co->name }}</option>@endforeach</select></div>
                            <div class="col-5"><label class="form-label small" for="sha">Hasta</label><input id="sha" type="date" name="hasta" class="form-control form-control-sm num" value="{{ $s->hasta?->toDateString() }}"></div>
                            @if($s->sucursal_id)<input type="hidden" name="sucursal_id" value="{{ $s->sucursal_id }}">@endif
                        </div>
                        <span class="secundario small">Aplica a todas las clases que vienen. Si alguien ya reservó, su clase se mueve al nuevo horario y le llega un aviso.</span>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primario btn-sm">Guardar horario</button>
                            <button class="btn btn-fantasma btn-sm text-danger ms-auto" form="ag-terminar-{{ $s->id }}">Ya no repetir</button>
                        </div>
                    </form>
                    <form id="ag-terminar-{{ $s->id }}" method="post" action="{{ route('admin.clases.series.destroy', $s) }}" onsubmit="return confirm('¿Dejar de repetir esta clase? Se quitan las próximas que no tengan reservaciones.')">@csrf @method('delete')</form>
                </details>
            @endif

            {{-- Cancelar o eliminar solo esta --}}
            @if($puedeEditar && ! $c->cancelada && ! $c->pasada)
                <details class="sec" @if($errors->has('motivo')) open @endif>
                    <summary class="tt mb-0 text-danger" style="cursor: pointer"><span><i class="bi bi-x-octagon me-1"></i>Cancelar {{ $s ? 'solo esta clase' : 'esta clase' }}</span><i class="bi bi-chevron-down"></i></summary>
                    <form method="post" action="{{ route('admin.clases.cancelar', $c) }}" class="d-grid gap-2 mt-2" onsubmit="return confirm('¿Cancelar la clase? Se les regresa su clase a los inscritos y se les avisa.')">@csrf
                        <textarea name="motivo" rows="2" class="form-control @error('motivo') is-invalid @enderror" required maxlength="500" placeholder="Ej. El entrenador se enfermó">{{ old('motivo') }}</textarea>
                        @error('motivo')<div class="text-danger small">{{ $message }}</div>@enderror
                        <span class="secundario small">A {{ $activas->where('estado', 'reservada')->count() }} inscritos se les regresa su clase y les llega un aviso.</span>
                        <button class="btn btn-borde btn-sm text-danger">Cancelar clase</button>
                    </form>
                    @if($c->reservaciones->isEmpty())
                        <form method="post" action="{{ route('admin.clases.destroy', $c) }}" class="mt-2" onsubmit="return confirm('¿Eliminar esta clase?')">@csrf @method('delete')
                            <button class="btn btn-fantasma btn-sm text-danger w-100"><i class="bi bi-trash me-1"></i> Eliminar (nadie ha reservado)</button></form>
                    @endif
                </details>
            @endif
        </div>
    </aside>
</div>
