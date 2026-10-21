{{-- Su paquete vigente: anillo de clases y vigencia. $membresia = Membresia|null --}}
@if($membresia)
    @php
        $dias = $membresia->dias_restantes;
        $pocas = ! $membresia->ilimitada && $membresia->restantes <= 1;
        $vence = $dias <= 3;
    @endphp
    <div class="tarjeta paquete">
        @include('atleta._anillo', ['m' => $membresia])
        <div class="min-w-0">
            <div class="nombre-p">{{ $membresia->nombre }}</div>
            @unless($membresia->ilimitada)
                <div class="fila {{ $pocas ? 'alerta' : '' }}"><span>Usadas</span><span class="num">{{ $membresia->usados }} de {{ $membresia->creditos }}</span></div>
            @endunless
            <div class="fila {{ $vence ? 'alerta' : '' }}"><span>Vence</span><span>{{ $dias <= 0 ? 'hoy' : ($dias === 1 ? 'mañana' : $membresia->vence_el->locale('es')->isoFormat('D MMM')) }}</span></div>
        </div>
    </div>
    @if($pocas || $vence)
        <div class="aviso info" style="margin-top: 10px"><i class="bi bi-lightning-charge-fill"></i> {{ $pocas ? ($membresia->restantes === 0 ? 'Ya usaste todas tus clases.' : 'Te queda una clase.') : 'Tu paquete vence pronto.' }} Renuévalo en recepción para no perder tu ritmo.</div>
    @endif
@else
    <div class="aviso info"><i class="bi bi-ticket-perforated"></i> No tienes un paquete vigente. Pídelo en recepción para reservar tus clases.</div>
@endif
