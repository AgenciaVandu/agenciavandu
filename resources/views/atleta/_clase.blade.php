{{-- Una clase en el portal. $c = Clase (con ocupadas_count), $mia = id de su reservación o null, $puedeReservar, $conDia --}}
@php
    $libres = $c->libres;
    $estado = $c->en_curso ? ['En curso', 'ok vivo'] : ($mia ? ['Reservada', 'ok'] : ($libres === 0 ? ['Llena', 'llena'] : ($libres <= 3 ? ($libres === 1 ? 'Último lugar' : "Quedan $libres") : null)));
    if (is_string($estado)) $estado = [$estado, 'pocos'];
    $min = $c->inicia_at->diffInMinutes($c->termina_at);
@endphp
<div class="clase" style="--c: {{ $c->color }}">
    <div class="hora"><span class="cifra">{{ $c->inicio->format('H:i') }}</span><small class="num">{{ $min }} min</small></div>
    <span class="riel" aria-hidden="true"></span>
    <a href="{{ route('atleta.clase', $c) }}" class="info">
        @if($conDia ?? false)<div class="dia">{{ ucfirst(rtrim($c->inicio->locale('es')->isoFormat('ddd D MMM'), '.')) }}</div>@endif
        <div class="n">{{ $c->nombre }}</div>
        <div class="m">{{ collect([$c->coach?->primer_nombre ? 'Con ' . $c->coach->primer_nombre : null, $c->sucursal?->nombre])->filter()->join(', ') ?: ' ' }}</div>
        @include('atleta._cupo', ['c' => $c, 'mia' => (bool) $mia])
    </a>
    <div class="accion">
        @if($estado)<span class="pill {{ $estado[1] }}">{{ $estado[0] }}</span>@endif
        @if($puedeReservar && ! $c->en_curso)
            @if($mia)
                <form method="post" action="{{ route('atleta.cancelar', $mia) }}" onsubmit="return confirm({{ Js::from('¿Cancelar tu lugar en ' . $c->nombre . '?') }})">@csrf<button class="btn sec chico">Cancelar</button></form>
            @elseif($libres > 0)
                <form method="post" action="{{ route('atleta.reservar', $c) }}">@csrf<button class="btn chico">Reservar</button></form>
            @endif
        @endif
    </div>
</div>
