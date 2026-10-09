@php
    $h = now()->diffInHours($p->vigente_hasta, false);
    $cerrada = in_array($p->estado, ['aceptada', 'rechazada', 'negociacion']);
    $rel = $p->vigente_hasta->locale('es')->diffForHumans(['parts' => 1, 'syntax' => \Carbon\CarbonInterface::DIFF_RELATIVE_TO_NOW]);
    if ($p->estado === 'negociacion' && $h <= 0) {
        // Sigue viva aunque haya pasado la fecha: se muestra así para no confundir con "perdida"
        $clase = 'vig-pronto'; $icono = 'bi-chat-dots'; $txt = 'Vigencia pasada · en negociación';
    } else {
        $clase = $h <= 0 ? 'vig-vencida' : ($h <= 72 && ! $cerrada ? 'vig-pronto' : 'vig-ok');
        $txt = $h <= 0 ? 'Venció ' . $rel : (! empty($corto) ? ucfirst(str_replace('dentro de ', 'en ', $rel)) : 'Vence ' . str_replace('dentro de ', 'en ', $rel));
        $icono = $h <= 0 ? 'bi-x-circle' : ($clase === 'vig-pronto' ? 'bi-hourglass-split' : 'bi-clock');
    }
@endphp
<span class="vig {{ $clase }}" title="Vigente hasta el {{ $p->vigencia_local->format('d/m/Y H:i') }}"><i class="bi {{ $icono }}"></i> {{ $txt }}</span>
