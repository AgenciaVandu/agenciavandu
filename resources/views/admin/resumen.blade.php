@extends('admin.layout')
@section('titulo', 'Resumen')

@php
    $dinero = fn ($n) => '$' . number_format($n, 0);
    $dineroCorto = function ($n) {
        if ($n >= 1000000) return '$' . rtrim(rtrim(number_format($n / 1000000, 1), '0'), '.') . ' M';
        if ($n >= 1000) return '$' . rtrim(rtrim(number_format($n / 1000, 1), '0'), '.') . ' k';
        return '$' . number_format($n, 0);
    };
    // Escala de la gráfica: tope "redondo" por encima del máximo
    $max = max(1, $meses->max('monto'));
    $paso = 10 ** floor(log10($max));
    $tope = ceil($max / $paso) * $paso;
    if ($tope / $paso <= 3) { $paso /= 2; $tope = ceil($max / $paso) * $paso; }
    $marcas = collect(range(0, 4))->map(fn ($i) => $tope * $i / 4)->reverse()->values();
    $nombre = explode(' ', trim(auth()->user()->name ?? ''))[0];
@endphp

@push('head')
<style>
    .kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 20px; }
    .kpi { padding: 18px 20px; }
    .kpi .k { font-size: 13.5px; color: var(--muted); display: flex; align-items: center; gap: 8px; }
    .kpi .k i { font-size: 15px; }
    .kpi .v { font-size: 30px; font-weight: 600; letter-spacing: -.02em; line-height: 1.1; margin-top: 10px; }
    .kpi .d { font-size: 13.5px; color: var(--muted); margin-top: 6px; }
    .kpi.alerta .v { color: var(--amber); }

    .rejilla { display: grid; grid-template-columns: minmax(0, 1.65fr) minmax(0, 1fr); gap: 20px; align-items: start; }

    /* Gráfica */
    .graf { position: relative; height: 240px; display: grid; grid-template-columns: 56px 1fr; }
    .graf .eje { position: relative; }
    .graf .eje span { position: absolute; right: 10px; transform: translateY(-50%); font-size: 12px; color: var(--muted); }
    .graf .area { position: relative; border-bottom: 1px solid var(--line-strong); }
    .graf .linea { position: absolute; left: 0; right: 0; border-top: 1px solid #EEF0F3; }
    .graf .cols { position: absolute; inset: 0; display: grid; grid-template-columns: repeat(6, 1fr); }
    .graf .col { position: relative; display: flex; align-items: flex-end; justify-content: center; gap: 2px; cursor: default; }
    .graf .col:hover, .graf .col:focus-visible { background: rgba(19,22,29,.035); outline: none; }
    .graf .barra { width: 18px; border-radius: 4px 4px 0 0; min-height: 0; }
    .graf .b1 { background: #4A5BD4; }
    .graf .b2 { background: #00A862; }
    .graf .tip { position: absolute; bottom: calc(100% + 8px); left: 50%; transform: translateX(-50%); z-index: 2;
                 background: var(--ink); color: #fff; border-radius: 8px; padding: 10px 12px; font-size: 13px; white-space: nowrap;
                 box-shadow: 0 8px 24px rgba(16,24,40,.2); pointer-events: none; opacity: 0; transition: opacity .12s; }
    .graf .col:hover .tip, .graf .col:focus-visible .tip { opacity: 1; }
    .graf .col:first-child .tip { left: 0; transform: none; }
    .graf .col:nth-last-child(-n+2) .tip { left: auto; right: 0; transform: none; }
    .graf .tip b { display: block; margin-bottom: 4px; }
    .graf .tip .f { display: flex; align-items: center; gap: 8px; justify-content: space-between; }
    .graf .tip .f span:first-child { display: inline-flex; align-items: center; gap: 6px; color: #C9CDD6; }
    .punto { width: 8px; height: 8px; border-radius: 2px; display: inline-block; }
    .meses { display: grid; grid-template-columns: 56px repeat(6, 1fr); margin-top: 8px; }
    .meses span { text-align: center; font-size: 12.5px; color: var(--muted); }
    .meses span.actual { color: var(--text); font-weight: 600; }
    .leyenda { display: flex; gap: 16px; font-size: 13px; color: var(--text-2); }
    .leyenda span { display: inline-flex; align-items: center; gap: 6px; }

    /* Listas */
    .lista { list-style: none; margin: 0; padding: 0; }
    .lista li + li { border-top: 1px solid var(--line); }
    .lista a { display: flex; align-items: center; gap: 12px; padding: 13px 20px; color: inherit; text-decoration: none; }
    .lista a:hover { background: #FAFBFC; }
    .lista .der { margin-left: auto; text-align: right; flex: none; }
    .lista .vacia { padding: 22px 20px; color: var(--muted); font-size: 14px; display: flex; gap: 10px; align-items: center; }

    @media (max-width: 1199.98px) { .kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .rejilla { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 575.98px) { .kpis { gap: 10px; } .kpi { padding: 14px; } .kpi .v { font-size: 24px; } .graf .barra { width: 11px; } }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <h1>{{ $saludo }}{{ $nombre ? ', ' . $nombre : '' }}</h1>
        <p class="sub">{{ ucfirst(now(config('vandu.zona_horaria'))->locale('es')->isoFormat('dddd D [de] MMMM')) }}</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.clientes.create') }}" class="btn btn-borde"><i class="bi bi-person-plus me-1"></i> Nuevo cliente</a>
        <a href="{{ route('admin.presupuestos.create') }}" class="btn btn-primario"><i class="bi bi-plus-lg me-1"></i> Nueva cotización</a>
    </div>
</div>

<div class="aviso-push" x-data="avisoPush()" x-show="visible" x-cloak>
    <i class="bi bi-bell"></i>
    <div class="t"><b>Recibe avisos en este dispositivo</b><span>Te avisamos cuando un cliente abra su cotización o te escriban desde el sitio.</span></div>
    <a href="{{ route('admin.notificaciones') }}" class="btn btn-primario btn-sm">Activar</a>
    <button type="button" class="btn btn-fantasma btn-icono" @click="cerrar()" aria-label="Ahora no"><i class="bi bi-x-lg"></i></button>
</div>
@php $contactosNuevos = \App\Models\Cliente::where('nuevo', true)->orderByDesc('contacto_at')->get(['id', 'nombre', 'empresa', 'interes', 'contacto_at']); @endphp
@if($contactosNuevos->isNotEmpty())
    <a href="{{ $contactosNuevos->count() === 1 ? route('admin.clientes.show', $contactosNuevos->first()) : route('admin.clientes.index', ['ver' => 'nuevos']) }}" class="aviso-push aviso-contactos text-reset text-decoration-none">
        <i class="bi bi-inbox-fill"></i>
        <div class="t"><b>{{ $contactosNuevos->count() === 1 ? '1 contacto nuevo desde el sitio' : $contactosNuevos->count() . ' contactos nuevos desde el sitio' }}</b>
            <span>{{ $contactosNuevos->take(3)->map(fn ($c) => ($c->empresa ?: $c->nombre) . ($c->interes ? ' · ' . $c->interes : ''))->implode(' — ') }}</span></div>
        <i class="bi bi-chevron-right" style="font-size:15px; color: var(--muted)"></i>
    </a>
@endif
@include('admin._este-mes', ['mes' => \App\Support\EsteMes::datos(true), 'conIva' => true, 'enlace' => route('admin.finanzas', ['periodo' => 'mes'])])

<div class="kpis">
    <a href="{{ route('admin.presupuestos.index', ['filtro' => 'vigentes']) }}" class="panel kpi text-decoration-none text-reset">
        <div class="k"><i class="bi bi-file-earmark-text"></i> Cotizaciones vigentes</div>
        <div class="v num">{{ $kpi['vigentes'] }}</div>
        <div class="d num">{{ $dinero($kpi['vigentesMonto']) }} en juego, antes de IVA</div>
    </a>
    <a href="{{ route('admin.presupuestos.index', ['filtro' => 'por_vencer']) }}" class="panel kpi text-decoration-none text-reset {{ $kpi['porVencer'] ? 'alerta' : '' }}">
        <div class="k"><i class="bi bi-hourglass-split"></i> Vencen en 3 días</div>
        <div class="v num">{{ $kpi['porVencer'] }}</div>
        <div class="d">{{ $kpi['porVencer'] ? 'Buen momento para dar seguimiento' : 'Nada por vencer' }}</div>
    </a>
    <a href="{{ route('admin.finanzas') }}#por-cobrar" class="panel kpi text-decoration-none text-reset {{ $kpi['vencidos'] ? 'alerta' : '' }}">
        <div class="k"><i class="bi bi-cash-coin"></i> Por cobrar</div>
        <div class="v num">{{ $dinero($kpi['porCobrar']) }}</div>
        <div class="d num">{{ $kpi['pendientes'] }} {{ $kpi['pendientes'] === 1 ? 'pago pendiente' : 'pagos pendientes' }}@if($kpi['vencidos']) · {{ $kpi['vencidos'] }} {{ $kpi['vencidos'] === 1 ? 'vencido' : 'vencidos' }}@endif, con IVA</div>
    </a>
    <div class="panel kpi">
        <div class="k"><i class="bi bi-graph-up-arrow"></i> Tasa de aceptación</div>
        <div class="v num">{{ $kpi['tasa'] === null ? '—' : $kpi['tasa'] . '%' }}</div>
        <div class="d num">{{ $kpi['resueltas'] ? "De {$kpi['resueltas']} resueltas en 90 días" : 'Aún sin cotizaciones resueltas' }}</div>
    </div>
</div>

<div class="rejilla">
    <div class="d-grid gap-4">
        <section class="panel">
            <div class="panel-head">
                <h2>Monto cotizado por mes</h2>
                <div class="leyenda">
                    <span><i class="punto" style="background:#4A5BD4"></i> Cotizado</span>
                    <span><i class="punto" style="background:#00A862"></i> Aceptado</span>
                </div>
            </div>
            <div class="panel-body">
                <div class="graf" aria-hidden="true">
                    <div class="eje">
                        @foreach($marcas as $i => $m)
                            <span class="num" style="top: {{ $i * 25 }}%">{{ $dineroCorto($m) }}</span>
                        @endforeach
                    </div>
                    <div class="area">
                        @foreach(range(0, 3) as $i)<div class="linea" style="top: {{ $i * 25 }}%"></div>@endforeach
                        <div class="cols">
                            @foreach($meses as $m)
                                <div class="col" tabindex="0">
                                    <div class="barra b1" style="height: {{ $m['monto'] / $tope * 100 }}%"></div>
                                    <div class="barra b2" style="height: {{ $m['aceptado'] / $tope * 100 }}%"></div>
                                    <div class="tip">
                                        <b>{{ $m['completa'] }}</b>
                                        <div class="f"><span><i class="punto" style="background:#4A5BD4"></i> Cotizado</span><span class="num">{{ $dinero($m['monto']) }}</span></div>
                                        <div class="f"><span><i class="punto" style="background:#00A862"></i> Aceptado</span><span class="num">{{ $dinero($m['aceptado']) }}</span></div>
                                        <div class="f"><span>Cotizaciones</span><span class="num">{{ $m['cuantas'] }}</span></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="meses" aria-hidden="true"><span></span>@foreach($meses as $m)<span class="{{ $m['actual'] ? 'actual' : '' }}">{{ $m['etiqueta'] }}</span>@endforeach</div>

                <table class="visually-hidden">
                    <caption>Monto cotizado y aceptado por mes, antes de IVA</caption>
                    <tr><th>Mes</th><th>Cotizado</th><th>Aceptado</th><th>Cotizaciones</th></tr>
                    @foreach($meses as $m)<tr><td>{{ $m['completa'] }}</td><td>{{ $dinero($m['monto']) }}</td><td>{{ $dinero($m['aceptado']) }}</td><td>{{ $m['cuantas'] }}</td></tr>@endforeach
                </table>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <h2>Cotizaciones recientes</h2>
                <a href="{{ route('admin.presupuestos.index', ['filtro' => 'todas']) }}" class="btn btn-fantasma btn-sm">Ver todas</a>
            </div>
            @if($recientes->isEmpty())
                <div class="vacio">
                    <div class="ico"><i class="bi bi-file-earmark-plus"></i></div>
                    <h3>Todavía no hay cotizaciones</h3>
                    <p>Crea la primera y compártela con tu cliente en un enlace.</p>
                    <a href="{{ route('admin.presupuestos.create') }}" class="btn btn-primario">Crear cotización</a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="tabla">
                        <tbody>
                        @foreach($recientes as $p)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.presupuestos.edit', $p) }}" class="fila-link persona">
                                        @include('admin._avatar', ['nombre' => $p->cliente_empresa ?: $p->cliente_nombre])
                                        <div><div class="principal text-truncate">{{ $p->cliente_empresa ?: $p->cliente_nombre }}</div>
                                            <div class="secundario num">{{ $p->folio }} · {{ $p->fecha->locale('es')->isoFormat('D MMM') }}</div></div>
                                    </a>
                                </td>
                                <td class="text-end num fw-medium text-nowrap">{{ $p->monto($p->modo_iva === 'desglosado' ? $p->total : $p->subtotal) }}</td>
                                <td class="text-end"><span class="estado estado-{{ $p->estado }}">{{ \App\Models\Presupuesto::ESTADOS[$p->estado] }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    <div class="d-grid gap-4">
        <section class="panel">
            <div class="panel-head"><h2>Por vencer</h2><span class="ayuda">Próximas 72 h</span></div>
            <ul class="lista">
                @forelse($porVencer as $p)
                    <li><a href="{{ route('admin.presupuestos.edit', $p) }}">
                        @include('admin._avatar', ['nombre' => $p->cliente_empresa ?: $p->cliente_nombre])
                        <div class="min-w-0"><div class="principal text-truncate">{{ $p->cliente_empresa ?: $p->cliente_nombre }}</div>
                            <div class="secundario num">{{ $p->folio }}</div></div>
                        <div class="der">@include('admin.presupuestos._vigencia', ['p' => $p, 'corto' => true])</div>
                    </a></li>
                @empty
                    <li class="vacia"><i class="bi bi-check2-circle text-success"></i> Ninguna cotización vence pronto.</li>
                @endforelse
            </ul>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Enviadas sin abrir</h2><span class="ayuda">Vigentes</span></div>
            <ul class="lista">
                @forelse($sinAbrir as $p)
                    <li><a href="{{ route('admin.presupuestos.edit', $p) }}">
                        @include('admin._avatar', ['nombre' => $p->cliente_empresa ?: $p->cliente_nombre])
                        <div class="min-w-0"><div class="principal text-truncate">{{ $p->cliente_empresa ?: $p->cliente_nombre }}</div>
                            <div class="secundario">Enviada {{ $p->updated_at->locale('es')->diffForHumans() }}</div></div>
                        <div class="der"><i class="bi bi-chevron-right text-secondary"></i></div>
                    </a></li>
                @empty
                    <li class="vacia"><i class="bi bi-envelope-open"></i> Tus clientes ya abrieron todo lo enviado.</li>
                @endforelse
            </ul>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Actividad de clientes</h2></div>
            <ul class="lista">
                @forelse($actividad as $p)
                    <li><a href="{{ route('admin.presupuestos.edit', $p) }}">
                        <span class="avatar" style="background: var(--blue-soft); color: var(--blue)"><i class="bi bi-eye"></i></span>
                        <div class="min-w-0"><div class="text-truncate"><span class="principal">{{ $p->cliente_nombre }}</span> abrió {{ $p->folio }}</div>
                            <div class="secundario">{{ $p->ultima_vista_at->locale('es')->diffForHumans() }} · {{ $p->vistas }} {{ $p->vistas === 1 ? 'vista' : 'vistas' }}</div></div>
                    </a></li>
                @empty
                    <li class="vacia"><i class="bi bi-eye-slash"></i> Cuando un cliente abra su cotización, aparece aquí.</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
@endsection
