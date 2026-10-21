@extends('admin.layout')
@section('titulo', 'Escanear')
@include('admin.lealtad._estilos')

@push('head')
<style>
    .esc { display: grid; grid-template-columns: minmax(0, 1fr) 400px; gap: 24px; align-items: start; }
    .camara { position: relative; aspect-ratio: 1 / 1; max-height: 64vh; background: #0b0d12; border-radius: 16px; overflow: hidden; display: grid; place-items: center; }
    .camara video { width: 100%; height: 100%; object-fit: cover; }
    .camara .marco { position: absolute; inset: 18%; border: 3px solid rgba(255,255,255,.85); border-radius: 18px; box-shadow: 0 0 0 999px rgba(0,0,0,.35); pointer-events: none; }
    .camara .msg { position: absolute; bottom: 14px; left: 0; right: 0; text-align: center; color: #fff; font-size: 14px; }
    .camara .iniciar { position: absolute; inset: 0; display: grid; place-items: center; color: #fff; text-align: center; padding: 24px; }
    .ficha { border-radius: 16px; overflow: hidden; background: var(--surface); border: 1px solid var(--line); }
    .ficha .top { padding: 18px 20px; color: #fff; background: var(--ink); display: flex; align-items: center; gap: 14px; }
    .ficha .top.ok { background: #047A4B; } .ficha .top.no { background: #B42318; }
    .ficha .ini { width: 56px; height: 56px; border-radius: 50%; display: grid; place-items: center; background: rgba(255,255,255,.18); font-weight: 700; font-size: 20px; flex: none; }
    .ficha .top b { font-size: 19px; display: block; line-height: 1.2; }
    .ficha .top span { font-size: 14px; opacity: .9; }
    .ficha .cuerpo { padding: 16px 20px; display: grid; gap: 14px; }
    .ficha .camp { border: 1px solid var(--line); border-radius: 12px; padding: 12px 14px; }
    .ficha .camp .t { display: flex; justify-content: space-between; gap: 8px; font-weight: 600; }
    .ficha .barra { height: 8px; border-radius: 99px; background: var(--line); overflow: hidden; margin: 8px 0 6px; }
    .ficha .barra span { display: block; height: 100%; background: var(--marca); }
    .ficha .premio { display: flex; align-items: center; gap: 10px; background: #E3FBEF; border-radius: 10px; padding: 8px 10px; margin-top: 8px; }
    .ficha .premio b { flex: 1; color: #035F3A; }
    @media (max-width: 991.98px) { .esc { grid-template-columns: minmax(0, 1fr); } }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div><h1>Escanear QR</h1><p class="sub">Pídele al comensal que abra su QR de {{ config('vandu.plataforma.nombre') }} y apúntale con la cámara. Cambia cada minuto, así que una captura vieja no sirve.</p></div>
</div>

<div class="esc" x-data="lkEscaner({{ Js::from([
        'leer' => route('admin.lealtad.escanear.leer'), 'unir' => route('admin.lealtad.escanear.unir'), 'visita' => route('admin.lealtad.escanear.visita'),
        'canjear' => route('admin.lealtad.escanear.canjear'), 'token' => csrf_token()]) }})" x-init="iniciar()">
    <div>
        <div class="camara">
            <video x-ref="video" playsinline muted></video>
            <div class="marco" x-show="activa"></div>
            <div class="msg" x-show="activa" x-text="ocupado ? 'Revisando…' : 'Buscando código…'"></div>
            <div class="iniciar" x-show="! activa">
                <div><i class="bi bi-camera-video fs-1 d-block mb-2"></i>
                    <p class="mb-3" x-text="error || 'Se necesita la cámara para leer el QR.'"></p>
                    <button type="button" class="btn btn-primario" @click="iniciar()">Activar cámara</button></div>
            </div>
        </div>
        <form class="d-flex gap-2 mt-3" @submit.prevent="leer(manual); manual = ''">
            <input class="form-control" x-model="manual" placeholder="O escribe el código que aparece bajo su QR (ej. 12-483920)" aria-label="Código del comensal" autocomplete="off">
            <button class="btn btn-borde">Revisar</button>
        </form>
    </div>

    <div aria-live="polite">
        <template x-if="r && r.comensal">
            <div class="ficha">
                <div class="top" :class="aviso ? (aviso.ok ? 'ok' : 'no') : ''">
                    <div class="ini" x-text="r.comensal.iniciales"></div>
                    <div class="min-w-0"><b x-text="r.comensal.nombre"></b>
                        <span x-text="aviso ? aviso.mensaje : (r.inscrito ? (r.visitaHoy ? 'Ya registró su visita de hoy' : 'Está en tu programa') : 'Aún no está en tu programa')"></span></div>
                </div>
                <div class="cuerpo">
                    <template x-if="! r.inscrito">
                        <div><p class="secundario small mb-2">Al unirlo acepta compartir con el restaurante su nombre, teléfono y correo. Pregúntale si quiere unirse.</p>
                            <button type="button" class="btn btn-primario w-100" @click="accion('unir')" :disabled="ocupado"><i class="bi bi-person-plus me-1"></i> Unir al programa</button></div>
                    </template>
                    <template x-if="r.inscrito && ! r.visitaHoy">
                        <div class="d-flex gap-2">
                            <div class="input-group" style="max-width: 160px"><span class="input-group-text">$</span><input type="number" min="0" step="0.01" class="form-control num" x-model="monto" placeholder="Consumo" aria-label="Consumo (opcional)"></div>
                            <button type="button" class="btn btn-primario flex-grow-1" @click="accion('visita', { monto: monto || null })" :disabled="ocupado"><i class="bi bi-check2-circle me-1"></i> Registrar visita</button>
                        </div>
                    </template>
                    <template x-for="c in (r.campanas || [])" :key="c.nombre">
                        <div class="camp">
                            <div class="t"><span x-text="c.nombre"></span><span class="num" x-text="c.visitas + ' / ' + c.meta"></span></div>
                            <div class="barra"><span :style="'width:' + Math.min(100, c.visitas / c.meta * 100) + '%'"></span></div>
                            <div class="secundario small" x-show="c.siguiente" x-text="c.siguiente ? 'Le faltan ' + c.siguiente.faltan + (c.siguiente.faltan === 1 ? ' visita' : ' visitas') + ' para ' + c.siguiente.nombre : ''"></div>
                            <div class="secundario small" x-show="! c.vigente">La campaña terminó; puede canjear lo que ya ganó.</div>
                            <template x-for="p in c.disponibles" :key="p.id">
                                <div class="premio"><i class="bi bi-gift-fill" style="color: #047A4B"></i><b x-text="p.nombre"></b>
                                    <button type="button" class="btn btn-sm btn-primario" @click="accion('canjear', { premio_id: p.id })" :disabled="ocupado">Entregar</button></div>
                            </template>
                        </div>
                    </template>
                    <div class="secundario small" x-show="r.inscrito && ! (r.campanas || []).length">No hay campañas vigentes; la visita queda registrada para tus números.</div>
                    <button type="button" class="btn btn-fantasma btn-sm" @click="r = null; aviso = null">Escanear a otra persona</button>
                </div>
            </div>
        </template>
        <template x-if="r && ! r.comensal">
            <div class="ficha"><div class="top no"><i class="bi bi-exclamation-octagon-fill fs-3"></i><div><b x-text="r.mensaje"></b></div></div></div>
        </template>
        <template x-if="! r">
            <div class="panel panel-body text-center secundario"><i class="bi bi-qr-code-scan fs-1 d-block mb-2"></i>Aquí aparece quién es, sus visitas y los premios que puede canjear.</div>
        </template>
    </div>
</div>
@endsection

@push('scripts')
<script>
window.lkEscaner = function (cfg) {
    const post = async (url, datos) => {
        const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.token }, body: JSON.stringify(datos) });
        if (res.status === 422) { const j = await res.json().catch(() => ({})); return { ok: false, mensaje: j.message || 'Vuelve a escanear el QR.' }; }
        return res.json();
    };
    return {
        activa: false, ocupado: false, error: null, r: null, aviso: null, manual: '', monto: '', ultimo: '', ultimoT: 0, detector: null, stream: null, lienzo: null,
        async iniciar() {
            if (this.activa) return;
            if (!navigator.mediaDevices?.getUserMedia) { this.error = 'Este navegador no permite usar la cámara aquí. Escribe el código abajo.'; return; }
            try { this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false }); }
            catch (e) { this.error = 'No se pudo abrir la cámara. Revisa el permiso del navegador.'; return; }
            const v = this.$refs.video; v.srcObject = this.stream; await v.play().catch(() => {});
            this.activa = true; this.error = null;
            if ('BarcodeDetector' in window) { try { this.detector = new BarcodeDetector({ formats: ['qr_code'] }); } catch (e) { this.detector = null; } }
            if (!this.detector && !window.jsQR) await new Promise((ok) => { const s = document.createElement('script'); s.src = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js'; s.onload = ok; s.onerror = ok; document.head.appendChild(s); });
            this.escanear();
            const apagar = () => { this.stream?.getTracks().forEach((t) => t.stop()); this.activa = false; };
            window.addEventListener('pagehide', apagar, { once: true });
        },
        async escanear() {
            if (!this.activa || !document.body.contains(this.$refs.video)) { this.stream?.getTracks().forEach((t) => t.stop()); return; }
            if (!this.ocupado && !(this.r && this.r.comensal)) {
                const v = this.$refs.video;
                try {
                    let texto = null;
                    if (this.detector) { const c = await this.detector.detect(v); texto = c[0]?.rawValue || null; }
                    else if (window.jsQR && v.videoWidth) {
                        this.lienzo ||= document.createElement('canvas');
                        const w = 480, h = Math.round(v.videoHeight * 480 / v.videoWidth);
                        this.lienzo.width = w; this.lienzo.height = h;
                        const ctx = this.lienzo.getContext('2d', { willReadFrequently: true }); ctx.drawImage(v, 0, 0, w, h);
                        const q = jsQR(ctx.getImageData(0, 0, w, h).data, w, h); texto = q?.data || null;
                    }
                    if (texto) this.leer(texto);
                } catch (e) {}
            }
            setTimeout(() => this.escanear(), 200);
        },
        async leer(codigo) {
            codigo = (codigo || '').trim(); if (!codigo) return;
            if (codigo === this.ultimo && Date.now() - this.ultimoT < 6000) return;
            this.ultimo = codigo; this.ultimoT = Date.now(); this.ocupado = true; this.aviso = null; this.monto = '';
            try { const d = await post(cfg.leer, { codigo }); this.r = d; try { navigator.vibrate?.(d.ok ? 80 : [60, 60, 60]); } catch (e) {} }
            catch (e) { this.r = { ok: false, mensaje: 'Sin conexión. Intenta otra vez.' }; }
            this.ocupado = false;
        },
        async accion(tipo, extra = {}) {
            this.ocupado = true;
            try {
                const d = await post(cfg[tipo], Object.assign({ pase: this.r.pase }, extra));
                if (d.comensal) this.r = d;
                this.aviso = { ok: d.ok, mensaje: d.mensaje };
                try { navigator.vibrate?.(d.ok ? 80 : [60, 60, 60]); } catch (e) {}
            } catch (e) { this.aviso = { ok: false, mensaje: 'Sin conexión. Intenta otra vez.' }; }
            this.ocupado = false;
        },
    };
};
</script>
@endpush
