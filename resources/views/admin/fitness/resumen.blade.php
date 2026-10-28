@extends('admin.layout')
@section('titulo', 'Inicio')
@include('admin.fitness._estilos')

@php
    $u = auth()->user();
    $dinero = fn ($n) => '$' . number_format((float) $n, 0);
    $hora = (int) now(config('vandu.zona_horaria'))->format('G');
    $saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
    $pctHoy = $kpi['cupoHoy'] ? round($kpi['reservasHoy'] / $kpi['cupoHoy'] * 100) : 0;
@endphp

@push('head')
<style>
    .rf-grid { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 20px; align-items: start; }
    .sem { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 10px; padding: 16px 20px 18px; }
    .sem div { text-align: center; } .sem .e { font-size: 12.5px; color: var(--muted); margin-bottom: 6px; }
    .sem .col { height: 90px; background: var(--sunken); border-radius: 8px; display: flex; align-items: flex-end; overflow: hidden; }
    .sem .col span { width: 100%; background: var(--ink); }
    .sem .n { font-size: 12px; margin-top: 6px; color: var(--text-2); }
    .arranque { display: flex; gap: 12px; flex-wrap: wrap; }
    .arranque a { flex: 1 1 200px; }
    @media (max-width: 1199.98px) { .rf-grid { grid-template-columns: minmax(0, 1fr); } }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div><h1>{{ $saludo }}, {{ $u->primer_nombre }}</h1><p class="sub">{{ ucfirst(now(config('vandu.zona_horaria'))->locale('es')->isoFormat('dddd D [de] MMMM')) }}</p></div>
    <div class="d-flex flex-wrap gap-2">
        @if($u->puede('asistencia'))<a href="{{ route('admin.escanear') }}" class="btn btn-borde"><i class="bi bi-qr-code-scan me-1"></i> Escanear</a>@endif
        @if($u->puede('clientes'))<a href="{{ route('admin.clientes.create') }}" class="btn btn-primario"><i class="bi bi-person-plus me-1"></i> Nuevo atleta</a>@endif
    </div>
</div>

@if(($sinTipos || $sinPaquetes) && $u->puede('clases'))
    <section class="panel panel-body mb-4">
        <h2 class="fs-6 fw-semibold mb-1">Arranca tu gimnasio en 3 pasos</h2>
        <p class="secundario small mb-3">Después registra a tus atletas y mándales su acceso para que reserven.</p>
        <div class="arranque">
            <a href="{{ route('admin.clase-tipos') }}" class="btn {{ $sinTipos ? 'btn-primario' : 'btn-borde' }}"><i class="bi bi-tags me-1"></i> 1. Tipos de clase @unless($sinTipos)<i class="bi bi-check2"></i>@endunless</a>
            <a href="{{ route('admin.paquetes') }}" class="btn {{ ! $sinTipos && $sinPaquetes ? 'btn-primario' : 'btn-borde' }}"><i class="bi bi-ticket-perforated me-1"></i> 2. Paquetes y precios @unless($sinPaquetes)<i class="bi bi-check2"></i>@endunless</a>
            <a href="{{ route('admin.clases.create') }}" class="btn btn-borde"><i class="bi bi-calendar-plus me-1"></i> 3. Programa tus clases</a>
        </div>
        @if($sinTipos)
            <form method="post" action="{{ route('admin.clases.ejemplos') }}" class="mt-3">@csrf<button class="btn btn-link btn-sm px-0 text-secondary">O carga un gimnasio de ejemplo (clases HYROX, paquetes y atletas) para probar todo</button></form>
        @endif
    </section>
@endif

@if((\App\Support\Fitness\Ejemplo::hay() || \App\Support\Fitness\Ejemplo::hayHistorial()) && $u->puede('clases'))
    <div class="aviso" style="background: var(--blue-soft); color: var(--blue); border: 1px solid #cddcff; flex-wrap: wrap">
        <i class="bi bi-lightbulb"></i>
        <span class="flex-grow-1">Estás viendo un gimnasio de ejemplo: clases HYROX, paquetes, atletas y 8 semanas de historial. Cuando captures lo tuyo, quítalo.</span>
        <form method="post" action="{{ route('admin.clases.ejemplos.quitar') }}" onsubmit="return confirm('¿Quitar las clases, paquetes y atletas de ejemplo?')">@csrf @method('delete')<button class="btn btn-borde btn-sm">Quitar ejemplos</button></form>
    </div>
@endif

<div class="panel fx-kpis">
    <a href="{{ route('admin.clases.index') }}"><div class="k"><i class="bi bi-calendar-check"></i> Clases hoy</div><div class="v num">{{ $kpi['clasesHoy'] }}</div><div class="s">{{ $kpi['reservasHoy'] }} de {{ $kpi['cupoHoy'] }} lugares · {{ $pctHoy }}%</div></a>
    <a href="{{ route('admin.clases.index') }}"><div class="k"><i class="bi bi-person-check"></i> Asistencias hoy</div><div class="v num">{{ $kpi['asistenciasHoy'] }}</div><div class="s">de {{ $kpi['reservasHoy'] }} reservados</div></a>
    <a href="{{ route('admin.clientes.index', ['ver' => 'activos']) }}"><div class="k"><i class="bi bi-people"></i> Atletas activos</div><div class="v num">{{ $kpi['activos'] }}</div><div class="s">con paquete vigente · {{ $kpi['atletas'] }} registrados</div></a>
    <a href="{{ route('admin.clientes.index', ['ver' => 'por_vencer']) }}"><div class="k"><i class="bi bi-hourglass-split"></i> Por renovar</div><div class="v num" style="{{ $kpi['porRenovar'] ? 'color: var(--amber)' : '' }}">{{ $kpi['porRenovar'] }}</div><div class="s">vencen en 7 días o les queda 1 clase</div></a>
