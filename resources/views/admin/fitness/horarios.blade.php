@extends('admin.layout')
@section('titulo', 'Horarios')
@include('admin.fitness._estilos')

@php
    use App\Support\Fitness\Horarios\Datos;
    use App\Support\Fitness\Horarios\Hallazgo;
    $d = $datos;
    $c = $d->clases;
    $hay = $d->suficiente();
    $q = fn (array $x) => route('admin.fitness-reportes.horarios', array_filter(array_merge(['semanas' => $semanas === 8 ? null : $semanas, 'sucursal' => $sucursal], $x)));
    $tono = fn (int $o) => 'color-mix(in srgb, var(--marca) ' . (12 + (int) round(min(100, $o) * .8)) . '%, white)';
    $confTxt = ['alta' => 'Confianza alta', 'media' => 'Confianza media', 'baja' => 'Pocos datos'];
    $confN = ['alta' => 3, 'media' => 2, 'baja' => 1];
@endphp

@push('head')
<style>
    .hz-nav { margin-bottom: 18px; }
    /* El analista: franja oscura con el personaje */
    .hz-analista { position: relative; overflow: hidden; display: grid; grid-template-columns: 96px minmax(0, 1fr); gap: 20px; align-items: center; padding: 20px 24px;
                   background: radial-gradient(80% 140% at 100% 0%, rgba(107,59,255,.55) 0%, transparent 60%), var(--ink); color: #fff; border-radius: 16px; margin-bottom: 20px; }
    .hz-analista img { width: 96px; height: auto; align-self: end; margin-bottom: -20px; }
    .hz-analista h2 { font-size: 20px; font-weight: 700; margin: 0 0 4px; letter-spacing: -.01em; }
    .hz-analista .que { color: #C9C2E6; font-size: 14px; }
    .hz-analista .chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
    .hz-analista .chips span { font-size: 12.5px; padding: 3px 10px; border-radius: 99px; background: rgba(255,255,255,.1); color: #E9E5F7; }
    .hz-pasos { list-style: none; margin: 10px 0 0; padding: 0; display: grid; gap: 4px; font-size: 13.5px; color: #C9C2E6; }
    .hz-pasos li { display: flex; align-items: center; gap: 8px; }
    .hz-pasos li i { width: 16px; text-align: center; }
    .hz-pasos li.ok i { color: var(--cian); }
    .hz-girando { display: inline-block; width: 12px; height: 12px; border-radius: 50%; border: 2px solid rgba(255,255,255,.25); border-top-color: var(--cian); animation: hz-gira .7s linear infinite; }
    @keyframes hz-gira { to { transform: rotate(360deg); } }

    /* Hallazgos */
    .hz-hallazgos { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-bottom: 22px; }
    @media (max-width: 991.98px) { .hz-hallazgos { grid-template-columns: minmax(0, 1fr); } }
    .hz-h { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: 16px 18px; display: grid; gap: 8px; transition: border-color .15s, box-shadow .15s; }
    .hz-h:hover, .hz-h:focus-within { border-color: color-mix(in srgb, var(--t) 45%, var(--line)); box-shadow: 0 8px 22px rgba(26,16,61,.07); }
    .hz-h .tono { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600; color: var(--t); }
    .hz-h .tit { font-size: 15.5px; line-height: 1.4; }
    .hz-h .tit b { font-weight: 650; }
    .hz-h .det { font-size: 13.5px; color: var(--muted); }
    .hz-h .pie { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-top: 2px; }
    .hz-conf { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--muted); }
    .hz-conf i { display: inline-block; width: 4px; border-radius: 2px; background: var(--line-strong); }
    .hz-conf i:nth-child(1) { height: 6px; } .hz-conf i:nth-child(2) { height: 9px; } .hz-conf i:nth-child(3) { height: 12px; }
    .hz-conf i.on { background: var(--marca); }
    .hz-h.oportunidad { --t: #0E7FB8; } .hz-h.alerta { --t: #B45309; } .hz-h.dato { --t: var(--marca); }
    .hz-aparece { animation: hz-entra .35s ease both; }
    @keyframes hz-entra { from { opacity: 0; transform: translateY(6px); } }

    /* Mapa de calor */
    .hz-mapa { display: grid; grid-template-columns: 54px repeat(7, minmax(44px, 1fr)); gap: 4px; padding: 16px 18px 18px; min-width: 520px; }
    .hz-mapa .d { text-align: center; font-size: 12.5px; color: var(--muted); padding-bottom: 2px; }
    .hz-mapa .h { font-size: 12px; color: var(--muted); text-align: right; padding-right: 6px; align-self: center; }
    .hz-mapa .c { height: 34px; border-radius: 7px; display: grid; place-items: center; font-size: 12px; font-weight: 600; color: var(--ink); transition: box-shadow .15s, transform .15s; }
    .hz-mapa .c.alta { color: #fff; }
    .hz-mapa .c.vacia { background: repeating-linear-gradient(135deg, var(--sunken) 0 5px, #fff 5px 10px); border: 1px dashed var(--line); }
    .hz-mapa .c.resalta { box-shadow: 0 0 0 2px var(--cian), 0 0 0 4px rgba(65,201,255,.3); transform: scale(1.04); position: relative; z-index: 1; }
    .hz-leyenda { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--muted); }
    .hz-leyenda .grad { width: 120px; height: 8px; border-radius: 99px; background: linear-gradient(90deg, color-mix(in srgb, var(--marca) 12%, white), var(--marca)); }
    @media (max-width: 575.98px) { .hz-analista { grid-template-columns: 60px minmax(0, 1fr); gap: 14px; padding: 16px; } .hz-analista img { width: 60px; margin-bottom: -16px; } .hz-analista h2 { font-size: 17px; } .hz-leyenda { display: none; } }
    @media (prefers-reduced-motion: reduce) { .hz-aparece, .hz-girando { animation: none; } .hz-mapa .c { transition: none; } }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div><h1>Horarios</h1><p class="sub">Qué horarios se llenan, cuáles no y qué conviene hacer con tu agenda.</p></div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        @if($sucursales->count() > 1)
            <form method="get" class="m-0">@if($semanas !== 8)<input type="hidden" name="semanas" value="{{ $semanas }}">@endif
                <select name="sucursal" class="form-select form-select-sm w-auto" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()" aria-label="Sucursal">
                    <option value="">Todas las sucursales</option>
                    @foreach($sucursales as $s)<option value="{{ $s->id }}" @selected($sucursal === $s->id)>{{ $s->nombre }}</option>@endforeach</select></form>
        @endif
        <nav class="segmento" aria-label="Semanas a analizar">
            @foreach([4, 8, 12] as $n)<a href="{{ $q(['semanas' => $n === 8 ? null : $n]) }}" class="{{ $semanas === $n ? 'activo' : '' }}">{{ $n }} semanas</a>@endforeach
        </nav>
    </div>
</div>
<nav class="segmento hz-nav" aria-label="Reportes">
    <a href="{{ route('admin.fitness-reportes') }}"><i class="bi bi-bar-chart-line"></i> Resumen</a>
    <a href="{{ route('admin.fitness-reportes.horarios') }}" class="activo" aria-current="page"><i class="bi bi-grid-3x3"></i> Horarios</a>
</nav>

@if(! $hay)
    <div class="panel"><div class="vacio"><div class="ico"><i class="bi bi-grid-3x3"></i></div>
        <h3>Aún hay pocas clases para analizar</h3>
        <p>Cuando tengas al menos dos semanas de clases con reservaciones, aquí verás tus horarios más llenos, los que casi nadie usa y qué conviene abrir o mover.</p>
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <a href="{{ route('admin.clases.index') }}" class="btn btn-primario">Ir a la agenda</a>
            @if(auth()->user()->puede('clases'))
                <form method="post" action="{{ route('admin.clases.ejemplos.historial') }}" class="m-0">@csrf
                    <button class="btn btn-borde"><i class="bi bi-magic me-1"></i> Ver con datos de ejemplo</button></form>
            @endif
        </div>
        @if(auth()->user()->puede('clases'))<p class="secundario small mt-3 mb-0">Carga 8 semanas de clases inventadas para probar o enseñar esta pantalla. Se borran con «Quitar ejemplos» del Resumen.</p>@endif
    </div></div>
@else
<div x-data="horariosFx({{ Js::from(['clave' => 'hz-' . $semanas . '-' . $sucursal . '-' . now()->toDateString()]) }})" x-init="iniciar()">
    {{-- El analista --}}
    <section class="hz-analista" aria-live="polite">
        <img src="{{ route('plataforma.img', 'mascota-chica.webp') }}" alt="">
        <div>
            <h2 x-text="listo ? {{ Js::from(count($hallazgos) ? config('vandu.plataforma.nombre') . ' encontró ' . count($hallazgos) . (count($hallazgos) === 1 ? ' cosa' : ' cosas') . ' en tus horarios' : 'Tus horarios se ven parejos') }} : 'Revisando tus horarios…'">{{ config('vandu.plataforma.nombre') }} revisó tus horarios</h2>
            <div class="que">{{ ucfirst($analizador->nombre()) }} de tus últimas {{ $semanas }} semanas de clases y reservaciones.</div>
            <ul class="hz-pasos" x-show="!listo" x-cloak>
                @foreach(['Leyendo ' . $c->count() . ' clases y ' . $c->sum('reservas') . ' reservaciones', 'Comparando días y horas', 'Buscando qué abrir, mover o cuidar'] as $i => $paso)
                    <li :class="{ ok: paso > {{ $i }} }"><i x-show="paso > {{ $i }}" class="bi bi-check-lg"></i><span x-show="paso === {{ $i }}" class="hz-girando"></span><i x-show="paso < {{ $i }}" class="bi bi-dot"></i> {{ $paso }}</li>
                @endforeach
            </ul>
            <div class="chips" x-show="listo">
                <span class="num">{{ $c->count() }} clases</span>
                <span class="num">{{ $c->sum('reservas') }} reservaciones</span>
                <span class="num">{{ $c->sum('asistencias') }} asistencias</span>
                <span class="num">{{ $c->sum('cupo') ? round($c->sum('reservas') / $c->sum('cupo') * 100) : 0 }}% de ocupación</span>
            </div>
        </div>
    </section>

    {{-- Hallazgos --}}
    <div class="hz-hallazgos" x-show="listo" x-cloak>
        @forelse($hallazgos as $i => $h)
            @php $t = Hallazgo::TONOS[$h->tono]; @endphp
            <article class="hz-h {{ $h->tono }} hz-aparece" style="animation-delay: {{ $i * 90 }}ms" tabindex="0"
                     @mouseenter="resaltar({{ Js::from($h->celdas) }})" @mouseleave="resaltar([])" @focusin="resaltar({{ Js::from($h->celdas) }})" @focusout="resaltar([])">
                <span class="tono"><i class="bi {{ $t['icono'] }}"></i> {{ $t['texto'] }}</span>
                <div class="tit">{!! $h->titulo !!}</div>
                <div class="det">{{ $h->detalle }}</div>
                <div class="pie">
                    <span class="hz-conf" title="Según cuántas semanas de datos lo respaldan">@for($k = 1; $k <= 3; $k++)<i class="{{ $k <= $confN[$h->confianza] ? 'on' : '' }}"></i>@endfor {{ $confTxt[$h->confianza] }}</span>
                    @if($h->accion)<a href="{{ $h->accion['url'] }}" class="btn btn-borde btn-sm">{{ $h->accion['texto'] }}</a>@endif
                </div>
            </article>
        @empty
            <div class="panel" style="grid-column: 1 / -1"><div class="panel-body secundario">No hay horarios muy llenos ni muy vacíos en este periodo. Revisa el mapa para ver el detalle.</div></div>
        @endforelse
    </div>

    {{-- Mapa de calor --}}
    <section class="panel">
        <div class="panel-head">
            <div><h2>Ocupación por día y hora</h2><span class="ayuda">Promedio de lugares reservados contra el cupo, últimas {{ $semanas }} semanas</span></div>
            <div class="hz-leyenda"><span>0%</span><span class="grad"></span><span>100%</span></div>
        </div>
        <div class="table-responsive">
            <div class="hz-mapa" role="table" aria-label="Ocupación por día y hora">
                <div role="columnheader"></div>
                @foreach(Datos::DIAS_CORTOS as $dc)<div class="d" role="columnheader">{{ $dc }}</div>@endforeach
                @foreach($d->horas as $h)
                    <div class="h num" role="rowheader">{{ sprintf('%02d:00', $h) }}</div>
                    @foreach(range(1, 7) as $dia)
                        @php $cel = $d->celdas[$dia][$h] ?? null; @endphp
                        @if($cel)
                            <div class="c num {{ $cel['ocupacion'] >= 60 ? 'alta' : '' }}" role="cell" style="background: {{ $tono($cel['ocupacion']) }}"
                                 :class="{ resalta: esta({{ $dia }}, {{ $h }}) }"
                                 title="{{ ucfirst(Datos::DIAS[$dia]) }} {{ sprintf('%02d:00', $h) }} · {{ $cel['ocupacion'] }}% de ocupación · {{ $cel['clases'] }} {{ $cel['clases'] === 1 ? 'clase' : 'clases' }} ({{ implode(', ', $cel['tipos']) }})">{{ $cel['ocupacion'] }}%</div>
                        @else
                            <div class="c vacia" role="cell" :class="{ resalta: esta({{ $dia }}, {{ $h }}) }" title="{{ ucfirst(Datos::DIAS[$dia]) }} {{ sprintf('%02d:00', $h) }} · sin clases"></div>
                        @endif
                    @endforeach
                @endforeach
            </div>
        </div>
    </section>
    <p class="secundario small mt-3 mb-0"><i class="bi bi-info-circle me-1"></i> Cómo se calcula: se toman las clases que ya se dieron (sin las canceladas) y se compara cuántos lugares se reservaron contra el cupo. Los hallazgos salen de reglas sobre esos números; la confianza sube con más semanas de datos.</p>
</div>
@endif
@endsection

@push('scripts')
<script>
window.horariosFx = function (cfg) {
    let visto = false;
    try { visto = sessionStorage.getItem(cfg.clave) === '1'; } catch (e) {}
    const sinMovimiento = matchMedia('(prefers-reduced-motion: reduce)').matches;
    return {
        listo: visto || sinMovimiento, paso: 0, celdas: [],
        iniciar() {
            if (this.listo) return;
            const t = setInterval(() => {
                this.paso++;
                if (this.paso >= 3) { clearInterval(t); setTimeout(() => { this.listo = true; try { sessionStorage.setItem(cfg.clave, '1'); } catch (e) {} }, 250); }
            }, 420);
        },
        resaltar(c) { this.celdas = c; },
        esta(d, h) { return this.celdas.some((x) => x[0] === d && x[1] === h); },
    };
};
</script>
@endpush
