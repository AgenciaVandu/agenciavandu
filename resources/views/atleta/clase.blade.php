@extends('atleta.layout')
@section('titulo', $c->nombre)
@php $min = $c->inicia_at->diffInMinutes($c->termina_at); $horas = (int) \App\Support\Fitness\Ajustes::get('horas_cancelar'); @endphp
@section('contenido')
<p style="margin: 0"><a href="{{ route('atleta.clases', ['dia' => $c->inicio->toDateString()]) }}" class="sub" style="text-decoration: none"><i class="bi bi-chevron-left"></i> Clases</a></p>

<div class="boleto" style="--c: {{ $c->color }}; margin-top: 12px">
    <div class="que">{{ ucfirst($c->inicio->locale('es')->isoFormat('dddd D [de] MMMM')) }}</div>
    <div class="nombre">{{ $c->nombre }}</div>
    <div class="reloj" style="margin-top: 10px"><span class="cifra">{{ $c->inicio->format('H:i') }}</span><span class="eti num">{{ $min }} min · termina {{ $c->fin->format('H:i') }}</span></div>
    <div class="pie">
        <span>{{ collect([$c->coach ? 'Con ' . $c->coach->primer_nombre : null, $c->sucursal?->nombre])->filter()->join(', ') ?: ' ' }}</span>
        @if($c->cancelada)<span class="pill llena">Cancelada</span>
        @elseif($mia)<span class="pill ok">Tienes lugar</span>
        @elseif($c->libres === 0)<span class="pill llena">Llena</span>
        @else<span class="num">{{ $c->libres }} {{ $c->libres === 1 ? 'lugar libre' : 'lugares libres' }}</span>@endif
    </div>
    @unless($c->cancelada)<div style="position: relative; z-index: 1">@include('atleta._cupo', ['c' => $c, 'mia' => (bool) $mia])</div>@endunless
</div>

@if($c->tipo?->descripcion)<p class="sub" style="margin-top: 16px; color: var(--tiza)">{{ $c->tipo->descripcion }}</p>@endif

@if($puedeReservar && ! $c->cancelada && ! $c->en_curso && ! $c->pasada)
    <div style="margin-top: 18px">
        @if($mia)
            <form method="post" action="{{ route('atleta.cancelar', $mia) }}" onsubmit="return confirm('¿Cancelar tu lugar?')">@csrf
                <button class="btn peligro full">Cancelar mi lugar</button></form>
            <p class="sub" style="font-size: 13.5px; text-align: center; margin-top: 8px">
                @if($mia->credito_usado)
                    @if($c->inicia_at->copy()->subHours($horas)->isFuture())Si cancelas antes del {{ $c->inicio->copy()->subHours($horas)->locale('es')->isoFormat('ddd D [a las] H:mm') }}, te regresamos tu clase.
                    @else Ya pasó el límite de {{ $horas }} horas: si cancelas, la clase se descuenta de tu paquete.@endif
                @else Con tu membresía ilimitada puedes cancelar sin que se descuente nada.@endif
            </p>
        @elseif($c->libres > 0)
            <form method="post" action="{{ route('atleta.reservar', $c) }}">@csrf<button class="btn full"><i class="bi bi-lightning-charge-fill"></i> Reservar mi lugar</button></form>
        @endif
    </div>
@endif

@if($c->bloques)
    <h2>Entrenamiento</h2>
    <div class="tarjeta" style="--c: {{ $c->color }}">
        @foreach($c->bloques as $b)
            <div class="bloque">
                <div class="bn">{{ $b['nombre'] ?: 'Bloque' }}@if(! empty($b['formato']))<span>{{ $b['formato'] }}</span>@endif</div>
                <ol>@foreach($b['ejercicios'] as $e)<li><span class="cant">{{ $e['cantidad'] ?: '—' }}</span><span>{{ $e['nombre'] }}@if($e['nota'])<br><span class="nota">{{ $e['nota'] }}</span>@endif</span></li>@endforeach</ol>
            </div>
        @endforeach
    </div>
@endif
@endsection
