{{-- Una clase en el portal. $c = Clase (con ocupadas_count), $mia = id de su reservación o null, $puedeReservar --}}
@php
    $libres = $c->libres;
    $estado = $mia ? ['Reservada', 'ok'] : ($libres === 0 ? ['Llena', 'llena'] : ($libres <= 3 ? [$libres . ' ' . ($libres === 1 ? 'lugar' : 'lugares'), 'pocos'] : [$libres . ' lugares', '']));
@endphp
<div class="clase">
    <span class="c" style="background: {{ $c->color }}"></span>
    <a href="{{ route('atleta.clase', $c) }}" style="text-decoration: none; min-width: 0">
        <div class="h num">@if($conDia ?? false){{ ucfirst(rtrim($c->inicio->locale('es')->isoFormat('ddd D MMM'), '.')) }} · @endif{{ $c->horario }}@if($c->en_curso) · <b>en curso</b>@endif</div>
        <div class="n">{{ $c->nombre }}</div>
        <div class="m">{{ collect([$c->coach?->primer_nombre ? 'Con ' . $c->coach->primer_nombre : null, $c->sucursal?->nombre])->filter()->join(' · ') }}</div>
    </a>
    <div style="display: grid; gap: 6px; justify-items: end">
        <span class="pill {{ $estado[1] }}">{{ $estado[0] }}</span>
        @if($puedeReservar && ! $c->en_curso)
            @if($mia)
                <form method="post" action="{{ route('atleta.cancelar', $mia) }}" onsubmit="return confirm('¿Cancelar tu lugar?')">@csrf<button class="btn sec chico">Cancelar</button></form>
            @elseif($libres > 0)
                <form method="post" action="{{ route('atleta.reservar', $c) }}">@csrf<button class="btn chico">Reservar</button></form>
            @endif
        @endif
    </div>
</div>
