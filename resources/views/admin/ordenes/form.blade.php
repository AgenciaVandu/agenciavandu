@extends('admin.layout')
@php
    $nuevo = ! $o->exists;
    $estado = [
        'conceptos' => old('conceptos', $conceptos),
        'conIva'    => (bool) old('con_iva', $nuevo ? true : $o->con_iva),
        'ivaPct'    => (float) old('iva_porcentaje', $o->iva_porcentaje ?? 16),
    ];
    $unidades = ['pzas', 'juegos', 'm²', 'm', 'millares', 'cajas', 'horas', 'servicio'];
@endphp
@section('titulo', $nuevo ? 'Nueva orden de compra' : 'Editar ' . $o->folio)

@push('head')
<style>
    .editor-oc { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 20px; align-items: start; }
    .concepto { display: grid; grid-template-columns: 22px minmax(0, 1fr) 36px; gap: 8px 10px; align-items: start; padding: 16px 20px; border-bottom: 1px solid var(--line); }
    .concepto .n { padding-top: 8px; color: var(--muted); font-size: 13px; }
    .concepto .nums { grid-column: 2; display: grid; grid-template-columns: 110px 120px 160px minmax(0, 1fr); gap: 10px; align-items: end; }
    .concepto .nums .form-label { font-size: 12.5px; color: var(--muted); margin-bottom: 4px; }
    .concepto .imp { text-align: right; font-weight: 600; padding-bottom: 9px; }
    .totales { padding: 16px 20px; display: grid; gap: 6px; justify-content: end; grid-template-columns: auto 140px; text-align: right; }
    .totales .t { font-weight: 600; font-size: 17px; }
    .resumen-lat { position: sticky; top: 20px; }
    @media (max-width: 991.98px) {
        .editor-oc { grid-template-columns: minmax(0, 1fr); }
        .resumen-lat { position: static; }
        .concepto .nums { grid-template-columns: 1fr 1fr; }
        .concepto .imp { grid-column: 1 / -1; padding-bottom: 0; }
    }
</style>
@endpush

