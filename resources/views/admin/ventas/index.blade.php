@extends('admin.layout')
@section('titulo', 'Ventas')

@php
    use App\Http\Controllers\Admin\VentasController as V;
    use App\Models\Prospecto;
    $d = fn ($n) => '$' . number_format($n ?? 0, 0);
    $u = fn (array $x) => route('admin.ventas.index', array_merge(['ver' => $vista === 'embudo' ? null : $vista, 'periodo' => $periodo, 'q' => $q ?: null], $x));
    $tablero = [
        'etapas' => $abiertas->map(fn ($e) => ['id' => $e->id, 'nombre' => $e->nombre, 'prob' => $e->probabilidad])->values(),
        'ganada' => $etapas->firstWhere('tipo', 'ganada')->id,
        'perdida' => $etapas->firstWhere('tipo', 'perdida')->id,
        'motivos' => Prospecto::MOTIVOS,
        'token' => csrf_token(),
        'prospectos' => $columnas->flatMap(fn ($c) => $c['prospectos'])->map(fn ($p) => [
            'id' => $p->id, 'etapa' => $p->etapa_id, 'quien' => $p->quien, 'nombre' => $p->empresa ? $p->nombre : null,
            'interes' => $p->interes, 'valor' => (float) $p->valor, 'dias' => $p->dias_en_etapa, 'nuevo' => $p->nuevo,
            'origen' => Prospecto::ORIGENES[$p->origen]['icono'] ?? 'bi-dot', 'origenTexto' => Prospecto::ORIGENES[$p->origen]['texto'] ?? '',
            'tarea' => ($t = $p->tareas->first()) ? ['texto' => $t->titulo, 'cuando' => $t->cuando, 'vencida' => $t->vencida] : null,
            'url' => route('admin.ventas.show', $p), 'mover' => route('admin.ventas.etapa', $p),
        ])->values(),
    ];
@endphp

