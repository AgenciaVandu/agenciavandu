@extends('admin.layout')
@section('titulo', 'Proyectos')

@php
    $filtros = ['activo' => 'Activos', 'pausado' => 'En pausa', 'terminado' => 'Terminados', 'todos' => 'Todos'];
@endphp

@push('head')
<style>
    .barra-prog { height: 6px; border-radius: 99px; background: #E9EBEE; overflow: hidden; width: 120px; }
    .barra-prog span { display: block; height: 100%; background: var(--ink); border-radius: 99px; }
    .tipo { display: inline-flex; align-items: center; gap: 6px; font-size: 13.5px; color: var(--text-2); white-space: nowrap; }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <h1>Proyectos</h1>
        <p class="sub">Cotizaciones aceptadas en marcha: etapas, pagos y entregables.</p>
    </div>
    <a href="{{ route('admin.presupuestos.index', ['filtro' => 'aceptada']) }}" class="btn btn-borde"><i class="bi bi-file-earmark-check me-1"></i> Cotizaciones aceptadas</a>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <nav class="segmento" aria-label="Filtrar proyectos">
        @foreach($filtros as $k => $label)
            <a href="{{ route('admin.proyectos.index', ['filtro' => $k, 'tipo' => $tipo]) }}" class="{{ $filtro === $k ? 'activo' : '' }}">{{ $label }} <span class="n num">{{ $conteos[$k] ?? '' }}</span></a>
        @endforeach
    </nav>
    <nav class="segmento" aria-label="Tipo de proyecto">
        <a href="{{ route('admin.proyectos.index', ['filtro' => $filtro]) }}" class="{{ ! $tipo ? 'activo' : '' }}">Todos los tipos</a>
        @foreach(config('vandu.proyectos') as $k => $m)
            <a href="{{ route('admin.proyectos.index', ['filtro' => $filtro, 'tipo' => $k]) }}" class="{{ $tipo === $k ? 'activo' : '' }}"><i class="bi {{ $m['icono'] }}"></i> {{ $m['nombre'] }}</a>
        @endforeach
    </nav>
</div>

<div class="panel">
    @if($proyectos->isEmpty())
        <div class="vacio">
            <div class="ico"><i class="bi bi-kanban"></i></div>
            <h3>No hay proyectos {{ $filtro === 'todos' ? 'todavía' : 'en esta vista' }}</h3>
            <p>Cuando un cliente acepte una cotización, conviértela en proyecto desde la cotización.</p>
            <a href="{{ route('admin.presupuestos.index', ['filtro' => 'aceptada']) }}" class="btn btn-primario">Ver cotizaciones aceptadas</a>
        </div>
    @else
        <div class="table-responsive">
            <table class="tabla">
                <thead>
                    <tr><th>Proyecto</th><th>Tipo</th><th>Avance</th><th>Siguiente paso</th><th class="text-end">Cobrado</th><th><span class="visually-hidden">Enlace</span></th></tr>
                </thead>
                <tbody>
                @foreach($proyectos as $p)
                    @php $quien = $p->cliente?->empresa ?: $p->cliente?->nombre; @endphp
                    <tr>
                        <td style="min-width: 260px">
                            <a href="{{ route('admin.proyectos.show', $p) }}" class="fila-link persona">
                                @include('admin._avatar', ['nombre' => $quien])
                                <div>
                                    <div class="principal text-truncate">{{ $p->nombre }}</div>
                                    <div class="secundario text-truncate">{{ $quien }}</div>
                                </div>
                            </a>
                        </td>
                        <td><span class="tipo"><i class="bi {{ $p->metodologia['icono'] ?? 'bi-kanban' }}"></i> {{ $p->tipo_nombre }}</span></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="barra-prog" role="progressbar" aria-valuenow="{{ $p->progreso }}" aria-valuemin="0" aria-valuemax="100" aria-label="Avance"><span style="width: {{ $p->progreso }}%"></span></div>
                                <span class="secundario num">{{ $p->progreso }}%</span>
                            </div>
                        </td>
                        <td>
                            @php $paso = $p->siguiente_paso; @endphp
                            @if($p->estado !== 'activo')
                                <span class="estado {{ $p->estado === 'terminado' ? 'estado-aceptada' : 'estado-borrador' }}">{{ \App\Models\Proyecto::ESTADOS[$p->estado] }}</span>
                            @elseif(str_contains((string) $paso, 'pendiente'))
                                <span class="vig vig-pronto"><i class="bi bi-cash-coin"></i> {{ $paso }}</span>
                            @else
                                <span class="secundario">{{ $paso }}</span>
                            @endif
                        </td>
                        <td class="text-end num text-nowrap">
                            <div class="fw-medium">${{ number_format($p->pagado, 0) }}</div>
                            <div class="secundario">de ${{ number_format($p->monto_total, 0) }}</div>
                        </td>
                        <td class="text-end"><button type="button" class="btn btn-fantasma btn-icono" data-copiar="{{ $p->url_publica }}" title="Copiar enlace del cliente" aria-label="Copiar enlace del cliente"><i class="bi bi-link-45deg"></i></button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@if($proyectos->hasPages())
    <div class="mt-3">{{ $proyectos->links('pagination::bootstrap-5') }}</div>
@endif
@endsection
