@extends('atleta.layout')
@section('titulo', 'Inicio')
@section('contenido')
<h1>Hola, {{ $a->primer_nombre }}</h1>
<p class="sub">{{ $asistencias }} {{ $asistencias === 1 ? 'clase tomada' : 'clases tomadas' }}. ¡Sigue así!</p>

<div style="margin-top: 16px">@include('atleta._creditos')</div>

<h2>Tus próximas clases <a href="{{ route('atleta.clases') }}">Reservar</a></h2>
<div class="lista">
    @forelse($proximas as $r)
        @include('atleta._clase', ['c' => $r->clase->loadCount('ocupadas'), 'mia' => $r->id, 'conDia' => true, 'puedeReservar' => (bool) \App\Support\Fitness\Ajustes::get('atleta_reserva')])
    @empty
        <div class="vacio">No tienes clases reservadas.<br><a href="{{ route('atleta.clases') }}" class="btn" style="margin-top: 12px">Ver clases</a></div>
    @endforelse
</div>

@if($hoy->isNotEmpty())
    <h2>Hoy en el gimnasio <a href="{{ route('atleta.clases') }}">Todas</a></h2>
    <div class="lista">
        @php $mias = $a->reservaciones()->whereIn('clase_id', $hoy->pluck('id'))->where('estado', '!=', 'cancelada')->pluck('id', 'clase_id'); @endphp
        @foreach($hoy as $c)@include('atleta._clase', ['c' => $c, 'mia' => $mias[$c->id] ?? null, 'puedeReservar' => (bool) \App\Support\Fitness\Ajustes::get('atleta_reserva')])@endforeach
    </div>
@endif

<a href="{{ route('atleta.credencial') }}" class="btn sec full" style="margin-top: 22px"><i class="bi bi-qr-code"></i> Mi credencial para entrar</a>
@endsection
