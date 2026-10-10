@extends('admin.layout')
@section('titulo', $t->exists ? 'Editar tarea' : 'Nueva tarea')

@php
    $ini = [
        'cliente'  => (string) old('cliente_id', $t->cliente_id ?? ''),
        'proyecto' => (string) old('proyecto_id', $t->proyecto_id ?? ''),
        'titulo'   => old('titulo', $t->titulo ?? ''),
        'carpeta'  => old('carpeta', $t->carpeta ?? ''),
        'manual'   => (bool) old('carpeta', $t->carpeta),
        'proyectos'=> $proyectos->map(fn ($p) => ['id' => (string) $p->id, 'cliente' => (string) $p->cliente_id, 'nombre' => $p->nombre])->values(),
        'bases'    => $bases,
        'explorar' => route('admin.dropbox.explorar'),
        'crear'    => route('admin.tareas.carpeta'),
        'dropbox'  => $dropbox,
        'destino'  => (string) old('destino', $t->destino ?? ''),
        'etapa'    => (string) old('etapa_id', $t->etapa_id ?? ''),
        'completar'=> (bool) old('completar_etapa', $t->completar_etapa),
        'porProyecto' => $etapasPorProyecto,
    ];
@endphp

@push('head')
<style>
    .tf-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    .tf-grid .ancho { grid-column: 1 / -1; }
    .tf-grid textarea { min-height: 120px; }
    .carpeta-campo { display: flex; gap: 8px; }
    .carpeta-campo input { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13.5px; }
    .dest-op { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; }
    .dest-op label { display: flex; gap: 10px; align-items: flex-start; border: 1.5px solid var(--line-strong); border-radius: 12px; padding: 12px; cursor: pointer; }
    .dest-op label:has(input:checked) { border-color: var(--ink); background: var(--sunken); }
    .dest-op input { display: none; }
    .dest-op i { font-size: 18px; margin-top: 1px; }
    .dest-op b { display: block; font-weight: 600; font-size: 14px; }
    .dest-op small { display: block; color: var(--muted); font-size: 12.5px; line-height: 1.35; }
    @media (max-width: 767.98px) { .dest-op { grid-template-columns: minmax(0, 1fr); } }
    .selector { border: 1px solid var(--line-strong); border-radius: 12px; margin-top: 10px; overflow: hidden; }
    .selector .migas-d { display: flex; flex-wrap: wrap; gap: 4px; align-items: center; padding: 10px 12px; background: var(--sunken); border-bottom: 1px solid var(--line); font-size: 13.5px; }
    .selector .migas-d button { border: 0; background: none; padding: 2px 4px; border-radius: 6px; color: var(--text-2); }
    .selector .migas-d button:hover { background: var(--surface); color: var(--text); }
    .selector ul { list-style: none; margin: 0; padding: 4px; max-height: 280px; overflow-y: auto; }
    .selector li button { width: 100%; display: flex; align-items: center; gap: 10px; border: 0; background: none; padding: 8px 10px; border-radius: 8px; text-align: left; color: var(--text); font-size: 14.5px; }
    .selector li button:hover { background: var(--sunken); }
    .selector li i { color: #5B8DEF; }
    .selector .pie-d { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 10px 12px; border-top: 1px solid var(--line); }
    @media (max-width: 575.98px) { .tf-grid { grid-template-columns: minmax(0, 1fr); } .carpeta-campo { flex-direction: column; } }
</style>
@endpush

@section('contenido')
<div class="migas">
    <a href="{{ route('admin.tareas.index') }}">Tareas</a> <i class="bi bi-chevron-right small"></i>
    @if($t->exists)<a href="{{ route('admin.tareas.show', $t) }}">{{ $t->titulo }}</a> <i class="bi bi-chevron-right small"></i> <span>Editar</span>@else<span>Nueva</span>@endif
</div>
<div class="page-head"><div><h1>{{ $t->exists ? 'Editar tarea' : 'Nueva tarea' }}</h1>
    <p class="sub">Quien la reciba la verá en “Mis tareas” y podrá subir su trabajo directo a la carpeta que elijas.</p></div></div>

<form method="post" action="{{ $t->exists ? route('admin.tareas.update', $t) : route('admin.tareas.store') }}" class="panel" style="max-width: 920px" x-data="formTarea({{ Js::from($ini) }})">
    @csrf @if($t->exists) @method('put') @endif
    <div class="panel-body tf-grid">
        <div class="ancho">
            <label class="form-label" for="tf-titulo">Qué hay que hacer</label>
            <input id="tf-titulo" name="titulo" class="form-control" x-model="titulo" @input="sugerir()" required maxlength="160" placeholder="Cargar contenido Tatich Maya" autofocus>
        </div>
        <div class="ancho">
            <label class="form-label" for="tf-desc">Detalles <span class="secundario fw-normal">(opcional)</span></label>
            <textarea id="tf-desc" name="descripcion" class="form-control" maxlength="5000" placeholder="Qué se necesita, formatos, referencias…">{{ old('descripcion', $t->descripcion) }}</textarea>
        </div>
        <div>
            <label class="form-label" for="tf-cliente">Cliente</label>
            <select id="tf-cliente" name="cliente_id" class="form-select" x-model="cliente" @change="if (proyecto && !proyectosDe().find((p) => p.id === proyecto)) proyecto = ''; sugerir()">
                <option value="">Sin cliente</option>
                @foreach($clientes as $c)<option value="{{ $c->id }}">{{ $c->empresa ?: $c->nombre }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="tf-proyecto">Proyecto <span class="secundario fw-normal">(opcional)</span></label>
            <select id="tf-proyecto" name="proyecto_id" class="form-select" x-model="proyecto" @change="alElegirProyecto()">
                <option value="">Sin proyecto</option>
                <template x-for="p in proyectosDe()" :key="p.id"><option :value="p.id" x-text="p.nombre" :selected="p.id === proyecto"></option></template>
            </select>
        </div>
        <div>
            <label class="form-label" for="tf-quien">Para</label>
            <select id="tf-quien" name="asignada_a" class="form-select">
                <option value="">Sin asignar por ahora</option>
                @foreach($equipo as $p)<option value="{{ $p->id }}" @selected((string) old('asignada_a', $t->asignada_a) === (string) $p->id)>{{ $p->name }}{{ $p->puesto ? ' · ' . $p->puesto : '' }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="tf-fecha">Fecha límite <span class="secundario fw-normal">(opcional)</span></label>
            <input id="tf-fecha" type="date" name="fecha_limite" class="form-control num" value="{{ old('fecha_limite', $t->fecha_limite?->toDateString()) }}">
        </div>

        <div class="ancho">
            <label class="form-label" for="tf-carpeta">Carpeta de entrega en Dropbox</label>
            <div class="carpeta-campo">
                <input id="tf-carpeta" name="carpeta" class="form-control" x-model="carpeta" @input="manual = true" maxlength="480" placeholder="/Vandu/Clientes/…">
                <button type="button" class="btn btn-borde text-nowrap" @click="abrir()" :disabled="!dropbox"><i class="bi bi-folder2-open me-1"></i> Elegir en Dropbox</button>
            </div>
            <div class="form-text">
                <template x-if="!manual"><span>Sugerida según el cliente o proyecto; se crea sola al guardar. Puedes cambiarla.</span></template>
                <template x-if="manual"><span>Lo que se suba en esta tarea cae en esa carpeta. <button type="button" class="btn btn-link p-0 align-baseline" style="font-size:inherit" @click="manual = false; sugerir()">Usar la sugerida</button></span></template>
                @unless($dropbox)<span class="d-block mt-1" style="color:var(--amber)"><i class="bi bi-exclamation-triangle"></i> Dropbox no está conectado: se podrá subir trabajo cuando lo conectes.</span>@endunless
            </div>

            <div class="selector" x-show="abierto" x-cloak>
                <div class="migas-d">
                    <template x-for="(m, i) in migas" :key="m.ruta"><span class="d-inline-flex align-items-center gap-1"><i class="bi bi-chevron-right small text-secondary" x-show="i"></i><button type="button" @click="ir(m.ruta)" x-text="m.nombre"></button></span></template>
                    <span class="ms-auto secundario" x-show="cargando">Cargando…</span>
                </div>
                <ul>
                    <template x-for="c in carpetas" :key="c.ruta"><li><button type="button" @click="ir(c.ruta)"><i class="bi bi-folder-fill"></i> <span x-text="c.nombre"></span></button></li></template>
                    <li x-show="!cargando && !carpetas.length" class="secundario px-3 py-2" style="font-size:13.5px">No hay subcarpetas aquí.</li>
                </ul>
                <div class="aviso aviso-error m-2 py-2" x-show="error" x-text="error" style="font-size:13.5px"></div>
                <div class="pie-d">
                    <button type="button" class="btn btn-primario btn-sm" @click="usar(ruta)"><i class="bi bi-check2 me-1"></i> Usar esta carpeta</button>
                    <div class="d-flex gap-2 ms-auto">
                        <input class="form-control form-control-sm" x-model="nueva" placeholder="Nueva carpeta aquí" maxlength="90" style="width: 190px" @keydown.enter.prevent="crearCarpeta()" aria-label="Nombre de la carpeta nueva">
                        <button type="button" class="btn btn-borde btn-sm text-nowrap" @click="crearCarpeta()" :disabled="!nueva.trim()">Crear</button>
                    </div>
                    <button type="button" class="btn btn-fantasma btn-sm" @click="abierto = false">Cerrar</button>
                </div>
            </div>
        </div>

        <div class="ancho tf-destino" x-show="proyecto" x-cloak>
            <span class="form-label d-block">Al aprobar la tarea, lo entregado se incluye en el proyecto como</span>
            <div class="dest-op">
                @foreach(\App\Models\Tarea::DESTINOS as $dk => $dd)
                    <label><input type="radio" name="destino" value="{{ $dk }}" x-model="destino"><i class="bi {{ $dd['icono'] }}"></i><span><b>{{ $dd['texto'] }}</b><small>{{ $dd['ayuda'] }}</small></span></label>
                @endforeach
                <label><input type="radio" name="destino" value="" x-model="destino"><i class="bi bi-slash-circle"></i><span><b>No incluir</b><small>Se queda solo en la carpeta de la tarea</small></span></label>
            </div>
            <div class="row g-3 mt-1" x-show="destino !== '' || etapa">
                <div class="col-md-6">
                    <label class="form-label" for="tf-etapa">Etapa del proyecto <span class="secundario fw-normal">(opcional)</span></label>
                    <select id="tf-etapa" name="etapa_id" class="form-select" x-model="etapa">
                        <option value="">Ninguna en particular</option>
                        <template x-for="e in etapasDe()" :key="e.id"><option :value="e.id" x-text="e.nombre + (e.estado === 'completada' ? ' · completada' : '')" :selected="e.id === etapa"></option></template>
                    </select>
                    <div class="form-text" x-show="destino === 'documento'">Los archivos van a los documentos de esa etapa.</div>
                </div>
                <div class="col-md-6 d-flex align-items-end" x-show="etapa">
                    <label class="form-check m-0"><input type="checkbox" class="form-check-input" name="completar_etapa" value="1" x-model="completar"> <span class="form-check-label">Al aprobar, marcar esa etapa como completada</span></label>
                </div>
            </div>
        </div>

        <label class="form-check m-0"><input type="checkbox" class="form-check-input" name="urgente" value="1" @checked(old('urgente', $t->urgente))> <span class="form-check-label">Es urgente</span></label>
        <label class="form-check m-0"><input type="hidden" name="correo" value="0"><input type="checkbox" class="form-check-input" name="correo" value="1" checked> <span class="form-check-label">Avisarle también por correo</span></label>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center px-3 pb-3 px-md-4">
        <button class="btn btn-primario px-4"><i class="bi bi-check2 me-1"></i> {{ $t->exists ? 'Guardar' : 'Crear tarea' }}</button>
        <a href="{{ $t->exists ? route('admin.tareas.show', $t) : route('admin.tareas.index') }}" class="btn btn-borde">Cancelar</a>
        @if($t->exists)
            <button type="submit" form="borrar-tarea" class="btn btn-fantasma text-danger ms-auto" onclick="return confirm('¿Eliminar esta tarea? Lo que se subió se queda en Dropbox.')"><i class="bi bi-trash me-1"></i> Eliminar</button>
        @endif
    </div>
</form>
@if($t->exists)
    <form id="borrar-tarea" method="post" action="{{ route('admin.tareas.destroy', $t) }}" class="d-none">@csrf @method('delete')</form>
@endif
@endsection

@push('scripts')
<script>
window.formTarea = (cfg) => ({
    ...cfg, abierto: false, cargando: false, error: '', ruta: '/', migas: [], carpetas: [], nueva: '',
    init() { if (!this.carpeta) this.sugerir(); },
    proyectosDe() { return this.proyectos.filter((p) => !this.cliente || p.cliente === this.cliente); },
    alElegirProyecto() {
        const p = this.proyectos.find((x) => x.id === this.proyecto);
        if (p && p.cliente) this.cliente = p.cliente;
        // Por defecto: a la galería si el proyecto tiene galería; si no, a sus documentos
        const info = this.porProyecto[this.proyecto];
        if (info && !this.destino) this.destino = info.galeria ? 'galeria' : 'documento';
        if (!this.etapasDe().find((e) => e.id === this.etapa)) { this.etapa = ''; this.completar = false; }
        this.sugerir();
    },
    etapasDe() { return (this.porProyecto[this.proyecto] || {}).etapas || []; },
    limpio(t) { return (t || '').replace(/[\\/<>:"|?*\x00-\x1F]+/g, '-').replace(/\s+/g, ' ').trim().replace(/^[ .-]+|[ .-]+$/g, '').slice(0, 90); },
    sugerir() {
        if (this.manual) return;
        const nombre = this.limpio(this.titulo) || 'Entregas';
        const p = this.bases.proyectos[this.proyecto];
        const base = p ? p.ruta + '/Equipo' : (this.bases.clientes[this.cliente] || this.bases.raiz);
        this.carpeta = base + '/' + nombre;
    },
    abrir() {
        this.abierto = !this.abierto;
        if (!this.abierto) return;
        // Empieza en la carpeta principal de la agencia en Dropbox
        this.ir(this.bases.inicio || '/');
    },
    async ir(ruta) {
        this.cargando = true; this.error = '';
        try {
            const r = await fetch(this.explorar + '?ruta=' + encodeURIComponent(ruta), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const j = await r.json();
            if (!r.ok) {
                if (ruta !== '/') return this.ir(ruta.split('/').slice(0, -1).join('/') || '/');
                throw new Error(j.error || j.message || 'No se pudo abrir la carpeta.');
            }
            this.ruta = j.ruta; this.migas = j.migas; this.carpetas = j.carpetas;
        } catch (e) { this.error = e.message; }
        this.cargando = false;
    },
    usar(ruta) { this.carpeta = ruta; this.manual = true; this.abierto = false; },
    async crearCarpeta() {
        const f = new FormData();
        f.append('_token', this.$root.querySelector('input[name=_token]').value);
        f.append('en', this.ruta); f.append('nombre', this.nueva);
        const r = await fetch(this.crear, { method: 'POST', body: f, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const j = await r.json().catch(() => ({}));
        if (!r.ok) { this.error = j.message || 'No se pudo crear la carpeta.'; return; }
        this.nueva = '';
        await this.ir(j.ruta);
    },
});
</script>
@endpush
