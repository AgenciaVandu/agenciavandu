@extends('atleta.layout')
@section('titulo', $c->nombre)
@section('contenido')
<p style="margin: 0"><a href="{{ route('atleta.clases', ['dia' => $c->inicio->toDateString()]) }}" class="sub" style="text-decoration: none">← Clases</a></p>
<h1>{{ $c->nombre }}</h1>
<p class="sub">{{ $c->fecha_texto }} · {{ $c->horario }}{{ $c->coach ? ' · con ' . $c->coach->primer_nombre : '' }}{{ $c->sucursal ? ' · ' . $c->sucursal->nombre : '' }}</p>

<div class="tarjeta" style="margin-top: 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px">
    <div><b class="num">{{ $c->ocupados }} de {{ $c->cupo }}</b><div class="sub" style="font-size: 14px">lugares ocupados</div></div>
    @if($c->cancelada)<span class="pill llena">Cancelada</span>
    @elseif($mia)<span class="pill ok">Tienes lugar</span>
    @elseif($c->libres === 0)<span class="pill llena">Llena</span>
    @else<span class="pill">{{ $c->libres }} libres</span>@endif
</div>

@if($c->tipo?->descripcion)<p style="margin-top: 14px">{{ $c->tipo->descripcion }}</p>@endif

@if($c->bloques)
    <h2>Entrenamiento</h2>
    <div class="tarjeta">
        @foreach($c->bloques as $b)
            <div class="bloque"><b>{{ $b['nombre'] ?: 'Bloque' }}</b>@if(! empty($b['formato']))<span class="sub"> · {{ $b['formato'] }}</span>@endif
                <ul>@foreach($b['ejercicios'] as $e)<li>{{ $e['cantidad'] ? $e['cantidad'] . ' ' : '' }}{{ $e['nombre'] }}@if($e['nota'])<span class="sub"> — {{ $e['nota'] }}</span>@endif</li>@endforeach</ul></div>
        @endforeach
    </div>
@endif

@if($puedeReservar && ! $c->cancelada && ! $c->en_curso && ! $c->pasada)
    <div style="margin-top: 20px">
        @if($mia)
            <form method="post" action="{{ route('atleta.cancelar', $mia) }}" onsubmit="return confirm('¿Cancelar tu lugar?')">@csrf
                <button class="btn peligro full">Cancelar mi lugar</button></form>
            <p class="sub" style="font-size: 13.5px; text-align: center; margin-top: 8px">Si cancelas con menos de {{ \App\Support\Fitness\Ajustes::get('horas_cancelar') }} horas, la clase se descuenta de tu paquete.</p>
        @elseif($c->libres > 0)
            <form method="post" action="{{ route('atleta.reservar', $c) }}">@csrf<button class="btn full">Reservar mi lugar</button></form>
        @endif
    </div>
@endif
@endsection
