@props(['atleta', 'grande' => false])
@if($atleta?->foto_url)
    <img src="{{ $atleta->foto_url }}" alt="" {{ $attributes->merge(['class' => 'fx-avatar' . ($grande ? ' g' : '')]) }} loading="lazy">
@else
    <span {{ $attributes->merge(['class' => 'fx-avatar' . ($grande ? ' g' : '')]) }} aria-hidden="true">{{ $atleta?->iniciales }}</span>
@endif
