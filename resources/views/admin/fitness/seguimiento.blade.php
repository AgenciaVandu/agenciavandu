@extends('admin.layout')
@section('titulo', 'Seguimiento')
@include('admin.fitness._estilos')

@php
    use App\Support\Fitness\Embudo;
    $r = $resultados;
    $d = fn ($n) => '$' . number_format($n ?? 0, 0);
    $u = fn (array $x) => route('admin.seguimiento', array_filter(array_merge(['ver' => $ver === 'tablero' ? null : $ver, 'periodo' => $periodo === 'mes' ? null : $periodo, 'q' => $q ?: null], $x)));
    $gestiona = auth()->user()->puede('membresias');
    $descartados = $grupos['descartado'];
@endphp

@push('head')
<style>
    .seg-tablero { display: grid; grid-auto-flow: column; grid-auto-columns: minmax(200px, 1fr); gap: 12px; overflow-x: auto; padding-bottom: 8px; scroll-snap-type: x proximity; align-items: start; }
    .seg-col { background: var(--sunken, #F1F2F4); border-radius: 14px; padding: 10px; display: grid; gap: 8px; scroll-snap-align: start; }
    .seg-col .cab { padding: 4px 4px 2px; }
    .seg-col .cab .n { font-weight: 600; display: flex; justify-content: space-between; align-items: center; gap: 8px; }
    .seg-col .cab .n .c { font-size: 12px; color: var(--muted); font-weight: 500; }
    .seg-col .cab .a { font-size: 12.5px; color: var(--muted); margin-top: 2px; line-height: 1.35; }
    .seg-col .vacia { font-size: 13px; color: var(--muted); padding: 14px 6px; text-align: center; }
    .seg-t { position: relative; background: var(--surface, #fff); border: 1px solid var(--line); border-radius: 12px; padding: 10px 12px; display: grid; gap: 6px; }
    .seg-t .quien { padding-right: 26px; display: flex; align-items: center; gap: 10px; color: inherit; text-decoration: none; min-width: 0; }
    .seg-t .quien .fx-avatar { width: 32px; height: 32px; font-size: 12px; }
    .seg-t .quien b { font-weight: 600; line-height: 1.2; }
    .seg-t .linea { font-size: 12.5px; color: var(--text-2); }
    .seg-t .linea.info { color: var(--blue, #2563EB); } .seg-t .linea.ok { color: var(--green-ink); } .seg-t .linea.alerta { color: #B45309; } .seg-t .linea.malo { color: var(--red); }
    .seg-t .nota { font-size: 12.5px; color: var(--muted); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .seg-t .tarea { font-size: 12px; color: var(--text-2); display: flex; gap: 5px; align-items: center; }
    .seg-t .tarea.vencida { color: var(--red); }
    .seg-t .acciones { display: flex; gap: 6px; align-items: center; margin-top: 2px; }
    .seg-t .acciones .btn { padding: 3px 10px; font-size: 12.5px; white-space: nowrap; }
    .seg-t .mas { position: absolute; top: 6px; right: 6px; border: 0; background: none; width: 28px; height: 28px; border-radius: 7px; color: var(--muted); }
    .seg-t .mas:hover, .seg-t .mas[aria-expanded=true] { background: var(--sunken); color: var(--text); }
    .seg-t .acciones .wa { color: #128C7E; }
    .seg-t .panel-mas { grid-column: 1 / -1; border-top: 1px solid var(--line); padding-top: 8px; display: grid; gap: 10px; }
    .seg-t .panel-mas .t { font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
    .seg-t .panel-mas .form-control, .seg-t .panel-mas .form-select { font-size: 13px; padding: 4px 8px; }
    .seg-t .cuando { display: grid; grid-template-columns: minmax(0, 1fr); gap: 6px; }
    .seg-t .cuando .form-control { min-width: 0; }
    .seg-pie { font-size: 12.5px; color: var(--muted); }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div><h1>Seguimiento</h1><p class="sub">Quién preguntó, quién vino a su prueba y quién está por renovar. Cada quien cambia de columna solo al asignarle su prueba o su paquete.</p></div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.clientes.index') }}" class="btn btn-borde"><i class="bi bi-people me-1"></i> Atletas</a>
    </div>
</div>

{{-- Resultados del periodo --}}
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
    <nav class="segmento" aria-label="Periodo">
        @foreach(Embudo::PERIODOS as $k => $t)<a href="{{ $u(['periodo' => $k === 'mes' ? null : $k]) }}" class="{{ $periodo === $k ? 'activo' : '' }}">{{ $t }}</a>@endforeach
    </nav>
</div>
<div class="panel fx-kpis">
    <div><div class="k"><i class="bi bi-stars"></i> Clases de prueba</div><div class="v num">{{ $r['pruebas'] }}</div>
        <div class="s">{{ count($grupos['interesado']) }} {{ count($grupos['interesado']) === 1 ? 'interesado espera' : 'interesados esperan' }} la suya</div></div>
    <div><div class="k"><i class="bi bi-person-check"></i> Se inscribieron</div><div class="v num">{{ $r['conversion'] !== null ? $r['conversion'] . '%' : '—' }}</div>
        <div class="s">{{ $r['convertidos'] }} de {{ $r['pruebas'] }} que probaron</div></div>
    <div><div class="k"><i class="bi bi-arrow-repeat"></i> Renovaron</div><div class="v num">{{ $r['renovacion'] !== null ? $r['renovacion'] . '%' : '—' }}</div>
        <div class="s">{{ $r['renovaron'] }} de {{ $r['tocaba'] }} paquetes que vencieron</div></div>
    <div><div class="k"><i class="bi bi-cash-coin"></i> Ventas del periodo</div><div class="v num">{{ $d($r['ingresoNuevos'] + $r['ingresoRenov']) }}</div>
        <div class="s">{{ $d($r['ingresoNuevos']) }} nuevos ({{ $r['nuevos'] }}) · {{ $d($r['ingresoRenov']) }} renovaciones ({{ $r['renovaciones'] }})</div></div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <nav class="segmento" aria-label="Vista">
        <a href="{{ $u(['ver' => null]) }}" class="{{ $ver === 'tablero' ? 'activo' : '' }}">Tablero</a>
        <a href="{{ $u(['ver' => 'descartados']) }}" class="{{ $ver === 'descartados' ? 'activo' : '' }}">Descartados @if($descartados->count())<span class="n num">{{ $descartados->count() }}</span>@endif</a>
    </nav>
    <form class="buscador" method="get" role="search">
        @if($ver !== 'tablero')<input type="hidden" name="ver" value="{{ $ver }}">@endif
        @if($periodo !== 'mes')<input type="hidden" name="periodo" value="{{ $periodo }}">@endif
        <i class="bi bi-search"></i><input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Nombre, teléfono o correo" aria-label="Buscar">
    </form>
</div>

@if($ver === 'tablero')
    {{-- Nuevo interesado --}}
    <details class="panel mb-3" @if($errors->hasAny(['nombre', 'telefono', 'email', 'origen', 'interes'])) open @endif>
        <summary class="panel-head" style="cursor: pointer"><h2 class="m-0"><i class="bi bi-person-plus me-2"></i>Registrar interesado</h2><span class="secundario small d-none d-md-inline">Alguien preguntó por WhatsApp, redes o pasó al gimnasio</span></summary>
        <form method="post" action="{{ route('admin.seguimiento.store') }}" class="panel-body">
            @csrf
            <div class="fx-grid-form">
                <div><label class="form-label small" for="i-nombre">Nombre</label>
                    <input id="i-nombre" name="nombre" class="form-control" value="{{ old('nombre') }}" required maxlength="160">
                    @error('nombre')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                <div><label class="form-label small" for="i-tel">WhatsApp</label>
                    <input id="i-tel" name="telefono" type="tel" class="form-control num" value="{{ old('telefono') }}" maxlength="30" placeholder="999 123 4567"></div>
                <div><label class="form-label small" for="i-mail">Correo <span class="secundario">(opcional)</span></label>
                    <input id="i-mail" name="email" type="email" class="form-control" value="{{ old('email') }}" maxlength="190">
                    @error('email')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                <div><label class="form-label small" for="i-origen">¿Cómo llegó?</label>
                    <select id="i-origen" name="origen" class="form-select">
                        @foreach(Embudo::ORIGENES as $k => $o)<option value="{{ $k }}" @selected(old('origen', 'whatsapp') === $k)>{{ $o['texto'] }}</option>@endforeach</select></div>
            </div>
            <div class="mt-3"><label class="form-label small" for="i-int">Qué busca <span class="secundario">(opcional)</span></label>
                <input id="i-int" name="interes" class="form-control" value="{{ old('interes') }}" maxlength="300" placeholder="Ej. prepararse para HYROX, bajar de peso, clases en la mañana"></div>
            <div class="d-flex justify-content-end mt-3"><button class="btn btn-primario"><i class="bi bi-check2 me-1"></i> Guardar interesado</button></div>
        </form>
    </details>

    <div class="seg-tablero">
        @foreach(Embudo::ETAPAS as $clave => $e)
            <section class="seg-col" aria-label="{{ $e['texto'] }}">
                <div class="cab">
                    <div class="n"><span><i class="bi {{ $e['icono'] }} me-1"></i>{{ $e['texto'] }}</span><span class="c num">{{ count($grupos[$clave]) }}</span></div>
                    <div class="a">{{ $e['ayuda'] }}</div>
                </div>
                @forelse($grupos[$clave] as $c)
                    @include('admin.fitness._seguimiento_tarjeta', ['c' => $c])
                @empty
                    <div class="vacia">Nadie por ahora</div>
                @endforelse
            </section>
        @endforeach
    </div>
    <p class="seg-pie mt-2">Los inscritos al corriente no aparecen aquí: los ves en <a href="{{ route('admin.clientes.index', ['ver' => 'activos']) }}">Atletas</a>.</p>
@else
    @if($descartados->isEmpty())
        <div class="panel"><div class="vacio"><div class="ico"><i class="bi bi-archive"></i></div>
            <h3>Nadie descartado</h3><p>Cuando alguien no quiera seguir, descártalo con su motivo para que no estorbe en el tablero.</p></div></div>
    @else
        <div class="panel"><div class="table-responsive"><table class="tabla">
            <thead><tr><th>Persona</th><th>Motivo</th><th>Cuándo</th><th></th></tr></thead>
            <tbody>
            @foreach($descartados as $c)
                <tr>
                    <td><a href="{{ route('admin.clientes.show', [$c, 'ver' => 'atleta']) }}" class="fila-link d-flex align-items-center gap-2"><x-fitness.avatar :atleta="$c" />
                        <div class="min-w-0"><div class="principal text-truncate">{{ $c->nombre }}</div><div class="secundario small text-truncate">{{ $c->telefono ?: $c->email ?: 'Sin contacto' }}</div></div></a></td>
                    <td>{{ $c->motivo_descarte ?: '—' }}</td>
                    <td class="secundario small">{{ $c->descartado_at?->locale('es')->diffForHumans() }}</td>
                    <td class="text-end">
                        <form method="post" action="{{ route('admin.seguimiento.recuperar', $c) }}">@csrf<button class="btn btn-borde btn-sm"><i class="bi bi-arrow-counterclockwise me-1"></i> Volver al seguimiento</button></form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div></div>
    @endif
@endif
@endsection
