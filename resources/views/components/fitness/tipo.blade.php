@props(['tipo'])
<span {{ $attributes->merge(['class' => 'fx-pastilla']) }}><span class="pt" style="background: {{ $tipo?->color ?? '#13161D' }}"></span>{{ $tipo?->nombre ?? 'Clase' }}</span>
