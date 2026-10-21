@extends('admin.layout')
@section('titulo', 'Atletas')
@include('admin.fitness._estilos')

@php $tz = config('vandu.zona_horaria'); @endphp

@section('contenido')
<div class="page-head">
    <div><h1>Atletas</h1><p class="sub">Tus clientes del gimnasio: su paquete, cuántas clases les quedan y cuándo vinieron.</p></div>
    <div class="d-flex flex-wrap gap-2">
        @if(auth()->user()->puede('asistencia'))<a href="{{ route('admin.escanear') }}" class="btn btn-borde"><i class="bi bi-qr-code-scan me-1"></i> Escanear</a>@endif
        <a href="{{ route('admin.clientes.create') }}" class="btn btn-primario"><i class="bi bi-person-plus me-1"></i> Nuevo atleta</a>
    </div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <nav class="segmento" aria-label="Filtrar atletas">
        @foreach(\App\Http\Controllers\Admin\Fitness\AtletasController::VISTAS as $k => $t)
            <a href="{{ route('admin.clientes.index', array_filter(['ver' => $k === 'todos' ? null : $k, 'q' => $q ?: null])) }}" class="{{ $ver === $k ? 'activo' : '' }}">{{ $t }} @if($conteos[$k])<span class="n num">{{ $conteos[$k] }}</span>@endif</a>
        @endforeach
    </nav>
    <form class="buscador" method="get" role="search">
        @if($ver !== 'todos')<input type="hidden" name="ver" value="{{ $ver }}">@endif
        <i class="bi bi-search"></i><input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Nombre, correo o teléfono" aria-label="Buscar atleta">
    </form>
</div>

@if($atletas->isEmpty())
    <div class="panel"><div class="vacio"><div class="ico"><i class="bi bi-people"></i></div>
        <h3>{{ $q || $ver !== 'todos' ? 'Nadie coincide' : 'Aún no tienes atletas' }}</h3>
        <p>{{ $q || $ver !== 'todos' ? 'Prueba con otra búsqueda.' : 'Registra a tus atletas, asígnales un paquete y mándales su acceso para reservar.' }}</p>
        @unless($q || $ver !== 'todos')<a href="{{ route('admin.clientes.create') }}" class="btn btn-primario">Registrar atleta</a>@endunless
    </div></div>
@else
    <div class="panel">
        <div class="table-responsive"><table class="tabla">
            <thead><tr><th>Atleta</th><th>Paquete</th><th style="width: 170px">Clases</th><th>Vence</th><th>Última vez</th></tr></thead>
            <tbody>
            @foreach($atletas as $a)
                @php $m = $a->membresias->filter->vigente->sortBy(fn ($x) => [$x->con_creditos ? 0 : 1, $x->vence_el->toDateString()])->first(); @endphp
                <tr>
                    <td style="min-width: 220px"><a href="{{ route('admin.clientes.show', [$a, 'ver' => 'atleta']) }}" class="fila-link d-flex align-items-center gap-2">
                        <x-fitness.avatar :atleta="$a" />
                        <div class="min-w-0"><div class="principal text-truncate">{{ $a->nombre }}</div><div class="secundario small text-truncate">{{ $a->telefono ?: $a->email ?: 'Sin contacto' }}</div></div></a></td>
                    <td>@if($a->activo === false)<span class="estado estado-cancelada">Baja</span>
                        @elseif($m){{ $m->nombre }}@if(! $m->pagada) <span class="estado estado-rechazada ms-1">Debe</span>@endif
                        @else<span class="secundario">Sin paquete</span>@endif</td>
                    <td>@if($m)
                            @if($m->ilimitada)<span class="fx-pastilla"><i class="bi bi-infinity"></i> Ilimitada</span>
                            @else<x-fitness.ocupacion :ocupados="$m->usados" :cupo="$m->creditos" :creditos="true" />@endif
                        @endif</td>
                    <td class="num {{ $m && $m->dias_restantes <= 3 ? 'text-danger fw-medium' : '' }}">{{ $m ? $m->vence_el->locale('es')->isoFormat('D MMM') : '—' }}</td>
                    <td class="secundario small">{{ $a->ultima_asistencia ? \Illuminate\Support\Carbon::parse($a->ultima_asistencia)->locale('es')->diffForHumans() : 'Nunca' }} · {{ $a->asistencias_count }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
    @if($atletas->hasPages())<div class="mt-3">{{ $atletas->links('pagination::bootstrap-5') }}</div>@endif
@endif
@endsection
