@extends('admin.layout')
@php
    $nuevo = ! $producto->exists;
    $mp = old('mostrar_precio', $producto->mostrar_precio === null ? '' : (int) $producto->mostrar_precio);
@endphp
@section('titulo', $nuevo ? 'Nuevo producto' : 'Editar producto')

@push('head')
<style>
    .seccion { display: grid; grid-template-columns: 260px minmax(0, 1fr); gap: 32px; padding: 28px 0; border-top: 1px solid var(--line); }
    .seccion:first-of-type { border-top: 0; padding-top: 4px; }
    .seccion h2 { font-size: 15.5px; font-weight: 600; margin: 0 0 4px; }
    .seccion p { color: var(--muted); font-size: 14px; margin: 0; }
    .barra-acciones { position: sticky; bottom: 0; z-index: 5; margin: 8px -36px -64px; padding: 14px 36px; background: rgba(244,245,247,.92);
                      backdrop-filter: blur(6px); border-top: 1px solid var(--line); display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
    .chips-u { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    .chips-u button { font-size: 12.5px; padding: 3px 10px; border-radius: 99px; border: 1px solid var(--line); background: var(--surface); color: var(--ink); }
    .chips-u button:hover, .chips-u button.activo { border-color: var(--ink); }
    .foto-caja { display: flex; gap: 18px; align-items: center; flex-wrap: wrap; }
    .foto-vista { width: 132px; height: 132px; border-radius: 14px; border: 1px dashed var(--line-strong, #cfd3da); background: var(--sunken);
                  display: grid; place-items: center; color: var(--faint); overflow: hidden; font-size: 30px; flex: none; }
    .foto-vista img { width: 100%; height: 100%; object-fit: cover; }
    .margen-vivo { font-size: 13px; margin-top: 6px; color: var(--muted); }
    .margen-vivo b { color: var(--green-ink); }
    .margen-vivo.bajo b { color: var(--red); }
    .interruptor { display: flex; gap: 12px; align-items: flex-start; padding: 12px 0; }
    .interruptor + .interruptor { border-top: 1px solid var(--line); }
    .interruptor .form-check-input { margin-top: 3px; flex: none; }
    .interruptor small { display: block; color: var(--muted); font-size: 13px; }
    @media (max-width: 991.98px) { .seccion { grid-template-columns: minmax(0, 1fr); gap: 14px; } .barra-acciones { margin: 8px -16px -48px; padding: 12px 16px; } }
</style>
@endpush

@section('contenido')
<div class="migas">
    <a href="{{ route('admin.productos.index') }}">Productos</a> <i class="bi bi-chevron-right small"></i>
    @unless($nuevo)<a href="{{ route('admin.productos.show', $producto) }}">{{ $producto->nombre }}</a> <i class="bi bi-chevron-right small"></i>@endunless
    <span>{{ $nuevo ? 'Nuevo' : 'Editar' }}</span>
</div>
<div class="page-head"><h1>{{ $nuevo ? 'Nuevo producto' : 'Editar producto' }}</h1></div>

<form method="post" enctype="multipart/form-data" action="{{ $nuevo ? route('admin.productos.store') : route('admin.productos.update', $producto) }}"
      x-data="{
          unidad: {{ Js::from(old('unidad', $producto->unidad)) }},
          precio: {{ Js::from(old('precio', $producto->precio)) }},
          costo: {{ Js::from(old('costo', $producto->costo)) }},
          controla: {{ Js::from((bool) old('controla_existencia', $producto->controla_existencia)) }},
          vista: {{ Js::from($producto->foto_url) }},
          quitar: false,
          get margen() { const p = parseFloat(this.precio), c = parseFloat(this.costo); return p > 0 && c >= 0 && this.costo !== '' && this.costo !== null ? Math.round((p - c) / p * 1000) / 10 : null; },
          elegir(e) { const f = e.target.files[0]; if (f) { this.vista = URL.createObjectURL(f); this.quitar = false; } },
      }">
    @csrf
    @unless($nuevo) @method('put') @endunless

    <div class="seccion">
        <div><h2>Producto</h2><p>Así lo ven tus vendedores al cotizar y tus clientes en su página.</p></div>
        <div class="panel panel-body row g-3 mx-0">
            <div class="col-md-8">
                <label class="form-label" for="nombre">Nombre</label>
                <input name="nombre" id="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre', $producto->nombre) }}" required maxlength="200" @if($nuevo) autofocus @endif placeholder="Silla ejecutiva Milán negra">
                @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @unless($nuevo)<div class="form-text">Su página: <span class="num">{{ $producto->url }}</span>. Si cambias el nombre, el enlace se actualiza y el anterior sigue funcionando.</div>@endunless
            </div>
            <div class="col-md-4">
                <label class="form-label" for="clave">Clave o SKU <span class="text-secondary fw-normal">(opcional)</span></label>
                <input name="clave" id="clave" class="form-control num @error('clave') is-invalid @enderror" value="{{ old('clave', $producto->clave) }}" maxlength="60" autocomplete="off">
                @error('clave')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            @if(\App\Support\Cuentas::actual()?->tiene('pos'))
            <div class="col-md-4"><label class="form-label" for="codigo_barras">Código de barras</label><input name="codigo_barras" id="codigo_barras" class="form-control num" value="{{ old('codigo_barras', $producto->codigo_barras) }}" maxlength="80" autocomplete="off"></div>
            @endif
            <div class="col-md-6">
                <label class="form-label" for="categoria">Categoría</label>
                <input name="categoria" id="categoria" class="form-control" value="{{ old('categoria', $producto->categoria?->nombre) }}" list="lista-categorias" autocomplete="off" maxlength="80" placeholder="Sillas, Escritorios…">
                <datalist id="lista-categorias">@foreach($categorias as $c)<option value="{{ $c }}">@endforeach</datalist>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="unidad">Se vende por</label>
                <input name="unidad" id="unidad" class="form-control" x-model="unidad" maxlength="30" list="lista-unidades" autocomplete="off">
                <datalist id="lista-unidades">@foreach($unidades as $u)<option value="{{ $u }}">@endforeach</datalist>
                <div class="chips-u" role="group" aria-label="Unidades frecuentes">
                    @foreach(array_slice($unidades, 0, 6) as $u)
                        <button type="button" @click="unidad = {{ Js::from($u) }}" :class="{ activo: unidad === {{ Js::from($u) }} }">{{ $u }}</button>
                    @endforeach
                </div>
            </div>
            <div class="col-12">
                <label class="form-label" for="descripcion">Descripción <span class="text-secondary fw-normal">(medidas, material, colores, garantía)</span></label>
                <textarea name="descripcion" id="descripcion" rows="4" class="form-control" maxlength="5000">{{ old('descripcion', $producto->descripcion) }}</textarea>
                <div class="form-text">Se copia a la cotización cuando eligen este producto.</div>
            </div>
        </div>
    </div>

    <div class="seccion">
        <div><h2>Precio{{ \App\Support\Inventario::veCostos() ? ' y costo' : '' }}</h2><p>Precio de venta antes de IVA.{{ \App\Support\Inventario::veCostos() ? ' El costo y el margen solo los ve quien administra productos, nunca los vendedores.' : '' }}</p></div>
        <div class="panel panel-body row g-3 mx-0">
            <div class="col-md-4">
                <label class="form-label" for="precio">Precio de venta</label>
                <div class="input-group"><span class="input-group-text">$</span>
                    <input name="precio" id="precio" type="number" step="0.01" min="0" class="form-control num @error('precio') is-invalid @enderror" x-model="precio" required inputmode="decimal"></div>
            </div>
            @if(\App\Support\Inventario::veCostos())
                <div class="col-md-4">
                    <label class="form-label" for="costo">Costo <span class="text-secondary fw-normal">(opcional)</span></label>
                    <div class="input-group"><span class="input-group-text">$</span>
                        <input name="costo" id="costo" type="number" step="0.01" min="0" class="form-control num" x-model="costo" inputmode="decimal"></div>
                    <div class="margen-vivo" :class="{ bajo: margen !== null && margen < 15 }" x-show="margen !== null" x-cloak>Margen: <b x-text="margen + '%'"></b></div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="proveedor_id">Proveedor <span class="text-secondary fw-normal">(opcional)</span></label>
                    <select name="proveedor_id" id="proveedor_id" class="form-select">
                        <option value="">Sin proveedor</option>
                        @foreach($proveedores as $pr)<option value="{{ $pr->id }}" @selected((int) old('proveedor_id', $producto->proveedor_id) === $pr->id)>{{ $pr->empresa }}</option>@endforeach
                    </select>
                </div>
            @endif
        </div>
    </div>

    <div class="seccion">
        <div><h2>Inventario</h2><p>Las existencias bajan solas cuando una cotización se acepta, y regresan si se cancela.</p></div>
        <div class="panel panel-body">
            <label class="interruptor">
                <input type="hidden" name="controla_existencia" value="0">
                <input class="form-check-input" type="checkbox" role="switch" name="controla_existencia" value="1" x-model="controla">
                <span>Llevar existencias de este producto<small>Apágalo para servicios o productos sobre pedido.</small></span>
            </label>
            <div class="row g-3 pt-2" x-show="controla" x-cloak>
                @if($nuevo)
                    <div class="col-md-6">
                        <label class="form-label" for="existencia_inicial">¿Cuántas tienes hoy?</label>
                        <div class="input-group"><input name="existencia_inicial" id="existencia_inicial" type="number" step="0.01" min="0" class="form-control num" value="{{ old('existencia_inicial') }}" inputmode="decimal" placeholder="0"><span class="input-group-text" x-text="unidad || 'pieza'"></span></div>
                    </div>
                @else
                    <div class="col-md-6">
                        <span class="form-label d-block">Existencia actual</span>
                        <div class="fs-5 fw-semibold num">{{ $producto->existencia_texto }}</div>
                        <div class="form-text">Para cambiarla registra una entrada, salida o conteo en la <a href="{{ route('admin.productos.show', $producto) }}">ficha del producto</a>; así queda en el historial.</div>
                    </div>
                @endif
                <div class="col-md-6">
                    <label class="form-label" for="minimo">Avisarme cuando queden <span class="text-secondary fw-normal">(opcional)</span></label>
                    <div class="input-group"><input name="minimo" id="minimo" type="number" step="0.01" min="0" class="form-control num" value="{{ old('minimo', $producto->minimo !== null ? \App\Models\Producto::numero($producto->minimo) : '') }}" inputmode="decimal" placeholder="Ej. 5"><span class="input-group-text" x-text="unidad || 'pieza'"></span></div>
                </div>
            </div>
        </div>
    </div>

    <div class="seccion">
        <div><h2>Foto</h2><p>Se ve al cotizar y en su página. La reducimos para que cargue rápido.</p></div>
        <div class="panel panel-body">
            <div class="foto-caja">
                <div class="foto-vista">
                    <template x-if="vista && !quitar"><img :src="vista" alt="Foto del producto"></template>
                    <i class="bi bi-image" x-show="!vista || quitar"></i>
                </div>
                <div class="d-grid gap-2">
                    <label class="btn btn-borde mb-0"><i class="bi bi-upload me-1"></i> <span x-text="vista && !quitar ? 'Cambiar foto' : 'Subir foto'"></span>
                        <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" class="visually-hidden" @change="elegir"></label>
                    @if($producto->foto)
                        <label class="small d-flex gap-2 align-items-center"><input type="checkbox" name="quitar_foto" value="1" x-model="quitar"> Quitar la foto</label>
                    @endif
                    <span class="small text-secondary">JPG, PNG o WebP, hasta 12 MB.</span>
                </div>
            </div>
            @error('foto')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="seccion">
        <div><h2>Página pública</h2><p>Cada producto tiene su enlace para mandarlo por WhatsApp o redes. Al abrirlo, el cliente te escribe directo.</p></div>
        <div class="panel panel-body">
            <label class="interruptor">
                <input type="hidden" name="publico" value="0">
                <input class="form-check-input" type="checkbox" role="switch" name="publico" value="1" @checked(old('publico', $producto->publico))>
                <span>Tiene página pública<small>Apágalo si es un producto interno que no quieres compartir.</small></span>
            </label>
            <div class="interruptor">
                <span class="w-100">
                    <label for="mostrar_precio" class="d-block mb-1">Precio en su página</label>
                    <select name="mostrar_precio" id="mostrar_precio" class="form-select" style="max-width: 360px">
                        <option value="" @selected($mp === '')>Lo que diga el catálogo ({{ \App\Support\Cuentas::actual()?->ajuste('catalogo.mostrar_precio', true) ? 'mostrarlo' : 'no mostrarlo' }})</option>
                        <option value="1" @selected((string) $mp === '1')>Mostrar el precio</option>
                        <option value="0" @selected((string) $mp === '0')>No mostrarlo, que lo pidan por WhatsApp</option>
                    </select>
                </span>
            </div>
            @unless($nuevo)
                <label class="interruptor">
                    <input type="hidden" name="activo" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" name="activo" value="1" @checked(old('activo', $producto->activo))>
                    <span>Activo<small>Desactívalo si ya no lo vendes: deja de aparecer al cotizar y su página se apaga, pero conserva su historial.</small></span>
                </label>
            @endunless
        </div>
    </div>

    <div class="barra-acciones">
        @unless($nuevo)
            <button type="submit" form="eliminar" class="btn btn-fantasma text-danger me-auto"><i class="bi bi-trash me-1"></i> Eliminar</button>
        @endunless
        <a href="{{ $nuevo ? route('admin.productos.index') : route('admin.productos.show', $producto) }}" class="btn btn-borde">Cancelar</a>
        <button class="btn btn-primario">{{ $nuevo ? 'Guardar producto' : 'Guardar cambios' }}</button>
    </div>
</form>

@unless($nuevo)
    <form id="eliminar" method="post" action="{{ route('admin.productos.destroy', $producto) }}"
          onsubmit="return confirm({{ Js::from('¿Eliminar ' . $producto->nombre . '? Se borra su historial de movimientos y su página deja de funcionar. Si ya no lo vendes, mejor desactívalo.') }})">
        @csrf @method('delete')
        <input type="hidden" name="confirmar" value="1">
    </form>
@endunless
@endsection
