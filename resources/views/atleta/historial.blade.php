@extends('atleta.layout')
@section('titulo', 'Historial')
@section('contenido')
<p style="margin: 0"><a href="{{ route('atleta.perfil') }}" class="sub" style="text-decoration: none">← Perfil</a></p>
<h1>Tu historial</h1>
<p class="sub">{{ $asistencias }} {{ $asistencias === 1 ? 'clase tomada' : 'clases tomadas' }}</p>

<h2>Clases</h2>
<div class="lista">
    @forelse($reservaciones as $r)
        <div class="clase">
            <span class="c" style="background: {{ $r->clase->color }}"></span>
            <div><div class="h">{{ $r->clase->fecha_texto }} · {{ $r->clase->horario }}</div><div class="n">{{ $r->clase->nombre }}</div></div>
            <span class="pill {{ $r->estado === 'asistio' ? 'ok' : ($r->estado === 'falta' ? 'llena' : '') }}">{{ $r->estado === 'asistio' ? 'Asististe' : ($r->estado === 'falta' ? 'Faltaste' : 'Cancelada') }}</span>
        </div>
    @empty
        <div class="vacio">Aún no tienes clases en tu historial.</div>
    @endforelse
</div>
@if($reservaciones->hasPages())<div style="margin-top: 14px; display: flex; justify-content: space-between">
    @if($reservaciones->previousPageUrl())<a href="{{ $reservaciones->previousPageUrl() }}" class="btn sec chico">Anteriores</a>@else<span></span>@endif
    @if($reservaciones->nextPageUrl())<a href="{{ $reservaciones->nextPageUrl() }}" class="btn sec chico">Más</a>@endif</div>@endif

<h2>Tus paquetes</h2>
<div class="lista">
    @foreach($membresias as $m)
        <div class="tarjeta"><b>{{ $m->nombre }}</b><div class="sub" style="font-size: 14px">{{ $m->inicia_el->locale('es')->isoFormat('D MMM') }} – {{ $m->vence_el->locale('es')->isoFormat('D MMM YYYY') }} · {{ $m->ilimitada ? 'ilimitado' : $m->usados . ' de ' . $m->creditos . ' clases' }}</div></div>
    @endforeach
</div>
@endsection
