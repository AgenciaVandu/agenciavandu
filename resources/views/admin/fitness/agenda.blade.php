@extends('admin.layout')
@section('titulo', 'Agenda de clases')
@include('admin.fitness._estilos')

@php
    $hoy = now(config('vandu.zona_horaria'))->toDateString();
    $q = fn (array $extra) => route('admin.clases.index', array_filter(array_merge(['semana' => $semana ?: null, 'sucursal' => $sucursal, 'coach' => $soloMias ? null : $coach], $extra), fn ($v) => $v !== null));
    $pct = $resumen['lugares'] ? round($resumen['ocupados'] / $resumen['lugares'] * 100) : 0;
@endphp

@push('head')
<style>
    .semana { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 10px; align-items: start; }
    .dia { min-width: 0; }
    .dia-top { display: flex; align-items: baseline; justify-content: space-between; gap: 6px; padding: 6px 4px 10px; }
    .dia-top b { font-size: 14px; } .dia-top span { font-size: 12.5px; color: var(--muted); }
    .dia.hoy .dia-top b { color: var(--green-ink); }
    .dia.hoy .dia-top b::after { content: ' · hoy'; font-weight: 500; }
    .clase-t { display: block; text-decoration: none; color: inherit; background: var(--surface); border: 1px solid var(--line); border-left: 4px solid var(--c, #13161D); border-radius: 10px; padding: 9px 10px 10px; margin-bottom: 8px; transition: box-shadow .15s; }
    .clase-t:hover { box-shadow: 0 6px 18px rgba(16,24,40,.10); }
    .clase-t .h { font-size: 12.5px; color: var(--muted); }
    .clase-t .n { font-weight: 600; font-size: 14px; line-height: 1.25; margin: 1px 0 6px; }
    .clase-t .m { font-size: 12px; color: var(--muted); margin-top: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .clase-t.cancelada { opacity: .55; } .clase-t.cancelada .n { text-decoration: line-through; }
    .clase-t.pasada { background: var(--sunken); }
    .dia .nueva { display: block; text-align: center; font-size: 12.5px; color: var(--muted); border: 1px dashed var(--line-strong, #D5D8DE); border-radius: 10px; padding: 6px; text-decoration: none; }
    .dia .nueva:hover { color: var(--ink); border-color: var(--ink); }
    @media (max-width: 1199.98px) { .semana { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    @media (max-width: 767.98px) { .semana { grid-template-columns: minmax(0, 1fr); } .dia-top { padding-top: 14px; } }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <h1>{{ $soloMias ? 'Mis clases' : 'Agenda de clases' }}</h1>
        <p class="sub">{{ ucfirst($lunes->locale('es')->isoFormat('D [de] MMMM')) }} al {{ $domingo->locale('es')->isoFormat('D [de] MMMM') }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if(auth()->user()->puede('asistencia'))<a href="{{ route('admin.escanear') }}" class="btn btn-borde"><i class="bi bi-qr-code-scan me-1"></i> Escanear</a>@endif
        @if(auth()->user()->puede('clases'))<a href="{{ route('admin.clases.create') }}" class="btn btn-primario"><i class="bi bi-plus-lg me-1"></i> Programar clase</a>@endif
    </div>
</div>
@include('admin.fitness._submenu')

<div class="panel fx-kpis">
    <div><div class="k"><i class="bi bi-calendar-week"></i> Clases esta semana</div><div class="v num">{{ $resumen['clases'] }}</div></div>
    <div><div class="k"><i class="bi bi-people"></i> Lugares reservados</div><div class="v num">{{ $resumen['ocupados'] }} <span class="secundario" style="font-size:14px; font-weight:400">de {{ $resumen['lugares'] }}</span></div></div>
    <div><div class="k"><i class="bi bi-speedometer2"></i> Ocupación</div><div class="v num">{{ $pct }}%</div></div>
    <div class="d-flex align-items-center gap-2">
        <a class="btn btn-borde btn-icono" href="{{ $q(['semana' => $semana - 1]) }}" aria-label="Semana anterior"><i class="bi bi-chevron-left"></i></a>
        <a class="btn btn-borde btn-sm flex-grow-1" href="{{ $q(['semana' => null]) }}">Esta semana</a>
        <a class="btn btn-borde btn-icono" href="{{ $q(['semana' => $semana + 1]) }}" aria-label="Semana siguiente"><i class="bi bi-chevron-right"></i></a>
    </div>
</div>

@if(! $soloMias && ($sucursales->count() > 1 || $coaches->count() > 1))
    <form method="get" class="d-flex flex-wrap gap-2 mb-3">
        @if($semana)<input type="hidden" name="semana" value="{{ $semana }}">@endif
        @if($sucursales->count() > 1)
            <select name="sucursal" class="form-select w-auto" onchange="this.form.submit()" aria-label="Sucursal"><option value="">Todas las sucursales</option>
                @foreach($sucursales as $s)<option value="{{ $s->id }}" @selected($sucursal === $s->id)>{{ $s->nombre }}</option>@endforeach</select>
        @endif
        @if($coaches->count() > 1)
            <select name="coach" class="form-select w-auto" onchange="this.form.submit()" aria-label="Entrenador"><option value="">Todos los entrenadores</option>
                @foreach($coaches as $co)<option value="{{ $co->id }}" @selected($coach === $co->id)>{{ $co->name }}</option>@endforeach</select>
        @endif
    </form>
@endif

<div class="semana">
    @foreach($dias as $fecha => $d)
        <section class="dia {{ $fecha === $hoy ? 'hoy' : '' }}" aria-label="{{ $d['fecha']->locale('es')->isoFormat('dddd D') }}">
            <div class="dia-top"><b>{{ ucfirst($d['fecha']->locale('es')->isoFormat('ddd D')) }}</b><span>{{ $d['clases']->where('estado', 'programada')->count() ?: '' }}</span></div>
            @foreach($d['clases'] as $c)
                <a href="{{ route('admin.clases.show', $c) }}" class="clase-t {{ $c->cancelada ? 'cancelada' : '' }} {{ $c->pasada ? 'pasada' : '' }}" style="--c: {{ $c->color }}">
                    <div class="h num">{{ $c->horario }}</div>
                    <div class="n">{{ $c->nombre }}</div>
                    @if($c->cancelada)<span class="fx-pastilla">Cancelada</span>@else<x-fitness.ocupacion :ocupados="$c->ocupadas_count" :cupo="$c->cupo" />@endif
                    <div class="m">{{ collect([$c->coach?->primer_nombre, $sucursales->count() > 1 ? $c->sucursal?->nombre : null])->filter()->join(' · ') ?: 'Sin entrenador' }}</div>
                </a>
            @endforeach
            @if(auth()->user()->puede('clases') && $fecha >= $hoy)
                <a class="nueva" href="{{ route('admin.clases.create', ['fecha' => $fecha]) }}"><i class="bi bi-plus"></i> Clase</a>
            @endif
        </section>
    @endforeach
</div>
@endsection
