@php $esta = $s && old('_solicitud') == $s->id || ! $s && old('_solicitud') === 'nueva'; @endphp
<div><label class="form-label" for="{{ $pre }}-titulo">¿Qué necesitas?</label>
    <input name="titulo" id="{{ $pre }}-titulo" class="form-control @if($esta) @error('titulo') is-invalid @enderror @endif" maxlength="160" required
           value="{{ $esta ? old('titulo') : $s?->titulo }}" placeholder="Ej. Logotipo en vector y fotos del equipo">
    @if($esta) @error('titulo')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif</div>
<div><label class="form-label" for="{{ $pre }}-inst">Instrucciones <span class="secundario">(opcional)</span></label>
    <textarea name="instrucciones" id="{{ $pre }}-inst" rows="2" class="form-control" maxlength="2000" placeholder="Formato, tamaño, cuántos…">{{ $esta ? old('instrucciones') : $s?->instrucciones }}</textarea></div>
<div style="max-width:220px"><label class="form-label" for="{{ $pre }}-fecha">Para cuándo <span class="secundario">(opcional)</span></label>
    <input type="date" name="fecha_limite" id="{{ $pre }}-fecha" class="form-control" value="{{ $esta ? old('fecha_limite') : $s?->fecha_limite?->format('Y-m-d') }}"></div>
