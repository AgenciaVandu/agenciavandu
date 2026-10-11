@extends('admin.layout')
@php $nuevo = ! $proveedor->exists; @endphp
@section('titulo', $nuevo ? 'Nuevo proveedor' : 'Editar proveedor')

@push('head')
<style>
    .seccion { display: grid; grid-template-columns: 260px minmax(0, 1fr); gap: 32px; padding: 28px 0; border-top: 1px solid var(--line); }
    .seccion:first-of-type { border-top: 0; padding-top: 4px; }
    .seccion h2 { font-size: 15.5px; font-weight: 600; margin: 0 0 4px; }
    .seccion p { color: var(--muted); font-size: 14px; margin: 0; }
    .rubros { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
    .rubros button { font-size: 12.5px; padding: 3px 10px; border-radius: 99px; border: 1px solid var(--line); background: var(--surface); color: var(--ink); }
    .rubros button:hover, .rubros button.activo { border-color: var(--ink); }
    .barra-acciones { position: sticky; bottom: 0; z-index: 5; margin: 8px -36px -64px; padding: 14px 36px; background: rgba(244,245,247,.92);
                      backdrop-filter: blur(6px); border-top: 1px solid var(--line); display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
    @media (max-width: 991.98px) { .seccion { grid-template-columns: minmax(0, 1fr); gap: 14px; } .barra-acciones { margin: 8px -16px -48px; padding: 12px 16px; } }
</style>
@endpush

@section('contenido')
<div class="migas">
    <a href="{{ route('admin.proveedores.index') }}">Proveedores</a> <i class="bi bi-chevron-right small"></i>
    @unless($nuevo)<a href="{{ route('admin.proveedores.show', $proveedor) }}">{{ $proveedor->empresa }}</a> <i class="bi bi-chevron-right small"></i>@endunless
    <span>{{ $nuevo ? 'Nuevo' : 'Editar' }}</span>
</div>
<div class="page-head"><h1>{{ $nuevo ? 'Nuevo proveedor' : 'Editar proveedor' }}</h1></div>

<form method="post" action="{{ $nuevo ? route('admin.proveedores.store') : route('admin.proveedores.update', $proveedor) }}">
    @csrf
    @unless($nuevo) @method('put') @endunless

    <div class="seccion">
        <div><h2>Proveedor</h2><p>Así aparece en las órdenes de compra.</p></div>
        <div class="panel panel-body row g-3 mx-0" x-data="{ rubro: {{ Js::from(old('rubro', $proveedor->rubro)) }} }">
            <div class="col-md-7">
                <label class="form-label" for="empresa">Empresa o nombre comercial</label>
                <input name="empresa" id="empresa" class="form-control @error('empresa') is-invalid @enderror" value="{{ old('empresa', $proveedor->empresa) }}" required autofocus>
                @error('empresa')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-5">
                <label class="form-label" for="rubro">Rubro</label>
                <input name="rubro" id="rubro" class="form-control" x-model="rubro" list="lista-rubros" autocomplete="off">
                <datalist id="lista-rubros">@foreach($rubros as $r)<option value="{{ $r }}">@endforeach</datalist>
            </div>
            <div class="col-12" style="margin-top: 4px">
                <div class="rubros" role="group" aria-label="Rubros frecuentes">
                    @foreach($rubros as $r)
                        <button type="button" @click="rubro = {{ Js::from($r) }}" :class="{ activo: rubro === {{ Js::from($r) }} }">{{ $r }}</button>
                    @endforeach
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="nombre">Persona de contacto</label>
                <input name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $proveedor->nombre) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="telefono">Teléfono / WhatsApp</label>
                <input name="telefono" id="telefono" class="form-control num" inputmode="tel" value="{{ old('telefono', $proveedor->telefono) }}">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="email">Correo</label>
                <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $proveedor->email) }}">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="direccion">Dirección o taller <span class="text-secondary fw-normal">(opcional)</span></label>
                <input name="direccion" id="direccion" class="form-control" value="{{ old('direccion', $proveedor->direccion) }}">
            </div>
        </div>
    </div>

    <div class="seccion">
        <div><h2>Datos fiscales</h2><p>Para revisar que sus facturas vengan bien.</p></div>
        <div class="panel panel-body row g-3 mx-0">
            <div class="col-md-5">
                <label class="form-label" for="rfc">RFC</label>
                <input name="rfc" id="rfc" class="form-control text-uppercase num" maxlength="14" value="{{ old('rfc', $proveedor->rfc) }}" autocomplete="off">
            </div>
            <div class="col-md-7">
                <label class="form-label" for="razon_social">Razón social</label>
                <input name="razon_social" id="razon_social" class="form-control text-uppercase" value="{{ old('razon_social', $proveedor->razon_social) }}">
            </div>
        </div>
    </div>

    <div class="seccion">
        <div><h2>Notas</h2><p>Solo las ve tu equipo: precios acordados, tiempos, con quién tratar.</p></div>
        <div class="panel panel-body">
            <label class="form-label visually-hidden" for="notas">Notas</label>
            <textarea name="notas" id="notas" rows="4" class="form-control">{{ old('notas', $proveedor->notas) }}</textarea>
        </div>
    </div>

    <div class="barra-acciones">
        @unless($nuevo)
            <button type="submit" form="eliminar" class="btn btn-fantasma text-danger me-auto"><i class="bi bi-trash me-1"></i> Eliminar proveedor</button>
        @endunless
        <a href="{{ $nuevo ? route('admin.proveedores.index') : route('admin.proveedores.show', $proveedor) }}" class="btn btn-borde">Cancelar</a>
        <button class="btn btn-primario">{{ $nuevo ? 'Guardar proveedor' : 'Guardar cambios' }}</button>
    </div>
</form>

@unless($nuevo)
    @php $n = $proveedor->ordenes()->count(); @endphp
    <form id="eliminar" method="post" action="{{ route('admin.proveedores.destroy', $proveedor) }}"
          onsubmit="return confirm({{ Js::from('¿Eliminar a ' . $proveedor->empresa . ($n ? ' y sus ' . $n . ' órdenes de compra con sus facturas y archivos? Su enlace dejará de funcionar.' : '?')) }})">
        @csrf @method('delete')
        <input type="hidden" name="confirmar" value="1">
    </form>
@endunless
@endsection
