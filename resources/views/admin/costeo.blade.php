@extends('admin.layout')
@section('titulo', 'Costeo')

@php
    use App\Support\Costeo;
    $inicial = ['utilidad' => old('utilidad', $utilidad), 'componentes' => old('componentes', $componentes)];
@endphp

@push('head')
<style>
    .cs-grid { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 20px; align-items: start; }
    .cs-fila { display: grid; grid-template-columns: minmax(0, 1fr) 170px 180px 74px; gap: 10px; align-items: center; padding: 12px 20px; border-top: 1px solid var(--line); }
    .cs-cab { display: grid; grid-template-columns: minmax(0, 1fr) 170px 180px 74px; gap: 10px; padding: 10px 20px; font-size: 12.5px; color: var(--muted); background: var(--sunken); }
    .cs-fila .acc { display: flex; gap: 2px; justify-content: flex-end; }
    .cs-fila .acc button { border: 0; background: none; width: 30px; height: 32px; border-radius: 7px; color: var(--text-2); }
    .cs-fila .acc button:hover:not(:disabled) { background: var(--sunken); color: var(--text); }
    .cs-fila .acc button:disabled { opacity: .3; }
    .cs-ej { font-size: 13.5px; display: grid; gap: 6px; }
    .cs-ej div { display: flex; justify-content: space-between; gap: 10px; }
    .cs-ej .t { border-top: 1px solid var(--line); padding-top: 6px; font-weight: 600; }
    .cs-ayuda dt { font-weight: 600; font-size: 13.5px; }
    .cs-ayuda dd { color: var(--muted); font-size: 13px; margin: 2px 0 12px; }
    @media (max-width: 991.98px) {
        .cs-grid { grid-template-columns: minmax(0, 1fr); }
        .cs-cab { display: none; }
        .cs-fila { grid-template-columns: 1fr 1fr; }
        .cs-fila .nom { grid-column: 1 / -1; }
        .cs-fila .acc { grid-column: 1 / -1; justify-content: flex-start; }
    }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <h1>Costeo</h1>
        <p class="sub">Los costos que capturas al cotizar. Con ellos sale el precio sugerido, tu utilidad y el margen en Finanzas.</p>
    </div>
</div>

