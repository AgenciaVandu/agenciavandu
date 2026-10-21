{{-- Campos de un paquete. $p = Paquete|null, $pre = prefijo de ids --}}
@php $mio = $errors->any() && (string) old('_paquete') === (string) ($p?->id ?? 'nuevo'); $v = fn ($k, $def = null) => $mio ? old($k, $def) : ($p?->{$k} ?? $def); @endphp
<div x-data="{ ilimitado: {{ ($mio ? old('ilimitado') : ($p && $p->ilimitado)) ? 'true' : 'false' }} }">
    <div class="fx-grid-form">
        <div style="grid-column: 1 / -1"><label class="form-label small" for="{{ $pre }}-n">Nombre</label>
            <input id="{{ $pre }}-n" name="nombre" class="form-control @if($mio) @error('nombre') is-invalid @enderror @endif" value="{{ $v('nombre') }}" required maxlength="200" placeholder="Paquete 8 clases"></div>
        <div><label class="form-label small" for="{{ $pre }}-p">Precio</label>
            <div class="input-group"><span class="input-group-text">$</span><input id="{{ $pre }}-p" name="precio" type="number" step="0.01" min="0" class="form-control num" value="{{ $v('precio', 0) }}" required></div></div>
        <div><label class="form-label small" for="{{ $pre }}-v">Vigencia</label>
            <div class="input-group"><input id="{{ $pre }}-v" name="vigencia_dias" type="number" min="1" max="3650" class="form-control num" value="{{ $v('vigencia_dias', 30) }}" required><span class="input-group-text">días</span></div></div>
        <div><label class="form-label small" for="{{ $pre }}-c">Clases incluidas</label>
            <input id="{{ $pre }}-c" name="creditos" type="number" min="1" max="1000" class="form-control num @if($mio) @error('creditos') is-invalid @enderror @endif" value="{{ $v('creditos', 8) }}" :disabled="ilimitado" :required="! ilimitado"></div>
        <div class="d-flex align-items-end"><label class="form-check m-0 pb-2"><input type="checkbox" name="ilimitado" value="1" class="form-check-input" x-model="ilimitado"> <span class="form-check-label small">Ilimitado</span></label></div>
    </div>
    @if($mio) @error('creditos')<div class="text-danger small mt-1">{{ $message }}</div>@enderror @endif
    <div class="fx-grid-form mt-2">
        <div><label class="form-label small" for="{{ $pre }}-m">Máximo al día</label>
            <input id="{{ $pre }}-m" name="max_por_dia" type="number" min="1" max="10" class="form-control num" value="{{ $v('max_por_dia') }}" placeholder="Sin límite"></div>
        <div class="d-flex align-items-end"><label class="form-check m-0 pb-2"><input type="checkbox" name="es_prueba" value="1" class="form-check-input" @checked($v('es_prueba'))> <span class="form-check-label small">Clase de prueba (una vez por atleta)</span></label></div>
    </div>
    @if($tipos->isNotEmpty())
        <div class="mt-2"><span class="form-label small d-block">Sirve para <span class="secundario">(si no marcas ninguno, para todas)</span></span>
            <div class="d-flex flex-wrap gap-2">
                @foreach($tipos as $t)
                    <label class="fx-pastilla" style="cursor:pointer"><input type="checkbox" name="tipos_clase[]" value="{{ $t->id }}" class="form-check-input m-0" @checked(in_array($t->id, (array) $v('tipos_clase', []), false))> <span class="pt" style="background: {{ $t->color }}"></span>{{ $t->nombre }}</label>
                @endforeach
            </div></div>
    @endif
    <div class="mt-2"><label class="form-label small" for="{{ $pre }}-d">Descripción</label>
        <textarea id="{{ $pre }}-d" name="descripcion" rows="2" class="form-control" maxlength="1000">{{ $v('descripcion') }}</textarea></div>
    <label class="form-check mt-2"><input type="checkbox" name="activo" value="1" class="form-check-input" @checked($v('activo', true))> <span class="form-check-label small">Se puede vender</span></label>
</div>
