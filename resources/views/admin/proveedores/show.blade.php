@extends('admin.layout')
@section('titulo', $proveedor->empresa)

@php
    $abiertas = $ordenes->filter(fn ($o) => ! in_array($o->estado, ['pagada', 'cancelada'], true));
    $portal = $proveedor->portal_url;
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
    .chip-rubro { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; padding: 2px 10px; border-radius: 99px; background: var(--surface); border: 1px solid var(--line); }
    .ico-fact { color: var(--green-ink); }
    @media (max-width: 991.98px) { .ficha { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 575.98px) { .mini { grid-template-columns: minmax(0, 1fr); } .mini > div + div { border-left: 0; border-top: 1px solid var(--line); } }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.proveedores.index') }}">Proveedores</a> <i class="bi bi-chevron-right small"></i> <span>{{ $proveedor->empresa }}</span></div>
<div class="page-head">
    <div class="cabecera">
        @include('admin._avatar', ['nombre' => $proveedor->empresa])
        <div>
            <h1>{{ $proveedor->empresa }}</h1>
            <p class="sub">@if($proveedor->rubro)<span class="chip-rubro">{{ $proveedor->rubro }}</span> @endif{{ $proveedor->nombre ?: 'Proveedor' }} · desde {{ $proveedor->created_at->locale('es')->isoFormat('MMMM YYYY') }}</p>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.proveedores.edit', $proveedor) }}" class="btn btn-borde"><i class="bi bi-pencil me-1"></i> Editar</a>
        <a href="{{ route('admin.ordenes.create', ['proveedor' => $proveedor->id]) }}" class="btn btn-primario"><i class="bi bi-plus-lg me-1"></i> Nueva orden de compra</a>
    </div>
</div>

<div class="ficha">
    <div class="d-grid gap-4">
        <div class="panel mini">
            <div><div class="k">Órdenes abiertas</div><div class="v num">{{ $abiertas->count() }}</div></div>
            <div><div class="k">Por pagar</div><div class="v num">${{ number_format($porPagar, 0) }}</div></div>
            <div><div class="k">Pagado</div><div class="v num">${{ number_format($pagado, 0) }}</div></div>
        </div>

        <section class="panel">
            <div class="panel-head"><h2>Historial de órdenes</h2><span class="ayuda">{{ $ordenes->count() }} en total</span></div>
            @if($ordenes->isEmpty())
                <div class="vacio">
                    <div class="ico"><i class="bi bi-receipt"></i></div>
                    <h3>Sin órdenes todavía</h3>
                    <p>Crea la primera: anota qué necesitas, las indicaciones y la fecha de entrega.</p>
                    <a href="{{ route('admin.ordenes.create', ['proveedor' => $proveedor->id]) }}" class="btn btn-primario">Crear orden de compra</a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="tabla">
                        <thead><tr><th>Folio</th><th>Pedido</th><th>Proyecto</th><th class="text-end">Total</th><th>Estado</th></tr></thead>
                        <tbody>
                        @foreach($ordenes as $o)
                            <tr>
                                <td class="text-nowrap"><a href="{{ route('admin.ordenes.show', $o) }}" class="fila-link"><span class="principal num">{{ $o->folio }}</span></a>
                                    <div class="secundario">{{ $o->fecha?->locale('es')->isoFormat('D MMM YYYY') }}</div></td>
                                <td style="min-width: 200px"><div class="text-truncate" style="max-width: 280px">{{ $o->titulo }}</div>
                                    <div class="secundario text-truncate" style="max-width: 280px">{{ $o->resumen }}</div></td>
                                <td class="secundario" style="min-width: 140px">
                                    @if($o->proyecto)<a href="{{ route('admin.proyectos.show', $o->proyecto) }}" class="text-reset">{{ $o->proyecto->nombre }}</a>@else — @endif
                                </td>
                                <td class="text-end num fw-medium text-nowrap">{{ \App\Models\OrdenCompra::dinero($o->total) }}</td>
                                <td class="text-nowrap"><span class="estado estado-{{ $o->estado }}">{{ $o->estado_texto }}</span>
                                    @if($o->facturas->count())<i class="bi bi-receipt ico-fact ms-1" title="Con factura"></i>@endif</td>
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
            <div class="panel-head"><h2>Espacio del proveedor</h2><span class="ayuda">@if($proveedor->portal_visto_at)Lo abrió {{ $proveedor->portal_visto_at->locale('es')->diffForHumans() }}@else Aún no lo abre @endif</span></div>
            <div class="panel-body d-grid gap-2">
                <p class="secundario m-0" style="font-size:13.5px">Un solo enlace, sin usuario ni contraseña, con todas sus órdenes. Ahí descarga el PDF, te sube facturas, muestras, catálogos o ponchados y deja comentarios.</p>
                <div class="d-flex gap-2">
                    <input class="form-control" style="font-size:13px" value="{{ $portal }}" readonly onclick="this.select()" aria-label="Enlace del espacio del proveedor">
                    <button type="button" class="btn btn-primario text-nowrap" data-copiar="{{ $portal }}" title="Copiar enlace"><i class="bi bi-copy"></i></button>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @if($proveedor->whatsapp)<a class="btn btn-borde btn-sm" target="_blank" rel="noopener" href="https://wa.me/{{ $proveedor->whatsapp }}?text={{ rawurlencode('Hola ' . $proveedor->primer_nombre . ', aquí puedes ver nuestras órdenes de compra, subir tus facturas y archivos: ' . $portal) }}"><i class="bi bi-whatsapp me-1"></i> WhatsApp</a>@endif
                    <a class="btn btn-borde btn-sm" target="_blank" rel="noopener" href="{{ $portal }}?vista_previa=1"><i class="bi bi-eye me-1"></i> Ver como proveedor</a>
                </div>
            </div>
        </section>

        @if($proyectos->isNotEmpty())
            <section class="panel">
                <div class="panel-head"><h2>Proyectos</h2><span class="ayuda">{{ $proyectos->count() }}</span></div>
                <ul class="list-unstyled m-0">
                    @foreach($proyectos as $pr)
                        <li class="{{ $loop->first ? '' : 'border-top' }}">
                            <a href="{{ route('admin.proyectos.show', $pr) }}" class="d-block px-3 py-3 text-decoration-none text-reset">
                                <div class="principal text-truncate">{{ $pr->nombre }}</div>
                                <div class="secundario text-truncate">{{ $pr->cliente?->empresa ?: $pr->cliente?->nombre }} · {{ $ordenes->where('proyecto_id', $pr->id)->count() }} {{ $ordenes->where('proyecto_id', $pr->id)->count() === 1 ? 'orden' : 'órdenes' }}</div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="panel">
            <div class="panel-head"><h2>Datos</h2></div>
            <dl class="panel-body datos m-0">
                @if($proveedor->nombre)<dt>Contacto</dt><dd>{{ $proveedor->nombre }}</dd>@endif
                @if($proveedor->telefono)<dt>Teléfono</dt><dd class="num"><a href="tel:{{ preg_replace('/\D/', '', $proveedor->telefono) }}" class="text-reset">{{ $proveedor->telefono }}</a></dd>@endif
                @if($proveedor->email)<dt>Correo</dt><dd><a href="mailto:{{ $proveedor->email }}" class="text-reset">{{ $proveedor->email }}</a></dd>@endif
                @if($proveedor->direccion)<dt>Dirección</dt><dd>{{ $proveedor->direccion }}</dd>@endif
                @if($proveedor->rfc)<dt>RFC</dt><dd class="num">{{ $proveedor->rfc }}</dd>@endif
                @if($proveedor->razon_social)<dt>Razón social</dt><dd>{{ $proveedor->razon_social }}</dd>@endif
                @if($proveedor->notas)<dt>Notas</dt><dd style="white-space: pre-line">{{ $proveedor->notas }}</dd>@endif
                @if(! $proveedor->nombre && ! $proveedor->telefono && ! $proveedor->email && ! $proveedor->rfc && ! $proveedor->notas)
                    <dd class="secundario">Sin datos todavía. <a href="{{ route('admin.proveedores.edit', $proveedor) }}">Agregar</a></dd>
                @endif
            </dl>
        </section>
    </div>
</div>
@endsection
