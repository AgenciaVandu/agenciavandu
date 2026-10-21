@extends('atleta.layout')
@section('titulo', 'Inicio')
@php
    $puedeReservar = (bool) \App\Support\Fitness\Ajustes::get('atleta_reserva');
    $siguiente = $proximas->first();
    $resto = $proximas->slice(1)->values();
@endphp
@section('contenido')
<h1>Hola, {{ $a->primer_nombre }}</h1>
<p class="sub">{{ $asistencias === 0 ? 'Tu primera clase te espera.' : ($asistencias === 1 ? 'Llevas 1 clase. Esto apenas empieza.' : "Llevas $asistencias clases. Sigue sumando.") }}</p>

{{-- Próxima clase con cuenta regresiva --}}
<div style="margin-top: 18px">
@if($siguiente)
    @php $c = $siguiente->clase; @endphp
    <a href="{{ route('atleta.clase', $c) }}" class="boleto" style="--c: {{ $c->color }}" data-inicio="{{ $c->inicia_at->toIso8601String() }}" data-fin="{{ $c->termina_at->toIso8601String() }}" id="boleto">
        <div class="que">Tu próxima clase</div>
        <div class="nombre">{{ $c->nombre }}</div>
        <div class="cuando">{{ ucfirst($c->inicio->locale('es')->isoFormat('dddd D [de] MMMM')) }}, <span class="num">{{ $c->horario }}</span></div>
        <div class="reloj"><span class="cifra" id="cuenta">{{ $c->inicio->locale('es')->diffForHumans() }}</span><span class="eti" id="cuenta-eti">para empezar</span></div>
        <div class="pie">
            <span>{{ collect([$c->coach?->primer_nombre ? 'Con ' . $c->coach->primer_nombre : null, $c->sucursal?->nombre])->filter()->join(', ') }}</span>
            <span class="num">{{ $c->ocupados }}/{{ $c->cupo }} lugares</span>
        </div>
    </a>
@else
    <div class="vacio">No tienes clases reservadas.<br><a href="{{ route('atleta.clases') }}" class="btn" style="margin-top: 14px"><i class="bi bi-calendar-plus"></i> Reservar una clase</a></div>
@endif
</div>

<h2>Tu paquete</h2>
@include('atleta._creditos')

@if($resto->isNotEmpty())
    <h2>También reservaste</h2>
    <div class="lista">
        @foreach($resto as $r)
            @include('atleta._clase', ['c' => $r->clase->loadCount('ocupadas'), 'mia' => $r->id, 'conDia' => true, 'puedeReservar' => $puedeReservar])
        @endforeach
    </div>
@endif

@if($hoy->isNotEmpty())
    <h2>Hoy en el gimnasio <a href="{{ route('atleta.clases') }}">Ver todas</a></h2>
    <div class="lista">
        @php $mias = $a->reservaciones()->whereIn('clase_id', $hoy->pluck('id'))->where('estado', '!=', 'cancelada')->pluck('id', 'clase_id'); @endphp
        @foreach($hoy as $c)@include('atleta._clase', ['c' => $c, 'mia' => $mias[$c->id] ?? null, 'puedeReservar' => $puedeReservar])@endforeach
    </div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const b = document.getElementById('boleto'); if (!b) return;
    const ini = new Date(b.dataset.inicio).getTime(), fin = new Date(b.dataset.fin).getTime();
    const cifra = document.getElementById('cuenta'), eti = document.getElementById('cuenta-eti');
    const dos = (n) => String(n).padStart(2, '0');
    function pintar() {
        const ahora = Date.now(), falta = Math.floor((ini - ahora) / 1000);
        if (ahora >= fin) { cifra.textContent = 'Terminó'; eti.textContent = ''; return false; }
        if (falta <= 0) { cifra.textContent = 'En curso'; eti.textContent = '¡a darle!'; b.classList.add('pronto'); return true; }
        const d = Math.floor(falta / 86400), h = Math.floor(falta % 86400 / 3600), m = Math.floor(falta % 3600 / 60), s = falta % 60;
        if (d >= 1) { cifra.textContent = d + (d === 1 ? ' día' : ' días'); eti.textContent = dos(h) + ' h ' + dos(m) + ' min para empezar'; }
        else { cifra.textContent = dos(h) + ':' + dos(m) + ':' + dos(s); eti.textContent = 'para empezar'; }
        b.classList.toggle('pronto', falta < 3600);
        return true;
    }
    if (pintar()) { const t = setInterval(() => { if (!pintar()) clearInterval(t); }, 1000); }
})();
</script>
@endpush
