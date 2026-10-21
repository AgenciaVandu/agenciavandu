{{-- Cupo de la clase: una marca por lugar (ocupados en gris, el tuyo con el color de la clase) --}}
@php $ocup = (int) ($c->ocupadas_count ?? $c->ocupados); @endphp
@if($c->cupo <= 30)
    <div class="cupo" aria-label="{{ $ocup }} de {{ $c->cupo }} lugares ocupados">
        @for($i = 0; $i < $c->cupo; $i++)<i class="{{ $i < $ocup ? (($mia ?? false) && $i === $ocup - 1 ? 'yo' : 'o') : '' }}"></i>@endfor
    </div>
@else
    <div class="cupo continuo" aria-label="{{ $ocup }} de {{ $c->cupo }} lugares ocupados"><span style="width: {{ min(100, round($ocup / max(1, $c->cupo) * 100)) }}%"></span></div>
@endif
