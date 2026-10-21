@extends('admin.layout')
@section('titulo', 'Escanear')
@include('admin.fitness._estilos')

@push('head')
<style>
    .esc { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 24px; align-items: start; }
    .camara { position: relative; aspect-ratio: 1 / 1; max-height: 70vh; background: #0b0d12; border-radius: 16px; overflow: hidden; display: grid; place-items: center; }
    .camara video { width: 100%; height: 100%; object-fit: cover; }
    .camara .marco { position: absolute; inset: 18%; border: 3px solid rgba(255,255,255,.85); border-radius: 18px; box-shadow: 0 0 0 999px rgba(0,0,0,.35); pointer-events: none; }
    .camara .msg { position: absolute; bottom: 14px; left: 0; right: 0; text-align: center; color: #fff; font-size: 14px; }
    .camara .iniciar { position: absolute; inset: 0; display: grid; place-items: center; color: #fff; text-align: center; padding: 24px; }
    .resultado { border-radius: 16px; padding: 22px; color: #fff; text-align: center; }
    .resultado.ok { background: #047A4B; } .resultado.no { background: #B42318; } .resultado.aviso { background: #9A5300; }
    .resultado .foto { width: 132px; height: 132px; border-radius: 50%; object-fit: cover; border: 4px solid rgba(255,255,255,.85); margin: 0 auto 12px; display: grid; place-items: center; background: rgba(255,255,255,.18); font-size: 40px; font-weight: 650; }
    .resultado h2 { font-size: 26px; margin: 0; font-weight: 650; }
    .resultado .n { font-size: 20px; font-weight: 600; margin-top: 8px; }
    .resultado .d { opacity: .9; font-size: 14px; margin-top: 2px; }
    .resultado .btn-op { background: #fff; color: var(--ink); border: 0; border-radius: 10px; padding: 10px 14px; font-weight: 600; width: 100%; margin-top: 8px; }
    @media (max-width: 991.98px) { .esc { grid-template-columns: minmax(0, 1fr); } }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div><h1>Escanear entrada</h1><p class="sub">Apunta la cámara a la credencial QR del atleta. {{ $sucursal ? 'Sucursal: ' . $sucursal->nombre . '.' : '' }}</p></div>
</div>

<div class="esc" x-data="fxEscaner({{ Js::from(['checkin' => route('admin.escanear.checkin'), 'inscribir' => route('admin.escanear.inscribir'), 'token' => csrf_token()]) }})" x-init="iniciar()">
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
        <form class="d-flex gap-2 mt-3" @submit.prevent="enviar(manual); manual = ''">
            <input class="form-control" x-model="manual" placeholder="O escribe / usa un lector: FIT:…" aria-label="Código de la credencial" autocomplete="off">
            <button class="btn btn-borde">Revisar</button>
        </form>
    </div>

    <div class="d-grid gap-3">
        <template x-if="r">
            <div class="resultado" :class="r.ok ? 'ok' : (r.tipo === 'sin_reserva' || r.tipo === 'repetido' ? 'aviso' : 'no')" role="status" aria-live="assertive">
                <template x-if="r.atleta">
                    <div>
                        <template x-if="r.atleta.foto"><img class="foto" :src="r.atleta.foto" alt=""></template>
                        <template x-if="! r.atleta.foto"><div class="foto" x-text="r.atleta.iniciales"></div></template>
                    </div>
                </template>
                <h2><i class="bi" :class="r.ok ? 'bi-check-circle-fill' : 'bi-exclamation-octagon-fill'"></i> <span x-text="r.mensaje"></span></h2>
                <div class="n" x-show="r.atleta" x-text="r.atleta?.nombre"></div>
                <div class="d" x-show="r.clase" x-text="r.clase ? r.clase.nombre + ' · ' + r.clase.horario : ''"></div>
                <div class="d" x-show="r.atleta?.paquete" x-text="r.atleta?.paquete"></div>
                <div class="d" x-show="r.atleta?.vence" x-text="r.atleta?.vence"></div>
                <template x-for="o in (r.opciones || [])" :key="o.id">
                    <button type="button" class="btn-op" @click="inscribir(o.id)" x-text="'Inscribir y dejar pasar · ' + o.nombre + ' ' + o.horario + ' (' + o.libres + ' libres)'"></button>
                </template>
                <a class="d-block mt-3 text-white small" x-show="r.atleta" :href="r.atleta?.url">Ver su ficha</a>
            </div>
        </template>
        <template x-if="! r">
            <div class="panel panel-body text-center secundario"><i class="bi bi-qr-code-scan fs-1 d-block mb-2"></i>Aquí aparece la foto y si puede pasar.</div>
        </template>

        <section class="panel">
            <div class="panel-head"><h2>Clases de ahora</h2></div>
            <div class="fx-lista">
                @forelse($clases as $c)
                    <a href="{{ route('admin.clases.show', $c) }}" class="fx-fila text-reset text-decoration-none">
                        <span class="fx-color" style="background: {{ $c->color }}"></span>
                        <div class="flex-grow-1 min-w-0"><div class="principal">{{ $c->nombre }}</div><div class="secundario small">{{ $c->horario }}{{ $c->coach ? ' · ' . $c->coach->primer_nombre : '' }}</div></div>
                        <x-fitness.ocupacion :ocupados="$c->ocupadas_count" :cupo="$c->cupo" style="width: 110px" />
                    </a>
                @empty
                    <div class="panel-body secundario small">No hay clases con la entrada abierta en este momento.</div>
                @endforelse
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
window.fxEscaner = function (cfg) {
    return {
        activa: false, ocupado: false, error: null, r: null, manual: '', ultimo: '', ultimoT: 0, detector: null, stream: null, lienzo: null, limpiar: null,
        async iniciar() {
            if (this.activa) return;
            if (!navigator.mediaDevices?.getUserMedia) { this.error = 'Este navegador no permite usar la cámara aquí. Escribe el código abajo.'; return; }
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
            } catch (e) { this.error = 'No se pudo abrir la cámara. Revisa el permiso del navegador.'; return; }
            const v = this.$refs.video; v.srcObject = this.stream; await v.play().catch(() => {});
            this.activa = true; this.error = null;
            if ('BarcodeDetector' in window) {
                try { this.detector = new BarcodeDetector({ formats: ['qr_code'] }); } catch (e) { this.detector = null; }
            }
            if (!this.detector && !window.jsQR) await new Promise((ok) => { const s = document.createElement('script'); s.src = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js'; s.onload = ok; s.onerror = ok; document.head.appendChild(s); });
            this.leer();
            // Al salir de la página se apaga la cámara
            const apagar = () => { this.stream?.getTracks().forEach((t) => t.stop()); this.activa = false; };
            window.addEventListener('pagehide', apagar, { once: true });
            document.addEventListener('vandu:navegar', apagar, { once: true });
        },
        async leer() {
            if (!this.activa || !document.body.contains(this.$refs.video)) { this.stream?.getTracks().forEach((t) => t.stop()); return; }
            if (!this.ocupado) {
                const v = this.$refs.video;
                try {
                    let texto = null;
                    if (this.detector) {
                        const c = await this.detector.detect(v); texto = c[0]?.rawValue || null;
                    } else if (window.jsQR && v.videoWidth) {
                        this.lienzo ||= document.createElement('canvas');
                        const w = 480, h = Math.round(v.videoHeight * 480 / v.videoWidth);
                        this.lienzo.width = w; this.lienzo.height = h;
                        const ctx = this.lienzo.getContext('2d', { willReadFrequently: true }); ctx.drawImage(v, 0, 0, w, h);
                        const q = jsQR(ctx.getImageData(0, 0, w, h).data, w, h); texto = q?.data || null;
                    }
                    if (texto) this.enviar(texto);
                } catch (e) {}
            }
            setTimeout(() => this.leer(), 180);
        },
        async enviar(codigo) {
            codigo = (codigo || '').trim(); if (!codigo) return;
            // El mismo código en pocos segundos no se vuelve a mandar
            if (codigo === this.ultimo && Date.now() - this.ultimoT < 6000) return;
            this.ultimo = codigo; this.ultimoT = Date.now(); this.ocupado = true;
            try {
                const res = await fetch(cfg.checkin, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.token }, body: JSON.stringify({ codigo }) });
                this.mostrar(await res.json());
            } catch (e) { this.mostrar({ ok: false, tipo: 'error', mensaje: 'Sin conexión. Intenta otra vez.' }); }
            this.ocupado = false;
        },
        async inscribir(claseId) {
            this.ocupado = true;
            try {
                const res = await fetch(cfg.inscribir, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.token }, body: JSON.stringify({ cliente_id: this.r.atleta.id, clase_id: claseId }) });
                const d = await res.json(); if (!d.atleta) d.atleta = this.r.atleta; this.mostrar(d);
            } catch (e) {}
            this.ocupado = false;
        },
        mostrar(d) {
            this.r = d;
            try { navigator.vibrate?.(d.ok ? 80 : [60, 60, 60]); } catch (e) {}
            clearTimeout(this.limpiar);
            // Si hay que decidir algo (inscribirlo), se queda en pantalla
            if (!(d.opciones || []).length) this.limpiar = setTimeout(() => { this.r = null; }, 6000);
        },
    };
};
</script>
@endpush
