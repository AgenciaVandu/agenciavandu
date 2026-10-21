{{-- Tarjeta de sellos de una campaña. $a = avance de Programa::avance() --}}
@php
    $k = $a['campana'];
    $premiosEn = $a['premios']->keyBy(fn ($x) => $x['premio']->visitas);
    $n = min(20, $a['meta']);
@endphp
<div>
    <div class="camp-n">{{ $k->nombre }}<span class="num">{{ min($a['visitas'], $a['meta']) }} de {{ $a['meta'] }} visitas</span></div>
    @if($a['meta'] <= 20)
        <div class="sellos" style="margin-top: 10px; {{ $n < 5 ? 'grid-template-columns: repeat(' . $n . ', 1fr)' : '' }}" role="img" aria-label="{{ $a['visitas'] }} de {{ $a['meta'] }} visitas">
            @for($i = 1; $i <= $n; $i++)
                @php
                    $pr = $premiosEn->get($i);
                    $lleno = $i <= $a['visitas'];
                    $icono = $pr ? ('bi-gift' . ($lleno ? '-fill' : '')) : ($lleno ? 'bi-check-lg' : null);
                @endphp
                <span class="sello {{ $lleno ? 'lleno' : '' }} {{ $pr ? 'premio' : '' }}" title="{{ $pr ? $pr['premio']->nombre : 'Visita ' . $i }}">
                    @if($icono)
                        <i class="bi {{ $icono }}"></i>
                    @else
                        {{ $i }}
                    @endif
                </span>
            @endfor
        </div>
    @else
        <div style="height: 12px; border-radius: 99px; background: #F1E6DB; overflow: hidden; margin-top: 10px"><div style="height: 100%; width: {{ min(100, round($a['visitas'] / $a['meta'] * 100)) }}%; background: var(--purpura)"></div></div>
    @endif
    @foreach($a['disponibles'] as $x)
        <div class="ganado" style="margin-top: 10px"><i class="bi bi-gift-fill"></i><span><b>{{ $x['premio']->nombre }}</b> está listo. Pídelo en tu próxima visita{{ $k->vigente ? '' : ' (hasta el ' . $k->canje_hasta->locale('es')->isoFormat('D [de] MMMM') . ')' }}.</span></div>
    @endforeach
    @if($a['siguiente'])
        <p class="proximo" style="margin: 10px 0 0">Te {{ $a['siguiente']['faltan'] === 1 ? 'falta 1 visita' : 'faltan ' . $a['siguiente']['faltan'] . ' visitas' }} para <b>{{ $a['siguiente']['premio']->nombre }}</b>.</p>
    @elseif($a['disponibles']->isEmpty())
        <p class="proximo" style="margin: 10px 0 0">¡Completaste esta campaña! 🎉</p>
    @endif
</div>
