@extends('admin.layout')
@section('titulo', 'Etapas del embudo')

@php
    $abiertas = $etapas->where('tipo', 'abierta')->values();
    $inicial = ['etapas' => old('etapas', $abiertas->map(fn ($e) => ['id' => $e->id, 'nombre' => $e->nombre, 'probabilidad' => $e->probabilidad, 'n' => $e->prospectos_count])->values()->all())];
    $ganada = $etapas->firstWhere('tipo', 'ganada');
    $perdida = $etapas->firstWhere('tipo', 'perdida');
@endphp

@push('head')
<style>
    .et-fila { display: grid; grid-template-columns: 30px minmax(0, 1fr) 150px 90px 74px; gap: 10px; align-items: center; padding: 12px 20px; border-top: 1px solid var(--line); }
    .et-fila .num-et { width: 26px; height: 26px; border-radius: 50%; background: var(--sunken); display: grid; place-items: center; font-size: 12.5px; font-weight: 600; }
    .et-fila .acc { display: flex; gap: 2px; justify-content: flex-end; }
    .et-fila .acc button { border: 0; background: none; width: 30px; height: 32px; border-radius: 7px; color: var(--text-2); }
    .et-fila .acc button:hover:not(:disabled) { background: var(--sunken); color: var(--text); }
    .et-fila .acc button:disabled { opacity: .3; }
    .et-fija { display: grid; grid-template-columns: 30px minmax(0, 1fr) 150px; gap: 10px; align-items: center; padding: 12px 20px; border-top: 1px solid var(--line); }
    @media (max-width: 767.98px) {
        .et-fila { grid-template-columns: 26px minmax(0, 1fr) 96px; }
        .et-fila .n-pros { display: none; }
        .et-fila .acc { grid-column: 2 / -1; justify-content: flex-start; }
        .et-fija { grid-template-columns: 26px minmax(0, 1fr); }
        .et-fija .lbl { grid-column: 2; }
    }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.ventas.index') }}">Ventas</a> <i class="bi bi-chevron-right small"></i> <span>Etapas</span></div>
<div class="page-head">
    <div>
        <h1>Etapas del embudo</h1>
        <p class="sub">Los pasos por los que pasa un prospecto hasta que lo ganas. La probabilidad sirve para el pronóstico de ventas.</p>
    </div>
</div>

<form method="post" action="{{ route('admin.ventas.etapas.guardar') }}" x-data="etapasEmbudo({{ Js::from($inicial) }})" style="max-width: 860px">
    @csrf @method('put')
    <section class="panel">
        <div class="panel-head"><h2>En proceso</h2><span class="ayuda">En este orden aparecen en el tablero</span></div>
        <template x-for="(e, i) in etapas" :key="i">
            <div class="et-fila">
                <span class="num-et" x-text="i + 1"></span>
                <div>
                    <label class="visually-hidden" :for="'et'+i">Nombre</label>
                    <input class="form-control" :id="'et'+i" :name="`etapas[${i}][nombre]`" x-model="e.nombre" maxlength="60" required>
                    <input type="hidden" :name="`etapas[${i}][id]`" :value="e.id || ''">
                </div>
                <div class="input-group">
                    <input type="number" min="0" max="100" class="form-control num" :name="`etapas[${i}][probabilidad]`" x-model.number="e.probabilidad" :aria-label="'Probabilidad de ' + e.nombre" required>
                    <span class="input-group-text">%</span>
                </div>
                <span class="secundario n-pros" style="font-size: 13px" x-text="e.n ? e.n + (e.n === 1 ? ' prospecto' : ' prospectos') : ''"></span>
                <div class="acc">
                    <button type="button" @click="mover(i, -1)" :disabled="i === 0" :aria-label="'Subir ' + e.nombre"><i class="bi bi-arrow-up"></i></button>
                    <button type="button" @click="quitar(i)" :disabled="etapas.length === 1" :aria-label="'Quitar ' + e.nombre"><i class="bi bi-trash"></i></button>
                </div>
            </div>
        </template>
        <div class="panel-body border-top">
            <button type="button" class="btn btn-borde btn-sm" @click="etapas.push({ id: null, nombre: '', probabilidad: 50, n: 0 }); $nextTick(() => document.getElementById('et' + (etapas.length - 1)).focus())"><i class="bi bi-plus-lg me-1"></i> Agregar etapa</button>
        </div>
    </section>

    <section class="panel mt-4">
        <div class="panel-head"><h2>Cierre</h2><span class="ayuda">Siempre existen; puedes cambiarles el nombre</span></div>
        <div class="et-fija">
            <i class="bi bi-trophy" style="color: var(--green-ink); font-size: 18px"></i>
            <input class="form-control" name="ganada" value="{{ old('ganada', $ganada->nombre) }}" maxlength="60" required aria-label="Nombre de la etapa de ganado">
            <span class="secundario lbl" style="font-size: 13px">Se cerró la venta · 100%</span>
        </div>
        <div class="et-fija">
            <i class="bi bi-x-octagon" style="color: var(--red); font-size: 18px"></i>
            <input class="form-control" name="perdida" value="{{ old('perdida', $perdida->nombre) }}" maxlength="60" required aria-label="Nombre de la etapa de perdido">
            <span class="secundario lbl" style="font-size: 13px">No se cerró · 0%</span>
        </div>
    </section>

    <div class="d-flex gap-2 mt-4">
        <button class="btn btn-primario">Guardar embudo</button>
        <a href="{{ route('admin.ventas.index') }}" class="btn btn-borde">Volver a Ventas</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
function etapasEmbudo(init) {
    return {
        ...init,
        mover(i, d) { const [x] = this.etapas.splice(i, 1); this.etapas.splice(i + d, 0, x); },
        quitar(i) {
            const e = this.etapas[i];
            if (e.n && ! confirm(`${e.nombre} tiene ${e.n} ${e.n === 1 ? 'prospecto' : 'prospectos'}. Al quitarla pasan a la primera etapa. ¿Seguir?`)) return;
            this.etapas.splice(i, 1);
        },
    };
}
</script>
@endpush
