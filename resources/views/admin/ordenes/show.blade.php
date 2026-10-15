@extends('admin.layout')
@section('titulo', $o->folio . ' · ' . $o->proveedor->empresa)

@php
    $p = $o->proveedor;
    $facturas = $o->archivos->where('tipo', 'factura');
    $adjuntos = $o->adjuntos;
    $otros = $o->archivos->where('tipo', '!=', 'factura')->reject(fn ($a) => $adjuntos->contains('id', $a->id));
    $tz = config('vandu.zona_horaria');
    $cuando = fn ($f) => $f->copy()->setTimezone($tz)->locale('es')->isoFormat('D MMM, h:mm a');
@endphp

@push('head')
<style>
    .ficha { display: grid; grid-template-columns: minmax(0, 1fr) 330px; gap: 20px; align-items: start; }
    .titulo-doc { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
    .datos dt { font-size: 13px; color: var(--muted); font-weight: 400; }
    .datos dd { margin: 2px 0 14px; overflow-wrap: anywhere; }
    .datos dd:last-child { margin-bottom: 0; }
    .tabla td.desc { white-space: pre-line; }
    .tot td { border-top: 1px solid var(--line); }
    .indicaciones { white-space: pre-line; font-size: 14.5px; }
    .archivo { display: grid; grid-template-columns: 40px minmax(0, 1fr) auto; gap: 12px; align-items: center; padding: 12px 20px; border-top: 1px solid var(--line); }
    .archivo:first-child { border-top: 0; }
    .archivo .ic { width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center; background: var(--surface-2, #F3F4F6); font-size: 18px; }
    .archivo .ic.prov { background: var(--blue-soft); color: var(--blue); }
    .archivo .ic.fact { background: var(--green-soft); color: var(--green-ink); }
    .archivo .n { font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .archivo .s { font-size: 12.5px; color: var(--muted); }
    .chat { display: grid; gap: 12px; padding: 16px 20px; }
    .msj { max-width: 85%; padding: 10px 14px; border-radius: 14px; background: var(--surface-2, #F3F4F6); }
    .msj.yo { justify-self: end; background: #13161D; color: #fff; }
    .msj .quien { font-size: 12px; opacity: .7; margin-bottom: 2px; }
    .msj .texto { white-space: pre-line; overflow-wrap: anywhere; }
    .adjuntos { border: 1.5px solid #13161D; }
    .adjuntos .panel-head h2 i { color: var(--green-ink); }
    .estados { display: flex; flex-wrap: wrap; gap: 6px; }
    .estados button { border: 1px solid var(--line); background: var(--surface); border-radius: 99px; padding: 4px 12px; font-size: 13px; }
    .cancelada-info { padding: 10px 12px; border-radius: 10px; background: #F4F5F7; font-size: 14px; display: grid; gap: 2px; white-space: pre-line; }
    .cancelar-o > summary { list-style: none; } .cancelar-o > summary::-webkit-details-marker { display: none; }
    .cancelar-o[open] > summary { display: none; }
    .estados button.activo { background: #13161D; color: #fff; border-color: #13161D; }
    .estados button:hover:not(.activo) { border-color: #13161D; }
    @media (max-width: 991.98px) { .ficha { grid-template-columns: minmax(0, 1fr); } }
</style>
@endpush

@section('contenido')
<div class="migas">
    <a href="{{ route('admin.proveedores.index') }}">Proveedores</a> <i class="bi bi-chevron-right small"></i>
    <a href="{{ route('admin.proveedores.show', $p) }}">{{ $p->empresa }}</a> <i class="bi bi-chevron-right small"></i>
    <span>{{ $o->folio }}</span>
</div>
<div class="page-head">
    <div>
        <div class="titulo-doc">
            <h1>{{ $o->titulo }}</h1>
            <span class="estado estado-{{ $o->estado }}">{{ $o->estado_texto }}</span>
        </div>
        <p class="sub num">{{ $o->folio }} · {{ $p->empresa }}@if($o->proyecto) · <a href="{{ route('admin.proyectos.show', $o->proyecto) }}" class="text-reset">{{ $o->proyecto->nombre }}</a>@endif
            @if($o->vista_at) · <span title="{{ $cuando($o->vista_at) }}"><i class="bi bi-eye"></i> la vio {{ $o->vista_at->locale('es')->diffForHumans() }}</span>@elseif($o->estado !== 'borrador') · aún no la abre @endif</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.ordenes.pdf', $o) }}" target="_blank" class="btn btn-borde"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</a>
        <a href="{{ route('admin.ordenes.edit', $o) }}" class="btn btn-borde"><i class="bi bi-pencil me-1"></i> Editar</a>
        @if($o->estado === 'borrador')
            @if($p->whatsapp)
                <form method="post" action="{{ route('admin.ordenes.enviar', $o) }}" target="_blank" onsubmit="setTimeout(() => location.reload(), 800)">@csrf
                    <input type="hidden" name="por" value="whatsapp">
                    <button class="btn btn-primario"><i class="bi bi-whatsapp me-1"></i> Enviar por WhatsApp</button>
                </form>
            @else
                <form method="post" action="{{ route('admin.ordenes.enviar', $o) }}">@csrf
                    <button class="btn btn-primario"><i class="bi bi-send me-1"></i> Marcar como enviada</button>
                </form>
            @endif
        @endif
    </div>
</div>

@if($o->estado === 'borrador')
    <div class="aviso mb-4" style="background: var(--amber-soft); color: var(--amber); border: 1px solid #F5D9A8"><i class="bi bi-info-circle-fill"></i>
        Es un borrador: el proveedor todavía no la ve. Al enviarla aparece en su enlace{{ $p->whatsapp ? ' y se abre WhatsApp con el mensaje listo' : '' }}.</div>
@endif

<div class="ficha">
    <div class="d-grid gap-4">
        <section class="panel adjuntos" id="adjuntos">
            <div class="panel-head">
                <h2><i class="bi bi-pin-angle-fill me-1"></i> Archivos para el proveedor</h2>
                @if($adjuntos->count() > 1)<a href="{{ route('admin.ordenes.zip', $o) }}" class="btn btn-fantasma btn-sm"><i class="bi bi-file-zip me-1"></i> Descargar todos</a>@endif
            </div>
            @foreach($adjuntos as $a)
                @include('admin.ordenes._archivo', ['a' => $a])
            @endforeach
            <form method="post" action="{{ route('admin.ordenes.subir', $o) }}" enctype="multipart/form-data" class="panel-body {{ $adjuntos->isNotEmpty() ? 'border-top' : '' }} d-flex flex-wrap gap-2 align-items-end">
                @csrf
                <div style="min-width: 190px">
                    <label class="form-label" for="tipo-archivo">Tipo</label>
                    <select name="tipo" id="tipo-archivo" class="form-select">
                        <option value="produccion">Para producción</option>
                        <option value="diseno">Diseño o referencia</option>
                    </select>
                </div>
                <div class="flex-grow-1" style="min-width: 220px">
                    <label class="form-label" for="archivos-agencia">{{ $adjuntos->isEmpty() ? 'Archivos para imprimir, bordar o fabricar' : 'Agregar más' }} <span class="text-secondary fw-normal">(hasta 50 MB c/u)</span></label>
                    <input type="file" name="archivos[]" id="archivos-agencia" class="form-control" multiple required>
                </div>
                <button class="btn btn-primario"><i class="bi bi-upload me-1"></i> Subir</button>
            </form>
            @if($adjuntos->isNotEmpty() && $o->estado !== 'borrador')
                <div class="panel-body border-top secundario py-2" style="font-size: 12.5px"><i class="bi bi-eye me-1"></i> El proveedor los ve hasta arriba de su orden, con botón para descargarlos todos.</div>
            @endif
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Qué se pidió</h2><span class="ayuda">{{ $o->conceptos->count() }} {{ $o->conceptos->count() === 1 ? 'concepto' : 'conceptos' }}</span></div>
            <div class="table-responsive">
                <table class="tabla">
                    <thead><tr><th>Concepto</th><th class="text-center">Cantidad</th><th class="text-end">Precio</th><th class="text-end">Importe</th></tr></thead>
                    <tbody>
                    @foreach($o->conceptos as $c)
                        <tr>
                            <td class="desc" style="min-width: 220px">{{ $c->descripcion }}</td>
                            <td class="text-center num text-nowrap">{{ $c->cantidad_texto }}</td>
                            <td class="text-end num text-nowrap">{{ \App\Models\OrdenCompra::dinero($c->precio) }}</td>
                            <td class="text-end num fw-medium text-nowrap">{{ \App\Models\OrdenCompra::dinero($c->importe) }}</td>
                        </tr>
                    @endforeach
                    <tr class="tot"><td colspan="3" class="text-end secundario">Subtotal</td><td class="text-end num">{{ \App\Models\OrdenCompra::dinero($o->subtotal) }}</td></tr>
                    @if($o->con_iva)<tr><td colspan="3" class="text-end secundario">IVA {{ rtrim(rtrim(number_format($o->iva_porcentaje, 2), '0'), '.') }}%</td><td class="text-end num">{{ \App\Models\OrdenCompra::dinero($o->iva) }}</td></tr>@endif
                    <tr><td colspan="3" class="text-end fw-semibold">Total</td><td class="text-end num fw-semibold">{{ \App\Models\OrdenCompra::dinero($o->total) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        @if($o->indicaciones)
            <section class="panel">
                <div class="panel-head"><h2>Indicaciones</h2></div>
                <div class="panel-body indicaciones">{{ $o->indicaciones }}</div>
            </section>
        @endif

        <section class="panel" id="facturas">
            <div class="panel-head"><h2>Facturas</h2><span class="ayuda">{{ $facturas->isEmpty() ? 'El proveedor la sube desde su enlace' : $facturas->count() . ' ' . ($facturas->count() === 1 ? 'archivo' : 'archivos') }}</span></div>
            @forelse($facturas as $a)
                @include('admin.ordenes._archivo', ['a' => $a])
            @empty
                <div class="panel-body secundario" style="font-size: 14px"><i class="bi bi-receipt me-1"></i> Todavía no hay factura. Cuando el proveedor la suba (PDF y XML) te llega un aviso y la orden pasa a <b>Facturada</b>.</div>
            @endforelse
        </section>

        <section class="panel" id="archivos">
            <div class="panel-head"><h2>Archivos del proveedor</h2><span class="ayuda">Muestras, catálogos y ponchados que te sube</span></div>
            @forelse($otros as $a)
                @include('admin.ordenes._archivo', ['a' => $a])
            @empty
                <div class="panel-body secundario" style="font-size: 14px">Todavía no sube archivos. Los sube desde su enlace y te llega un aviso.</div>
            @endforelse
        </section>

        <section class="panel" id="comentarios">
            <div class="panel-head"><h2>Comentarios</h2><span class="ayuda">Los ve el proveedor en su enlace</span></div>
            @if($o->comentarios->isNotEmpty())
                <div class="chat">
                    @foreach($o->comentarios as $m)
                        <div class="msj {{ $m->autor === 'agencia' ? 'yo' : '' }}">
                            <div class="quien">{{ $m->autor === 'agencia' ? ($m->user?->name ?? config('vandu.marca.nombre')) : $p->empresa }} · {{ $cuando($m->created_at) }}</div>
                            <div class="texto">{{ $m->texto }}</div>
                        </div>
                    @endforeach
                </div>
            @endif
            <form method="post" action="{{ route('admin.ordenes.comentar', $o) }}" class="panel-body {{ $o->comentarios->isNotEmpty() ? 'border-top' : '' }} d-grid gap-2">
                @csrf
                <label class="visually-hidden" for="texto">Comentario</label>
                <textarea name="texto" id="texto" rows="2" class="form-control" required maxlength="3000"></textarea>
                <div><button class="btn btn-borde btn-sm"><i class="bi bi-send me-1"></i> Enviar comentario</button></div>
            </form>
        </section>
    </div>

    <div class="d-grid gap-4">
        <section class="panel">
            <div class="panel-head"><h2>Estado</h2></div>
            <form method="post" action="{{ route('admin.ordenes.estado', $o) }}" class="panel-body estados">
                @csrf @method('patch')
                @foreach(\Illuminate\Support\Arr::except(\App\Models\OrdenCompra::ESTADOS, $o->estado === 'cancelada' ? [] : ['cancelada']) as $k => $e)
                    <button name="estado" value="{{ $k }}" class="{{ $o->estado === $k ? 'activo' : '' }}" @if($o->estado === $k) aria-pressed="true" disabled @endif>{{ $e['texto'] }}</button>
                @endforeach
            </form>
            <div class="panel-body pt-0">
                @if($o->estado === 'cancelada')
                    <div class="cancelada-info">
                        <b><i class="bi bi-x-octagon me-1"></i> Cancelada{{ $o->cancelada_at ? ' ' . $o->cancelada_at->locale('es')->diffForHumans() : '' }}</b>
                        @if($o->motivo_cancelacion)<div>Motivo: {{ $o->motivo_cancelacion }}</div>@endif
                        <div class="secundario" style="font-size:12.5px">No cuenta como gasto ni como pendiente de pago. Para reactivarla elige otro estado.</div>
                    </div>
                @else
                    <details class="cancelar-o" @if($errors->has('motivo')) open @endif>
                        <summary class="btn btn-fantasma btn-sm text-danger"><i class="bi bi-x-octagon me-1"></i> Cancelar orden</summary>
                        <form method="post" action="{{ route('admin.ordenes.estado', $o) }}" class="d-grid gap-2 mt-2" onsubmit="return confirm('¿Cancelar la orden {{ $o->folio }}?')">
                            @csrf @method('patch') <input type="hidden" name="estado" value="cancelada">
                            <label class="form-label mb-0" for="motivo-orden">Motivo</label>
                            <textarea name="motivo" id="motivo-orden" rows="2" maxlength="1000" required class="form-control @error('motivo') is-invalid @enderror" placeholder="Ej. El proveedor no tenía el material a tiempo">{{ old('motivo') }}</textarea>
                            @error('motivo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="secundario" style="font-size:12.5px">Deja de contar como gasto y como pendiente de pago. El proveedor verá que se canceló.</div>
                            <div><button class="btn btn-borde btn-sm text-danger">Cancelar orden</button></div>
                        </form>
                    </details>
                @endif
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Entrega</h2></div>
            <dl class="panel-body datos m-0">
                <dt>Fecha</dt><dd class="{{ $o->retrasada ? 'text-danger fw-medium' : '' }}">{{ $o->fecha_entrega ? $o->fecha_entrega->locale('es')->isoFormat('dddd D [de] MMMM') : 'Sin fecha' }}@if($o->retrasada) · retrasada @endif</dd>
                @if($o->lugar_entrega)<dt>Lugar</dt><dd>{{ $o->lugar_entrega }}</dd>@endif
                <dt>Fecha de la orden</dt><dd>{{ $o->fecha?->locale('es')->isoFormat('D [de] MMMM YYYY') }}@if($o->autor) · {{ $o->autor->name }}@endif</dd>
                @if($o->enviada_at)<dt>Enviada</dt><dd>{{ $cuando($o->enviada_at) }}</dd>@endif
            </dl>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>{{ $p->empresa }}</h2></div>
            <div class="panel-body d-grid gap-2">
                <div class="secundario" style="font-size: 13.5px">{{ collect([$p->rubro, $p->nombre, $p->telefono])->filter()->implode(' · ') }}</div>
                @if($o->estado !== 'borrador')
                    <div class="d-flex gap-2">
                        <input class="form-control" style="font-size:13px" value="{{ $o->url_proveedor }}" readonly onclick="this.select()" aria-label="Enlace de esta orden para el proveedor">
                        <button type="button" class="btn btn-primario text-nowrap" data-copiar="{{ $o->url_proveedor }}" title="Copiar enlace"><i class="bi bi-copy"></i></button>
                    </div>
                @endif
                <div class="d-flex flex-wrap gap-2">
                    @if($o->estado !== 'borrador' && $p->whatsapp)
                        <form method="post" action="{{ route('admin.ordenes.enviar', $o) }}" target="_blank">@csrf<input type="hidden" name="por" value="whatsapp">
                            <button class="btn btn-borde btn-sm"><i class="bi bi-whatsapp me-1"></i> Reenviar</button></form>
                    @endif
                    <a class="btn btn-borde btn-sm" target="_blank" rel="noopener" href="{{ $o->url_proveedor }}?vista_previa=1"><i class="bi bi-eye me-1"></i> Ver como proveedor</a>
                    <a class="btn btn-fantasma btn-sm" href="{{ route('admin.proveedores.show', $p) }}">Ver proveedor</a>
                </div>
            </div>
        </section>

        <div class="d-flex flex-wrap gap-2">
            <form method="post" action="{{ route('admin.ordenes.duplicar', $o) }}">@csrf<button class="btn btn-fantasma btn-sm"><i class="bi bi-copy me-1"></i> Duplicar</button></form>
            <form method="post" action="{{ route('admin.ordenes.destroy', $o) }}" onsubmit="return confirm({{ Js::from('¿Eliminar ' . $o->folio . ' con sus facturas y archivos? El proveedor dejará de verla.') }})">
                @csrf @method('delete')
                <button class="btn btn-fantasma btn-sm text-danger"><i class="bi bi-trash me-1"></i> Eliminar</button>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
    @include('partials.auto-actualizar', ['url' => route('admin.ordenes.version', $o), 'version' => $version])
@endpush
