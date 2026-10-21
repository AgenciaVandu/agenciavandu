@extends('admin.layout')
@section('titulo', $c->nombre)
@include('admin.fitness._estilos')

@php
    $activas = $c->reservaciones->where('estado', '!=', 'cancelada');
    $canceladas = $c->reservaciones->where('estado', 'cancelada');
    $u = auth()->user();
    $pasarLista = $u->puede('asistencia') || $u->puede('clases');
    $gestionar = $u->puede('clases') || $u->puede('membresias');
@endphp

@push('head')
<style>
    .ficha-c { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 24px; align-items: start; }
    .lista-a .fx-fila { padding: 12px 18px; flex-wrap: wrap; }
    .lista-a .acciones { display: flex; gap: 6px; margin-left: auto; }
    .lista-a .acciones form { margin: 0; }
    .lista-a .acciones .on { background: var(--ink); color: #fff; border-color: var(--ink); }
    .bloque-v { padding: 12px 18px; } .bloque-v + .bloque-v { border-top: 1px solid var(--line); }
    .bloque-v ul { margin: 6px 0 0; padding-left: 18px; } .bloque-v li { margin: 2px 0; }
    @media (max-width: 991.98px) { .ficha-c { grid-template-columns: minmax(0, 1fr); } }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.clases.index') }}">Agenda</a> <i class="bi bi-chevron-right small"></i> <span>{{ $c->nombre }}</span></div>
<div class="page-head">
    <div>
        <div class="d-flex align-items-center gap-2 flex-wrap"><h1>{{ $c->nombre }}</h1>
            @if($c->cancelada)<span class="estado estado-cancelada">Cancelada</span>@elseif($c->en_curso)<span class="estado estado-aceptada">En curso</span>@elseif($c->pasada)<span class="estado estado-borrador">Terminó</span>@endif</div>
        <p class="sub">{{ $c->fecha_texto }} · {{ $c->horario }}{{ $c->coach ? ' · ' . $c->coach->name : '' }}{{ $c->sucursal ? ' · ' . $c->sucursal->nombre : '' }}@if($c->serie) · <i class="bi bi-arrow-repeat"></i> horario fijo @endif</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if($pasarLista && ! $c->cancelada)<a href="{{ route('admin.escanear') }}" class="btn btn-borde"><i class="bi bi-qr-code-scan me-1"></i> Escanear</a>@endif
        @if($puedeEditar && ! $c->cancelada)<a href="{{ route('admin.clases.edit', $c) }}" class="btn btn-borde"><i class="bi bi-pencil me-1"></i> Editar</a>@endif
    </div>
</div>

@if($c->cancelada)
    <div class="aviso" style="background: var(--sunken); border: 1px solid var(--line)"><i class="bi bi-x-octagon"></i> Se canceló{{ $c->motivo_cancelacion ? ': ' . $c->motivo_cancelacion : '' }}</div>
@endif
@error('reservacion')<div class="aviso aviso-error"><i class="bi bi-exclamation-triangle-fill"></i> {{ $message }}</div>@enderror

<div class="ficha-c">
    <div class="d-grid gap-4">
        <section class="panel">
            <div class="panel-head">
                <div><h2>Asistentes</h2><span class="ayuda num">{{ $activas->count() }} de {{ $c->cupo }} lugares · {{ $activas->where('estado', 'asistio')->count() }} ya entraron</span></div>
                <x-fitness.ocupacion :ocupados="$activas->count()" :cupo="$c->cupo" style="width: 160px" />
            </div>
            <div class="fx-lista lista-a">
                @forelse($activas as $r)
                    <div class="fx-fila">
                        <x-fitness.avatar :atleta="$r->cliente" />
                        <div class="min-w-0">
                            <a href="{{ route('admin.clientes.show', [$r->cliente, 'ver' => 'atleta']) }}" class="principal text-reset">{{ $r->cliente->nombre }}</a>
                            <div class="secundario small">{{ $r->membresia?->nombre ?? 'Sin paquete' }}{{ $r->membresia ? ' · ' . $r->membresia->restantes_texto : '' }}
                                @if($r->checkin_at) · entró {{ $r->checkin_at->setTimezone(config('vandu.zona_horaria'))->format('H:i') }}@endif
                                @if($r->origen === 'atleta') · reservó en su portal @endif</div>
                        </div>
                        <div class="acciones">
                            @if($pasarLista && ! $c->cancelada)
                                @foreach(['asistio' => ['Asistió', 'bi-check-lg'], 'falta' => ['Faltó', 'bi-x-lg']] as $k => [$t, $i])
                                    <form method="post" action="{{ route('admin.reservaciones.asistencia', $r) }}">@csrf @method('patch')
                                        <input type="hidden" name="estado" value="{{ $r->estado === $k ? 'reservada' : $k }}">
                                        <button class="btn btn-borde btn-sm {{ $r->estado === $k ? 'on' : '' }}" title="{{ $r->estado === $k ? 'Quitar marca' : $t }}"><i class="bi {{ $i }}"></i> <span class="d-none d-sm-inline">{{ $t }}</span></button></form>
                                @endforeach
                            @endif
                            @if($gestionar && $r->estado === 'reservada' && ! $c->pasada)
                                <form method="post" action="{{ route('admin.reservaciones.cancelar', $r) }}" onsubmit="return confirm({{ Js::from('¿Quitar a ' . $r->cliente->nombre . ' de la clase? Se le regresa su clase.') }})">@csrf
                                    <input type="hidden" name="devolver" value="1">
                                    <button class="btn btn-fantasma btn-sm text-danger" title="Quitar de la clase" aria-label="Quitar a {{ $r->cliente->nombre }}"><i class="bi bi-person-dash"></i></button></form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="vacio py-4"><div class="ico"><i class="bi bi-people"></i></div><h3>Nadie ha reservado</h3><p>Los atletas reservan desde su portal o aquí los puedes inscribir.</p></div>
                @endforelse
            </div>
            @if($gestionar && ! $c->cancelada && ! $c->pasada)
                <form method="post" action="{{ route('admin.reservaciones.store', $c) }}" class="panel-body d-flex flex-wrap gap-2 border-top">
                    @csrf
                    @if($atletas->isEmpty())
                        <span class="secundario small">No hay más atletas con paquete vigente para este día. <a href="{{ route('admin.clientes.index') }}">Asigna un paquete</a> para inscribirlos.</span>
                    @else
                        <select name="cliente_id" class="form-select flex-grow-1" style="max-width: 360px" required aria-label="Atleta"><option value="">Inscribir a un atleta…</option>
                            @foreach($atletas as $a)<option value="{{ $a->id }}">{{ $a->nombre }}</option>@endforeach</select>
                        <button class="btn btn-primario" @disabled($activas->count() >= $c->cupo)><i class="bi bi-person-plus me-1"></i> {{ $activas->count() >= $c->cupo ? 'Clase llena' : 'Inscribir' }}</button>
                    @endif
                </form>
            @endif
        </section>

        @if($canceladas->isNotEmpty())
            <details class="panel">
                <summary class="panel-body py-3" style="cursor:pointer">{{ $canceladas->count() }} {{ $canceladas->count() === 1 ? 'cancelación' : 'cancelaciones' }}</summary>
                <div class="fx-lista">@foreach($canceladas as $r)
                    <div class="fx-fila"><x-fitness.avatar :atleta="$r->cliente" /><div><div>{{ $r->cliente->nombre }}</div>
                        <div class="secundario small">Canceló {{ $r->cancelada_at?->locale('es')->diffForHumans() }} · {{ $r->credito_devuelto ? 'se le regresó su clase' : ($r->credito_usado ? 'perdió la clase (canceló tarde)' : 'membresía ilimitada') }}</div></div></div>
                @endforeach</div>
            </details>
        @endif
    </div>

    <aside class="d-grid gap-4">
        <section class="panel">
            <div class="panel-head"><h2>Entrenamiento</h2>@if($puedeEditar && ! $c->cancelada)<a href="{{ route('admin.clases.edit', $c) }}" class="btn btn-fantasma btn-sm">Editar</a>@endif</div>
            @forelse($c->bloques as $b)
                <div class="bloque-v"><b>{{ $b['nombre'] ?: 'Bloque' }}</b>@if(! empty($b['formato']))<span class="secundario small"> · {{ $b['formato'] }}</span>@endif
                    <ul>@foreach($b['ejercicios'] as $e)<li>{{ $e['cantidad'] ? $e['cantidad'] . ' ' : '' }}{{ $e['nombre'] }}@if($e['nota'])<span class="secundario"> — {{ $e['nota'] }}</span>@endif</li>@endforeach</ul></div>
            @empty
                <div class="panel-body secundario small">Sin entrenamiento capturado.</div>
            @endforelse
            @if($c->notas)<div class="panel-body border-top small"><b>Notas:</b> {{ $c->notas }}</div>@endif
        </section>

        @if($puedeEditar && ! $c->cancelada && ! $c->pasada)
            <details class="panel" @if($errors->has('motivo')) open @endif>
                <summary class="panel-body py-3 text-danger" style="cursor:pointer"><i class="bi bi-x-octagon me-1"></i> Cancelar esta clase</summary>
                <form method="post" action="{{ route('admin.clases.cancelar', $c) }}" class="panel-body pt-0 d-grid gap-2" onsubmit="return confirm('¿Cancelar la clase? Se les regresa su clase a los inscritos y se les avisa.')">@csrf
                    <textarea name="motivo" rows="2" class="form-control @error('motivo') is-invalid @enderror" required maxlength="500" placeholder="Ej. El entrenador se enfermó">{{ old('motivo') }}</textarea>
                    @error('motivo')<div class="text-danger small">{{ $message }}</div>@enderror
                    <span class="secundario small">A {{ $activas->where('estado', 'reservada')->count() }} inscritos se les regresa su clase y les llega un aviso.</span>
                    <button class="btn btn-borde text-danger">Cancelar clase</button>
                </form>
            </details>
            @if($c->reservaciones->isEmpty())
                <form method="post" action="{{ route('admin.clases.destroy', $c) }}" onsubmit="return confirm('¿Eliminar esta clase?')">@csrf @method('delete')
                    <button class="btn btn-fantasma text-danger w-100"><i class="bi bi-trash me-1"></i> Eliminar clase</button></form>
            @endif
        @endif
    </aside>
</div>
@endsection
