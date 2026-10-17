@extends('admin.layout')
@php $nuevo = ! $u->exists; @endphp
@section('titulo', $nuevo ? 'Nueva unidad' : 'Editar ' . $u->nombre)

@push('head')
<style>
    .seccion { display: grid; grid-template-columns: 240px minmax(0, 1fr); gap: 32px; padding: 28px 0; border-top: 1px solid var(--line); }
    .seccion:first-of-type { border-top: 0; padding-top: 4px; }
    .seccion h2 { font-size: 15.5px; font-weight: 600; margin: 0 0 4px; }
    .seccion > div > p { color: var(--muted); font-size: 14px; margin: 0; }
    .barra-acciones { position: sticky; bottom: 0; z-index: 5; margin: 8px -36px -64px; padding: 14px 36px; background: rgba(244,245,247,.92);
                      backdrop-filter: blur(6px); border-top: 1px solid var(--line); display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
    .fotos-u { display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; }
    .fotos-u .ft { position: relative; aspect-ratio: 4 / 3; border-radius: 10px; overflow: hidden; background: var(--sunken); border: 1px solid var(--line); }
    .fotos-u .ft img { width: 100%; height: 100%; object-fit: cover; }
    .fotos-u .ft .portada { position: absolute; left: 6px; top: 6px; font-size: 11px; background: var(--ink); color: #fff; border-radius: 99px; padding: 1px 8px; }
    .fotos-u .ft .acc { position: absolute; right: 6px; top: 6px; display: flex; gap: 4px; }
    .fotos-u .ft .acc button { width: 28px; height: 28px; border-radius: 8px; border: 0; background: rgba(255,255,255,.92); display: grid; place-items: center; }
    .subir-fotos { display: grid; place-items: center; text-align: center; aspect-ratio: 4 / 3; border: 2px dashed var(--line); border-radius: 10px; cursor: pointer; color: var(--muted); font-size: 13px; }
    .subir-fotos:hover { border-color: var(--ink); color: var(--ink); }
    .subir-fotos i { font-size: 22px; display: block; }
    @media (max-width: 991.98px) { .seccion { grid-template-columns: minmax(0, 1fr); gap: 14px; } .barra-acciones { margin: 8px -16px -48px; padding: 12px 16px; } }
</style>
@endpush

@section('contenido')
<div class="migas">
    <a href="{{ route('admin.unidades.index') }}">Unidades</a> <i class="bi bi-chevron-right small"></i>
    @unless($nuevo)<a href="{{ route('admin.unidades.show', $u) }}">{{ $u->nombre }}</a> <i class="bi bi-chevron-right small"></i>@endunless
    <span>{{ $nuevo ? 'Nueva' : 'Editar' }}</span>
</div>
<div class="page-head"><h1>{{ $nuevo ? 'Nueva unidad' : 'Editar unidad' }}</h1></div>

<form method="post" enctype="multipart/form-data" action="{{ $nuevo ? route('admin.unidades.store') : route('admin.unidades.update', $u) }}"
      x-data="{ nuevas: [], elegir(e) { this.nuevas = [...e.target.files].map((f) => URL.createObjectURL(f)); } }">
    @csrf
    @unless($nuevo) @method('put') @endunless

    <div class="seccion">
        <div><h2>Fotos</h2><p>La primera es la portada. Toma la de frente en tres cuartos, interiores, tablero y llantas.</p></div>
        <div class="panel panel-body">
            <div class="fotos-u">
                @foreach($u->fotos ?? [] as $i => $f)
                    <div class="ft">
                        <img src="{{ $f->miniatura_url }}" alt="Foto {{ $i + 1 }}">
                        @if($i === 0)<span class="portada">Portada</span>@endif
                        <div class="acc">
                            @if($i > 0)<button type="submit" form="portada-{{ $f->id }}" title="Usar como portada" aria-label="Usar como portada"><i class="bi bi-star"></i></button>@endif
                            <button type="submit" form="quitar-{{ $f->id }}" title="Quitar" aria-label="Quitar foto"><i class="bi bi-trash text-danger"></i></button>
                        </div>
                    </div>
                @endforeach
                <template x-for="src in nuevas" :key="src"><div class="ft"><img :src="src" alt=""><span class="portada" style="background: var(--blue)">Nueva</span></div></template>
                <label class="subir-fotos"><span><i class="bi bi-camera"></i> Agregar fotos</span>
                    <input type="file" name="fotos[]" accept="image/jpeg,image/png,image/webp" multiple class="visually-hidden" @change="elegir"></label>
            </div>
            <p class="secundario small mt-2 mb-0">Hasta 20 a la vez, JPG, PNG o WebP. Las reducimos para que la página cargue rápido.</p>
        </div>
    </div>

    <div class="seccion">
        <div><h2>Datos del auto</h2><p>Así aparece en su página y al venderlo.</p></div>
        <div class="panel panel-body row g-3 mx-0">
            <div class="col-md-4"><label class="form-label" for="marca">Marca</label>
                <input id="marca" name="marca" class="form-control" value="{{ old('marca', $u->marca) }}" required maxlength="60" list="marcas" placeholder="Nissan" @if($nuevo) autofocus @endif>
                <datalist id="marcas">@foreach($marcas->merge(['Chevrolet', 'Nissan', 'Volkswagen', 'Toyota', 'Honda', 'Mazda', 'Kia', 'Hyundai', 'Ford', 'Seat', 'Renault', 'Suzuki', 'Mitsubishi', 'Jeep', 'Dodge', 'RAM', 'BMW', 'Mercedes-Benz', 'Audi', 'MG', 'BYD'])->unique()->sort() as $m)<option value="{{ $m }}">@endforeach</datalist></div>
            <div class="col-md-4"><label class="form-label" for="modelo">Modelo</label><input id="modelo" name="modelo" class="form-control" value="{{ old('modelo', $u->modelo) }}" required maxlength="80" placeholder="Versa"></div>
            <div class="col-md-4"><label class="form-label" for="version">Versión</label><input id="version" name="version" class="form-control" value="{{ old('version', $u->version) }}" maxlength="80" placeholder="Advance"></div>
            <div class="col-6 col-md-3"><label class="form-label" for="anio">Año</label><input id="anio" name="anio" type="number" class="form-control num" value="{{ old('anio', $u->anio) }}" min="1950" max="{{ now()->year + 1 }}"></div>
            <div class="col-6 col-md-3"><label class="form-label" for="tipo">Tipo</label>
                <select id="tipo" name="tipo" class="form-select"><option value="">—</option>@foreach(\App\Models\Unidad::TIPOS as $t)<option @selected(old('tipo', $u->tipo) === $t)>{{ $t }}</option>@endforeach</select></div>
            <div class="col-6 col-md-3"><label class="form-label" for="color">Color</label><input id="color" name="color" class="form-control" value="{{ old('color', $u->color) }}" maxlength="40"></div>
            <div class="col-6 col-md-3"><label class="form-label" for="km">Kilometraje</label><input id="km" name="kilometraje" type="number" min="0" class="form-control num" value="{{ old('kilometraje', $u->kilometraje) }}"></div>
            <div class="col-6 col-md-3"><label class="form-label" for="trans">Transmisión</label>
                <select id="trans" name="transmision" class="form-select"><option value="">—</option>@foreach(\App\Models\Unidad::TRANSMISIONES as $t)<option @selected(old('transmision', $u->transmision) === $t)>{{ $t }}</option>@endforeach</select></div>
            <div class="col-6 col-md-3"><label class="form-label" for="comb">Combustible</label>
                <select id="comb" name="combustible" class="form-select"><option value="">—</option>@foreach(\App\Models\Unidad::COMBUSTIBLES as $t)<option @selected(old('combustible', $u->combustible) === $t)>{{ $t }}</option>@endforeach</select></div>
            <div class="col-6 col-md-3"><label class="form-label" for="placas">Placas</label><input id="placas" name="placas" class="form-control text-uppercase num" value="{{ old('placas', $u->placas) }}" maxlength="20"></div>
            <div class="col-6 col-md-3"><label class="form-label" for="serie">No. de serie</label><input id="serie" name="serie" class="form-control text-uppercase num" value="{{ old('serie', $u->serie) }}" maxlength="40"></div>
            <div class="col-12"><label class="form-label" for="desc">Descripción</label>
                <textarea id="desc" name="descripcion" rows="3" class="form-control" maxlength="5000" placeholder="Único dueño, servicios de agencia, llantas nuevas…">{{ old('descripcion', $u->descripcion) }}</textarea></div>
            <div class="col-12"><label class="form-label" for="ficha">Ficha técnica <span class="secundario fw-normal">(un dato por línea)</span></label>
                <textarea id="ficha" name="ficha" rows="5" class="form-control" maxlength="5000" placeholder="Motor: 1.6 L&#10;Aire acondicionado&#10;Pantalla con Apple CarPlay&#10;Bolsas de aire: 6">{{ old('ficha', $u->ficha) }}</textarea></div>
        </div>
    </div>

    <div class="seccion">
        <div><h2>Precio y venta</h2><p>El precio sale en su página con el "desde $X al mes" según tus condiciones de crédito.</p></div>
        <div class="panel panel-body row g-3 mx-0">
            <div class="col-md-4"><label class="form-label" for="precio">Precio de venta</label>
                <div class="input-group"><span class="input-group-text">$</span><input id="precio" name="precio" type="number" step="0.01" min="0" class="form-control num" value="{{ old('precio', $u->precio ?: '') }}" required></div></div>
            @if($veCosto)
                <div class="col-md-4"><label class="form-label" for="costo">Costo <span class="secundario fw-normal">(solo lo ves tú)</span></label>
                    <div class="input-group"><span class="input-group-text">$</span><input id="costo" name="costo" type="number" step="0.01" min="0" class="form-control num" value="{{ old('costo', $u->costo) }}"></div></div>
            @endif
            <div class="col-md-4"><label class="form-label" for="estado">Estado</label>
                @if($u->estado === 'vendido')
                    <div class="form-control-plaintext"><span class="estado estado-pagada">Vendido</span></div>
                @else
                    <select id="estado" name="estado" class="form-select"><option value="disponible" @selected(old('estado', $u->estado) === 'disponible')>Disponible</option><option value="apartado" @selected(old('estado', $u->estado) === 'apartado')>Apartado</option></select>
                @endif
            </div>
            @if($sucursales->isNotEmpty())
                <div class="col-md-6"><label class="form-label" for="suc">Sucursal</label>
                    <select id="suc" name="sucursal_id" class="form-select"><option value="">Sin sucursal</option>@foreach($sucursales as $s)<option value="{{ $s->id }}" @selected((int) old('sucursal_id', $u->sucursal_id) === $s->id)>{{ $s->nombre }}</option>@endforeach</select></div>
            @endif
            <div class="col-md-6"><label class="form-label" for="vend">Vendedor asignado</label>
                <select id="vend" name="vendedor_id" class="form-select"><option value="">Nadie</option>@foreach($vendedores as $v)<option value="{{ $v->id }}" @selected((int) old('vendedor_id', $u->vendedor_id) === $v->id)>{{ $v->name }}</option>@endforeach</select>
                <div class="form-text">El botón de WhatsApp de su página le escribe a él.</div></div>
            <div class="col-12"><label class="d-flex gap-2 align-items-center m-0"><input type="hidden" name="publico" value="0"><input type="checkbox" class="form-check-input m-0" name="publico" value="1" @checked(old('publico', $u->publico ?? true))> Mostrar su página pública</label></div>
        </div>
    </div>

    <div class="barra-acciones">
        @unless($nuevo)<button type="submit" form="eliminar" class="btn btn-fantasma text-danger me-auto"><i class="bi bi-trash me-1"></i> Eliminar</button>@endunless
        <a href="{{ $nuevo ? route('admin.unidades.index') : route('admin.unidades.show', $u) }}" class="btn btn-borde">Cancelar</a>
        <button class="btn btn-primario">{{ $nuevo ? 'Guardar unidad' : 'Guardar cambios' }}</button>
    </div>
</form>

@unless($nuevo)
    <form id="eliminar" method="post" action="{{ route('admin.unidades.destroy', $u) }}" onsubmit="return confirm('¿Eliminar esta unidad y sus fotos?')">@csrf @method('delete')</form>
    @foreach($u->fotos as $i => $f)
        <form id="quitar-{{ $f->id }}" method="post" action="{{ route('admin.unidades.foto.quitar', [$u, $f]) }}">@csrf @method('delete')</form>
        <form id="portada-{{ $f->id }}" method="post" action="{{ route('admin.unidades.fotos.orden', $u) }}">@csrf
            @foreach($u->fotos->sortBy(fn ($x) => $x->id === $f->id ? -1 : $x->orden) as $x)<input type="hidden" name="orden[]" value="{{ $x->id }}">@endforeach</form>
    @endforeach
@endunless
@endsection
