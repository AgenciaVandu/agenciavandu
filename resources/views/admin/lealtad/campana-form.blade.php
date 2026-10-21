@extends('admin.layout')
@section('titulo', $k->exists ? 'Editar campaña' : 'Nueva campaña')
@include('admin.lealtad._estilos')

@php
    $lista = old('premios', $premios);
@endphp

@section('contenido')
<div class="migas"><a href="{{ route('admin.lealtad.campanas') }}">Campañas</a> <i class="bi bi-chevron-right small"></i> <span>{{ $k->exists ? $k->nombre : 'Nueva' }}</span></div>
<div class="page-head"><div><h1>{{ $k->exists ? 'Editar campaña' : 'Nueva campaña' }}</h1><p class="sub">Las visitas cuentan entre las dos fechas. Los premios se desbloquean al llegar a su número de visitas.</p></div></div>

<form method="post" action="{{ $k->exists ? route('admin.lealtad.campanas.update', $k) : route('admin.lealtad.campanas.store') }}" style="max-width: 860px"
      x-data="{ meta: {{ (int) old('meta_visitas', $k->meta_visitas) }}, premios: {{ Js::from(array_values($lista)) }},
                agregar() { const ult = this.premios.length ? Math.max(...this.premios.map(p => +p.visitas || 0)) : 0; this.premios.push({ visitas: Math.min(this.meta, ult + 2 || 1), nombre: '', descripcion: '' }); },
                quitar(i) { this.premios.splice(i, 1); } }">
    @csrf @if($k->exists) @method('put') @endif
    <section class="panel mb-4">
        <div class="panel-body">
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label" for="c-nombre">Nombre</label>
                    <input id="c-nombre" name="nombre" class="form-control" value="{{ old('nombre', $k->nombre) }}" required maxlength="120" placeholder="Temporada navideña">
                    @error('nombre')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                <div class="col-md-4"><label class="form-label" for="c-meta">Meta de visitas</label>
                    <input id="c-meta" name="meta_visitas" type="number" min="1" max="200" class="form-control num" x-model.number="meta" required></div>
                <div class="col-12"><label class="form-label" for="c-desc">Descripción <span class="secundario fw-normal">(la ven tus comensales)</span></label>
                    <input id="c-desc" name="descripcion" class="form-control" value="{{ old('descripcion', $k->descripcion) }}" maxlength="600" placeholder="Junta 10 visitas y gana una cena para dos."></div>
                <div class="col-sm-4"><label class="form-label" for="c-ini">Empieza</label>
                    <input id="c-ini" name="inicia_el" type="date" class="form-control num" value="{{ old('inicia_el', $k->inicia_el?->toDateString()) }}" required></div>
                <div class="col-sm-4"><label class="form-label" for="c-fin">Termina</label>
                    <input id="c-fin" name="termina_el" type="date" class="form-control num" value="{{ old('termina_el', $k->termina_el?->toDateString()) }}" required>
                    @error('termina_el')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                <div class="col-sm-4"><label class="form-label" for="c-canje">Días para canjear después</label>
                    <input id="c-canje" name="dias_canje" type="number" min="0" max="180" class="form-control num" value="{{ old('dias_canje', $k->dias_canje) }}" required></div>
            </div>
        </div>
    </section>

    <section class="panel mb-4">
        <div class="panel-head"><div><h2>Premios</h2><span class="ayuda">Se desbloquean conforme suman visitas dentro de la campaña</span></div>
            <button type="button" class="btn btn-borde btn-sm" @click="agregar()"><i class="bi bi-plus-lg me-1"></i> Agregar premio</button></div>
        <div class="panel-body d-grid gap-3">
            @error('premios')<div class="text-danger small">{{ $message }}</div>@enderror
            @if($errors->has('premios.*'))<div class="text-danger small">{{ collect($errors->get('premios.*'))->flatten()->first() }}</div>@endif
            <div class="lk-camino" aria-hidden="true">
                <template x-for="i in Math.min(30, meta)" :key="i"><span class="p" :class="{ on: premios.some(p => +p.visitas === i) }"></span></template>
            </div>
            <template x-for="(p, i) in premios" :key="i">
                <div class="d-flex flex-wrap gap-2 align-items-start">
                    <input type="hidden" :name="'premios[' + i + '][id]'" :value="p.id || ''">
                    <div style="width: 120px"><label class="form-label small">En la visita</label>
                        <input type="number" min="1" :max="meta" class="form-control num" :name="'premios[' + i + '][visitas]'" x-model.number="p.visitas" required></div>
                    <div class="flex-grow-1" style="min-width: 200px"><label class="form-label small">Premio</label>
                        <input class="form-control" :name="'premios[' + i + '][nombre]'" x-model="p.nombre" maxlength="120" required placeholder="Postre de la casa"></div>
                    <div class="flex-grow-1" style="min-width: 200px"><label class="form-label small">Detalle <span class="secundario">(opcional)</span></label>
                        <input class="form-control" :name="'premios[' + i + '][descripcion]'" x-model="p.descripcion" maxlength="300" placeholder="Válido de lunes a jueves"></div>
                    <button type="button" class="btn btn-fantasma text-danger" style="margin-top: 26px" @click="quitar(i)" :disabled="premios.length === 1" aria-label="Quitar premio"><i class="bi bi-trash"></i></button>
                </div>
            </template>
        </div>
    </section>

    <div class="d-flex gap-2">
        <button class="btn btn-primario px-4"><i class="bi bi-check2 me-1"></i> {{ $k->exists ? 'Guardar' : 'Crear campaña' }}</button>
        <a href="{{ route('admin.lealtad.campanas') }}" class="btn btn-borde">Cancelar</a>
        @if($k->exists)
            <button type="submit" form="borrar-campana" class="btn btn-fantasma text-danger ms-auto">Eliminar</button>
        @endif
    </div>
</form>
@if($k->exists)
    <form id="borrar-campana" method="post" action="{{ route('admin.lealtad.campanas.destroy', $k) }}" onsubmit="return confirm('¿Eliminar esta campaña y sus premios?')">@csrf @method('delete')</form>
@endif
@endsection
