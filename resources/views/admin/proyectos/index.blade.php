@extends('admin.layout')
@section('titulo', 'Proyectos')

@php
    $filtros = ['activo' => 'En marcha', 'pausado' => 'En pausa', 'terminado' => 'Terminados', 'cancelado' => 'Cancelados', 'todos' => 'Todos'];
    $dinero = fn ($n) => '$' . number_format($n, 0);
    $q = fn (array $extra = []) => route('admin.proyectos.index', array_filter(array_merge(['filtro' => $filtro, 'tipo' => $tipo, 'vista' => $vista === 'lista' ? 'lista' : null, 'semana' => $semana['n'] ?: null], $extra), fn ($v) => $v !== null && $v !== 0));
    $paginado = $proyectos instanceof \Illuminate\Contracts\Pagination\Paginator;
    $zona = config('vandu.zona_horaria');
    $hoy = now($zona)->toDateString();
    $bloques = 15;   // la barra de progreso en cuadritos
    $lunes = $semana['lunes'];
    $dom = $lunes->copy()->addDays(6);
    $rango = $lunes->isSameMonth($dom)
        ? $lunes->day . ' – ' . $dom->locale('es')->isoFormat('D [de] MMMM YYYY')
        : $lunes->locale('es')->isoFormat('D MMM') . ' – ' . $dom->locale('es')->isoFormat('D MMM YYYY');
@endphp

