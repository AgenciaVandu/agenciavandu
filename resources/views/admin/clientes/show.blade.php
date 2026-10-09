@extends('admin.layout')
@section('titulo', $cliente->empresa ?: $cliente->nombre)

@php
    $ps = $cliente->presupuestos;
    $aceptado = $ps->where('estado', 'aceptada')->sum(fn ($p) => $p->subtotal);
    $vigentes = $ps->filter(fn ($p) => $p->vigente)->count();
@endphp

@push('head')
<style>
    .ficha { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 20px; align-items: start; }
    .cabecera { display: flex; align-items: center; gap: 16px; }
    .cabecera .avatar { width: 56px; height: 56px; font-size: 19px; border-radius: 14px; }
    .datos dt { font-size: 13px; color: var(--muted); font-weight: 400; }
    .datos dd { margin: 2px 0 16px; overflow-wrap: anywhere; }
    .datos dd:last-child { margin-bottom: 0; }
    .mini { display: grid; grid-template-columns: repeat(3, 1fr); }
    .mini > div { padding: 16px 20px; }
    .mini > div + div { border-left: 1px solid var(--line); }
    .mini .v { font-size: 22px; font-weight: 600; letter-spacing: -.01em; }
    .mini .k { font-size: 13px; color: var(--muted); }
    @media (max-width: 991.98px) { .ficha { grid-template-columns: 1fr; } }
    @media (max-width: 575.98px) { .mini { grid-template-columns: 1fr; } .mini > div + div { border-left: 0; border-top: 1px solid var(--line); } }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.clientes.index') }}">Clientes</a> <i class="bi bi-chevron-right small"></i> <span>{{ $cliente->empresa ?: $cliente->nombre }}</span></div>
<div class="page-head">
    <div class="cabecera">
        @include('admin._avatar', ['nombre' => $cliente->empresa ?: $cliente->nombre])
        <div>
            <h1>{{ $cliente->empresa ?: $cliente->nombre }}</h1>
            <p class="sub">{{ $cliente->empresa ? $cliente->nombre : 'Cliente' }} · desde {{ $cliente->created_at->locale('es')->isoFormat('MMMM YYYY') }}</p>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.clientes.edit', $cliente) }}" class="btn btn-borde"><i class="bi bi-pencil me-1"></i> Editar</a>
        <a href="{{ route('admin.presupuestos.create', ['cliente' => $cliente->id]) }}" class="btn btn-primario"><i class="bi bi-plus-lg me-1"></i> Nueva cotización</a>
    </div>
</div>

<div class="ficha">
    <div class="d-grid gap-4">
        <div class="panel mini">
            <div><div class="k">Cotizaciones</div><div class="v num">{{ $ps->count() }}</div></div>
            <div><div class="k">Vigentes</div><div class="v num">{{ $vigentes }}</div></div>
            <div><div class="k">Aceptado, antes de IVA</div><div class="v num">${{ number_format($aceptado, 0) }}</div></div>
        </div>

        <section class="panel">
            <div class="panel-head"><h2>Cotizaciones</h2></div>
            @if($ps->isEmpty())
                <div class="vacio">
                    <div class="ico"><i class="bi bi-file-earmark-plus"></i></div>
                    <h3>Sin cotizaciones todavía</h3>
                    <p>Crea la primera para {{ $cliente->nombre }}.</p>
                    <a href="{{ route('admin.presupuestos.create', ['cliente' => $cliente->id]) }}" class="btn btn-primario">Crear cotización</a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="tabla">
                        <thead><tr><th>Folio</th><th>Concepto</th><th class="text-end">Importe</th><th>Vigencia</th><th>Estado</th><th><span class="visually-hidden">Enlace</span></th></tr></thead>
                        <tbody>
                        @foreach($ps as $p)
                            <tr>
                                <td class="text-nowrap"><a href="{{ route('admin.presupuestos.edit', $p) }}" class="fila-link"><span class="principal num">{{ $p->folio }}</span></a>
                                    <div class="secundario">{{ $p->fecha->locale('es')->isoFormat('D MMM YYYY') }}</div></td>
                                <td class="secundario" style="min-width: 180px">{{ \Illuminate\Support\Str::limit($p->conceptos->first()?->resumen, 60) }}</td>
                                <td class="text-end num fw-medium text-nowrap">{{ $p->monto($p->modo_iva === 'desglosado' ? $p->total : $p->subtotal) }}</td>
                                <td>@include('admin.presupuestos._vigencia', ['p' => $p])</td>
                                <td><span class="estado estado-{{ $p->estado }}">{{ \App\Models\Presupuesto::ESTADOS[$p->estado] }}</span></td>
                                <td class="text-end"><button type="button" class="btn btn-fantasma btn-icono" data-copiar="{{ $p->url_publica }}" title="Copiar enlace del cliente" aria-label="Copiar enlace de {{ $p->folio }}"><i class="bi bi-link-45deg"></i></button></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    <div class="d-grid gap-4">
        @if($cliente->proyectos->isNotEmpty())
            <section class="panel">
                <div class="panel-head"><h2>Proyectos</h2></div>
                <ul class="list-unstyled m-0">
                    @foreach($cliente->proyectos as $pr)
                        <li class="{{ $loop->first ? '' : 'border-top' }}">
                            <a href="{{ route('admin.proyectos.show', $pr) }}" class="d-block px-3 py-3 text-decoration-none text-reset">
                                <div class="d-flex justify-content-between gap-2"><span class="principal text-truncate">{{ $pr->nombre }}</span><span class="secundario num">{{ $pr->progreso }}%</span></div>
                                <div class="secundario"><i class="bi {{ $pr->metodologia['icono'] ?? 'bi-kanban' }} me-1"></i>{{ $pr->tipo_nombre }} · {{ $pr->siguiente_paso ?? 'Terminado' }}</div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
        <section class="panel">
            <div class="panel-head"><h2>Contacto</h2></div>
            <dl class="panel-body datos mb-0">
                <dt>Correo</dt>
                <dd>@if($cliente->email)<a href="mailto:{{ $cliente->email }}">{{ $cliente->email }}</a>@else<span class="text-secondary">—</span>@endif</dd>
                <dt>Teléfono</dt>
                <dd class="num">
                    @if($cliente->telefono)
                        {{ $cliente->telefono }}
                        @if($cliente->whatsapp)<a href="https://wa.me/{{ $cliente->whatsapp }}" target="_blank" class="btn btn-borde btn-sm ms-2"><i class="bi bi-whatsapp"></i> WhatsApp</a>@endif
                    @else<span class="text-secondary">—</span>@endif
                </dd>
            </dl>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Datos fiscales</h2></div>
            @if($cliente->rfc || $cliente->razon_social)
                <dl class="panel-body datos mb-0">
                    <dt>RFC</dt><dd class="num">{{ $cliente->rfc ?: '—' }}</dd>
                    <dt>Razón social</dt><dd>{{ $cliente->razon_social ?: '—' }}</dd>
                    <dt>Uso de CFDI</dt><dd>{{ $cliente->uso_cfdi ?: '—' }}</dd>
                </dl>
            @else
                <div class="panel-body secundario">Sin datos fiscales. <a href="{{ route('admin.clientes.edit', $cliente) }}">Agregarlos</a> para facturar.</div>
            @endif
        </section>

        @if($cliente->notas)
            <section class="panel">
                <div class="panel-head"><h2>Notas</h2></div>
                <div class="panel-body" style="white-space: pre-line">{{ $cliente->notas }}</div>
            </section>
        @endif
    </div>
</div>
@endsection
