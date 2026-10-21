{{-- Campos de un tipo de clase. $t = ClaseTipo|null, $pre = prefijo de ids --}}
<div class="fx-grid-form">
    <div style="grid-column: span 2"><label class="form-label small" for="{{ $pre }}-n">Nombre</label>
        <input id="{{ $pre }}-n" name="nombre" class="form-control" value="{{ $t?->nombre }}" required maxlength="100" placeholder="HYROX Engine"></div>
    <div><label class="form-label small" for="{{ $pre }}-d">Duración</label>
        <div class="input-group"><input id="{{ $pre }}-d" name="duracion" type="number" min="10" max="480" class="form-control num" value="{{ $t?->duracion ?? 60 }}" required><span class="input-group-text">min</span></div></div>
    <div><label class="form-label small" for="{{ $pre }}-c">Cupo</label>
        <div class="input-group"><input id="{{ $pre }}-c" name="cupo" type="number" min="1" max="500" class="form-control num" value="{{ $t?->cupo ?? 12 }}" required><span class="input-group-text">lugares</span></div></div>
</div>
<div class="mt-2"><label class="form-label small" for="{{ $pre }}-de">Descripción</label>
    <textarea id="{{ $pre }}-de" name="descripcion" rows="2" class="form-control" maxlength="1000" placeholder="Qué se trabaja y para quién es">{{ $t?->descripcion }}</textarea></div>
<div class="d-flex flex-wrap align-items-center gap-3 mt-2" x-data="{ color: @js($t?->color ?? \App\Models\ClaseTipo::COLORES[array_rand(\App\Models\ClaseTipo::COLORES)]) }">
    <span class="form-label small m-0">Color</span>
    <div class="d-flex gap-1 flex-wrap" role="radiogroup" aria-label="Color">
        @foreach(\App\Models\ClaseTipo::COLORES as $col)
            <button type="button" class="btn p-0 border-0" style="width:26px;height:26px;border-radius:8px;background:{{ $col }}" :style="color === '{{ $col }}' ? 'outline: 3px solid var(--green); outline-offset: 2px' : ''" @click="color = '{{ $col }}'" aria-label="Color {{ $col }}"></button>
        @endforeach
        <input type="color" x-model="color" style="width:26px;height:26px;padding:0;border:1px solid var(--line);border-radius:8px" aria-label="Otro color">
    </div>
    <input type="hidden" name="color" :value="color">
    <label class="form-check ms-auto m-0"><input type="checkbox" name="activo" value="1" class="form-check-input" @checked($t?->activo ?? true)> <span class="form-check-label small">Se puede programar</span></label>
</div>
