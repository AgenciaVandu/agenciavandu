@php
    $h = now()->diffInHours($p->vigente_hasta, false);
    $clase = $h <= 0 ? 'vig-vencida' : ($h <= 48 ? 'vig-pronto' : 'vig-ok');
    $txt = $h <= 0
        ? 'Venció ' . $p->vigente_hasta->locale('es')->diffForHumans()
        : 'Vence ' . $p->vigente_hasta->locale('es')->diffForHumans();
@endphp
<span class="{{ $clase }} small" title="{{ $p->vigencia_local->format('d/m/Y H:i') }}">
    <i class="bi {{ $h <= 0 ? 'bi-x-circle' : 'bi-clock' }}"></i> {{ $txt }}
</span>
