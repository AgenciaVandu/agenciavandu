{{--
    "Cobrado este mes": vista rápida del dinero que entró en el mes en curso.
    Params: $mes (App\Support\EsteMes::datos()), $conIva (bool), $enlace (opcional)
--}}
@php
    $d = fn ($n) => '$' . number_format($n ?? 0, 0);
    $sube = $mes['variacion'] !== null && $mes['variacion'] >= 0;
@endphp
<section class="este-mes" aria-label="Cobrado este mes">
    <div class="em-principal">
        <div class="em-k"><span class="em-punto"></span> Cobrado en {{ mb_strtolower($mes['mes']) }}</div>
        <div class="em-v num">{{ $d($mes['cobrado']) }}</div>
        <div class="em-s num">
            {{ $mes['pagos'] }} {{ $mes['pagos'] === 1 ? 'pago recibido' : 'pagos recibidos' }}
            · {{ $conIva ? 'con IVA' : 'antes de IVA' }}
            @if($conIva && $mes['cobradoSinIva'] != $mes['cobradoConIva']) <span class="em-tenue">({{ $d($mes['cobradoSinIva']) }} sin IVA)</span>@endif
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
            <span class="em-tenue num">{{ $mes['ventas'] }} {{ $mes['ventas'] === 1 ? 'cotización aceptada' : 'cotizaciones aceptadas' }} · {{ $conIva ? 'con IVA' : 'antes de IVA' }}</span>
        </div>
        @if(! empty($enlace))
            <a href="{{ $enlace }}" class="em-link">{{ $textoEnlace ?? 'Ver el mes en Finanzas' }} <i class="bi bi-arrow-right"></i></a>
        @endif
    </div>
</section>
@once
@push('head')
<style>
    .este-mes { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr); gap: 24px; align-items: center; background: #13161D; color: #fff;
                border-radius: var(--radius, 14px); padding: 22px 26px; margin-bottom: 16px; position: relative; overflow: hidden; }
    .este-mes::after { content: ''; position: absolute; left: 0; bottom: 0; height: 3px; width: 100%; background: #00F385; }
    .em-k { display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: #B9BEC9; }
    .em-punto { width: 8px; height: 8px; border-radius: 50%; background: #00F385; box-shadow: 0 0 0 4px rgba(0,243,133,.15); }
    .em-v { font-size: clamp(32px, 4vw, 44px); font-weight: 600; letter-spacing: -.03em; line-height: 1.05; margin-top: 8px; }
    .em-s { font-size: 13.5px; color: #D5D9E1; margin-top: 6px; }
    .em-tenue { color: #9AA0AC; font-size: 13px; }
    .em-lado { display: grid; gap: 12px; justify-items: start; border-left: 1px solid rgba(255,255,255,.12); padding-left: 24px; }
    .em-comp { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .em-delta { display: inline-flex; align-items: center; gap: 4px; font-weight: 600; font-size: 13px; padding: 3px 9px; border-radius: 99px; }
    .em-delta.sube { background: rgba(0,243,133,.16); color: #4DFFAE; }
    .em-delta.baja { background: rgba(255,120,120,.16); color: #FF9C9C; }
    .em-vendido { display: grid; gap: 1px; }
    .em-vendido b { font-size: 20px; font-weight: 600; letter-spacing: -.01em; }
    .em-link { color: #00F385; font-size: 13.5px; font-weight: 500; text-decoration: none; }
    .em-link:hover { text-decoration: underline; color: #4DFFAE; }
    @media (max-width: 767.98px) { .este-mes { grid-template-columns: 1fr; padding: 18px; gap: 16px; } .em-lado { border-left: 0; padding-left: 0; border-top: 1px solid rgba(255,255,255,.12); padding-top: 14px; } }
</style>
@endpush
@endonce
