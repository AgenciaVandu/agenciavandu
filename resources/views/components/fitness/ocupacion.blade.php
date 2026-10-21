@props(['ocupados' => 0, 'cupo' => 1, 'creditos' => false])
{{-- Barra de cupo de una clase (o de clases usadas de un paquete con :creditos="true") --}}
@php
    $pct = $cupo > 0 ? min(100, round($ocupados / $cupo * 100)) : 0;
    $libres = max(0, $cupo - $ocupados);
    $texto = $creditos
        ? ($libres === 0 ? 'Sin clases' : 'Quedan ' . $libres)
        : ($libres === 0 ? 'Llena' : $libres . ' ' . ($libres === 1 ? 'libre' : 'libres'));
@endphp
<div {{ $attributes->merge(['class' => 'fx-ocupacion']) }} title="{{ $ocupados }} de {{ $cupo }}">
    <div class="barra {{ $libres === 0 ? 'llena' : ($pct >= 80 ? 'casi' : '') }}"><span style="width: {{ $pct }}%"></span></div>
    <div class="t num"><span>{{ $ocupados }}/{{ $cupo }}</span><span>{{ $texto }}</span></div>
</div>