</div>
@if($u->puede('finanzas'))
<div class="panel fx-kpis">
    <a href="{{ route('admin.finanzas') }}"><div class="k"><i class="bi bi-bag-check"></i> Vendido este mes</div><div class="v num">{{ $dinero($kpi['vendidoMes']) }}</div></a>
    <a href="{{ route('admin.finanzas') }}"><div class="k"><i class="bi bi-cash-coin"></i> Cobrado este mes</div><div class="v num">{{ $dinero($kpi['cobradoMes']) }}</div></a>
    <a href="{{ route('admin.clientes.index', ['ver' => 'pendientes']) }}"><div class="k"><i class="bi bi-exclamation-circle"></i> Te deben</div><div class="v num" style="{{ $kpi['porCobrar'] ? 'color: var(--red)' : '' }}">{{ $dinero($kpi['porCobrar']) }}</div></a>
    <div><div class="k"><i class="bi bi-ticket-perforated"></i> Paquetes vigentes</div><div class="v num">{{ $kpi['membresias'] }}</div></div>
</div>
@endif

<div class="rf-grid">
    <div class="d-grid gap-4">
        <section class="panel">
            <div class="panel-head"><h2>Clases de hoy</h2>@if($u->puede('clases'))<a href="{{ route('admin.clases.create', ['fecha' => now(config('vandu.zona_horaria'))->toDateString()]) }}" class="btn btn-fantasma btn-sm"><i class="bi bi-plus"></i> Clase</a>@endif</div>
            <div class="fx-lista">
                @forelse($clasesHoy as $c)
                    <a href="{{ route('admin.clases.show', $c) }}" class="fx-fila text-reset text-decoration-none" style="{{ $c->cancelada ? 'opacity:.55' : '' }}">
                        <span class="num" style="width: 92px; font-weight: 600">{{ $c->horario }}</span>
                        <span class="fx-color" style="background: {{ $c->color }}"></span>
                        <div class="flex-grow-1 min-w-0"><div class="principal">{{ $c->nombre }}</div>
                            <div class="secundario small">{{ $c->cancelada ? 'Cancelada' : ($c->coach?->name ?? 'Sin entrenador') . ' · ' . $c->asistencias_count . ' ya entraron' }}</div></div>
                        @unless($c->cancelada)<x-fitness.ocupacion :ocupados="$c->ocupadas_count" :cupo="$c->cupo" style="width: 130px" />@endunless
                    </a>
                @empty
                    <div class="vacio py-4"><div class="ico"><i class="bi bi-calendar"></i></div><h3>No hay clases hoy</h3></div>
                @endforelse
            </div>
        </section>
        <section class="panel">
            <div class="panel-head"><h2>Ocupación de la semana</h2></div>
            <div class="sem">
                @foreach($dias as $d)
                    @php $p = $d['cupo'] ? round($d['ocupados'] / $d['cupo'] * 100) : 0; @endphp
                    <div title="{{ $d['ocupados'] }} de {{ $d['cupo'] }} lugares en {{ $d['clases'] }} clases"><div class="e">{{ $d['etiqueta'] }}</div><div class="col"><span style="height: {{ $p }}%"></span></div><div class="n num">{{ $d['clases'] ? $p . '%' : '—' }}</div></div>
                @endforeach
            </div>
        </section>
    </div>
    <aside class="d-grid gap-4">
        <section class="panel">
            <div class="panel-head"><h2>Por renovar</h2>@if($kpi['porRenovar'])<a href="{{ route('admin.clientes.index', ['ver' => 'por_vencer']) }}" class="btn btn-fantasma btn-sm">Ver todos</a>@endif</div>
            <div class="fx-lista">
                @forelse($porRenovar as $m)
                    <a class="fx-fila text-reset text-decoration-none" href="{{ route('admin.clientes.show', [$m->cliente_id, 'ver' => 'atleta']) }}">
                        <x-fitness.avatar :atleta="$m->cliente" />
                        <div class="flex-grow-1 min-w-0"><div class="principal text-truncate">{{ $m->cliente?->nombre }}</div><div class="secundario small">{{ $m->nombre }} · {{ $m->restantes_texto }}</div></div>
                        <span class="small {{ $m->dias_restantes <= 2 ? 'text-danger' : 'secundario' }}">{{ $m->dias_restantes <= 0 ? 'hoy' : $m->dias_restantes . ' d' }}</span>
                    </a>
                @empty
                    <div class="panel-body secundario small">Nadie por renovar esta semana.</div>
                @endforelse
            </div>
        </section>
        <section class="panel">
            <div class="panel-head"><h2>Actividad reciente</h2></div>
            <div class="fx-lista">
                @forelse($actividad as $x)
                    <div class="fx-fila"><x-fitness.avatar :atleta="$x['atleta']" />
                        <div class="min-w-0 small"><b>{{ $x['atleta']?->primer_nombre }}</b> {{ $x['texto'] }} {{ $x['clase']?->nombre }} <span class="secundario">· {{ $x['clase']?->inicio->locale('es')->isoFormat('ddd D, H:mm') }}</span>
                            <div class="secundario">{{ $x['cuando']->locale('es')->diffForHumans() }}</div></div></div>
                @empty
                    <div class="panel-body secundario small">Aquí verás reservaciones, cancelaciones y entradas.</div>
                @endforelse
            </div>
        </section>
    </aside>
</div>
@endsection
