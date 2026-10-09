@extends('admin.layout')
@section('titulo', 'Cotizaciones')

@php
    $filtros = ['vigentes' => 'Vigentes', 'por_vencer' => 'Por vencer', 'vencidas' => 'Vencidas', 'aceptada' => 'Aceptadas', 'todas' => 'Todas'];
@endphp

@section('contenido')
<div class="page-head">
    <div>
        <h1>Cotizaciones</h1>
        <p class="sub">Todo lo que has cotizado, con su vigencia y lo que ha visto cada cliente.</p>
    </div>
    <a href="{{ route('admin.presupuestos.create') }}" class="btn btn-primario"><i class="bi bi-plus-lg me-1"></i> Nueva cotización</a>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <nav class="segmento" aria-label="Filtrar cotizaciones">
        @foreach($filtros as $k => $label)
            <a href="{{ route('admin.presupuestos.index', ['filtro' => $k, 'q' => $q ?: null]) }}" class="{{ $filtro === $k ? 'activo' : '' }}"
               @if($filtro === $k) aria-current="true" @endif>{{ $label }} <span class="n num">{{ $conteos[$k] ?? '' }}</span></a>
        @endforeach
    </nav>
    <form class="buscador" method="get" role="search">
        <input type="hidden" name="filtro" value="{{ $filtro }}">
        <i class="bi bi-search"></i>
        <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Buscar por folio o cliente" aria-label="Buscar cotizaciones">
    </form>
</div>

