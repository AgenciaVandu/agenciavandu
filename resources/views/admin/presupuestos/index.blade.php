@extends('admin.layout')
@section('titulo', 'Cotizaciones')

@section('contenido')
<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
    <h1 class="h3 mb-0">Cotizaciones</h1>
    <form class="d-flex gap-2" method="get">
        <input type="hidden" name="filtro" value="{{ $filtro }}">
        <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Buscar folio o cliente" aria-label="Buscar">
        <button class="btn btn-outline-dark"><i class="bi bi-search"></i></button>
    </form>
</div>

@php
    $filtros = ['vigentes' => 'Vigentes', 'vencidas' => 'Vencidas', 'aceptada' => 'Aceptadas', 'todas' => 'Todas'];
@endphp
<ul class="nav nav-pills mb-3 gap-1">
    @foreach($filtros as $k => $label)
        <li class="nav-item">
            <a class="nav-link py-1 {{ $filtro === $k ? 'active bg-dark' : 'text-dark' }}" href="{{ route('admin.presupuestos.index', ['filtro' => $k, 'q' => $q]) }}">{{ $label }}</a>
        </li>
    @endforeach
</ul>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light small">
                <tr>
                    <th>Folio</th>
                    <th>Cliente</th>
                    <th class="text-end">Importe</th>
                    <th>Vigencia</th>
                    <th>Estado</th>
                    <th class="text-center">Vistas</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse($presupuestos as $p)
                @php
                    $wa = $p->cliente?->whatsapp;
                    $msg = "Hola {$p->cliente_nombre}, te comparto la cotización {$p->folio}: {$p->url_publica}";
                @endphp
                <tr>
                    <td class="num"><a href="{{ route('admin.presupuestos.edit', $p) }}" class="text-decoration-none fw-semibold">{{ $p->folio }}</a>
                        <div class="small text-muted">{{ $p->fecha->format('d/m/Y') }}</div></td>
                    <td>
                        <div class="fw-semibold">{{ $p->cliente_nombre }}</div>
                        <div class="small text-muted">{{ $p->cliente_empresa }}</div>
                    </td>
                    <td class="text-end num">{{ $p->monto($p->modo_iva === 'desglosado' ? $p->total : $p->subtotal) }}</td>
                    <td>@include('admin.presupuestos._vigencia', ['p' => $p])</td>
                    <td><span class="estado estado-{{ $p->estado }}">{{ \App\Models\Presupuesto::ESTADOS[$p->estado] ?? $p->estado }}</span></td>
                    <td class="text-center num" title="{{ $p->ultima_vista_at ? 'Última: ' . $p->ultima_vista_at->timezone(config('vandu.zona_horaria'))->format('d/m/Y H:i') : 'El cliente aún no la abre' }}">
                        {{ $p->vistas ?: '—' }}
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('admin.presupuestos.edit', $p) }}" class="btn btn-sm btn-v">Editar</a>
                        <button type="button" class="btn btn-sm btn-outline-dark" data-copiar="{{ $p->url_publica }}" title="Copiar enlace del cliente"><i class="bi bi-link-45deg"></i></button>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-dark dropdown-toggle" data-bs-toggle="dropdown" aria-label="Más acciones"></button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" target="_blank" href="{{ $p->url_publica }}?vista_previa=1"><i class="bi bi-eye me-2"></i>Ver como cliente</a></li>
                                <li><a class="dropdown-item" target="_blank" href="{{ route('admin.presupuestos.pdf', $p) }}"><i class="bi bi-file-earmark-pdf me-2"></i>Ver PDF</a></li>
                                @if($wa)
                                    <li><a class="dropdown-item" target="_blank" href="https://wa.me/{{ $wa }}?text={{ rawurlencode($msg) }}"><i class="bi bi-whatsapp me-2"></i>Enviar por WhatsApp</a></li>
                                @endif
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="post" action="{{ route('admin.presupuestos.rapido', $p) }}">@csrf @method('patch')
                                        <input type="hidden" name="extender_dias" value="{{ config('vandu.vigencia_dias') }}">
                                        <button class="dropdown-item"><i class="bi bi-calendar-plus me-2"></i>Vigencia: {{ config('vandu.vigencia_dias') }} días desde hoy</button>
                                    </form>
                                </li>
                                @foreach(\App\Models\Presupuesto::ESTADOS as $k => $label)
                                    @continue($k === $p->estado)
                                    <li>
                                        <form method="post" action="{{ route('admin.presupuestos.rapido', $p) }}">@csrf @method('patch')
                                            <input type="hidden" name="estado" value="{{ $k }}">
                                            <button class="dropdown-item">Marcar como {{ strtolower($label) }}</button>
                                        </form>
                                    </li>
                                @endforeach
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="post" action="{{ route('admin.presupuestos.duplicar', $p) }}">@csrf
                                        <button class="dropdown-item"><i class="bi bi-copy me-2"></i>Duplicar</button>
                                    </form>
                                </li>
                                <li>
                                    <form method="post" action="{{ route('admin.presupuestos.destroy', $p) }}" onsubmit="return confirm('¿Eliminar {{ $p->folio }}? El enlace del cliente dejará de funcionar.')">@csrf @method('delete')
                                        <button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Eliminar</button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center py-5">
                    <p class="text-muted mb-3">No hay cotizaciones {{ $filtro === 'todas' ? '' : 'en este filtro' }}.</p>
                    <a href="{{ route('admin.presupuestos.create') }}" class="btn btn-verde">Crear cotización</a>
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $presupuestos->links('pagination::bootstrap-5') }}</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@endpush
