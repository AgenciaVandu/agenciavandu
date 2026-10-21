{{-- Su paquete vigente: clases que le quedan y vigencia. $membresia = Membresia|null --}}
@if($membresia)
    <div class="creditos">
        <div><div class="k">{{ $membresia->nombre }}</div>
            <div class="k" style="margin-top:2px">Vence el {{ $membresia->vence_el->locale('es')->isoFormat('D [de] MMMM') }}</div></div>
        <div style="text-align: right"><div class="v num">{{ $membresia->ilimitada ? '∞' : $membresia->restantes }}</div><div class="k">{{ $membresia->ilimitada ? 'ilimitada' : ($membresia->restantes === 1 ? 'clase' : 'clases') }}</div></div>
        @unless($membresia->ilimitada)<div class="barra"><span style="width: {{ $membresia->creditos ? round($membresia->restantes / $membresia->creditos * 100) : 0 }}%"></span></div>@endunless
    </div>
    @if(! $membresia->ilimitada && $membresia->restantes <= 1 || $membresia->dias_restantes <= 3)
        <div class="aviso info" style="margin-top: 10px"><i class="bi bi-hourglass-split"></i> {{ ! $membresia->ilimitada && $membresia->restantes <= 1 ? ($membresia->restantes === 0 ? 'Ya usaste todas tus clases.' : 'Te queda una clase.') : 'Tu paquete vence pronto.' }} Renuévalo en recepción.</div>
    @endif
@else
    <div class="aviso info"><i class="bi bi-ticket-perforated"></i> No tienes un paquete vigente. Pídelo en recepción para reservar tus clases.</div>
@endif
