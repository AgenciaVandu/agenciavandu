@extends('admin.layout')
@php $nuevo = ! $p->exists; @endphp
@section('titulo', $nuevo ? 'Nuevo prospecto' : 'Editar prospecto')

@push('head')
<style>
    .seccion { display: grid; grid-template-columns: 260px minmax(0, 1fr); gap: 32px; padding: 28px 0; border-top: 1px solid var(--line); }
    .seccion:first-of-type { border-top: 0; padding-top: 4px; }
    .seccion h2 { font-size: 15.5px; font-weight: 600; margin: 0 0 4px; }
    .seccion p { color: var(--muted); font-size: 14px; margin: 0; }
    .barra-acciones { position: sticky; bottom: 0; z-index: 5; margin: 8px -36px -64px; padding: 14px 36px; background: rgba(244,245,247,.92);
                      backdrop-filter: blur(6px); border-top: 1px solid var(--line); display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
    @media (max-width: 991.98px) { .seccion { grid-template-columns: minmax(0, 1fr); gap: 14px; } .barra-acciones { margin: 8px -16px -48px; padding: 12px 16px; } }
</style>
@endpush

@section('contenido')
<div class="migas">
    <a href="{{ route('admin.ventas.index') }}">Ventas</a> <i class="bi bi-chevron-right small"></i>
    @unless($nuevo)<a href="{{ route('admin.ventas.show', $p) }}">{{ $p->quien }}</a> <i class="bi bi-chevron-right small"></i>@endunless
    <span>{{ $nuevo ? 'Nuevo' : 'Editar' }}</span>
</div>
<div class="page-head"><h1>{{ $nuevo ? 'Nuevo prospecto' : 'Editar prospecto' }}</h1></div>

<form method="post" action="{{ $nuevo ? route('admin.ventas.store') : route('admin.ventas.update', $p) }}">
    @csrf
    @unless($nuevo) @method('put') @endunless

    <div class="seccion">
        <div><h2>Contacto</h2><p>Quién es y cómo localizarlo.</p></div>
        <div class="panel panel-body row g-3 mx-0">
            <div class="col-md-6">
                <label class="form-label" for="nombre">Nombre</label>
                <input name="nombre" id="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre', $p->nombre) }}" required autofocus maxlength="160">
                @error('nombre')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="empresa">Empresa <span class="text-secondary fw-normal">(opcional)</span></label>
                <input name="empresa" id="empresa" class="form-control" value="{{ old('empresa', $p->empresa) }}" maxlength="160">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="telefono">Teléfono / WhatsApp</label>
                <input name="telefono" id="telefono" class="form-control num" inputmode="tel" value="{{ old('telefono', $p->telefono) }}" maxlength="40">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="email">Correo</label>
                <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $p->email) }}" maxlength="160">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label class="form-label" for="origen">¿De dónde llegó?</label>
                <select name="origen" id="origen" class="form-select">
                    @foreach(\App\Models\Prospecto::ORIGENES as $k => $o)<option value="{{ $k }}" @selected(old('origen', $p->origen) === $k)>{{ $o['texto'] }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="interes">Le interesa</label>
                <input name="interes" id="interes" class="form-control" list="servicios" value="{{ old('interes', $p->interes) }}" maxlength="160">
                <datalist id="servicios">@foreach($servicios as $s)<option value="{{ $s }}">@endforeach</datalist>
            </div>
        </div>
    </div>

    <div class="seccion">
        <div><h2>Venta</h2><p>Cuánto podría valer y en qué paso va. El valor cuenta en el embudo y el pronóstico.</p></div>
        <div class="panel panel-body row g-3 mx-0">
            <div class="col-md-4">
                <label class="form-label" for="valor">Valor estimado <span class="text-secondary fw-normal">(antes de IVA)</span></label>
                <div class="input-group"><span class="input-group-text">$</span><input type="number" step="0.01" min="0" name="valor" id="valor" class="form-control num" value="{{ old('valor', $p->valor) }}"></div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="etapa_id">Etapa</label>
                <select name="etapa_id" id="etapa_id" class="form-select">
                    @foreach($etapas as $e)<option value="{{ $e->id }}" @selected((string) old('etapa_id', $p->etapa_id) === (string) $e->id)>{{ $e->nombre }}{{ $e->tipo === 'abierta' ? ' · ' . $e->probabilidad . '%' : '' }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="responsable_id">Responsable</label>
                <select name="responsable_id" id="responsable_id" class="form-select">
                    <option value="">Sin asignar</option>
                    @foreach($equipo as $u)<option value="{{ $u->id }}" @selected((string) old('responsable_id', $p->responsable_id) === (string) $u->id)>{{ $u->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="notas">Notas</label>
                <textarea name="notas" id="notas" rows="3" class="form-control" maxlength="5000">{{ old('notas', $p->notas) }}</textarea>
            </div>
        </div>
    </div>

    <div class="barra-acciones">
        <a href="{{ $nuevo ? route('admin.ventas.index') : route('admin.ventas.show', $p) }}" class="btn btn-borde">Cancelar</a>
        <button class="btn btn-primario">{{ $nuevo ? 'Guardar prospecto' : 'Guardar cambios' }}</button>
    </div>
</form>
@endsection