@push('head')
<style>
    .pv { --card: #FFFFFF; --soft: #F3F4F5; --borde: #E7E8EA; --verde: #8FE89A; --verde-osc: #2E9E4F; --vacio: #EEF0F2; }

    /* Encabezado */
    .pv-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
    .pv-head h1 { font-size: 30px; letter-spacing: -.02em; margin: 0; }
    .pv-stats { display: flex; flex-wrap: wrap; gap: 8px; }
    .pv-stat { display: inline-flex; align-items: center; gap: 8px; background: var(--card); border: 1px solid var(--borde); border-radius: 99px; padding: 7px 14px 7px 8px; font-size: 13.5px; color: var(--text-2); }
    .pv-stat b { color: var(--text); font-weight: 600; font-size: 15px; }
    .pv-stat .ic { width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center; background: var(--soft); font-size: 13px; }
    .pv-stat.rojo b, .pv-stat.rojo .ic { color: var(--red); } .pv-stat.rojo .ic { background: #FDECEC; }
    .pv-stat.verde .ic { background: #E4F9E7; color: var(--verde-osc); }

    .pv-barra { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin: 26px 0 16px; }
    .pv-barra h2 { font-size: 22px; font-weight: 600; letter-spacing: -.01em; margin: 0; }
    .pv-pills { display: inline-flex; background: var(--card); border: 1px solid var(--borde); border-radius: 99px; padding: 4px; gap: 2px; }
    .pv-pills a { padding: 6px 14px; border-radius: 99px; font-size: 13.5px; color: var(--text-2); text-decoration: none; white-space: nowrap; }
    .pv-pills a.activo { background: var(--verde); color: #0E2A16; font-weight: 600; }
    .pv-pills a .n { opacity: .6; margin-left: 3px; font-size: 12px; }
    .pv-sel { border-radius: 99px !important; border-color: var(--borde) !important; padding-left: 14px !important; font-size: 13.5px !important; }

    /* Tarjetas tipo referencia */
    .pv-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 18px; }
    .pv-card { position: relative; background: var(--card); border: 1px solid var(--borde); border-radius: 24px; padding: 22px 22px 18px; display: flex; flex-direction: column; gap: 16px; transition: box-shadow .18s, transform .18s; }
    .pv-card:hover { box-shadow: 0 12px 32px rgba(17, 24, 39, .08); transform: translateY(-2px); }
    .pv-card::before { content: ""; position: absolute; left: -1px; top: 26px; bottom: 26px; width: 4px; border-radius: 0 4px 4px 0; background: var(--acento, transparent); }
    .pv-card.rojo { --acento: var(--red); } .pv-card.ambar { --acento: #F2A93B; } .pv-card.azul { --acento: #4C7DF0; }
    .pv-top { display: flex; align-items: flex-start; gap: 8px; }
    .pv-tags { display: flex; flex-wrap: wrap; gap: 8px; flex: 1; min-width: 0; }
    .pv-tag { display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--borde); border-radius: 10px; padding: 5px 11px; font-size: 13px; color: var(--text); background: var(--card); white-space: nowrap; }
    .pv-tag.rojo { border-color: #F6C9C9; background: #FFF4F4; color: #B42318; }
    .pv-tag.ambar { border-color: #F7DDAE; background: #FFF8EC; color: #9A5B00; }
    .pv-tag.azul { border-color: #C9D8FB; background: #F3F7FF; color: #2456C8; }
    .pv-tag.verde { border-color: #C8EFCF; background: #F2FCF4; color: #1F7A3A; }
    .pv-mas { position: relative; z-index: 2; width: 36px; height: 36px; border: 1px solid var(--borde); border-radius: 10px; background: var(--card); display: grid; place-items: center; color: var(--text); flex: none; }
    .pv-mas:hover { background: var(--soft); }
    .pv-card h3 { font-size: 21px; font-weight: 600; line-height: 1.25; letter-spacing: -.01em; margin: 0; }
    .pv-card h3 a { color: var(--text); text-decoration: none; }
    .pv-card h3 a::after { content: ""; position: absolute; inset: 0; border-radius: 24px; }
    .pv-nota { font-size: 14.5px; color: var(--text-2); margin: -8px 0 0; }

    .pv-caja { background: var(--soft); border-radius: 14px; padding: 12px 14px; display: grid; gap: 8px; }
    .pv-caja .fila { display: flex; justify-content: space-between; gap: 10px; font-size: 13.5px; align-items: center; }
    .pv-caja .fila b { font-weight: 600; }
    .pv-caja .tarde { color: #B42318; }
    .pv-flag { display: inline-flex; align-items: center; gap: 8px; border: 1px solid var(--borde); border-radius: 10px; padding: 6px 12px; font-size: 14px; background: var(--card); width: fit-content; }
    .pv-flag.tarde { border-color: #F6C9C9; color: #B42318; background: #FFF4F4; }

    .pv-prog .fila { display: flex; justify-content: space-between; font-size: 15px; font-weight: 500; margin-bottom: 8px; }
    .pv-bloques { display: grid; grid-template-columns: repeat({{ $bloques }}, 1fr); gap: 4px; }
    .pv-bloques i { aspect-ratio: 1.15; border-radius: 5px; background: var(--vacio); }
    .pv-bloques i.on { background: var(--verde); }
    .pv-card.rojo .pv-bloques i.on { background: #F5A3A3; }

    .pv-pie { display: flex; align-items: center; gap: 8px; margin-top: auto; }
    .pv-pie .avatar { width: 38px; height: 38px; border-radius: 50%; font-size: 13px; border: 2px solid var(--card); }
    .pv-pie .quien { font-size: 13.5px; color: var(--text-2); flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pv-chip { position: relative; z-index: 2; display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--borde); border-radius: 12px; padding: 6px 10px; font-size: 14px; color: var(--text); background: var(--card); text-decoration: none; white-space: nowrap; }
    .pv-chip:hover { background: var(--soft); color: var(--text); }
    .pv-chip i { font-size: 15px; }

    /* Semana */
    .pv-cal { background: var(--card); border: 1px solid var(--borde); border-radius: 24px; overflow: hidden; }
    .pv-cal-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 20px; border-bottom: 1px solid var(--borde); }
    .pv-cal-head .rango { display: inline-flex; align-items: center; gap: 10px; border: 1px solid var(--borde); border-radius: 99px; padding: 6px 8px 6px 14px; font-size: 14.5px; font-weight: 500; }
    .pv-cal-head .rango a { width: 30px; height: 30px; border-radius: 50%; display: grid; place-items: center; color: var(--text); text-decoration: none; }
    .pv-cal-head .rango a:hover { background: var(--soft); }
    .pv-hoy { border: 1px solid var(--borde); border-radius: 99px; padding: 6px 14px; font-size: 13.5px; color: var(--text); text-decoration: none; }
    .pv-dias { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); }
    .pv-dia { min-height: 190px; padding: 12px 10px; border-left: 1px dashed var(--borde); display: flex; flex-direction: column; gap: 8px; }
    .pv-dia:first-child { border-left: 0; }
    .pv-dia .d { font-size: 13.5px; color: var(--muted); display: flex; align-items: center; gap: 6px; padding: 0 4px 4px; }
    .pv-dia .d b { font-size: 18px; color: var(--text); font-weight: 600; }
    .pv-dia.hoy { background: linear-gradient(180deg, #F2FCF4 0, transparent 120px); }
    .pv-dia.hoy .d b { background: var(--verde); border-radius: 8px; padding: 0 7px; }
    .pv-dia.finde { background: #FAFAFB; }
    .pv-ev { position: relative; display: block; background: var(--soft); border-radius: 14px; padding: 10px 11px; text-decoration: none; color: var(--text); font-size: 13px; transition: background .15s; }
    .pv-ev:hover { background: #ECEEF0; color: var(--text); }
    .pv-ev .ic { width: 26px; height: 26px; border-radius: 8px; display: grid; place-items: center; background: var(--card); margin-bottom: 6px; font-size: 13px; }
    .pv-ev.entrega .ic { color: #2456C8; } .pv-ev.evento .ic { color: var(--verde-osc); } .pv-ev.pago .ic { color: #9A5B00; }
    .pv-ev.vencido { background: #FFF4F4; } .pv-ev.vencido .ic { color: #B42318; }
    .pv-ev b { display: block; font-weight: 600; font-size: 13.5px; line-height: 1.25; }
    .pv-ev span { display: block; color: var(--muted); font-size: 12.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pv-vacio-dia { font-size: 12px; color: #B8BCC4; padding: 0 4px; }

    /* Lista */
    .barra-prog { height: 6px; border-radius: 99px; background: #E9EBEE; overflow: hidden; width: 120px; }
    .barra-prog span { display: block; height: 100%; background: var(--verde-osc); border-radius: 99px; }

    @media (max-width: 991.98px) {
        .pv-dias { grid-template-columns: minmax(0, 1fr); }
        .pv-dia { min-height: 0; border-left: 0; border-top: 1px dashed var(--borde); flex-direction: row; flex-wrap: wrap; align-items: flex-start; }
        .pv-dia:first-child { border-top: 0; }
        .pv-dia .d { width: 74px; flex-direction: column; align-items: flex-start; gap: 0; }
        .pv-dia .pv-ev { flex: 1 1 200px; }
        .pv-dia.vacio-movil { display: none; }
    }
    @media (max-width: 575.98px) {
        .pv-head h1 { font-size: 26px; }
        .pv-grid { grid-template-columns: minmax(0, 1fr); }
        .pv-card { padding: 18px 18px 16px; border-radius: 20px; }
        .pv-card h3 { font-size: 19px; }
        .pv-barra > div { width: 100%; min-width: 0; }
        .pv-pills { display: flex; overflow-x: auto; max-width: 100%; scrollbar-width: none; }
        .pv-pills::-webkit-scrollbar { display: none; }
        .pv-stat { padding: 5px 12px 5px 6px; font-size: 13px; }
    }
</style>
@endpush

@section('contenido')
<div class="pv">
    <div class="pv-head">
        <div>
            <h1>Proyectos</h1>
            <p class="sub mb-0">Lo que está en marcha, lo que se atrasó y lo que falta cobrar.</p>
        </div>
        <div class="pv-stats">
            <span class="pv-stat verde"><span class="ic"><i class="bi bi-play-fill"></i></span><b class="num">{{ $resumen['activos'] }}</b> en marcha</span>
            <span class="pv-stat {{ $resumen['atrasados'] ? 'rojo' : '' }}"><span class="ic"><i class="bi bi-exclamation-lg"></i></span><b class="num">{{ $resumen['atrasados'] }}</b> {{ $resumen['atrasados'] === 1 ? 'atrasado' : 'atrasados' }}</span>
            <span class="pv-stat"><span class="ic"><i class="bi bi-flag"></i></span><b class="num">{{ $resumen['semana'] }}</b> entregas en 7 días</span>
            <span class="pv-stat {{ $resumen['vencido'] > 0 ? 'rojo' : '' }}"><span class="ic"><i class="bi bi-cash"></i></span><b class="num">{{ $dinero($resumen['porCobrar']) }}</b> por cobrar{{ $resumen['vencido'] > 0 ? ' · ' . $dinero($resumen['vencido']) . ' vencido' : '' }}</span>
        </div>
    </div>

    {{-- ============ La semana ============ --}}
    @if($filtro === 'activo')
        <section class="pv-cal" aria-label="Entregas y pagos de la semana">
            <div class="pv-cal-head">
                <div class="d-flex align-items-center gap-2">
                    <h2 class="m-0" style="font-size:18px; font-weight:600">La semana</h2>
                    @if($semana['n'] !== 0)<a href="{{ $q(['semana' => null]) }}" class="pv-hoy">Hoy</a>@endif
                </div>
                <div class="rango">
                    <i class="bi bi-calendar3"></i> <span class="num">{{ $rango }}</span>
                    <a href="{{ $q(['semana' => $semana['n'] - 1]) }}" aria-label="Semana anterior"><i class="bi bi-chevron-left"></i></a>
                    <a href="{{ $q(['semana' => $semana['n'] + 1]) }}" aria-label="Semana siguiente"><i class="bi bi-chevron-right"></i></a>
                </div>
            </div>
            <div class="pv-dias">
                @for($i = 0; $i < 7; $i++)
                    @php $dia = $lunes->copy()->addDays($i); $k = $dia->toDateString(); $evs = $semana['eventos'][$k] ?? collect(); @endphp
                    <div class="pv-dia {{ $k === $hoy ? 'hoy' : '' }} {{ $i >= 5 ? 'finde' : '' }} {{ $evs->isEmpty() && $k !== $hoy ? 'vacio-movil' : '' }}">
                        <div class="d">{{ ucfirst($dia->locale('es')->isoFormat('ddd')) }} <b class="num">{{ $dia->day }}</b></div>
                        @forelse($evs as $ev)
                            @php $venc = $k < $hoy || ! empty($ev['atrasado']); @endphp
                            <a href="{{ route('admin.proyectos.show', $ev['proyecto']) }}" class="pv-ev {{ $ev['tipo'] }} {{ $venc ? 'vencido' : '' }}">
                                <span class="ic"><i class="bi {{ ['entrega' => 'bi-flag', 'evento' => 'bi-calendar-event', 'pago' => 'bi-cash-coin'][$ev['tipo']] }}"></i></span>
                                <b>{{ $ev['titulo'] }}</b>
                                <span>{{ $ev['proyecto']->nombre }}</span>
                                <span>{{ $ev['detalle'] }}</span>
                            </a>
                        @empty
                            @if($k === $hoy)<div class="pv-vacio-dia">Nada para hoy</div>@endif
                        @endforelse
                    </div>
                @endfor
            </div>
        </section>
    @endif

    <div class="pv-barra">
        <h2>{{ $filtros[$filtro] ?? 'Proyectos' }}</h2>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <nav class="pv-pills" aria-label="Filtrar proyectos">
                @foreach($filtros as $k => $label)
                    <a href="{{ $q(['filtro' => $k, 'semana' => null]) }}" class="{{ $filtro === $k ? 'activo' : '' }}">{{ $label }}<span class="n num">{{ $conteos[$k] ?? '' }}</span></a>
                @endforeach
            </nav>
            <select class="form-select form-select-sm pv-sel" style="width: auto" aria-label="Tipo de proyecto" onchange="location.href = this.value">
                <option value="{{ $q(['tipo' => null]) }}">Todos los tipos</option>
                @foreach(config('vandu.proyectos') as $k => $m)
                    <option value="{{ $q(['tipo' => $k]) }}" @selected($tipo === $k)>{{ $m['nombre'] }}</option>
                @endforeach
            </select>
            <nav class="pv-pills" aria-label="Vista">
                <a href="{{ $q(['vista' => null]) }}" class="{{ $vista === 'tarjetas' ? 'activo' : '' }}" title="Tarjetas"><i class="bi bi-grid"></i></a>
                <a href="{{ $q(['vista' => 'lista']) }}" class="{{ $vista === 'lista' ? 'activo' : '' }}" title="Lista"><i class="bi bi-list-ul"></i></a>
            </nav>
        </div>
    </div>

    @if($proyectos->isEmpty())
        <div class="panel">
            <div class="vacio">
                <div class="ico"><i class="bi bi-kanban"></i></div>
                <h3>No hay proyectos {{ $filtro === 'todos' ? 'todavía' : 'en esta vista' }}</h3>
                <p>Cuando un cliente acepte una cotización, conviértela en proyecto desde la cotización.</p>
                <a href="{{ route('admin.presupuestos.index', ['filtro' => 'aceptada']) }}" class="btn btn-primario">Ver cotizaciones aceptadas</a>
            </div>
        </div>
    @elseif($vista === 'tarjetas')
        <div class="pv-grid">
            @foreach($proyectos as $p)
                @php
                    [$sClave, $sTexto, $sColor] = $p->salud;
                    $quien = $p->cliente?->empresa ?: $p->cliente?->nombre;
                    $etapas = $p->etapas; $n = $etapas->count();
                    $actual = $p->etapa_actual;
                    $hechas = $etapas->where('estado', 'completada')->count();
                    $on = (int) round($p->progreso / 100 * $bloques);
                    $total = (float) $p->monto_total; $cobrado = $p->pagado;
                    $sigPago = $p->pagos->first(fn ($x) => ! $x->pagado);
                    $limite = $actual?->fecha_limite;
                    $tarde = $limite && $limite->toDateString() < $hoy;
                @endphp
                <article class="pv-card {{ in_array($sColor, ['rojo', 'ambar', 'azul'], true) ? $sColor : '' }}">
                    <div class="pv-top">
                        <div class="pv-tags">
                            <span class="pv-tag">#{{ \Illuminate\Support\Str::of($p->tipo_nombre)->before(' ')->lower() }}</span>
                            <span class="pv-tag {{ $sColor }}">{{ $sTexto }}</span>
                        </div>
                        <div class="dropdown">
                            <button type="button" class="pv-mas" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Acciones de {{ $p->nombre }}"><i class="bi bi-three-dots-vertical"></i></button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="{{ route('admin.proyectos.show', $p) }}"><i class="bi bi-box-arrow-up-right me-2"></i>Abrir proyecto</a></li>
                                <li><button type="button" class="dropdown-item" data-copiar="{{ $p->url_publica }}"><i class="bi bi-link-45deg me-2"></i>Copiar enlace del cliente</button></li>
                                @if($sigPago && $p->cliente)<li><a class="dropdown-item" href="{{ route('admin.proyectos.show', [$p, 'correo' => 'recordatorio_pago']) }}"><i class="bi bi-bell me-2"></i>Recordar pago</a></li>@endif
                                @if($p->cliente)<li><a class="dropdown-item" href="{{ route('admin.clientes.show', $p->cliente) }}"><i class="bi bi-person me-2"></i>Ver cliente</a></li>@endif
                            </ul>
                        </div>
                    </div>

                    <h3><a href="{{ route('admin.proyectos.show', $p) }}">{{ $p->nombre }}</a></h3>
                    @if($actual)
                        <p class="pv-nota">Ahora: {{ $actual->nombre }}{{ $n ? ' · etapa ' . ($etapas->search(fn ($e) => $e->id === $actual->id) + 1) . ' de ' . $n : '' }}</p>
                    @elseif($n)
                        <p class="pv-nota">Todas las etapas completadas</p>
                    @endif

                    @if($sigPago || $limite)
                        <div class="pv-caja">
                            @if($limite)
                                <div class="fila"><span class="pv-flag {{ $tarde ? 'tarde' : '' }}"><i class="bi bi-flag"></i> {{ ucfirst($limite->locale('es')->isoFormat('D MMM')) }}</span>
                                    <span class="{{ $tarde ? 'tarde' : 'secundario' }}">{{ $actual->es_fecha ? 'Agendado ' : 'Termina ' }}{{ $actual->falta_texto }}</span></div>
                            @endif
                            @if($sigPago)
                                <div class="fila"><span class="{{ $sigPago->vencido ? 'tarde' : '' }}"><i class="bi bi-cash-coin me-1"></i>{{ $sigPago->concepto }}{{ $sigPago->vencido ? ' vencido' : '' }}</span><b class="num {{ $sigPago->vencido ? 'tarde' : '' }}">{{ $dinero($sigPago->monto) }}</b></div>
                            @endif
                        </div>
                    @endif

                    <div class="pv-prog">
                        <div class="fila"><span>Avance</span><span class="num">{{ $p->progreso }}%</span></div>
                        <div class="pv-bloques" role="progressbar" aria-valuenow="{{ $p->progreso }}" aria-valuemin="0" aria-valuemax="100" aria-label="Avance">
                            @for($b = 0; $b < $bloques; $b++)<i class="{{ $b < $on ? 'on' : '' }}"></i>@endfor
                        </div>
                    </div>

                    <div class="pv-pie">
                        @include('admin._avatar', ['nombre' => $quien])
                        <span class="quien">{{ $quien }}</span>
                        <span class="pv-chip" title="Etapas terminadas"><i class="bi bi-check2-square"></i> <span class="num">{{ $hechas }}/{{ $n }}</span></span>
                        <span class="pv-chip" title="Cobrado de {{ $dinero($total) }}"><i class="bi bi-wallet2"></i> <span class="num">{{ $total > 0 ? round($cobrado / $total * 100) : 0 }}%</span></span>
                        @if($p->archivos_count)<span class="pv-chip" title="Archivos"><i class="bi bi-paperclip"></i> <span class="num">{{ $p->archivos_count }}</span></span>@endif
                    </div>
                </article>
            @endforeach
        </div>
    @else
        <div class="panel" style="border-radius: 20px">
            <div class="table-responsive">
                <table class="tabla">
                    <thead><tr><th>Proyecto</th><th>Estado</th><th>Avance</th><th>Siguiente paso</th><th class="text-end">Cobrado</th><th><span class="visually-hidden">Enlace</span></th></tr></thead>
                    <tbody>
                    @foreach($proyectos as $p)
                        @php $quien = $p->cliente?->empresa ?: $p->cliente?->nombre; [$sClave, $sTexto, $sColor] = $p->salud; @endphp
                        <tr>
                            <td style="min-width: 260px">
                                <a href="{{ route('admin.proyectos.show', $p) }}" class="fila-link persona">
                                    @include('admin._avatar', ['nombre' => $quien])
                                    <div><div class="principal text-truncate">{{ $p->nombre }}</div><div class="secundario text-truncate">{{ $quien }} · {{ $p->tipo_nombre }}</div></div>
                                </a>
                            </td>
                            <td><span class="pv-tag {{ $sColor }}">{{ $sTexto }}</span></td>
                            <td><div class="d-flex align-items-center gap-2"><div class="barra-prog"><span style="width: {{ $p->progreso }}%"></span></div><span class="secundario num">{{ $p->progreso }}%</span></div></td>
                            <td class="secundario">{{ $p->siguiente_paso ?? 'Terminado' }}</td>
                            <td class="text-end num text-nowrap"><div class="fw-medium">{{ $dinero($p->pagado) }}</div><div class="secundario">de {{ $dinero($p->monto_total) }}</div></td>
                            <td class="text-end"><button type="button" class="btn btn-fantasma btn-icono" data-copiar="{{ $p->url_publica }}" title="Copiar enlace del cliente" aria-label="Copiar enlace del cliente"><i class="bi bi-link-45deg"></i></button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
    @if($paginado && $proyectos->hasPages())
        <div class="mt-3">{{ $proyectos->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
@endsection
