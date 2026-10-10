@extends('admin.layout')
@section('titulo', ($post->titulo ?: 'Post') . ' · ' . ($cliente->empresa ?: $cliente->nombre))

@php
    $redes = config('vandu.redes.redes');
    $formatos = config('vandu.redes.formatos');
    $mesPost = $post->fecha_local?->format('Y-m');
@endphp

@push('head')
@include('redes._previa-recursos', ['parte' => 'estilos'])
<style>
    .rd-est { display: inline-flex; align-items: center; gap: 6px; font-weight: 500; }
    .rd-est::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--c); }
    .ed-grid { display: grid; grid-template-columns: minmax(0, 1fr) 420px; gap: 22px; align-items: start; }
    .ed-previa { position: sticky; top: 16px; }
    .ed-redes { display: flex; flex-wrap: wrap; gap: 8px; }
    .ed-redes label { display: inline-flex; align-items: center; gap: 7px; border: 1.5px solid var(--line-strong); border-radius: 10px; padding: 7px 12px; cursor: pointer; font-size: 14px; font-weight: 500; user-select: none; }
    .ed-redes label:has(input:checked) { border-color: var(--c); background: color-mix(in srgb, var(--c) 8%, #fff); color: var(--text); }
    .ed-redes label:has(input:checked) i { color: var(--c); }
    .ed-redes input { display: none; }
    .ed-formatos { display: flex; flex-wrap: wrap; gap: 6px; }
    .ed-formatos button { border: 1px solid var(--line-strong); background: var(--surface); border-radius: 99px; padding: 5px 12px; font-size: 13.5px; display: inline-flex; gap: 6px; align-items: center; }
    .ed-formatos button.activo { background: var(--ink); color: #fff; border-color: var(--ink); }
    .ed-cuenta { font-size: 12.5px; color: var(--muted); display: flex; gap: 12px; flex-wrap: wrap; margin-top: 4px; }
    .ed-cuenta .mal { color: var(--red); font-weight: 600; }
    .ed-soltar { border: 1.5px dashed var(--line-strong); border-radius: 12px; padding: 16px; text-align: center; color: var(--muted); font-size: 14px; transition: background .12s, border-color .12s; }
    .ed-soltar.sobre { background: var(--green-soft); border-color: var(--green-ink); color: var(--green-ink); }
    .ed-medios { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
    .ed-medio { position: relative; width: 88px; height: 88px; border-radius: 10px; overflow: hidden; background: var(--sunken); cursor: grab; }
    .ed-medio img, .ed-medio video { width: 100%; height: 100%; object-fit: cover; }
    .ed-medio .n { position: absolute; left: 4px; top: 4px; background: rgba(0,0,0,.6); color: #fff; font-size: 11px; font-weight: 700; border-radius: 99px; padding: 0 6px; }
    .ed-medio .x { position: absolute; right: 4px; top: 4px; width: 22px; height: 22px; border-radius: 50%; border: 0; background: rgba(0,0,0,.6); color: #fff; font-size: 12px; display: grid; place-items: center; }
    .ed-medio.sobre { outline: 3px solid var(--blue, #2F6FEB); }
    .ed-medio .play { position: absolute; inset: 0; display: grid; place-items: center; color: #fff; font-size: 22px; text-shadow: 0 1px 4px rgba(0,0,0,.5); pointer-events: none; }
    .ed-subiendo { height: 6px; border-radius: 99px; background: var(--sunken); overflow: hidden; margin-top: 8px; }
    .ed-subiendo span { display: block; height: 100%; background: var(--ink); transition: width .2s; }
    .ed-estados { display: flex; flex-wrap: wrap; gap: 6px; }
    .ed-estados label { display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--line-strong); border-radius: 99px; padding: 5px 11px; font-size: 13.5px; cursor: pointer; }
    .ed-estados label:has(input:checked) { background: var(--ink); color: #fff; border-color: var(--ink); }
    .ed-estados input { display: none; }
    .ed-estados .pt { width: 8px; height: 8px; border-radius: 50%; }
    .hilo { list-style: none; margin: 0; padding: 0; display: grid; gap: 10px; }
    .hilo li { padding: 10px 12px; border-radius: 12px; background: var(--sunken); font-size: 14px; }
    .hilo li.cliente { background: #EAF2FF; }
    .hilo li.cambios { background: #FFF4E5; }
    .hilo li.aprobado { background: var(--green-soft); }
    .hilo li.estado, .hilo li.revision { background: none; padding: 2px 12px; color: var(--muted); font-size: 12.5px; }
    .hilo .q { font-size: 12.5px; color: var(--muted); margin-bottom: 2px; }
    .hilo .t { white-space: pre-line; }
    .dbx-lista { max-height: 380px; overflow: auto; border: 1px solid var(--line); border-radius: 10px; }
    .dbx-fila { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border-top: 1px solid var(--line); cursor: pointer; font-size: 14px; }
    .dbx-fila:first-child { border-top: 0; }
    .dbx-fila:hover { background: var(--sunken); }
    .dbx-fila img { width: 36px; height: 36px; object-fit: cover; border-radius: 6px; }
    .dbx-fila .bi-folder-fill { color: #4C8DF6; font-size: 20px; }
    @media (max-width: 1199.98px) { .ed-grid { grid-template-columns: minmax(0, 1fr); } .ed-previa { position: static; } }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.redes') }}">Redes sociales</a> <i class="bi bi-chevron-right small"></i>
    <a href="{{ route('admin.redes.cliente', array_filter([$cliente, 'mes' => $mesPost])) }}">{{ $cliente->empresa ?: $cliente->nombre }}</a> <i class="bi bi-chevron-right small"></i> <span>{{ $post->titulo ?: 'Post' }}</span></div>

<div x-data="editorPost({{ Js::from([
        'post' => $datos, 'perfiles' => $perfiles, 'redes' => $redes, 'formatos' => $formatos, 'estados' => \App\Models\RedesPost::ESTADOS,
        'urls' => [
            'guardar' => route('admin.redes.guardar', $post), 'subir' => route('admin.redes.subir', $post), 'dropbox' => route('admin.redes.dropbox', $post),
            'orden' => route('admin.redes.ordenar', $post), 'comentar' => route('admin.redes.comentar', $post), 'quitar' => url('admin/redes/medios'),
            'explorar' => route('admin.archivos.listar'), 'mini' => route('admin.archivos.miniatura'),
        ],
        'token' => csrf_token(), 'maxMb' => config('vandu.redes.max_mb'), 'dropbox' => \App\Support\Dropbox\Dropbox::conectado(),
    ]) }})" @keydown.window.meta.s.prevent="guardar()" @keydown.window.ctrl.s.prevent="guardar()">

    <div class="page-head">
        <div>
            <h1 x-text="post.titulo || 'Post sin título'"></h1>
            <p class="sub"><span x-text="post.fecha_texto"></span> · <span class="rd-est" :style="'--c:' + estados[post.estado].color" x-text="estados[post.estado].texto"></span></p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="secundario" x-show="mensaje" x-text="mensaje" x-cloak></span>
            <button type="submit" form="duplicar-post" class="btn btn-borde"><i class="bi bi-copy me-1"></i> Duplicar</button>
            <button type="button" class="btn btn-primario px-4" @click="guardar()" :disabled="guardando"><i class="bi bi-check2 me-1"></i> <span x-text="sinGuardar ? 'Guardar' : 'Guardado'"></span></button>
        </div>
    </div>

    <div class="aviso aviso-error" x-show="error" x-cloak><i class="bi bi-exclamation-circle"></i> <span x-text="error"></span></div>

    <div class="ed-grid">
        <div class="d-grid gap-4">
            <section class="panel"><div class="panel-body d-grid gap-3">
                <div>
                    <span class="form-label d-block">Dónde se publica</span>
                    <div class="ed-redes">
                        <template x-for="(r, k) in redes" :key="k">
                            <label :style="'--c:' + r.color"><input type="checkbox" :value="k" x-model="post.redes" @change="cambio()"><i class="bi" :class="r.icono"></i> <span x-text="r.nombre"></span></label>
                        </template>
                    </div>
                </div>
                <div>
                    <span class="form-label d-block">Formato</span>
                    <div class="ed-formatos">
                        <template x-for="f in formatosDisponibles()" :key="f">
                            <button type="button" :class="post.formato === f && 'activo'" @click="post.formato = f; cambio()"><i class="bi" :class="formatos[f].icono"></i> <span x-text="formatos[f].nombre"></span></button>
                        </template>
                    </div>
                    <div class="ed-cuenta" x-show="aviso()" x-text="aviso()"></div>
                </div>
                <div class="row g-2">
                    <div class="col-sm-6"><label class="form-label" for="ed-fecha">Fecha y hora</label><input type="datetime-local" id="ed-fecha" class="form-control num" x-model="post.fecha" @input="cambio()"></div>
                    <div class="col-sm-6"><label class="form-label" for="ed-titulo">Nombre interno <span class="secundario fw-normal">(el cliente lo ve)</span></label><input id="ed-titulo" class="form-control" x-model="post.titulo" @input="cambio()" maxlength="120" placeholder="Promo fin de semana"></div>
                </div>
                <div>
                    <label class="form-label" for="ed-texto">Texto de la publicación</label>
                    <textarea id="ed-texto" class="form-control" rows="7" x-model="post.texto" @input="cambio()" placeholder="Escribe el texto, con emojis y #hashtags…"></textarea>
                    <div class="ed-cuenta">
                        <template x-for="r in post.redes" :key="r"><span :class="largo() > (limites[r] || 99999) && 'mal'"><i class="bi" :class="redes[r].icono"></i> <span x-text="largo().toLocaleString('es-MX') + ' / ' + (limites[r] || 0).toLocaleString('es-MX')"></span></span></template>
                        <span :class="post.redes.includes('instagram') && hashtags() > 30 && 'mal'"><span x-text="hashtags()"></span> hashtags</span>
                    </div>
                </div>
            </div></section>

            <section class="panel">
                <div class="panel-head"><h2>Fotos y videos</h2><span class="ayuda" x-text="post.medios.length ? post.medios.length + (post.medios.length === 1 ? ' archivo' : ' archivos') : ''"></span></div>
                <div class="panel-body">
                    <div class="ed-soltar" :class="arrastrandoArchivo && 'sobre'" @dragover.prevent="arrastrandoArchivo = true" @dragleave="arrastrandoArchivo = false" @drop.prevent="arrastrandoArchivo = false; subir($event.dataTransfer.files)">
                        <i class="bi bi-cloud-arrow-up" style="font-size:22px"></i><br>
                        Arrastra aquí fotos o videos, o
                        <label class="btn btn-borde btn-sm mx-1 mb-0">elígelos<input type="file" multiple accept="image/*,video/mp4,video/quicktime,video/webm" class="d-none" @change="subir($event.target.files); $event.target.value = ''"></label>
                        <template x-if="cfg.dropbox"><span>o <button type="button" class="btn btn-borde btn-sm" @click="abrirDropbox()"><i class="bi bi-dropbox me-1"></i> elige de Dropbox</button></span></template>
                        <div style="font-size:12px" class="mt-1">Hasta <span x-text="cfg.maxMb"></span> MB por archivo · se guardan en Dropbox</div>
                    </div>
                    <div class="ed-subiendo" x-show="progreso !== null" x-cloak><span :style="'width:' + (progreso || 0) + '%'"></span></div>
                    <div class="ed-medios" x-show="post.medios.length">
                        <template x-for="(m, i) in post.medios" :key="m.id">
                            <div class="ed-medio" draggable="true" :class="sobreMedio === i && 'sobre'"
                                 @dragstart="moviendo = i" @dragover.prevent="sobreMedio = i" @dragleave="sobreMedio = null" @drop.prevent="soltarMedio(i)" @dragend="moviendo = null; sobreMedio = null">
                                <template x-if="m.tipo === 'imagen'"><img :src="m.mini" alt="" loading="lazy" draggable="false"></template>
                                <template x-if="m.tipo === 'video'"><video :src="m.url + '#t=0.5'" muted preload="metadata"></video></template>
                                <span class="play" x-show="m.tipo === 'video'"><i class="bi bi-play-circle"></i></span>
                                <span class="n" x-text="i + 1"></span>
                                <button type="button" class="x" @click="quitar(m)" :aria-label="'Quitar ' + m.nombre"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </template>
                    </div>
                    <p class="secundario mt-2 mb-0" style="font-size:12.5px" x-show="post.medios.length > 1">Arrastra para cambiar el orden del carrusel.</p>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head"><h2>Estado y comentarios</h2></div>
                <div class="panel-body d-grid gap-3">
                    <div class="ed-estados">
                        <template x-for="(e, k) in estados" :key="k">
                            <label><input type="radio" :value="k" x-model="post.estado" @change="cambio()"><span class="pt" :style="'background:' + e.color"></span> <span x-text="e.texto"></span></label>
                        </template>
                    </div>
                    <p class="secundario m-0" style="font-size:12.5px" x-show="post.aprobado" x-text="'Aprobado por ' + post.aprobado"></p>
                    <ul class="hilo" x-show="post.comentarios.length">
                        <template x-for="(c, i) in post.comentarios" :key="i">
                            <li :class="[c.actor, c.tipo]">
                                <div class="q"><b x-text="c.autor || (c.actor === 'cliente' ? 'Cliente' : 'Agencia')"></b> · <span x-text="{ cambios: 'pidió un cambio', aprobado: 'aprobó', comentario: 'comentó', estado: '', revision: '' }[c.tipo] || ''"></span> <span x-text="c.fecha"></span></div>
                                <div class="t" x-text="c.texto" x-show="c.texto"></div>
                            </li>
                        </template>
                    </ul>
                    <div class="d-flex gap-2">
                        <textarea class="form-control" rows="2" x-model="respuesta" placeholder="Responder al cliente o dejar una nota…"></textarea>
                        <button type="button" class="btn btn-borde align-self-end" @click="comentar()" :disabled="!respuesta.trim()">Enviar</button>
                    </div>
                </div>
            </section>

            <div class="d-flex justify-content-between">
                <button type="submit" form="borrar-post" class="btn btn-fantasma text-danger"><i class="bi bi-trash me-1"></i> Eliminar post</button>
                <a href="{{ route('admin.redes.cliente', array_filter([$cliente, 'mes' => $mesPost])) }}" class="btn btn-borde">Volver al calendario</a>
            </div>
        </div>

        <aside class="ed-previa">
            @include('redes._vista-previa')
        </aside>
    </div>

    {{-- Elegir archivos que ya están en Dropbox --}}
    <div class="correo-velo" x-show="dbx.abierto" x-cloak @click.self="dbx.abierto = false">
        <div class="correo-ventana" style="width: min(640px, 100%)">
            <div class="correo-cabeza"><div><h2>Elegir de Dropbox</h2><span class="secundario" x-text="dbx.ruta"></span></div>
                <button type="button" class="btn btn-fantasma btn-icono" @click="dbx.abierto = false" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button></div>
            <div class="correo-campos">
                <div class="d-flex flex-wrap gap-1" style="font-size:13.5px"><template x-for="m in dbx.migas" :key="m.ruta"><button type="button" class="btn btn-fantasma btn-sm" @click="listarDropbox(m.ruta)" x-text="m.nombre"></button></template></div>
                <div class="dbx-lista">
                    <template x-for="c in dbx.carpetas" :key="c.ruta"><div class="dbx-fila" @click="listarDropbox(c.ruta)"><i class="bi bi-folder-fill"></i> <span x-text="c.nombre"></span></div></template>
                    <template x-for="a in dbx.archivos.filter(a => a.tipo === 'foto' || a.tipo === 'video')" :key="a.id">
                        <label class="dbx-fila"><input type="checkbox" class="form-check-input" :value="a.id" x-model="dbx.elegidos">
                            <template x-if="a.tipo === 'foto'"><img :src="cfg.urls.mini + '?id=' + encodeURIComponent(a.id)" alt="" loading="lazy"></template>
                            <template x-if="a.tipo === 'video'"><i class="bi bi-film" style="font-size:20px; width:36px; text-align:center"></i></template>
                            <span class="flex-grow-1 text-truncate" x-text="a.nombre"></span><span class="secundario num" x-text="a.peso"></span></label>
                    </template>
                    <div class="p-3 secundario" x-show="!dbx.cargando && !dbx.carpetas.length && !dbx.archivos.length">Carpeta vacía.</div>
                    <div class="p-3 secundario" x-show="dbx.cargando">Cargando…</div>
                </div>
            </div>
            <div class="correo-pie"><span class="secundario me-auto" x-text="dbx.elegidos.length ? dbx.elegidos.length + ' elegidos' : 'Se usan sin copiarse'"></span>
                <button type="button" class="btn btn-borde" @click="dbx.abierto = false">Cancelar</button>
                <button type="button" class="btn btn-primario" :disabled="!dbx.elegidos.length" @click="usarDropbox()">Agregar al post</button></div>
        </div>
    </div>
</div>

<form id="duplicar-post" method="post" action="{{ route('admin.redes.duplicar', $post) }}">@csrf</form>
<form id="borrar-post" method="post" action="{{ route('admin.redes.borrar', $post) }}" onsubmit="return confirm('¿Eliminar este post? Las fotos y videos de Dropbox no se borran.')">@csrf @method('delete')</form>
@endsection

@push('scripts')
@include('redes._previa-recursos', ['parte' => 'script'])
<script>
window.editorPost = (cfg) => ({
    ...window.redesVista(cfg.redes),
    cfg, post: cfg.post, perfiles: cfg.perfiles, redes: cfg.redes, formatos: cfg.formatos, estados: cfg.estados,
    limites: { instagram: 2200, facebook: 63206, tiktok: 2200, linkedin: 3000 },
    sinGuardar: false, guardando: false, mensaje: '', error: '', respuesta: '', progreso: null,
    arrastrandoArchivo: false, moviendo: null, sobreMedio: null,
    dbx: { abierto: false, ruta: '', migas: [], carpetas: [], archivos: [], elegidos: [], cargando: false },

    init() {
        window.addEventListener('beforeunload', (e) => { if (this.sinGuardar) { e.preventDefault(); e.returnValue = ''; } });
    },
    cambio() { this.sinGuardar = true; this.mensaje = ''; },
    formatosDisponibles() {
        const fs = new Set();
        (this.post.redes.length ? this.post.redes : ['instagram']).forEach((r) => (this.redes[r].formatos || []).forEach((f) => fs.add(f)));
        const lista = Object.keys(this.formatos).filter((f) => fs.has(f));
        if (!lista.includes(this.post.formato)) this.post.formato = lista[0];
        return lista;
    },
    aviso() {
        const f = this.post.formato, n = this.post.medios.length;
        if (f === 'carrusel' && n < 2) return 'Un carrusel necesita al menos 2 fotos o videos.';
        if ((f === 'reel' || f === 'video') && !this.post.medios.some((m) => m.tipo === 'video')) return 'Agrega un video para este formato.';
        if (this.post.redes.includes('tiktok') && f === 'post') return 'En TikTok se verá como video vertical.';
        return '';
    },
    largo() { return [...(this.post.texto || '')].length; },
    hashtags() { return ((this.post.texto || '').match(/#[\p{L}\p{N}_]+/gu) || []).length; },

    async pedir(url, metodo, cuerpo, json = true) {
        const r = await fetch(url, { method: metodo, credentials: 'same-origin', body: json ? JSON.stringify(cuerpo) : cuerpo,
            headers: Object.assign({ 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.token }, json ? { 'Content-Type': 'application/json' } : {}) });
        const d = await r.json().catch(() => ({}));
        if (!r.ok) throw new Error(d.mensaje || (d.errors ? Object.values(d.errors)[0][0] : 'Error ' + r.status));
        return d;
    },
    async guardar() {
        this.guardando = true; this.error = '';
        try {
            const d = await this.pedir(cfg.urls.guardar, 'PUT', { titulo: this.post.titulo, redes: this.post.redes, formato: this.post.formato, fecha: this.post.fecha, texto: this.post.texto, estado: this.post.estado });
            this.post = Object.assign(this.post, d.post); this.sinGuardar = false; this.mensaje = 'Guardado';
            setTimeout(() => { if (this.mensaje === 'Guardado') this.mensaje = ''; }, 2500);
        } catch (e) { this.error = e.message; }
        this.guardando = false;
    },
    async comentar() {
        try { const d = await this.pedir(cfg.urls.comentar, 'POST', { texto: this.respuesta }); this.post.comentarios = d.post.comentarios; this.respuesta = ''; }
        catch (e) { this.error = e.message; }
    },

    // Archivos
    medidas(f) {
        return new Promise((ok) => {
            const url = URL.createObjectURL(f);
            if (f.type.startsWith('video/')) {
                const v = document.createElement('video'); v.preload = 'metadata';
                v.onloadedmetadata = () => { ok([v.videoWidth, v.videoHeight]); URL.revokeObjectURL(url); };
                v.onerror = () => ok([0, 0]); v.src = url;
            } else {
                const i = new Image(); i.onload = () => { ok([i.naturalWidth, i.naturalHeight]); URL.revokeObjectURL(url); }; i.onerror = () => ok([0, 0]); i.src = url;
            }
        });
    },
    async subir(lista) {
        const archivos = [...lista].filter((f) => f.type.startsWith('image/') || f.type.startsWith('video/'));
        if (!archivos.length) return;
        const grande = archivos.find((f) => f.size > cfg.maxMb * 1048576);
        if (grande) { this.error = '“' + grande.name + '” pesa más de ' + cfg.maxMb + ' MB. Súbelo a Dropbox y elígelo desde ahí.'; return; }
        this.error = '';
        const fd = new FormData();
        for (const f of archivos) { const [w, h] = await this.medidas(f); fd.append('archivos[]', f); fd.append('ancho[]', w || ''); fd.append('alto[]', h || ''); }
        this.progreso = 0;
        const xhr = new XMLHttpRequest();
        xhr.open('POST', cfg.urls.subir);
        xhr.setRequestHeader('X-CSRF-TOKEN', cfg.token); xhr.setRequestHeader('Accept', 'application/json');
        xhr.upload.onprogress = (e) => { if (e.lengthComputable) this.progreso = Math.round(e.loaded / e.total * 95); };
        xhr.onload = () => {
            this.progreso = null;
            let d = {}; try { d = JSON.parse(xhr.responseText); } catch (e) {}
            if (xhr.status >= 200 && xhr.status < 300 && d.ok) { this.post.medios = d.medios; this.ajustarFormato(); }
            else this.error = d.mensaje || (d.errors ? Object.values(d.errors)[0][0] : (xhr.status === 413 ? 'El archivo es demasiado grande para el servidor.' : 'No se pudo subir (' + xhr.status + ').'));
        };
        xhr.onerror = () => { this.progreso = null; this.error = 'Se perdió la conexión al subir.'; };
        xhr.send(fd);
    },
    ajustarFormato() {
        const n = this.post.medios.length, video = this.post.medios.some((m) => m.tipo === 'video');
        if (n > 1 && this.post.formato === 'post') { this.post.formato = 'carrusel'; this.guardar(); }
        else if (n === 1 && video && this.post.formato === 'post' && this.post.redes.includes('instagram')) { this.post.formato = 'reel'; this.guardar(); }
    },
    async quitar(m) {
        if (!confirm('¿Quitar “' + m.nombre + '” de este post?')) return;
        try { const d = await this.pedir(cfg.urls.quitar + '/' + m.id, 'DELETE', {}); this.post.medios = d.medios; this.pvI = 0; } catch (e) { this.error = e.message; }
    },
    async soltarMedio(i) {
        const de = this.moviendo; this.sobreMedio = null; this.moviendo = null;
        if (de === null || de === i) return;
        const lista = [...this.post.medios]; const [m] = lista.splice(de, 1); lista.splice(i, 0, m);
        this.post.medios = lista; this.pvI = 0;
        try { await this.pedir(cfg.urls.orden, 'POST', { ids: lista.map((x) => x.id) }); } catch (e) { this.error = e.message; }
    },

    // Dropbox
    abrirDropbox() { this.dbx.abierto = true; this.dbx.elegidos = []; this.listarDropbox(this.dbx.ruta || ''); },
    async listarDropbox(ruta) {
        this.dbx.cargando = true;
        try {
            const r = await fetch(cfg.urls.explorar + (ruta ? '?ruta=' + encodeURIComponent(ruta) : ''), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            const d = await r.json();
            Object.assign(this.dbx, { ruta: d.ruta, migas: d.migas, carpetas: d.carpetas, archivos: d.archivos });
        } catch (e) { this.error = 'No se pudo leer Dropbox.'; }
        this.dbx.cargando = false;
    },
    async usarDropbox() {
        try { const d = await this.pedir(cfg.urls.dropbox, 'POST', { ids: this.dbx.elegidos }); this.post.medios = d.medios; this.dbx.abierto = false; this.ajustarFormato(); }
        catch (e) { this.error = e.message; }
    },
});
</script>
@endpush
