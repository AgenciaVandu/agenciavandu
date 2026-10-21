<div class="fx-grid-form">
    <div style="grid-column: 1 / -1"><label class="form-label small" for="{{ $pre }}-n">Nombre</label>
        <input id="{{ $pre }}-n" name="nombre" class="form-control" value="{{ $e?->nombre }}" required maxlength="100" placeholder="Sled Push"></div>
    <div><label class="form-label small" for="{{ $pre }}-c">Categoría</label>
        <input id="{{ $pre }}-c" name="categoria" list="categorias-ej" class="form-control" value="{{ $e?->categoria }}" maxlength="60" placeholder="Estación HYROX"></div>
    <div><label class="form-label small" for="{{ $pre }}-u">Se mide en</label>
        <select id="{{ $pre }}-u" name="unidad" class="form-select">@foreach(\App\Models\Ejercicio::UNIDADES as $k => $l)<option value="{{ $k }}" @selected(($e?->unidad ?? 'reps') === $k)>{{ $l }}</option>@endforeach</select></div>
</div>
<div class="mt-2"><label class="form-label small" for="{{ $pre }}-i">Instrucciones</label>
    <textarea id="{{ $pre }}-i" name="instrucciones" rows="2" class="form-control" maxlength="2000" placeholder="Técnica, peso sugerido, escalamiento…">{{ $e?->instrucciones }}</textarea></div>