@section('contenido')
<form method="post" enctype="multipart/form-data" action="{{ $nuevo ? route('admin.ordenes.store') : route('admin.ordenes.update', $o) }}"
      x-data="ordenCompra({{ Js::from($estado) }})" @keydown.ctrl.s.prevent="$el.requestSubmit()" @keydown.meta.s.prevent="$el.requestSubmit()">
    @csrf
    @unless($nuevo) @method('put') @endunless

    <div class="migas">
        <a href="{{ route('admin.proveedores.index') }}">Proveedores</a> <i class="bi bi-chevron-right small"></i>
        <a href="{{ route('admin.ordenes.index') }}">Órdenes de compra</a> <i class="bi bi-chevron-right small"></i>
        <span>{{ $nuevo ? 'Nueva' : $o->folio }}</span>
    </div>
    <div class="page-head">
        <div>
            <h1>{{ $nuevo ? 'Nueva orden de compra' : 'Editar ' . $o->folio }}</h1>
            <p class="sub">{{ $nuevo ? 'El folio se asigna al guardar. Se queda como borrador hasta que la envíes.' : ($o->estado === 'borrador' ? 'Borrador: el proveedor todavía no la ve.' : 'El proveedor ya la ve: tus cambios le aparecen en su enlace.') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ $nuevo ? route('admin.ordenes.index') : route('admin.ordenes.show', $o) }}" class="btn btn-borde">Cancelar</a>
            <button class="btn btn-primario px-4">{{ $nuevo ? 'Guardar orden' : 'Guardar cambios' }}</button>
        </div>
    </div>

    <div class="editor-oc">
        <div class="d-grid gap-4">
            <section class="panel">
                <div class="panel-head"><h2>Proveedor y proyecto</h2></div>
                <div class="panel-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="proveedor_id">Proveedor</label>
                        <div class="input-group">
                            <select name="proveedor_id" id="proveedor_id" class="form-select @error('proveedor_id') is-invalid @enderror" required>
                                <option value="">Elige un proveedor…</option>
                                @foreach($proveedores->groupBy(fn ($p) => $p->rubro ?: 'Sin rubro') as $rubro => $grupo)
                                    <optgroup label="{{ $rubro }}">
                                        @foreach($grupo as $p)
                                            <option value="{{ $p->id }}" @selected(old('proveedor_id', $o->proveedor_id) == $p->id)>{{ $p->empresa }}{{ $p->nombre ? ' — ' . $p->nombre : '' }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <a href="{{ route('admin.proveedores.create') }}" class="btn btn-borde" title="Nuevo proveedor"><i class="bi bi-plus-lg"></i></a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="proyecto_id">Proyecto <span class="text-secondary fw-normal">(opcional)</span></label>
                        <select name="proyecto_id" id="proyecto_id" class="form-select">
                            <option value="">Sin proyecto (gasto general)</option>
                            @foreach($proyectos as $pr)
                                <option value="{{ $pr->id }}" @selected(old('proyecto_id', $o->proyecto_id) == $pr->id)>{{ $pr->nombre }} · {{ $pr->cliente?->empresa ?: $pr->cliente?->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="titulo">Nombre de la solicitud</label>
                        <input name="titulo" id="titulo" class="form-control @error('titulo') is-invalid @enderror" value="{{ old('titulo', $o->titulo) }}" required maxlength="160">
                        @error('titulo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="fecha">Fecha</label>
                        <input type="date" name="fecha" id="fecha" class="form-control num" value="{{ old('fecha', ($o->fecha ?? now())->format('Y-m-d')) }}" required>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head"><h2>Qué necesitas</h2><span class="ayuda" x-text="conceptos.length + (conceptos.length === 1 ? ' concepto' : ' conceptos')"></span></div>
                <template x-for="(c, i) in conceptos" :key="i">
                    <div class="concepto">
                        <span class="n num" x-text="i + 1"></span>
                        <div>
                            <label class="visually-hidden" :for="'desc'+i">Concepto</label>
                            <textarea class="form-control" rows="2" :id="'desc'+i" :name="`conceptos[${i}][descripcion]`" x-model="c.descripcion" required maxlength="500" :placeholder="i === 0 ? 'Qué necesitas, p. ej. Camisa tipo Oxford con logo bordado' : ''"></textarea>
                        </div>
                        <div>
                            <button type="button" class="btn btn-fantasma btn-icono" @click="conceptos.splice(i, 1)" x-show="conceptos.length > 1" :aria-label="'Quitar concepto ' + (i + 1)"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <div class="nums">
                            <div>
                                <label class="form-label" :for="'cant'+i">Cantidad</label>
                                <input type="number" step="0.01" min="0" class="form-control num" :id="'cant'+i" :name="`conceptos[${i}][cantidad]`" x-model.number="c.cantidad" required>
                            </div>
                            <div>
                                <label class="form-label" :for="'uni'+i">Unidad</label>
                                <input class="form-control" :id="'uni'+i" :name="`conceptos[${i}][unidad]`" x-model="c.unidad" list="unidades" maxlength="30">
                            </div>
                            <div>
                                <label class="form-label" :for="'precio'+i">Precio unitario</label>
                                <div class="input-group"><span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0" class="form-control num" :id="'precio'+i" :name="`conceptos[${i}][precio]`" x-model.number="c.precio" placeholder="0.00"></div>
                            </div>
                            <div class="imp num" x-text="dinero(importe(c))"></div>
                        </div>
                    </div>
                </template>
                <datalist id="unidades">@foreach($unidades as $u)<option value="{{ $u }}">@endforeach</datalist>
                <div class="px-3 pt-3">
                    <button type="button" class="btn btn-borde btn-sm" @click="agregar()"><i class="bi bi-plus-lg me-1"></i> Agregar concepto</button>
                </div>
                <div class="totales num">
                    <span class="secundario">Subtotal</span><span x-text="dinero(subtotal)"></span>
                    <template x-if="conIva"><span class="secundario" x-text="'IVA ' + ivaPct + '%'"></span></template>
                    <template x-if="conIva"><span x-text="dinero(iva)"></span></template>
                    <span class="t">Total</span><span class="t" x-text="dinero(total)"></span>
                </div>
            </section>

            <section class="panel" x-data="{ n: 0, nombres: [] }">
                <div class="panel-head"><h2>Archivos para el proveedor</h2><span class="ayuda">Le aparecen hasta arriba en su enlace</span></div>
                <div class="panel-body d-grid gap-3">
                    @if(! $nuevo && $o->adjuntos->isNotEmpty())
                        <ul class="list-unstyled m-0 d-grid gap-1" style="font-size: 14px">
                            @foreach($o->adjuntos as $a)
                                <li class="d-flex gap-2 align-items-center"><i class="bi {{ $a->icono }}"></i><span class="text-truncate">{{ $a->nombre }}</span><span class="secundario text-nowrap">· {{ $a->tipo_texto }} · {{ $a->peso_texto }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label" for="tipo-adjunto">Tipo</label>
                            <select name="tipo" id="tipo-adjunto" class="form-select">
                                <option value="produccion">Para producción (impresión, bordado…)</option>
                                <option value="diseno">Diseño o referencia</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label" for="archivos">{{ ! $nuevo && $o->adjuntos->isNotEmpty() ? 'Agregar más archivos' : 'Archivos' }} <span class="text-secondary fw-normal">(hasta 10, de 50 MB c/u)</span></label>
                            <input type="file" name="archivos[]" id="archivos" class="form-control @error('archivos') is-invalid @enderror @error('archivos.*') is-invalid @enderror" multiple
                                   @change="nombres = [...$event.target.files].map(f => f.name)">
                            @error('archivos')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @error('archivos.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="secundario" style="font-size: 12.5px" x-show="nombres.length" x-text="'Se subirán al guardar: ' + nombres.join(', ')"></div>
                    <div class="secundario" style="font-size: 12.5px" x-show="! nombres.length">PDF, AI, PSD, CDR, ponchados (DST, PES…), imágenes o ZIP. Se guardan en la carpeta de la orden en tu Dropbox.</div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head"><h2>Indicaciones</h2><span class="ayuda">Aparecen en el PDF y en su enlace</span></div>
                <div class="panel-body">
                    <label class="form-label visually-hidden" for="indicaciones">Indicaciones</label>
                    <textarea name="indicaciones" id="indicaciones" rows="6" class="form-control">{{ old('indicaciones', $o->indicaciones) }}</textarea>
                    <div class="secundario mt-2" style="font-size: 12.5px">Tallas, colores, técnica, ubicación del logo, acabados… Los archivos de impresión o diseño los adjuntas arriba.</div>
                </div>
            </section>
        </div>

        <aside class="d-grid gap-4 resumen-lat">
            <section class="panel">
                <div class="panel-head"><h2>Entrega</h2></div>
                <div class="panel-body d-grid gap-3">
                    <div>
                        <label class="form-label" for="fecha_entrega">Fecha de entrega</label>
                        <input type="date" name="fecha_entrega" id="fecha_entrega" class="form-control num" value="{{ old('fecha_entrega', $o->fecha_entrega?->format('Y-m-d')) }}">
                    </div>
                    <div>
                        <label class="form-label" for="lugar_entrega">Dónde se entrega</label>
                        <input name="lugar_entrega" id="lugar_entrega" class="form-control" value="{{ old('lugar_entrega', $o->lugar_entrega) }}" maxlength="200">
                    </div>
                </div>
            </section>
            <section class="panel">
                <div class="panel-head"><h2>IVA</h2></div>
                <div class="panel-body d-grid gap-2">
                    <div class="form-check form-switch">
                        <input type="hidden" name="con_iva" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="con_iva" name="con_iva" value="1" x-model="conIva">
                        <label class="form-check-label" for="con_iva">Sumar IVA al total</label>
                    </div>
                    <div class="input-group" style="max-width: 140px" x-show="conIva">
                        <input type="number" step="0.01" min="0" max="100" name="iva_porcentaje" class="form-control num" x-model.number="ivaPct" aria-label="Porcentaje de IVA">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
            </section>
            <button class="btn btn-primario w-100">{{ $nuevo ? 'Guardar orden' : 'Guardar cambios' }}</button>
        </aside>
    </div>
</form>
@endsection

@push('scripts')
<script>
function ordenCompra(init) {
    return {
        ...init,
        importe(c) { return Math.round((+c.cantidad || 0) * (+c.precio || 0) * 100) / 100; },
        get subtotal() { return this.conceptos.reduce((s, c) => s + this.importe(c), 0); },
        get iva() { return this.conIva ? Math.round(this.subtotal * (+this.ivaPct || 0)) / 100 : 0; },
        get total() { return this.subtotal + this.iva; },
        dinero(n) { return '$' + (+n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        agregar() {
            const u = this.conceptos.at(-1)?.unidad || 'pzas';
            this.conceptos.push({ descripcion: '', cantidad: 1, unidad: u, precio: null });
            this.$nextTick(() => document.getElementById('desc' + (this.conceptos.length - 1))?.focus());
        },
    };
}
</script>
@endpush