<form method="post" action="{{ route('admin.costeo.update') }}" x-data="costeoNegocio({{ Js::from($inicial) }})">
    @csrf @method('put')
    <div class="cs-grid">
        <div class="d-grid gap-4">
            <section class="panel">
                <div class="panel-head"><h2>Costos de tu negocio</h2><span class="ayuda" x-text="componentes.length + (componentes.length === 1 ? ' costo' : ' costos')"></span></div>
                <div class="cs-cab" aria-hidden="true"><span>Nombre</span><span>Cómo se calcula</span><span>Tipo de gasto</span><span></span></div>
                <template x-for="(k, i) in componentes" :key="i">
                    <div class="cs-fila">
                        <div class="nom">
                            <label class="visually-hidden" :for="'nom'+i">Nombre</label>
                            <input class="form-control" :id="'nom'+i" :name="`componentes[${i}][nombre]`" x-model="k.nombre" maxlength="60" required>
                            <input type="hidden" :name="`componentes[${i}][clave]`" :value="k.clave || ''">
                        </div>
                        <div>
                            <label class="visually-hidden" :for="'modo'+i">Cómo se calcula</label>
                            <select class="form-select" :id="'modo'+i" :name="`componentes[${i}][modo]`" x-model="k.modo">
                                @foreach(Costeo::MODOS as $m => $info)<option value="{{ $m }}">{{ $info['texto'] }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="visually-hidden" :for="'clase'+i">Tipo de gasto</label>
                            <select class="form-select" :id="'clase'+i" :name="`componentes[${i}][clase]`" x-model="k.clase">
                                @foreach(Costeo::CLASES as $m => $info)<option value="{{ $m }}">{{ $info['texto'] }}</option>@endforeach
                            </select>
                        </div>
                        <div class="acc">
                            <button type="button" @click="mover(i, -1)" :disabled="i === 0" :aria-label="'Subir ' + k.nombre"><i class="bi bi-arrow-up"></i></button>
                            <button type="button" @click="componentes.splice(i, 1)" :disabled="componentes.length === 1" :aria-label="'Quitar ' + k.nombre"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </template>
                <div class="panel-body border-top">
                    <button type="button" class="btn btn-borde btn-sm" @click="componentes.push({ clave: '', nombre: '', modo: 'fijo', clase: 'operativo' }); $nextTick(() => document.getElementById('nom' + (componentes.length - 1)).focus())"><i class="bi bi-plus-lg me-1"></i> Agregar costo</button>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head"><h2>Utilidad por defecto</h2></div>
                <div class="panel-body d-flex flex-wrap gap-3 align-items-center">
                    <div class="input-group" style="max-width: 150px">
                        <input type="number" step="0.1" min="0" name="utilidad" class="form-control num" x-model.number="utilidad" aria-label="Utilidad por defecto" required>
                        <span class="input-group-text">%</span>
                    </div>
                    <span class="secundario" style="font-size: 13.5px">Sobre el costo. Se propone al costear un concepto y la ajustas en cada cotización.</span>
                </div>
            </section>

            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primario">Guardar costeo</button>
                @if($propio)
                    <button type="submit" form="restaurar" class="btn btn-fantasma">Restaurar los de fábrica</button>
                @endif
            </div>
        </div>

        <aside class="d-grid gap-4">
            <section class="panel">
                <div class="panel-head"><h2>Ejemplo</h2></div>
                <div class="panel-body cs-ej num">
                    <div class="secundario" style="font-size: 12.5px">10 piezas con estos costos:</div>
                    <template x-for="k in componentes.slice(0, 4)" :key="k.nombre">
                        <div><span x-text="k.nombre + (k.modo === 'unidad' ? ' · $100 c/u' : ' · $300')"></span><span x-text="dinero(k.modo === 'unidad' ? 1000 : 300)"></span></div>
                    </template>
                    <div class="t"><span>Costo</span><span x-text="dinero(costoEj)"></span></div>
                    <div><span x-text="'Utilidad ' + (utilidad || 0) + '%'"></span><span x-text="dinero(costoEj * (utilidad || 0) / 100)"></span></div>
                    <div class="t"><span>Precio sugerido</span><span x-text="dinero(costoEj * (1 + (utilidad || 0) / 100))"></span></div>
                </div>
            </section>
            <section class="panel">
                <dl class="panel-body cs-ayuda m-0">
                    @foreach(Costeo::MODOS as $info)<dt>{{ $info['texto'] }}</dt><dd>{{ $info['ayuda'] }}</dd>@endforeach
                    @foreach(Costeo::CLASES as $info)<dt>{{ $info['texto'] }}</dt><dd>{{ $info['ayuda'] }}</dd>@endforeach
                    <dd class="mb-0">Cambiar estos costos no altera las cotizaciones que ya hiciste.</dd>
                </dl>
            </section>
        </aside>
    </div>
</form>
<form id="restaurar" method="post" action="{{ route('admin.costeo.restaurar') }}" onsubmit="return confirm('¿Volver a los costos de fábrica? Se pierden los cambios que hiciste aquí.')">@csrf</form>
@endsection

@push('scripts')
<script>
function costeoNegocio(init) {
    return {
        ...init,
        mover(i, d) { const [x] = this.componentes.splice(i, 1); this.componentes.splice(i + d, 0, x); },
        get costoEj() { return this.componentes.slice(0, 4).reduce((s, k) => s + (k.modo === 'unidad' ? 1000 : 300), 0); },
        dinero(n) { return '$' + (+n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
    };
}
</script>
@endpush
