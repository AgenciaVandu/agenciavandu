@extends('admin.layout')
@section('titulo', 'Reportes del gimnasio')
@include('admin.fitness._estilos')

@php
    $maxDia = max(1, $diario->max('asistencias') ?? 1);
    $periodos = \App\Http\Controllers\Admin\FinanzasController::PERIODOS;
@endphp

@push('head')
<style>
    .barras-d { display: flex; align-items: flex-end; gap: 4px; height: 140px; padding: 14px 20px 6px; }
    .barras-d div { flex: 1; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; height: 100%; gap: 4px; min-width: 0; }
    .barras-d .b { width: 100%; max-width: 28px; background: var(--ink); border-radius: 5px 5px 2px 2px; min-height: 2px; }
    .barras-d .e { font-size: 10.5px; color: var(--muted); }
    .rep-grid { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr); gap: 20px; align-items: start; }
    @media (max-width: 1199.98px) { .rep-grid { grid-template-columns: minmax(0, 1fr); } }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div><h1>Reportes del gimnasio</h1><p class="sub">{{ $desde ? ucfirst($desde->locale('es')->isoFormat('D [de] MMMM')) . ' al ' . $hasta->locale('es')->isoFormat('D [de] MMMM YYYY') : 'Desde el inicio' }}</p></div>
    <nav class="segmento" aria-label="Periodo">
        @foreach($periodos as $k => $t)<a href="{{ route('admin.fitness-reportes', ['periodo' => $k]) }}" class="{{ $periodo === $k ? 'activo' : '' }}">{{ $t }}</a>@endforeach
    </nav>
</div>

<nav class="segmento mb-3" aria-label="Reportes">
    <a href="{{ route('admin.fitness-reportes') }}" class="activo" aria-current="page"><i class="bi bi-bar-chart-line"></i> Resumen</a>
    <a href="{{ route('admin.fitness-reportes.horarios') }}"><i class="bi bi-grid-3x3"></i> Horarios</a>
</nav>

<div class="panel fx-kpis">
    <div><div class="k"><i class="bi bi-person-check"></i> Asistencias</div><div class="v num">{{ number_format($kpi['asistencias']) }}</div><div class="s">{{ $kpi['asistieron'] }} atletas distintos</div></div>
    <div><div class="k"><i class="bi bi-speedometer2"></i> Ocupación</div><div class="v num">{{ $kpi['ocupacion'] }}%</div><div class="s">{{ $kpi['reservas'] }} lugares en {{ $kpi['clases'] }} clases</div></div>
    <div><div class="k"><i class="bi bi-x-circle"></i> Cancelaciones y faltas</div><div class="v num">{{ $kpi['canceladas'] }} <span class="secundario" style="font-size:14px;font-weight:400">/ {{ $kpi['faltas'] }}</span></div><div class="s">canceladas / faltaron · {{ $clasesCanceladas }} clases canceladas</div></div>
    <div><div class="k"><i class="bi bi-people"></i> Atletas activos</div><div class="v num">{{ $kpi['activos'] }}</div><div class="s">con paquete vigente hoy</div></div>
</div>

<div class="rep-grid">
    <div class="d-grid gap-4">
        <section class="panel">
            <div class="panel-head"><h2>Asistencias por día</h2></div>
            @if($diario->isEmpty())<div class="panel-body secundario">Sin clases en este periodo.</div>@else
            <div class="barras-d" role="img" aria-label="Asistencias por día">
                @foreach($diario as $d)
                    <div title="{{ \Illuminate\Support\Carbon::parse($d['dia'])->locale('es')->isoFormat('ddd D MMM') }}: {{ $d['asistencias'] }}">
                        <span class="b" style="height: {{ round($d['asistencias'] / $maxDia * 100) }}%"></span>
                        <span class="e num">{{ \Illuminate\Support\Carbon::parse($d['dia'])->format('j') }}</span>
                    </div>
                @endforeach
            </div>@endif
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Clases más populares</h2></div>
            <div class="table-responsive"><table class="tabla">
                <thead><tr><th>Clase</th><th class="text-end">Clases</th><th class="text-end">Reservas</th><th class="text-end">Asistencias</th><th style="width: 160px">Ocupación</th></tr></thead>
                <tbody>
                @forelse($porTipo as $t)
                    <tr><td><x-fitness.tipo :tipo="$t['tipo']" /></td><td class="text-end num">{{ $t['clases'] }}</td><td class="text-end num">{{ $t['reservas'] }}</td><td class="text-end num">{{ $t['asistencias'] }}</td>
                        <td><div class="fx-ocupacion"><div class="barra"><span style="width: {{ $t['ocupacion'] }}%"></span></div><div class="t num"><span>{{ $t['ocupacion'] }}%</span></div></div></td></tr>
                @empty<tr><td colspan="5" class="secundario">Sin datos.</td></tr>@endforelse
                </tbody></table></div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Ocupación por horario</h2></div>
            <div class="fx-lista">
                @forelse($porHora as $h)
                    <div class="fx-fila"><b class="num" style="width: 56px">{{ $h['hora'] }}</b>
                        <div class="flex-grow-1"><div class="fx-ocupacion"><div class="barra {{ $h['ocupacion'] >= 100 ? 'llena' : ($h['ocupacion'] >= 80 ? 'casi' : '') }}"><span style="width: {{ min(100, $h['ocupacion']) }}%"></span></div></div></div>
                        <span class="num" style="width: 90px; text-align: right">{{ $h['ocupacion'] }}% · {{ $h['clases'] }}</span></div>
                @empty<div class="panel-body secundario">Sin datos.</div>@endforelse
            </div>
        </section>
    </div>

    <aside class="d-grid gap-4">
        <section class="panel">
            <div class="panel-head"><h2>Por renovar</h2><span class="ayuda">vencen en 7 días o les queda 1 clase</span></div>
            <div class="fx-lista">
                @forelse($porVencer as $m)
                    <a class="fx-fila text-reset text-decoration-none" href="{{ route('admin.clientes.show', [$m->cliente_id, 'ver' => 'atleta']) }}">
                        <x-fitness.avatar :atleta="$m->cliente" />
                        <div class="min-w-0 flex-grow-1"><div class="principal text-truncate">{{ $m->cliente?->nombre }}</div><div class="secundario small">{{ $m->nombre }} · {{ $m->restantes_texto }}</div></div>
                        <span class="small {{ $m->dias_restantes <= 2 ? 'text-danger' : 'secundario' }}">{{ $m->dias_restantes <= 0 ? 'Vence hoy' : 'en ' . $m->dias_restantes . ' d' }}</span>
                    </a>
                @empty<div class="panel-body secundario small">Nadie por renovar esta semana.</div>@endforelse
            </div>
        </section>
        <section class="panel">
            <div class="panel-head"><h2>Los más constantes</h2></div>
            <div class="fx-lista">
                @forelse($top as $i => $t)
                    <div class="fx-fila"><span class="secundario num" style="width: 18px">{{ $i + 1 }}</span><x-fitness.avatar :atleta="$t->cliente" />
                        <span class="flex-grow-1 text-truncate">{{ $t->cliente?->nombre }}</span><b class="num">{{ $t->n }}</b></div>
                @empty<div class="panel-body secundario small">Sin asistencias en este periodo.</div>@endforelse
            </div>
        </section>
    </aside>
</div>
@endsection
