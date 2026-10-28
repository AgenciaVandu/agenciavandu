{{--
    "Cobrado este mes": vista rápida del dinero que entró en el mes en curso.
    Params: $mes (App\Support\EsteMes::datos()), $conIva (bool), $enlace (opcional)
--}}
@php
    $d = fn ($n) => '$' . number_format($n ?? 0, 0);
    $sube = $mes['variacion'] !== null && $mes['variacion'] >= 0;
    // Línea del mes: lo cobrado acumulado, día por día
    $serie = $mes['serie'] ?? [];
    $tope = max(1, max($serie ?: [0]));
    $nS = max(1, count($serie) - 1);
    $puntos = collect($serie)->map(fn ($v, $i) => round($i / $nS * 600, 1) . ',' . round(100 - $v / $tope * 88, 1))->join(' ');
@endphp
<section class="este-mes" aria-label="Cobrado este mes">
    <div class="em-principal">
        <div class="em-k"><span class="em-punto"></span> Cobrado en {{ mb_strtolower($mes['mes']) }}</div>
        <div class="em-v num">{{ $d($mes['cobrado']) }}</div>
        <div class="em-s num">
            {{ $mes['pagos'] }} {{ $mes['pagos'] === 1 ? 'pago recibido' : 'pagos recibidos' }}
            · {{ $conIva ? 'con IVA' : 'antes de IVA' }}
            @if($mes['cobradoSinIva'] != $mes['cobradoConIva'])
                <span class="em-tenue">({{ $conIva ? $d($mes['cobradoSinIva']) . ' sin IVA' : $d($mes['cobradoConIva']) . ' con IVA' }})</span>
            @endif
        </div>
    </div>
    <div class="em-lado">
        <div class="em-comp">
            @if($mes['variacion'] !== null)
                <span class="em-delta {{ $sube ? 'sube' : 'baja' }}"><i class="bi {{ $sube ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i> {{ abs($mes['variacion']) }}%</span>
                <span class="em-tenue num">vs. {{ $d($mes['anterior']) }} del 1 al {{ $mes['alDia'] }}</span>
            @else
                <span class="em-tenue">Sin cobros en el mismo tramo del mes pasado</span>
            @endif
        </div>
        <div class="em-vendido">
            <span class="em-tenue">Vendido este mes</span>
            <b class="num">{{ $d($mes['vendido']) }}</b>
            <span class="em-tenue num">{{ $mes['ventas'] }} {{ \App\Support\Cuentas::actual()?->tiene('stock') ? ($mes['ventas'] === 1 ? 'venta' : 'ventas') : ($mes['ventas'] === 1 ? 'cotización aceptada' : 'cotizaciones aceptadas') }} · {{ $conIva ? 'con IVA' : 'antes de IVA' }}</span>
        </div>
        @if(isset($teDeben))
            <div class="em-vendido">
                <span class="em-tenue">Te deben</span>
                <b class="num" style="{{ $teDeben > 0 ? 'color: #FFC46B' : '' }}">{{ $d($teDeben) }}</b>
                @if($teDeben > 0)<a href="{{ route('admin.cobrar') }}" class="em-link" style="font-size: 13px">Registrar pago <i class="bi bi-arrow-right"></i></a>@endif
            </div>
        @endif
        @if(! empty($enlace))
            <a href="{{ $enlace }}" class="em-link">{{ $textoEnlace ?? 'Ver el mes en Finanzas' }} <i class="bi bi-arrow-right"></i></a>
        @endif
    </div>
    @if(count($serie) > 1 && max($serie) > 0)
        <svg class="em-graf" viewBox="0 0 600 104" preserveAspectRatio="none" aria-hidden="true">
            <line x1="0" y1="103" x2="600" y2="103" stroke="rgba(255,255,255,.14)"></line>
            <polyline points="{{ $puntos }}" fill="none" stroke="#A99BFF" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"></polyline>
        </svg>
    @endif
</section>
@once
@push('head')
<style>
    .este-mes { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr); gap: 18px 24px; align-items: start; background: var(--ink, #16122E); color: #fff;
                border-radius: 18px; padding: 24px 26px; margin-bottom: 0; position: relative; overflow: hidden; }
    .em-k { display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: #C9C4E4; }
    .em-punto { width: 8px; height: 8px; border-radius: 50%; background: #A99BFF; box-shadow: 0 0 0 4px rgba(169,155,255,.18); }
    .em-v { font-size: clamp(32px, 4vw, 42px); font-weight: 600; letter-spacing: -.03em; line-height: 1.05; margin-top: 8px; }
    .em-s { font-size: 13.5px; color: #DCD8EE; margin-top: 6px; }
    .em-tenue { color: #ABA5C9; font-size: 13px; }
    .em-lado { display: grid; gap: 12px; justify-items: start; border-left: 1px solid rgba(255,255,255,.12); padding-left: 24px; }
    .em-comp { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .em-delta { display: inline-flex; align-items: center; gap: 4px; font-weight: 600; font-size: 13px; padding: 3px 9px; border-radius: 99px; }
    .em-delta.sube { background: rgba(77,214,160,.16); color: #7EE6BD; }
    .em-delta.baja { background: rgba(255,120,120,.16); color: #FFB0B0; }
    .em-vendido { display: grid; gap: 1px; }
    .em-vendido b { font-size: 20px; font-weight: 600; letter-spacing: -.01em; }
    .em-link { color: #C9C0FF; font-size: 13.5px; font-weight: 500; text-decoration: none; }
    .em-link:hover { text-decoration: underline; color: #fff; }
    .em-graf { grid-column: 1 / -1; width: 100%; height: 96px; display: block; }
    @media (max-width: 767.98px) { .este-mes { grid-template-columns: minmax(0, 1fr); padding: 18px; gap: 16px; } .em-lado { border-left: 0; padding-left: 0; border-top: 1px solid rgba(255,255,255,.12); padding-top: 14px; } }
</style>
@endpush
@endonce
