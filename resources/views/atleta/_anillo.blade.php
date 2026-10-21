{{-- Anillo del paquete: un segmento por clase (encendido = le queda, apagado = ya la usó). Ilimitada = anillo completo --}}
@php
    $total = $m->ilimitada ? 0 : (int) $m->creditos;
    $quedan = $m->ilimitada ? 0 : max(0, (int) $m->restantes);
    $r = 54; $cx = 60; $cy = 60;
    $seg = $total > 0 && $total <= 40;
    $arcos = [];
    if ($seg) {
        $hueco = $total > 20 ? 2.2 : 4;   // grados entre segmentos
        $paso = 360 / $total;
        for ($i = 0; $i < $total; $i++) {
            $a0 = deg2rad($i * $paso + $hueco / 2); $a1 = deg2rad(($i + 1) * $paso - $hueco / 2);
            $arcos[] = ['d' => sprintf('M %.2f %.2f A %d %d 0 %d 1 %.2f %.2f', $cx + $r * cos($a0), $cy + $r * sin($a0), $r, $r, ($paso - $hueco) > 180 ? 1 : 0, $cx + $r * cos($a1), $cy + $r * sin($a1)), 'on' => $i < $quedan];
        }
    }
    $color = ! $m->ilimitada && $quedan <= 1 ? 'var(--calor)' : 'var(--senal)';
@endphp
<div class="anillo" role="img" aria-label="{{ $m->ilimitada ? 'Clases ilimitadas' : "Te quedan $quedan de $total clases" }}">
    <svg viewBox="0 0 120 120" aria-hidden="true">
        @if($seg)
            @foreach($arcos as $arc)<path d="{{ $arc['d'] }}" fill="none" stroke="{{ $arc['on'] ? $color : 'var(--riel)' }}" stroke-width="9" stroke-linecap="butt"/>@endforeach
        @else
            <circle cx="60" cy="60" r="54" fill="none" stroke="var(--riel)" stroke-width="9"/>
            <circle cx="60" cy="60" r="54" fill="none" stroke="{{ $color }}" stroke-width="9" stroke-dasharray="{{ round(2 * M_PI * 54, 2) }}" stroke-dashoffset="{{ $m->ilimitada ? 0 : round(2 * M_PI * 54 * (1 - ($total ? $quedan / $total : 0)), 2) }}"/>
        @endif
    </svg>
    <div class="centro"><span class="cifra">{{ $m->ilimitada ? '∞' : $quedan }}</span><small>{{ $m->ilimitada ? 'ilimitada' : ($quedan === 1 ? 'clase' : 'clases') }}</small></div>
</div>
