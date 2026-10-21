@extends('atleta.layout')
@section('titulo', 'Historial')
@section('contenido')
<p style="margin: 0"><a href="{{ route('atleta.perfil') }}" class="sub" style="text-decoration: none"><i class="bi bi-chevron-left"></i> Perfil</a></p>
<h1>Tu historial</h1>
<div class="tarjeta" style="margin-top: 14px; display: flex; align-items: baseline; gap: 12px">
    <span class="cifra" style="font-size: 52px">{{ $asistencias }}</span><span class="sub">{{ $asistencias === 1 ? 'clase tomada' : 'clases tomadas' }} desde que llegaste</span>
</div>

<h2>Clases</h2>
<div class="lista">
    @forelse($reservaciones as $r)
        @php $c = $r->clase; @endphp
        <div class="clase" style="--c: {{ $c->color }}">
            <div class="hora"><span class="cifra">{{ $c->inicio->format('H:i') }}</span><small>{{ rtrim($c->inicio->locale('es')->isoFormat('D MMM'), '.') }}</small></div>
            <span class="riel" aria-hidden="true"></span>
            <div class="info"><div class="n">{{ $c->nombre }}</div><div class="m">{{ ucfirst($c->inicio->locale('es')->isoFormat('dddd')) }}</div></div>
            <span class="pill {{ $r->estado === 'asistio' ? 'ok' : ($r->estado === 'falta' ? 'llena' : '') }}">{{ $r->estado === 'asistio' ? 'Asististe' : ($r->estado === 'falta' ? 'Faltaste' : 'Cancelada') }}</span>
        </div>
    @empty
        <div class="vacio">Aún no tienes clases en tu historial.</div>
    @endforelse
</div>
@if($reservaciones->hasPages())<div style="margin-top: 14px; display: flex; justify-content: space-between">
    @if($reservaciones->previousPageUrl())<a href="{{ $reservaciones->previousPageUrl() }}" class="btn sec chico">Más recientes</a>@else<span></span>@endif
    @if($reservaciones->nextPageUrl())<a href="{{ $reservaciones->nextPageUrl() }}" class="btn sec chico">Anteriores</a>@endif</div>@endif

<h2>Tus paquetes</h2>
<div class="lista">
    @foreach($membresias as $m)
        <div class="tarjeta" style="padding: 14px 16px"><b>{{ $m->nombre }}</b><div class="sub" style="font-size: 14px">{{ $m->inicia_el->locale('es')->isoFormat('D MMM') }} al {{ $m->vence_el->locale('es')->isoFormat('D MMM YYYY') }}, {{ $m->ilimitada ? 'ilimitado' : $m->usados . ' de ' . $m->creditos . ' clases' }}</div></div>
    @endforeach
</div>
@endsection
