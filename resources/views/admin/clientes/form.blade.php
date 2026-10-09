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

<form method="post" enctype="multipart/form-data" action="{{ $nuevo ? route('admin.clientes.store') : route('admin.clientes.update', $cliente) }}">
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
        <div><h2>Datos de facturación</h2><p>Los capturas tú. Si tienes la Constancia de Situación Fiscal, súbela aquí y queda en el expediente del cliente.</p></div>
        <div class="panel panel-body row g-3 mx-0">
            <div class="col-md-5">
                <label class="form-label" for="rfc">RFC</label>
                <input name="rfc" id="rfc" class="form-control text-uppercase num @error('rfc') is-invalid @enderror" maxlength="14" value="{{ old('rfc', $cliente->rfc) }}" placeholder="XAXX010101000" autocomplete="off">
                @error('rfc')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-7">
                <label class="form-label" for="razon_social">Razón social <span class="text-secondary fw-normal">(tal como aparece en la constancia)</span></label>
                <input name="razon_social" id="razon_social" class="form-control text-uppercase" value="{{ old('razon_social', $cliente->razon_social) }}">
            </div>
            <div class="col-md-8">
                <label class="form-label" for="regimen_fiscal">Régimen fiscal</label>
                <select name="regimen_fiscal" id="regimen_fiscal" class="form-select">
                    <option value="">Sin definir</option>
                    @foreach(config('vandu.sat.regimenes') as $k => $v)
                        <option value="{{ $k }}" @selected(old('regimen_fiscal', $cliente->regimen_fiscal) == $k)>{{ $k }} · {{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="cp_fiscal">C.P. fiscal</label>
                <input name="cp_fiscal" id="cp_fiscal" class="form-control num @error('cp_fiscal') is-invalid @enderror" inputmode="numeric" maxlength="5" value="{{ old('cp_fiscal', $cliente->cp_fiscal) }}" placeholder="97000">
                @error('cp_fiscal')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="uso_cfdi">Uso de CFDI</label>
                @php $uso = old('uso_cfdi', $cliente->uso_cfdi); @endphp
                <select name="uso_cfdi" id="uso_cfdi" class="form-select">
                    <option value="">Sin definir</option>
                    @foreach(config('vandu.sat.usos_cfdi') as $k => $v)
                        <option value="{{ $k }}" @selected($uso == $k)>{{ $k }} · {{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6" x-data="{ m: {{ Js::from(old('metodo_pago', $cliente->metodo_pago)) }} }">
                <label class="form-label" for="metodo_pago">Método de pago</label>
                <div class="d-flex gap-2">
                <select name="metodo_pago" id="metodo_pago" class="form-select" x-model="m">
                    <option value="">Sin definir</option>
                    @foreach(config('vandu.metodos_pago') as $k => $v)
                        <option value="{{ $k }}" @selected(old('metodo_pago', $cliente->metodo_pago) === $k)>{{ $v }}</option>
                    @endforeach
                </select>
                <div class="input-group flex-nowrap" style="width: 150px" x-show="m === 'credito'" x-cloak>
                    <input type="number" min="1" max="365" name="dias_credito" class="form-control num" value="{{ old('dias_credito', $cliente->dias_credito) }}" placeholder="30" aria-label="Días de crédito" :disabled="m !== 'credito'">
                    <span class="input-group-text">días</span>
                </div>
                </div>
                <div class="secundario mt-1" style="font-size:12.5px" x-show="m === 'credito'" x-cloak>Sus proyectos se proponen a crédito: sin anticipo y con el pago a estos días de la entrega.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="email_factura">Correo para la factura <span class="text-secondary fw-normal">(si es otro)</span></label>
                <input type="email" name="email_factura" id="email_factura" class="form-control" value="{{ old('email_factura', $cliente->email_factura) }}" placeholder="facturas@empresa.com">
            </div>
            <div class="col-md-8">
                <label class="form-label" for="constancia">Constancia de Situación Fiscal <span class="text-secondary fw-normal">(PDF, opcional)</span></label>
                <input type="file" name="constancia" id="constancia" class="form-control @error('constancia') is-invalid @enderror" accept="application/pdf,image/jpeg,image/png">
                @error('constancia')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @if(! $nuevo && $cliente->constancias()->exists())
                    <div class="secundario mt-1" style="font-size:12.5px"><i class="bi bi-file-earmark-pdf"></i> Ya hay {{ $cliente->constancias()->count() }} en el expediente; si subes otra se agrega y la anterior se conserva.</div>
                @endif
            </div>
            <div class="col-md-4">
                <label class="form-label" for="constancia_emitida_el">Emitida el</label>
                <input type="date" name="constancia_emitida_el" id="constancia_emitida_el" class="form-control num" value="{{ old('constancia_emitida_el') }}">
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
