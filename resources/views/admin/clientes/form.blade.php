@extends('admin.layout')
@php $nuevo = ! $cliente->exists; @endphp
@section('titulo', $nuevo ? 'Nuevo cliente' : 'Editar cliente')

@push('head')
<style>
    .seccion { display: grid; grid-template-columns: 260px minmax(0, 1fr); gap: 32px; padding: 28px 0; border-top: 1px solid var(--line); }
    .seccion:first-of-type { border-top: 0; padding-top: 4px; }
    .seccion h2 { font-size: 15.5px; font-weight: 600; margin: 0 0 4px; }
    .seccion p { color: var(--muted); font-size: 14px; margin: 0; }
    .barra-acciones { position: sticky; bottom: 0; z-index: 5; margin: 8px -36px -64px; padding: 14px 36px; background: rgba(244,245,247,.92);
                      backdrop-filter: blur(6px); border-top: 1px solid var(--line); display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
    @media (max-width: 991.98px) { .seccion { grid-template-columns: 1fr; gap: 14px; } .barra-acciones { margin: 8px -16px -48px; padding: 12px 16px; } }
</style>
@endpush

@section('contenido')
<div class="migas">
    <a href="{{ route('admin.clientes.index') }}">Clientes</a> <i class="bi bi-chevron-right small"></i>
    @unless($nuevo)<a href="{{ route('admin.clientes.show', $cliente) }}">{{ $cliente->empresa ?: $cliente->nombre }}</a> <i class="bi bi-chevron-right small"></i>@endunless
    <span>{{ $nuevo ? 'Nuevo' : 'Editar' }}</span>
</div>
<div class="page-head"><h1>{{ $nuevo ? 'Nuevo cliente' : 'Editar cliente' }}</h1></div>

<form method="post" action="{{ $nuevo ? route('admin.clientes.store') : route('admin.clientes.update', $cliente) }}">
    @csrf
    @unless($nuevo) @method('put') @endunless

    <div class="seccion">
        <div><h2>Contacto</h2><p>Así aparece en el encabezado de cada cotización.</p></div>
        <div class="panel panel-body row g-3 mx-0">
            <div class="col-md-6">
                <label class="form-label" for="nombre">Nombre de contacto</label>
                <input name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $cliente->nombre) }}" required autofocus placeholder="Jessica Estefani">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="empresa">Empresa <span class="text-secondary fw-normal">(opcional)</span></label>
                <input name="empresa" id="empresa" class="form-control" value="{{ old('empresa', $cliente->empresa) }}" placeholder="Corporativo C&S">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="email">Correo</label>
                <input type="email" name="email" id="email" class="form-control" value="{{ old('email', $cliente->email) }}" placeholder="nombre@empresa.com">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="telefono">Teléfono / WhatsApp</label>
                <input name="telefono" id="telefono" class="form-control num" value="{{ old('telefono', $cliente->telefono) }}" placeholder="999 123 4567">
            </div>
        </div>
    </div>

    <div class="seccion">
        <div><h2>Datos fiscales</h2><p>Opcionales. Te sirven para emitir la factura cuando acepten.</p></div>
        <div class="panel panel-body row g-3 mx-0">
            <div class="col-md-5">
                <label class="form-label" for="rfc">RFC</label>
                <input name="rfc" id="rfc" class="form-control text-uppercase num" maxlength="13" value="{{ old('rfc', $cliente->rfc) }}">
            </div>
            <div class="col-md-7">
                <label class="form-label" for="uso_cfdi">Uso de CFDI</label>
                <input name="uso_cfdi" id="uso_cfdi" class="form-control text-uppercase" maxlength="10" value="{{ old('uso_cfdi', $cliente->uso_cfdi) }}" placeholder="G03">
            </div>
            <div class="col-12">
                <label class="form-label" for="razon_social">Razón social</label>
                <input name="razon_social" id="razon_social" class="form-control" value="{{ old('razon_social', $cliente->razon_social) }}">
            </div>
        </div>
    </div>

    <div class="seccion">
        <div><h2>Notas</h2><p>Solo las ves tú.</p></div>
        <div class="panel panel-body">
            <label class="form-label visually-hidden" for="notas">Notas</label>
            <textarea name="notas" id="notas" rows="4" class="form-control" placeholder="Preferencias, acuerdos, cómo prefiere que le contacten…">{{ old('notas', $cliente->notas) }}</textarea>
        </div>
    </div>

    <div class="barra-acciones">
        @unless($nuevo)
            <button type="submit" form="eliminar" class="btn btn-fantasma text-danger me-auto"><i class="bi bi-trash me-1"></i> Eliminar cliente</button>
        @endunless
        <a href="{{ $nuevo ? route('admin.clientes.index') : route('admin.clientes.show', $cliente) }}" class="btn btn-borde">Cancelar</a>
        @if($nuevo)
            <button class="btn btn-borde" name="y_cotizar" value="1">Guardar y crear cotización</button>
        @endif
        <button class="btn btn-primario">{{ $nuevo ? 'Guardar cliente' : 'Guardar cambios' }}</button>
    </div>
</form>

@unless($nuevo)
    <form id="eliminar" method="post" action="{{ route('admin.clientes.destroy', $cliente) }}"
          onsubmit="return confirm('¿Eliminar a {{ $cliente->nombre }} y sus {{ $cliente->presupuestos()->count() }} cotizaciones? Sus enlaces dejarán de funcionar.')">
        @csrf @method('delete')
    </form>
@endunless
@endsection