<div class="panel">
    @if($presupuestos->isEmpty())
        <div class="vacio">
            <div class="ico"><i class="bi {{ $q ? 'bi-search' : 'bi-file-earmark-plus' }}"></i></div>
            @if($q)
                <h3>Sin resultados para “{{ $q }}”</h3>
                <p>Prueba con otro folio o el nombre de la empresa.</p>
                <a href="{{ route('admin.presupuestos.index', ['filtro' => $filtro]) }}" class="btn btn-borde">Limpiar búsqueda</a>
            @else
                <h3>No hay cotizaciones {{ $filtro === 'todas' ? 'todavía' : 'en esta vista' }}</h3>
                <p>{{ $filtro === 'todas' ? 'Crea la primera y compártela con tu cliente.' : 'Cambia de filtro o crea una nueva.' }}</p>
                <a href="{{ route('admin.presupuestos.create') }}" class="btn btn-primario">Crear cotización</a>
            @endif
        </div>
    @else
        <div class="table-responsive">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Folio</th>
                        <th class="text-end">Importe</th>
                        <th>Vigencia</th>
                        <th>Estado</th>
                        <th class="text-center">Vistas</th>
                        <th class="text-end"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($presupuestos as $p)
                    @php
                        $wa = $p->cliente?->whatsapp;
                        $msg = "Hola {$p->cliente_nombre}, te comparto la cotización {$p->folio}: {$p->url_publica}";
                        $quien = $p->cliente_empresa ?: $p->cliente_nombre;
                    @endphp
                    <tr>
                        <td style="min-width: 240px">
                            <a href="{{ route('admin.presupuestos.edit', $p) }}" class="fila-link persona">
                                @include('admin._avatar', ['nombre' => $quien])
                                <div>
                                    <div class="principal text-truncate">{{ $quien }}</div>
                                    <div class="secundario text-truncate">{{ $p->cliente_empresa ? $p->cliente_nombre : \Illuminate\Support\Str::limit($p->conceptos->first()?->descripcion, 40) }}</div>
                                </div>
                            </a>
                        </td>
                        <td class="text-nowrap">
                            <div class="num fw-medium">{{ $p->folio }}</div>
                            <div class="secundario">{{ $p->fecha->locale('es')->isoFormat('D MMM YYYY') }}</div>
                        </td>
                        <td class="text-end num fw-medium text-nowrap">{{ $p->monto($p->modo_iva === 'desglosado' ? $p->total : $p->subtotal) }}</td>
                        <td>@include('admin.presupuestos._vigencia', ['p' => $p])</td>
                        <td><span class="estado estado-{{ $p->estado }}">{{ \App\Models\Presupuesto::ESTADOS[$p->estado] ?? $p->estado }}</span></td>
                        <td class="text-center num"
                            title="{{ $p->ultima_vista_at ? 'Última vez: ' . $p->ultima_vista_at->timezone(config('vandu.zona_horaria'))->format('d/m/Y H:i') : 'El cliente aún no la abre' }}">
                            @if($p->vistas)
                                <span class="d-inline-flex align-items-center gap-1"><i class="bi bi-eye text-secondary"></i> {{ $p->vistas }}</span>
                            @else
                                <span class="text-secondary">—</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <button type="button" class="btn btn-fantasma btn-icono" data-copiar="{{ $p->url_publica }}" title="Copiar enlace del cliente" aria-label="Copiar enlace del cliente"><i class="bi bi-link-45deg"></i></button>
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-fantasma btn-icono" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Más acciones para {{ $p->folio }}"><i class="bi bi-three-dots"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="{{ route('admin.presupuestos.edit', $p) }}"><i class="bi bi-pencil"></i> Editar</a></li>
                                    @if($p->proyecto)
                                        <li><a class="dropdown-item" href="{{ route('admin.proyectos.show', $p->proyecto) }}"><i class="bi bi-kanban"></i> Ver proyecto</a></li>
                                    @elseif($p->estado === 'aceptada')
                                        <li><a class="dropdown-item fw-medium" href="{{ route('admin.presupuestos.edit', $p) }}#convertir"><i class="bi bi-kanban"></i> Convertir en proyecto</a></li>
                                    @endif
                                    <li><a class="dropdown-item" target="_blank" href="{{ $p->url_publica }}?vista_previa=1"><i class="bi bi-eye"></i> Ver como cliente</a></li>
                                    <li><a class="dropdown-item" target="_blank" href="{{ route('admin.presupuestos.pdf', $p) }}"><i class="bi bi-file-earmark-pdf"></i> Ver PDF</a></li>
                                    @if($wa)
                                        <li><a class="dropdown-item" target="_blank" href="https://wa.me/{{ $wa }}?text={{ rawurlencode($msg) }}"><i class="bi bi-whatsapp"></i> Enviar por WhatsApp</a></li>
                                    @endif
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="post" action="{{ route('admin.presupuestos.rapido', $p) }}">@csrf @method('patch')
                                            <input type="hidden" name="extender_dias" value="{{ config('vandu.vigencia_dias') }}">
                                            <button class="dropdown-item"><i class="bi bi-calendar-plus"></i> Vigencia: {{ config('vandu.vigencia_dias') }} días desde hoy</button>
                                        </form>
                                    </li>
                                    @foreach(\App\Models\Presupuesto::ESTADOS as $k => $label)
                                        @continue($k === $p->estado)
                                        <li>
                                            <form method="post" action="{{ route('admin.presupuestos.rapido', $p) }}">@csrf @method('patch')
                                                <input type="hidden" name="estado" value="{{ $k }}">
                                                <button class="dropdown-item"><i class="bi bi-circle-fill" style="font-size:7px"></i> Marcar como {{ mb_strtolower($label) }}</button>
                                            </form>
                                        </li>
                                    @endforeach
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="post" action="{{ route('admin.presupuestos.duplicar', $p) }}">@csrf
                                            <button class="dropdown-item"><i class="bi bi-copy"></i> Duplicar</button>
                                        </form>
                                    </li>
                                    <li>
                                        <form method="post" action="{{ route('admin.presupuestos.destroy', $p) }}" onsubmit="return confirm('¿Eliminar {{ $p->folio }}? El enlace del cliente dejará de funcionar.')">@csrf @method('delete')
                                            <button class="dropdown-item text-danger"><i class="bi bi-trash text-danger"></i> Eliminar</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@if($presupuestos->hasPages())
    <div class="mt-3">{{ $presupuestos->links('pagination::bootstrap-5') }}</div>
@endif
@endsection
