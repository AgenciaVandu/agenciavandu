@extends('atleta.layout')
@section('titulo', 'Clases')
@section('contenido')
<h1>Clases</h1>
<p class="sub">{{ ucfirst($fecha->locale('es')->isoFormat('dddd D [de] MMMM')) }}</p>

<nav class="dias" aria-label="Días" style="margin-top: 12px">
    @foreach($semana as $d)
        <a href="{{ route('atleta.clases', array_filter(['dia' => $d->toDateString(), 'tipo' => $tipo])) }}" class="{{ $d->isSameDay($fecha) ? 'on' : '' }}">
            <span>{{ $loop->first ? 'Hoy' : rtrim($d->locale('es')->isoFormat('ddd'), '.') }}</span><b class="num">{{ $d->day }}</b></a>
    @endforeach
</nav>

@if($tipos->count() > 1)
    <div class="chips">
        <a href="{{ route('atleta.clases', ['dia' => $fecha->toDateString()]) }}" class="{{ ! $tipo ? 'on' : '' }}">Todas</a>
        @foreach($tipos as $t)<a href="{{ route('atleta.clases', ['dia' => $fecha->toDateString(), 'tipo' => $t->id]) }}" class="{{ $tipo === $t->id ? 'on' : '' }}">{{ $t->nombre }}</a>@endforeach
    </div>
@endif

@unless($membresia)<div class="aviso info"><i class="bi bi-ticket-perforated"></i> Necesitas un paquete vigente para reservar. Pídelo en recepción.</div>@endunless
@unless($puedeReservar)<div class="aviso info"><i class="bi bi-info-circle"></i> Las reservaciones se hacen en recepción.</div>@endunless

<div class="lista">
    @forelse($clases as $c)
        @include('atleta._clase', ['c' => $c, 'mia' => $mias[$c->id] ?? null])
    @empty
        <div class="vacio">No hay clases este día.</div>
    @endforelse
</div>
@endsection