@push('head')
<style>
    .kpis { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
    .kpi { padding: 14px 16px; }
    .kpi .k { font-size: 13px; color: var(--muted); display: flex; gap: 6px; align-items: center; }
    .kpi .v { font-size: 24px; font-weight: 600; letter-spacing: -.02em; margin-top: 6px; }
    .kpi .s { font-size: 12.5px; color: var(--muted); margin-top: 4px; }
    .kpi.destacado { background: var(--ink); color: #fff; border-color: var(--ink); }
    .kpi.destacado .k, .kpi.destacado .s { color: #A9AEBA; }
    .kpi.bueno .v { color: var(--green-ink); } .kpi.malo .v { color: var(--red); }
    .controles { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; margin-bottom: 16px; }

    /* Tablero */
    .tablero { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(250px, 1fr); gap: 12px; overflow-x: auto; padding-bottom: 8px; scroll-snap-type: x proximity; }
    .col { background: var(--sunken, #F1F2F4); border-radius: 14px; padding: 10px; display: flex; flex-direction: column; gap: 8px; min-height: 220px; scroll-snap-align: start; transition: box-shadow .12s, background .12s; }
    .col.sobre { background: #E7F8EF; box-shadow: inset 0 0 0 2px var(--green-ink); }
    .col-cab { padding: 4px 4px 6px; }
    .col-cab .n { font-weight: 600; display: flex; justify-content: space-between; gap: 8px; }
    .col-cab .n span { font-size: 12px; color: var(--muted); font-weight: 500; }
    .col-cab .m { font-size: 13px; color: var(--muted); margin-top: 2px; }
    .col-cab .m b { color: var(--text); font-weight: 600; }
    .tarjeta { background: var(--surface, #fff); border: 1px solid var(--line); border-radius: 12px; padding: 10px 12px; cursor: grab; position: relative; display: grid; gap: 4px; }
    .tarjeta:hover { border-color: #C9CDD6; }
    .tarjeta.arrastrando { opacity: .45; }
    .tarjeta a.t { font-weight: 600; color: inherit; text-decoration: none; line-height: 1.25; padding-right: 24px; }
    .tarjeta .s { font-size: 12.5px; color: var(--muted); }
    .tarjeta .pie { display: flex; justify-content: space-between; align-items: center; gap: 8px; font-size: 12.5px; margin-top: 2px; }
    .tarjeta .valor { font-weight: 600; font-size: 14px; }
    .tarjeta .sin-valor { color: var(--amber); font-weight: 500; }
    .tarjeta .tarea { font-size: 12px; color: var(--text-2); display: flex; gap: 5px; align-items: center; }
    .tarjeta .tarea.vencida { color: var(--red); }
    .tarjeta .etq-nuevo { font-size: 11px; font-weight: 600; padding: 0 7px; border-radius: 99px; background: var(--green-soft); color: var(--green-ink); }
    .tarjeta .menu { position: absolute; top: 6px; right: 4px; z-index: 2; }
    .tarjeta .menu > button { border: 0; background: none; width: 28px; height: 28px; border-radius: 7px; color: var(--muted); }
    .tarjeta .menu > button:hover { background: var(--sunken); color: var(--text); }
    .tarjeta .pop { position: fixed; max-height: calc(100vh - 24px); overflow-y: auto; background: var(--surface, #fff); border: 1px solid var(--line); border-radius: 12px; box-shadow: 0 12px 32px rgba(16,24,40,.16); padding: 6px; min-width: 210px; z-index: 5; }
    .tarjeta .pop .lbl { font-size: 11.5px; color: var(--muted); padding: 4px 8px; }
    .tarjeta .pop button { display: flex; width: 100%; gap: 8px; align-items: center; border: 0; background: none; text-align: left; padding: 7px 8px; border-radius: 8px; font-size: 14px; }
    .tarjeta .pop button:hover:not(:disabled) { background: var(--sunken); }
    .tarjeta .pop button:disabled { color: var(--muted); }
    .vacia { font-size: 13px; color: var(--muted); text-align: center; padding: 16px 6px; border: 1px dashed #D5D8DE; border-radius: 10px; }
    .cierres { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 12px; }
    .cierre { border: 2px dashed var(--line); border-radius: 14px; padding: 14px; text-align: center; color: var(--muted); font-weight: 500; transition: all .12s; }
    .cierre i { font-size: 18px; margin-right: 6px; }
    .cierre.ganar.sobre { border-color: var(--green-ink); background: var(--green-soft); color: var(--green-ink); }
    .cierre.perder.sobre { border-color: var(--red); background: #FEF3F2; color: var(--red); }
    .ayuda-tablero { font-size: 12.5px; color: var(--muted); margin-top: 8px; }

    /* Cerrar (ganar / perder) */
    dialog.cerrar { border: 0; border-radius: 16px; padding: 0; width: min(420px, calc(100vw - 32px)); box-shadow: 0 24px 64px rgba(0,0,0,.25); }
    dialog.cerrar::backdrop { background: rgba(19,22,29,.45); }
    dialog.cerrar form { padding: 20px; display: grid; gap: 12px; }

    /* Resultados */
    .rejilla { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 18px; align-items: start; }
    .hbar { display: grid; grid-template-columns: 170px minmax(0, 1fr) auto; gap: 8px 14px; align-items: center; }
    .hbar .et { font-size: 14px; color: var(--text-2); }
    .hbar .pista { height: 22px; background: #F1F2F4; border-radius: 6px; overflow: hidden; }
    .hbar .pista span { display: block; height: 100%; background: var(--ink); border-radius: 0 6px 6px 0; min-width: 2px; }
    .hbar .val { text-align: right; font-size: 14px; white-space: nowrap; }
    .hbar .val small { display: block; color: var(--muted); font-size: 12px; }
    @media (max-width: 1199.98px) { .kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); } .rejilla { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 767.98px) {
        .kpis { grid-template-columns: 1fr 1fr; } .kpi .v { font-size: 20px; }
        .tablero { grid-auto-columns: 82vw; margin: 0 -16px; padding: 0 16px 8px; scroll-padding: 16px; }
        .hbar { grid-template-columns: 1fr auto; grid-auto-flow: row dense; gap: 4px 12px; } .hbar .pista { grid-column: 1 / -1; margin-bottom: 8px; }
    }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <h1>Ventas</h1>
        <p class="sub">Tus prospectos: cuánto vale cada uno, en qué paso van, cuáles se ganan y cuáles se pierden.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.ventas.etapas') }}" class="btn btn-borde"><i class="bi bi-sliders me-1"></i> Etapas</a>
        <a href="{{ route('admin.ventas.create') }}" class="btn btn-primario"><i class="bi bi-plus-lg me-1"></i> Nuevo prospecto</a>
    </div>
</div>

<div class="kpis">
    <div class="panel kpi destacado">
        <div class="k"><i class="bi bi-funnel"></i> En el embudo</div>
        <div class="v num">{{ $d($kpi['embudo']) }}</div>
        <div class="s num">{{ $kpi['nEmbudo'] }} {{ $kpi['nEmbudo'] === 1 ? 'prospecto abierto' : 'prospectos abiertos' }}@if($kpi['nuevos']) · {{ $kpi['nuevos'] }} {{ $kpi['nuevos'] === 1 ? 'nuevo' : 'nuevos' }}@endif</div>
    </div>
    <div class="panel kpi">
        <div class="k"><i class="bi bi-graph-up"></i> Pronóstico</div>
        <div class="v num">{{ $d($kpi['pronostico']) }}</div>
        <div class="s">Lo que vale cada uno × la probabilidad de su etapa</div>
    </div>
    <a href="{{ $u(['ver' => 'ganados']) }}" class="panel kpi bueno text-reset text-decoration-none">
        <div class="k"><i class="bi bi-trophy"></i> Ganado</div>
        <div class="v num">{{ $d($kpi['ganado']) }}</div>
        <div class="s num">{{ $kpi['nGanados'] }} · {{ mb_strtolower(V::PERIODOS[$periodo]) }}</div>
    </a>
    <a href="{{ $u(['ver' => 'perdidos']) }}" class="panel kpi malo text-reset text-decoration-none">
        <div class="k"><i class="bi bi-x-octagon"></i> Perdido</div>
        <div class="v num">{{ $d($kpi['perdido']) }}</div>
        <div class="s num">{{ $kpi['nPerdidos'] }} · {{ mb_strtolower(V::PERIODOS[$periodo]) }}</div>
    </a>
    <a href="{{ $u(['ver' => 'resultados']) }}" class="panel kpi text-reset text-decoration-none">
        <div class="k"><i class="bi bi-bullseye"></i> Conversión</div>
        <div class="v num">{{ $kpi['conversion'] === null ? '—' : $kpi['conversion'] . '%' }}</div>
        <div class="s">De los que se cerraron, cuántos ganaste</div>
    </a>
</div>

<div class="controles">
    <nav class="segmento" aria-label="Vista">
        @foreach(V::VISTAS as $k => $t)
            <a href="{{ $u(['ver' => $k === 'embudo' ? null : $k]) }}" class="{{ $vista === $k ? 'activo' : '' }}">{{ $t }}</a>
        @endforeach
    </nav>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <nav class="segmento" aria-label="Periodo">
            @foreach(V::PERIODOS as $k => $t)
                <a href="{{ $u(['periodo' => $k]) }}" class="{{ $periodo === $k ? 'activo' : '' }}">{{ $t }}</a>
            @endforeach
        </nav>
        <form class="buscador" method="get" role="search">
            @if($vista !== 'embudo')<input type="hidden" name="ver" value="{{ $vista }}">@endif
            <input type="hidden" name="periodo" value="{{ $periodo }}">
            <i class="bi bi-search"></i>
            <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Nombre, empresa o servicio" aria-label="Buscar prospectos">
        </form>
    </div>
</div>

@if($vista === 'embudo')
    @if($kpi['sinValor'])
        <p class="secundario mb-2" style="font-size: 13.5px"><i class="bi bi-info-circle me-1"></i>{{ $kpi['sinValor'] }} {{ $kpi['sinValor'] === 1 ? 'prospecto no tiene' : 'prospectos no tienen' }} valor: ponle cuánto podría valer la venta para que cuente en el embudo y el pronóstico.</p>
    @endif
    <div x-data="tableroVentas({{ Js::from($tablero) }})">
        <div class="tablero">
            <template x-for="e in etapas" :key="e.id">
                <section class="col" :class="sobre === e.id && 'sobre'" @dragover.prevent="sobre = e.id" @dragleave="sobre === e.id && (sobre = null)" @drop.prevent="soltar(e.id)" :aria-label="e.nombre">
                    <div class="col-cab">
                        <div class="n"><span style="font-size: 14.5px; color: var(--text); font-weight: 600" x-text="e.nombre"></span><span x-text="e.prob + '%'"></span></div>
                        <div class="m num"><b x-text="dinero(suma(e.id))"></b> · <span x-text="de(e.id).length + (de(e.id).length === 1 ? ' prospecto' : ' prospectos')"></span></div>
                    </div>
                    <template x-for="p in de(e.id)" :key="p.id">
                        <article class="tarjeta" draggable="true" :class="arrastrado === p.id && 'arrastrando'" @dragstart="arrastrar($event, p)" @dragend="arrastrado = null; sobre = null"
                                 @click="if (! $event.target.closest('.menu, a')) $el.querySelector('a.t').click()">
                            <a class="t" :href="p.url" x-text="p.quien" draggable="false"></a>
                            <div class="s" x-show="p.nombre || p.interes" x-text="[p.nombre, p.interes].filter(Boolean).join(' · ')"></div>
                            <div class="tarea" x-show="p.tarea" :class="p.tarea && p.tarea.vencida && 'vencida'"><i class="bi bi-alarm"></i> <span x-text="p.tarea ? p.tarea.cuando + ' · ' + p.tarea.texto : ''"></span></div>
                            <div class="pie">
                                <span :class="p.valor ? 'valor num' : 'sin-valor'" x-text="p.valor ? dinero(p.valor) : 'Sin valor'"></span>
                                <span class="d-flex gap-2 align-items-center secundario">
                                    <span class="etq-nuevo" x-show="p.nuevo">Nuevo</span>
                                    <i class="bi" :class="p.origen" :title="p.origenTexto"></i>
                                    <span x-text="p.dias ? p.dias + ' d' : 'hoy'" title="Días en esta etapa"></span>
                                </span>
                            </div>
                            <div class="menu" x-data="{ abierto: false, pos: '' }" @click.outside="abierto = false" @keydown.escape.window="abierto = false">
                                <button type="button" :aria-label="'Mover a ' + p.quien" :aria-expanded="abierto"
                                        @click.prevent="const r = $el.getBoundingClientRect(); pos = `top:${Math.max(12, Math.min(r.bottom + 4, innerHeight - 380))}px; left:${Math.max(12, Math.min(r.right - 220, innerWidth - 232))}px`; abierto = ! abierto"><i class="bi bi-three-dots"></i></button>
                                <div class="pop" x-show="abierto" x-cloak x-transition.opacity :style="pos" @scroll.stop>
                                    <div class="lbl">Mover a</div>
                                    <template x-for="x in etapas" :key="x.id">
                                        <button type="button" :disabled="x.id === p.etapa" @click="abierto = false; mover(p, x.id)"><i class="bi bi-arrow-right-short"></i><span x-text="x.nombre"></span></button>
                                    </template>
                                    <hr class="my-1">
                                    <button type="button" @click="abierto = false; cerrar(p, 'ganar')" style="color: var(--green-ink)"><i class="bi bi-trophy"></i> Ganado</button>
                                    <button type="button" @click="abierto = false; cerrar(p, 'perder')" style="color: var(--red)"><i class="bi bi-x-octagon"></i> Perdido</button>
                                </div>
                            </div>
                        </article>
                    </template>
                    <div class="vacia" x-show="! de(e.id).length">Arrastra aquí un prospecto</div>
                </section>
            </template>
        </div>
        <div class="cierres d-none d-md-grid">
            <div class="cierre ganar" :class="sobre === 'ganar' && 'sobre'" @dragover.prevent="sobre = 'ganar'" @dragleave="sobre = null" @drop.prevent="soltarCierre('ganar')"><i class="bi bi-trophy"></i> Suelta aquí si lo ganaste</div>
            <div class="cierre perder" :class="sobre === 'perder' && 'sobre'" @dragover.prevent="sobre = 'perder'" @dragleave="sobre = null" @drop.prevent="soltarCierre('perder')"><i class="bi bi-x-octagon"></i> Suelta aquí si se perdió</div>
        </div>
        <p class="ayuda-tablero d-md-none"><i class="bi bi-hand-index me-1"></i>Desliza para ver las demás etapas. Usa <i class="bi bi-three-dots"></i> en cada prospecto para moverlo o cerrarlo.</p>

        <dialog class="cerrar" x-ref="dialogo" @close="cerrando = null">
            <form method="post" :action="cerrando ? cerrando.p.mover : ''">
                @csrf @method('patch')
                <input type="hidden" name="etapa_id" :value="cerrando ? (cerrando.tipo === 'ganar' ? ganada : perdida) : ''">
                <h3 class="h5 m-0" x-text="cerrando ? (cerrando.tipo === 'ganar' ? '¡Ganado! ' : 'Se perdió: ') + cerrando.p.quien : ''"></h3>
                <template x-if="cerrando && cerrando.tipo === 'ganar'">
                    <div>
                        <label class="form-label" for="valor-ganado">¿En cuánto se cerró? <span class="secundario fw-normal">(antes de IVA)</span></label>
                        <div class="input-group"><span class="input-group-text">$</span><input type="number" step="0.01" min="0" id="valor-ganado" name="valor" class="form-control num" :value="cerrando.p.valor || ''"></div>
                        <div class="secundario mt-1" style="font-size: 12.5px">Si le mandaste cotización desde Ventas, se marca solo como ganado cuando la acepta.</div>
                    </div>
                </template>
                <template x-if="cerrando && cerrando.tipo === 'perder'">
                    <div class="d-grid gap-2">
                        <div>
                            <label class="form-label" for="motivo">¿Por qué?</label>
                            <select id="motivo" name="motivo" class="form-select" required>
                                <template x-for="m in motivos" :key="m"><option :value="m" x-text="m"></option></template>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="nota-cierre">Nota <span class="secundario fw-normal">(opcional)</span></label>
                            <input id="nota-cierre" name="nota" class="form-control" maxlength="500">
                        </div>
                    </div>
                </template>
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-borde" @click="$refs.dialogo.close()">Cancelar</button>
                    <button class="btn btn-primario" x-text="cerrando && cerrando.tipo === 'ganar' ? 'Marcar como ganado' : 'Marcar como perdido'"></button>
                </div>
            </form>
        </dialog>
    </div>
@endif

@if($vista === 'ganados' || $vista === 'perdidos')
    @php $lista = $vista === 'ganados' ? $ganados : $perdidos; @endphp
    <section class="panel">
        <div class="panel-head"><h2>{{ $vista === 'ganados' ? 'Ganados' : 'Perdidos' }} · {{ mb_strtolower(V::PERIODOS[$periodo]) }}</h2><span class="num fw-semibold">{{ $d($lista->sum('valor')) }}</span></div>
        @if($lista->isEmpty())
            <div class="vacio"><div class="ico"><i class="bi {{ $vista === 'ganados' ? 'bi-trophy' : 'bi-emoji-neutral' }}"></i></div>
                <h3>{{ $vista === 'ganados' ? 'Sin ventas cerradas en este periodo' : 'Ningún prospecto perdido en este periodo' }}</h3>
                <p>Cambia el periodo arriba para ver más.</p></div>
        @else
            <div class="table-responsive">
                <table class="tabla">
                    <thead><tr><th>Prospecto</th><th>{{ $vista === 'ganados' ? 'Servicio' : 'Motivo' }}</th><th>Origen</th><th>Cerrado</th><th class="text-end">Valor</th></tr></thead>
                    <tbody>
                    @foreach($lista as $p)
                        <tr>
                            <td style="min-width: 200px"><a href="{{ route('admin.ventas.show', $p) }}" class="fila-link persona">@include('admin._avatar', ['nombre' => $p->quien])
                                <div><div class="principal text-truncate">{{ $p->quien }}</div><div class="secundario text-truncate">{{ $p->empresa ? $p->nombre : ($p->email ?: $p->telefono) }}</div></div></a></td>
                            <td class="secundario">{{ $vista === 'ganados' ? ($p->interes ?: '—') : $p->motivo_perdida }}</td>
                            <td class="secundario text-nowrap"><i class="bi {{ Prospecto::ORIGENES[$p->origen]['icono'] ?? 'bi-dot' }} me-1"></i>{{ Prospecto::ORIGENES[$p->origen]['texto'] ?? $p->origen }}</td>
                            <td class="secundario text-nowrap">{{ $p->cerrado_at?->locale('es')->isoFormat('D MMM YYYY') }}</td>
                            <td class="text-end num fw-semibold">{{ $p->valor ? $d($p->valor) : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endif

@if($vista === 'resultados')
    @php $r = $resultados; $maxPaso = max(1, $r['pasos']->max('n') ?? 1); @endphp
    <div class="kpis" style="grid-template-columns: repeat(4, minmax(0, 1fr))">
        <div class="panel kpi"><div class="k">Entraron</div><div class="v num">{{ $r['total'] }}</div><div class="s">Prospectos nuevos · {{ mb_strtolower(V::PERIODOS[$periodo]) }}</div></div>
        <div class="panel kpi bueno"><div class="k">Se convirtieron</div><div class="v num">{{ $r['ganados']['n'] }}</div><div class="s num">{{ $d($r['ganados']['valor']) }}@if($r['dias'] !== null) · cierran en {{ $r['dias'] }} días @endif</div></div>
        <div class="panel kpi malo"><div class="k">Se murieron</div><div class="v num">{{ $r['perdidos']['n'] }}</div><div class="s num">{{ $d($r['perdidos']['valor']) }} que no se cerró</div></div>
        <div class="panel kpi"><div class="k">Siguen abiertos</div><div class="v num">{{ $r['abiertos']['n'] }}</div><div class="s num">{{ $d($r['abiertos']['valor']) }} en juego</div></div>
    </div>
    <div class="rejilla">
        <section class="panel">
            <div class="panel-head"><h2>Hasta dónde llegan</h2><span class="ayuda">De los que entraron en el periodo</span></div>
            @if(! $r['total'])
                <div class="panel-body secundario">Sin prospectos en este periodo.</div>
            @else
                <div class="panel-body hbar">
                    @foreach($r['pasos'] as $i => $paso)
                        <span class="et">{{ $paso['etapa']->nombre }}</span>
                        <span class="pista" role="img" aria-label="{{ $paso['etapa']->nombre }}: {{ $paso['n'] }}"><span style="width: {{ $paso['n'] / $maxPaso * 100 }}%; opacity: {{ max(.35, 1 - $i * .13) }}"></span></span>
                        <span class="val num">{{ $paso['n'] }} <small>{{ $d($paso['valor']) }}@if($r['total']) · {{ round($paso['n'] / $r['total'] * 100) }}% @endif</small></span>
                    @endforeach
                    <span class="et fw-semibold" style="color: var(--green-ink)">Ganados</span>
                    <span class="pista"><span style="width: {{ $r['ganados']['n'] / $maxPaso * 100 }}%; background: var(--green-ink)"></span></span>
                    <span class="val num">{{ $r['ganados']['n'] }} <small>{{ $d($r['ganados']['valor']) }}@if($r['total']) · {{ round($r['ganados']['n'] / $r['total'] * 100) }}% @endif</small></span>
                </div>
            @endif
        </section>
        <div class="d-grid gap-3">
            <section class="panel">
                <div class="panel-head"><h2>Dónde se mueren</h2><span class="ayuda">Etapa en la que estaban al perderse</span></div>
                @if($r['murio']->isEmpty())
                    <div class="panel-body secundario">Ningún prospecto perdido en este periodo.</div>
                @else
                    <table class="tabla"><tbody>
                        @foreach($r['murio'] as $m)<tr><td>{{ $m['etapa'] }}</td><td class="text-end num">{{ $m['n'] }}</td><td class="text-end num fw-semibold">{{ $d($m['valor']) }}</td></tr>@endforeach
                    </tbody></table>
                @endif
            </section>
            <section class="panel">
                <div class="panel-head"><h2>Por qué se pierden</h2></div>
                @if($r['motivos']->isEmpty())
                    <div class="panel-body secundario">Sin motivos registrados.</div>
                @else
                    <table class="tabla"><tbody>
                        @foreach($r['motivos'] as $m)<tr><td>{{ $m['motivo'] }}</td><td class="text-end num">{{ $m['n'] }}</td><td class="text-end num fw-semibold">{{ $d($m['valor']) }}</td></tr>@endforeach
                    </tbody></table>
                @endif
            </section>
        </div>
    </div>
    <section class="panel mt-3">
        <div class="panel-head"><h2>De dónde llegan</h2><span class="ayuda">Y cuántos se vuelven venta</span></div>
        @if($r['origenes']->isEmpty())
            <div class="panel-body secundario">Sin prospectos en este periodo.</div>
        @else
            <div class="table-responsive"><table class="tabla">
                <thead><tr><th>Origen</th><th class="text-end">Prospectos</th><th class="text-end">Ganados</th><th class="text-end">Conversión</th><th class="text-end">Vendido</th></tr></thead>
                <tbody>
                @foreach($r['origenes'] as $o)
                    <tr><td><i class="bi {{ $o['icono'] }} me-1"></i>{{ $o['origen'] }}</td><td class="text-end num">{{ $o['n'] }}</td><td class="text-end num">{{ $o['ganados'] }}</td>
                        <td class="text-end num">{{ round($o['ganados'] / max(1, $o['n']) * 100) }}%</td><td class="text-end num fw-semibold">{{ $d($o['valor']) }}</td></tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </section>
@endif
@endsection

@push('scripts')
<script>
function tableroVentas(cfg) {
    return {
        ...cfg,
        arrastrado: null, sobre: null, cerrando: null,
        de(etapa) { return this.prospectos.filter(p => p.etapa === etapa); },
        suma(etapa) { return this.de(etapa).reduce((s, p) => s + (+p.valor || 0), 0); },
        dinero(n) { return '$' + Math.round(+n || 0).toLocaleString('es-MX'); },
        arrastrar(ev, p) { this.arrastrado = p.id; ev.dataTransfer.effectAllowed = 'move'; ev.dataTransfer.setData('text/plain', String(p.id)); },
        soltar(etapa) {
            const p = this.prospectos.find(x => x.id === this.arrastrado);
            this.sobre = null;
            if (p && p.etapa !== etapa) this.mover(p, etapa);
        },
        soltarCierre(tipo) {
            const p = this.prospectos.find(x => x.id === this.arrastrado);
            this.sobre = null;
            if (p) this.cerrar(p, tipo);
        },
        async mover(p, etapa) {
            const antes = p.etapa;
            p.etapa = etapa; p.dias = 0; p.nuevo = false;
            try {
                const r = await fetch(p.mover, {
                    method: 'PATCH', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.token },
                    body: JSON.stringify({ etapa_id: etapa }),
                });
                if (! r.ok) throw r;
            } catch (e) {
                p.etapa = antes;
                alert('No se pudo mover. Revisa tu conexión e inténtalo de nuevo.');
            }
        },
        cerrar(p, tipo) { this.cerrando = { p, tipo }; this.$nextTick(() => this.$refs.dialogo.showModal()); },
    };
}
</script>
@endpush
