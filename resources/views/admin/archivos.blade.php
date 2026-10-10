@extends('admin.layout')
@section('titulo', 'Archivos')

@push('head')
<style>
    .ar-barra { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; margin-bottom: 14px; }
    .ar-migas { display: flex; flex-wrap: wrap; align-items: center; gap: 2px; font-size: 14.5px; min-width: 0; }
    .ar-migas button { border: 0; background: none; padding: 4px 6px; border-radius: 7px; color: var(--text-2); max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ar-migas button:hover { background: var(--sunken); color: var(--text); }
    .ar-migas button:last-child { color: var(--text); font-weight: 600; }
    .ar-migas i { color: var(--muted); font-size: 11px; }
    .ar-herr { display: flex; gap: 8px; align-items: center; }
    .ar-herr .buscador input { width: 260px; }
    .ar-vista { display: inline-flex; background: #E9EBEE; border-radius: 9px; padding: 3px; }
    .ar-vista button { border: 0; background: none; width: 34px; height: 30px; border-radius: 7px; color: var(--text-2); }
    .ar-vista button.activo { background: var(--surface); color: var(--text); box-shadow: 0 1px 2px rgba(0,0,0,.08); }
    .ar-proyecto { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-radius: 10px; background: var(--green-soft); color: var(--green-ink); border: 1px solid #BDF2D6; margin-bottom: 14px; font-size: 14px; }
    .ar-proyecto a { color: inherit; font-weight: 600; }
    .ar-resumen { font-size: 13px; color: var(--muted); margin: 0 0 10px; }

    .ar-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(168px, 1fr)); gap: 12px; }
    .ar-item { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; cursor: pointer; text-align: left; padding: 0; display: flex; flex-direction: column; transition: border-color .12s, box-shadow .12s; color: var(--text); }
    .ar-item:hover { border-color: var(--line-strong); box-shadow: 0 4px 14px rgba(15,18,25,.06); }
    .ar-item:focus-visible { outline: 2px solid var(--ink); outline-offset: 2px; }
    .ar-thumb { aspect-ratio: 4 / 3; background: var(--sunken); display: grid; place-items: center; position: relative; overflow: hidden; }
    .ar-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .ar-thumb > i { font-size: 34px; color: #9AA0AC; }
    .ar-thumb .play { position: absolute; width: 38px; height: 38px; border-radius: 50%; background: rgba(15,18,25,.65); color: #fff; display: grid; place-items: center; font-size: 18px; }
    .ar-thumb .ext { position: absolute; bottom: 8px; left: 8px; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; padding: 1px 6px; border-radius: 5px; background: var(--surface); color: var(--text-2); text-transform: uppercase; }
    .ar-info { padding: 9px 11px 10px; min-width: 0; }
    .ar-info .n { font-size: 13.5px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ar-info .m { font-size: 12px; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ar-carpeta { flex-direction: row; align-items: center; gap: 10px; padding: 12px; }
    .ar-carpeta > i { font-size: 24px; color: #4C8DF6; }
    .ar-carpeta .ar-info { padding: 0; flex: 1; }
    .ar-badge { display: inline-block; font-size: 11px; font-weight: 600; padding: 0 6px; border-radius: 99px; background: var(--green-soft); color: var(--green-ink); }

    .ar-lista { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; overflow: hidden; }
    .ar-fila { display: grid; grid-template-columns: 40px minmax(0, 1fr) 110px 120px; gap: 12px; align-items: center; padding: 9px 14px; border: 0; border-top: 1px solid var(--line); background: none; width: 100%; text-align: left; color: var(--text); }
    .ar-fila:first-child { border-top: 0; }
    .ar-fila:hover { background: var(--sunken); }
    .ar-fila .ic { width: 36px; height: 36px; border-radius: 8px; background: var(--sunken); display: grid; place-items: center; overflow: hidden; }
    .ar-fila .ic img { width: 100%; height: 100%; object-fit: cover; }
    .ar-fila .ic i { font-size: 18px; color: #9AA0AC; }
    .ar-fila .ic .bi-folder-fill { color: #4C8DF6; }
    .ar-fila .n { font-size: 14px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ar-fila .n small { display: block; color: var(--muted); font-size: 12px; }
    .ar-fila .p, .ar-fila .f { font-size: 13px; color: var(--muted); }
    .ar-mas { display: flex; justify-content: center; margin-top: 14px; }

    .ar-cargando { display: grid; grid-template-columns: repeat(auto-fill, minmax(168px, 1fr)); gap: 12px; }
    .ar-cargando div { aspect-ratio: 4 / 3.6; border-radius: 12px; background: linear-gradient(90deg, var(--sunken), #F4F5F7, var(--sunken)); background-size: 200% 100%; animation: brillo 1.2s infinite; }
    @keyframes brillo { to { background-position: -200% 0; } }

    /* Visor */
    .ar-visor { position: fixed; inset: 0; z-index: 1090; background: rgba(10,12,16,.94); display: flex; flex-direction: column; color: #fff; }
    .ar-visor-cab { display: flex; align-items: center; gap: 10px; padding: 12px 16px; padding-top: calc(12px + env(safe-area-inset-top)); }
    .ar-visor-cab .t { flex: 1; min-width: 0; }
    .ar-visor-cab .t b { display: block; font-weight: 600; font-size: 15px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ar-visor-cab .t span { font-size: 12.5px; color: #9AA0AC; }
    .ar-visor-cab a, .ar-visor-cab button { color: #fff; border: 1px solid rgba(255,255,255,.2); background: rgba(255,255,255,.06); border-radius: 9px; height: 36px; padding: 0 12px; display: inline-flex; align-items: center; gap: 6px; font-size: 14px; text-decoration: none; }
    .ar-visor-cab a:hover, .ar-visor-cab button:hover { background: rgba(255,255,255,.14); }
    .ar-visor-cuerpo { flex: 1; min-height: 0; display: grid; place-items: center; position: relative; padding: 0 56px 20px; }
    .ar-visor-cuerpo img, .ar-visor-cuerpo video { max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 6px; }
    .ar-visor-cuerpo iframe { width: min(1000px, 100%); height: 100%; border: 0; border-radius: 8px; background: #fff; }
    .ar-visor-cuerpo .nada { text-align: center; color: #C9CDD5; }
    .ar-visor-cuerpo .nada i { font-size: 56px; display: block; margin-bottom: 10px; color: #6B7280; }
    .ar-flecha { position: absolute; top: 50%; transform: translateY(-50%); width: 44px; height: 44px; border-radius: 50%; border: 0; background: rgba(255,255,255,.1); color: #fff; font-size: 20px; }
    .ar-flecha:hover { background: rgba(255,255,255,.2); }
    .ar-flecha.izq { left: 8px; } .ar-flecha.der { right: 8px; }

    @media (max-width: 991.98px) {
        .ar-herr { width: 100%; }
        .ar-herr .buscador { flex: 1; }
        .ar-herr .buscador input { width: 100%; }
        .ar-grid, .ar-cargando { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .ar-fila { grid-template-columns: 40px minmax(0, 1fr) auto; }
        .ar-fila .f { display: none; }
        .ar-visor-cuerpo { padding: 0 0 12px; }
        .ar-flecha { display: none; }
        .ar-visor-cab .txt { display: none; }
    }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <h1>Archivos</h1>
        <p class="sub">Lo que tienes en Dropbox, sin salir del panel: fotos, videos, documentos y avances.</p>
    </div>
    @if($conectado)
        <a href="{{ route('admin.dropbox.abrir') }}" target="_blank" rel="noopener" class="btn btn-borde"><i class="bi bi-dropbox me-1"></i> Abrir en Dropbox</a>
    @endif
</div>

@if(! $conectado)
    <div class="panel"><div class="vacio">
        <div class="ico"><i class="bi bi-dropbox"></i></div>
        <h3>Dropbox no está conectado</h3>
        <p>Conéctalo para ver aquí tus carpetas y archivos.</p>
        <a href="{{ route('admin.dropbox') }}" class="btn btn-primario">Ir a Dropbox</a>
    </div></div>
@else
<div x-data="exploradorArchivos({{ Js::from([
        'inicio' => $inicio, 'raiz' => $raiz,
        'listar' => route('admin.archivos.listar'), 'buscarUrl' => route('admin.archivos.buscar'),
        'mini' => route('admin.archivos.miniatura'), 'ver' => route('admin.archivos.ver'),
    ]) }})" @keydown.window="tecla($event)">

    <div class="ar-barra">
        <nav class="ar-migas" aria-label="Ubicación">
            <template x-if="!busqueda">
                <div class="d-contents" style="display: contents">
                    <template x-for="(m, i) in migas" :key="m.ruta">
                        <span style="display: contents">
                            <i class="bi bi-chevron-right" x-show="i > 0"></i>
                            <button type="button" @click="abrir(m.ruta)" x-text="m.nombre" :aria-current="i === migas.length - 1 ? 'page' : null"></button>
                        </span>
                    </template>
                </div>
            </template>
            <template x-if="busqueda">
                <span style="display: contents">
                    <button type="button" @click="limpiarBusqueda()"><i class="bi bi-arrow-left"></i> Volver</button>
                    <span class="secundario ms-1">Resultados para “<b x-text="busqueda"></b>”</span>
                </span>
            </template>
        </nav>
        <div class="ar-herr">
            <form class="buscador" role="search" @submit.prevent="buscar()">
                <i class="bi bi-search"></i>
                <input type="search" class="form-control" placeholder="Buscar en {{ trim($raiz, '/') }}…" x-model="texto" aria-label="Buscar archivos" @search="if (!texto) limpiarBusqueda()">
            </form>
            <div class="ar-vista" role="group" aria-label="Vista">
                <button type="button" :class="vista === 'cuadros' && 'activo'" @click="cambiarVista('cuadros')" title="Cuadrícula" aria-label="Cuadrícula"><i class="bi bi-grid-3x3-gap"></i></button>
                <button type="button" :class="vista === 'lista' && 'activo'" @click="cambiarVista('lista')" title="Lista" aria-label="Lista"><i class="bi bi-list-ul"></i></button>
            </div>
        </div>
    </div>

    <div class="ar-proyecto" x-show="proyecto && !busqueda" x-cloak>
        <i class="bi bi-kanban"></i>
        <span>Carpeta del proyecto <a :href="proyecto?.url" x-text="proyecto?.nombre"></a><span x-show="proyecto?.cliente" x-text="' · ' + proyecto?.cliente"></span></span>
    </div>

    <div class="aviso aviso-error" x-show="error" x-cloak><i class="bi bi-exclamation-circle"></i> <span x-text="error"></span></div>

    <div class="ar-cargando" x-show="cargando" aria-label="Cargando"><div></div><div></div><div></div><div></div><div></div><div></div></div>

    <div x-show="!cargando" x-cloak>
        <p class="ar-resumen" x-show="carpetas.length || archivos.length" x-text="resumen()"></p>

        <div class="panel" x-show="!carpetas.length && !archivos.length && !error">
            <div class="vacio">
                <div class="ico"><i class="bi" :class="busqueda ? 'bi-search' : 'bi-folder2-open'"></i></div>
                <h3 x-text="busqueda ? 'No encontramos nada con ese nombre' : 'Esta carpeta está vacía'"></h3>
                <p x-text="busqueda ? 'Prueba con otra palabra o parte del nombre.' : 'Cuando subas fotos, videos o documentos aparecerán aquí.'"></p>
            </div>
        </div>

        {{-- Cuadrícula --}}
        <div x-show="vista === 'cuadros'">
            <div class="ar-grid mb-3" x-show="carpetas.length">
                <template x-for="c in carpetas" :key="c.ruta">
                    <button type="button" class="ar-item ar-carpeta" @click="abrir(c.ruta)">
                        <i class="bi bi-folder-fill"></i>
                        <div class="ar-info">
                            <div class="n" x-text="c.nombre"></div>
                            <div class="m">
                                <span class="ar-badge" x-show="c.proyecto">Proyecto</span>
                                <span x-text="c.en ? c.en : (c.proyecto ? c.proyecto.cliente || '' : 'Carpeta')"></span>
                            </div>
                        </div>
                    </button>
                </template>
            </div>
            <div class="ar-grid">
                <template x-for="(a, i) in visibles()" :key="a.id">
                    <button type="button" class="ar-item" @click="verArchivo(a)">
                        <div class="ar-thumb">
                            <template x-if="a.tipo === 'foto' || (a.tipo === 'video' && !a.sinMini)">
                                <img :src="mini + '?id=' + encodeURIComponent(a.id)" loading="lazy" alt="" x-on:error="a.tipo === 'video' ? a.sinMini = true : $el.replaceWith(Object.assign(document.createElement('i'), { className: 'bi bi-image' }))">
                            </template>
                            <template x-if="a.tipo === 'video' && a.sinMini"><i class="bi bi-film"></i></template>
                            <template x-if="a.tipo !== 'foto' && a.tipo !== 'video'"><i class="bi" :class="icono(a)"></i></template>
                            <span class="play" x-show="a.tipo === 'video'"><i class="bi bi-play-fill"></i></span>
                            <span class="ext" x-show="a.tipo !== 'foto'" x-text="a.ext"></span>
                        </div>
                        <div class="ar-info">
                            <div class="n" x-text="a.nombre"></div>
                            <div class="m" x-text="a.en ? a.en : [a.peso, a.fecha].filter(Boolean).join(' · ')"></div>
                        </div>
                    </button>
                </template>
            </div>
        </div>

        {{-- Lista --}}
        <div class="ar-lista" x-show="vista === 'lista' && (carpetas.length || archivos.length)">
            <template x-for="c in carpetas" :key="'c' + c.ruta">
                <button type="button" class="ar-fila" @click="abrir(c.ruta)">
                    <span class="ic"><i class="bi bi-folder-fill"></i></span>
                    <span class="n"><span x-text="c.nombre"></span> <span class="ar-badge" x-show="c.proyecto">Proyecto</span><small x-show="c.en" x-text="c.en"></small></span>
                    <span class="p">Carpeta</span><span class="f"></span>
                </button>
            </template>
            <template x-for="a in visibles()" :key="'a' + a.id">
                <button type="button" class="ar-fila" @click="verArchivo(a)">
                    <span class="ic">
                        <template x-if="a.tipo === 'foto'"><img :src="mini + '?id=' + encodeURIComponent(a.id)" loading="lazy" alt=""></template>
                        <template x-if="a.tipo !== 'foto'"><i class="bi" :class="icono(a)"></i></template>
                    </span>
                    <span class="n"><span x-text="a.nombre"></span><small x-show="a.en" x-text="a.en"></small></span>
                    <span class="p num" x-text="a.peso"></span>
                    <span class="f" x-text="a.fecha || ''"></span>
                </button>
            </template>
        </div>

        <div class="ar-mas" x-show="archivos.length > mostrar">
            <button type="button" class="btn btn-borde" @click="mostrar += 60">Mostrar más (<span x-text="archivos.length - mostrar"></span>)</button>
        </div>
    </div>

    {{-- Visor de fotos, videos y PDF --}}
    <template x-teleport="body">
    <div class="ar-visor" x-show="actual" x-cloak x-transition.opacity role="dialog" aria-modal="true" :aria-label="actual?.nombre"
         @touchstart.passive="toque = $event.changedTouches[0].clientX" @touchend.passive="deslizar($event.changedTouches[0].clientX)">
        <div class="ar-visor-cab">
            <div class="t"><b x-text="actual?.nombre"></b><span x-text="[actual?.peso, actual?.fecha, galeria().length > 1 && posicion() >= 0 ? (posicion() + 1) + ' de ' + galeria().length : null].filter(Boolean).join(' · ')"></span></div>
            <a :href="actual ? ver + '?id=' + encodeURIComponent(actual.id) : '#'" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> <span class="txt">Abrir</span></a>
            <button type="button" @click="cerrarVisor()" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="ar-visor-cuerpo">
            <template x-if="actual && actual.tipo === 'foto'">
                <img :src="mini + '?t=g&id=' + encodeURIComponent(actual.id)" :alt="actual.nombre" x-on:error="$el.src = ver + '?id=' + encodeURIComponent(actual.id)">
            </template>
            <template x-if="actual && actual.tipo === 'video'">
                <video :src="ver + '?id=' + encodeURIComponent(actual.id)" controls autoplay playsinline preload="metadata"></video>
            </template>
            <template x-if="actual && actual.tipo === 'pdf'">
                <iframe :src="ver + '?id=' + encodeURIComponent(actual.id)" :title="actual.nombre"></iframe>
            </template>
            <template x-if="actual && actual.tipo === 'archivo'">
                <div class="nada"><i class="bi" :class="icono(actual)"></i>
                    <p class="mb-3">Este tipo de archivo no se puede ver aquí.</p>
                    <a class="btn btn-primario" :href="ver + '?id=' + encodeURIComponent(actual.id)" target="_blank" rel="noopener"><i class="bi bi-download me-1"></i> Descargar</a>
                </div>
            </template>
            <button type="button" class="ar-flecha izq" x-show="galeria().length > 1 && posicion() >= 0" @click="mover(-1)" aria-label="Anterior"><i class="bi bi-chevron-left"></i></button>
            <button type="button" class="ar-flecha der" x-show="galeria().length > 1 && posicion() >= 0" @click="mover(1)" aria-label="Siguiente"><i class="bi bi-chevron-right"></i></button>
        </div>
    </div>
    </template>
</div>
@endif
@endsection

@push('scripts')
<script>
window.exploradorArchivos = (cfg) => ({
    ...cfg,
    ruta: cfg.inicio, migas: [], carpetas: [], archivos: [], proyecto: null, total: '',
    cargando: true, error: '', texto: '', busqueda: '', mostrar: 60, actual: null, toque: null,
    vista: (() => { try { return localStorage.getItem('vandu-archivos-vista') || 'cuadros'; } catch { return 'cuadros'; } })(),
    _n: 0,

    init() { document.body.style.overflow = ''; this.cargar(this.ruta, false); },

    async pedir(url) {
        const n = ++this._n;
        this.cargando = true; this.error = '';
        try {
            const r = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
            const d = await r.json().catch(() => ({}));
            if (n !== this._n) return null;
            if (!r.ok) throw new Error(d.error || 'No se pudo leer Dropbox (' + r.status + ').');
            return d;
        } catch (e) {
            if (n === this._n) { this.error = e.message; this.carpetas = []; this.archivos = []; }
            return null;
        } finally { if (n === this._n) this.cargando = false; }
    },

    async cargar(ruta, historial = true) {
        const d = await this.pedir(this.listar + '?ruta=' + encodeURIComponent(ruta));
        if (!d) return;
        Object.assign(this, { ruta: d.ruta, migas: d.migas, carpetas: d.carpetas, archivos: d.archivos, proyecto: d.proyecto, total: d.total, mostrar: 60, busqueda: '' });
        if (historial) {
            const u = new URL(location.href); u.searchParams.set('ruta', d.ruta);
            history.replaceState(null, '', u.href);
        }
    },

    abrir(ruta) { this.texto = ''; this.cargar(ruta); window.scrollTo({ top: 0 }); },

    async buscar() {
        const q = this.texto.trim();
        if (q.length < 2) return;
        const d = await this.pedir(this.buscarUrl + '?q=' + encodeURIComponent(q));
        if (!d) return;
        Object.assign(this, { carpetas: d.carpetas, archivos: d.archivos, total: d.total, busqueda: q, mostrar: 60, proyecto: null });
    },

    limpiarBusqueda() { this.texto = ''; this.busqueda = ''; this.cargar(this.ruta, false); },

    cambiarVista(v) { this.vista = v; try { localStorage.setItem('vandu-archivos-vista', v); } catch {} },

    visibles() { return this.archivos.slice(0, this.mostrar); },

    resumen() {
        const p = (n, s, pl) => n + ' ' + (n === 1 ? s : pl);
        return [this.carpetas.length ? p(this.carpetas.length, 'carpeta', 'carpetas') : null,
                this.archivos.length ? p(this.archivos.length, 'archivo', 'archivos') + ' · ' + this.total : null].filter(Boolean).join(' · ');
    },

    icono(a) {
        return ({ pdf: 'bi-file-earmark-pdf', video: 'bi-film', foto: 'bi-image' })[a.tipo]
            || ({ zip: 'bi-file-earmark-zip', rar: 'bi-file-earmark-zip', doc: 'bi-file-earmark-word', docx: 'bi-file-earmark-word', xls: 'bi-file-earmark-excel', xlsx: 'bi-file-earmark-excel',
                  ppt: 'bi-file-earmark-ppt', pptx: 'bi-file-earmark-ppt', xml: 'bi-filetype-xml', ai: 'bi-vector-pen', psd: 'bi-file-earmark-image', svg: 'bi-vector-pen',
                  mp3: 'bi-file-earmark-music', wav: 'bi-file-earmark-music', txt: 'bi-file-earmark-text', heic: 'bi-file-earmark-image' })[a.ext] || 'bi-file-earmark';
    },

    // Visor
    galeria() { return this.archivos.filter((a) => a.tipo === 'foto' || a.tipo === 'video'); },
    posicion() { return this.actual ? this.galeria().findIndex((a) => a.id === this.actual.id) : -1; },
    verArchivo(a) { this.actual = a; document.body.style.overflow = 'hidden'; },
    cerrarVisor() { this.actual = null; document.body.style.overflow = ''; },
    mover(d) {
        const g = this.galeria(), i = this.posicion();
        if (i < 0 || g.length < 2) return;
        this.actual = g[(i + d + g.length) % g.length];
    },
    tecla(e) {
        if (!this.actual) return;
        if (e.key === 'Escape') this.cerrarVisor();
        else if (e.key === 'ArrowRight') this.mover(1);
        else if (e.key === 'ArrowLeft') this.mover(-1);
    },
    deslizar(x) {
        if (this.toque === null) return;
        const dx = x - this.toque; this.toque = null;
        if (Math.abs(dx) > 60) this.mover(dx < 0 ? 1 : -1);
    },
});
</script>
@endpush
