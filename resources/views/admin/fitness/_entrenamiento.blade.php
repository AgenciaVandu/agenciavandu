{{-- Armador del entrenamiento: bloques (calentamiento, WOD…) con sus ejercicios. Se guarda como JSON en "entrenamiento". --}}
@php
    $bloques = old('entrenamiento') ? json_decode(old('entrenamiento'), true) : ($bloques ?? []);
    $bloques = $bloques ?: [];
@endphp
<div x-data="fxEntrenamiento({{ Js::from(['bloques' => $bloques, 'ejercicios' => $ejercicios]) }})" class="entreno">
    <input type="hidden" name="entrenamiento" :value="JSON.stringify(bloques)">
    <datalist id="lista-ejercicios"><template x-for="e in ejercicios" :key="e.id"><option :value="e.nombre"></option></template></datalist>
    <template x-for="(b, i) in bloques" :key="i">
        <div class="bloque">
            <div class="d-flex gap-2 align-items-center mb-2">
                <input class="form-control form-control-sm fw-semibold" x-model="b.nombre" placeholder="Bloque (Calentamiento, WOD, Fuerza…)" aria-label="Nombre del bloque" maxlength="80">
                <input class="form-control form-control-sm" style="max-width: 200px" x-model="b.formato" placeholder="Formato (AMRAP 20', 3 rondas…)" aria-label="Formato" maxlength="80">
                <button type="button" class="btn btn-fantasma btn-icono btn-sm text-danger" @click="bloques.splice(i, 1)" aria-label="Quitar bloque"><i class="bi bi-trash"></i></button>
            </div>
            <template x-for="(e, j) in b.ejercicios" :key="j">
                <div class="ej">
                    <input class="form-control form-control-sm" list="lista-ejercicios" x-model="e.nombre" @change="elegir(e)" placeholder="Ejercicio" aria-label="Ejercicio" maxlength="100">
                    <input class="form-control form-control-sm num" x-model="e.cantidad" :placeholder="unidadDe(e)" aria-label="Cantidad" maxlength="40">
                    <input class="form-control form-control-sm" x-model="e.nota" placeholder="Nota (peso, ritmo…)" aria-label="Nota" maxlength="200">
                    <button type="button" class="btn btn-fantasma btn-icono btn-sm" @click="b.ejercicios.splice(j, 1)" aria-label="Quitar ejercicio"><i class="bi bi-x-lg"></i></button>
                </div>
            </template>
            <button type="button" class="btn btn-link btn-sm px-0" @click="b.ejercicios.push({ ejercicio_id: null, nombre: '', cantidad: '', nota: '' })"><i class="bi bi-plus"></i> Ejercicio</button>
        </div>
    </template>
    <button type="button" class="btn btn-borde btn-sm" @click="bloques.push({ nombre: '', formato: '', ejercicios: [{ ejercicio_id: null, nombre: '', cantidad: '', nota: '' }] })"><i class="bi bi-plus-lg me-1"></i> Agregar bloque</button>
    <span class="secundario small ms-2" x-show="! ejercicios.length">Tip: agrega ejercicios a tu biblioteca para elegirlos aquí.</span>
</div>

@once
@push('head')
<style>
    .entreno .bloque { border: 1px solid var(--line); border-radius: 10px; padding: 10px 12px; margin-bottom: 10px; background: var(--sunken); }
    .entreno .ej { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 2fr) 32px; gap: 6px; margin-bottom: 6px; }
    @media (max-width: 575.98px) { .entreno .ej { grid-template-columns: minmax(0, 1fr) 90px 32px; } .entreno .ej input:nth-child(3) { grid-column: 1 / 3; } }
</style>
@endpush
@push('scripts')
<script>
window.fxEntrenamiento = function (d) {
    const unidades = @json(\App\Models\Ejercicio::UNIDADES);
    return {
        bloques: (d.bloques || []).map((b) => ({ nombre: b.nombre || '', formato: b.formato || '', ejercicios: (b.ejercicios || []).map((e) => ({ ejercicio_id: e.ejercicio_id || null, nombre: e.nombre || '', cantidad: e.cantidad || '', nota: e.nota || '' })) })),
        ejercicios: d.ejercicios || [],
        elegir(e) { const x = this.ejercicios.find((y) => y.nombre.toLowerCase() === (e.nombre || '').toLowerCase()); e.ejercicio_id = x ? x.id : null; },
        unidadDe(e) { const x = this.ejercicios.find((y) => y.id === e.ejercicio_id); return x ? (unidades[x.unidad] || x.unidad) : 'Cantidad'; },
    };
};
</script>
@endpush
@endonce
