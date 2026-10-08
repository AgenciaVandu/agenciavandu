@extends('admin.layout')
@php $nuevo = ! $cliente->exists; @endphp
@section('titulo', $nuevo ? 'Nuevo cliente' : $cliente->nombre)

@section('contenido')
<a href="{{ route('admin.clientes.index') }}" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left"></i> Clientes</a>
<h1 class="h3 mb-3">{{ $nuevo ? 'Nuevo cliente' : 'Editar cliente' }}</h1>

<form method="post" action="{{ $nuevo ? route('admin.clientes.store') : route('admin.clientes.update', $cliente) }}" class="row g-4">
    @csrf
    @unless($nuevo) @method('put') @endunless

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Contacto</div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="nombre">Nombre de contacto</label>
                    <input name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $cliente->nombre) }}" required autofocus>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="empresa">Empresa</label>
                    <input name="empresa" id="empresa" class="form-control" value="{{ old('empresa', $cliente->empresa) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="email">Correo</label>
                    <input type="email" name="email" id="email" class="form-control" value="{{ old('email', $cliente->email) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="telefono">Teléfono / WhatsApp</label>
                    <input name="telefono" id="telefono" class="form-control num" value="{{ old('telefono', $cliente->telefono) }}" placeholder="999 123 4567">
                </div>
                <div class="col-12">
                    <label class="form-label" for="notas">Notas</label>
                    <textarea name="notas" id="notas" rows="3" class="form-control">{{ old('notas', $cliente->notas) }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">Datos fiscales <span class="small text-muted fw-normal">(opcional)</span></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="rfc">RFC</label>
                    <input name="rfc" id="rfc" class="form-control text-uppercase" maxlength="13" value="{{ old('rfc', $cliente->rfc) }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="uso_cfdi">Uso de CFDI</label>
                    <input name="uso_cfdi" id="uso_cfdi" class="form-control text-uppercase" maxlength="10" value="{{ old('uso_cfdi', $cliente->uso_cfdi) }}" placeholder="G03">
                </div>
                <div class="col-12">
                    <label class="form-label" for="razon_social">Razón social</label>
                    <input name="razon_social" id="razon_social" class="form-control" value="{{ old('razon_social', $cliente->razon_social) }}">
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-v">{{ $nuevo ? 'Guardar cliente' : 'Guardar cambios' }}</button>
            @if($nuevo)
                <button class="btn btn-verde" name="y_cotizar" value="1">Guardar y crear cotización</button>
            @endif
        </div>
    </div>
</form>

@unless($nuevo)
    <form method="post" action="{{ route('admin.clientes.destroy', $cliente) }}" class="mt-4"
          onsubmit="return confirm('¿Eliminar a {{ $cliente->nombre }} y sus {{ $cliente->presupuestos()->count() }} cotizaciones? Sus enlaces dejarán de funcionar.')">
        @csrf @method('delete')
        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Eliminar cliente</button>
    </form>
@endunless
@endsection
