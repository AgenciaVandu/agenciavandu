{{-- Estilos y lógica de la vista previa por red. Incluir una vez por página: @include('redes._previa-recursos', ['parte' => 'estilos'|'script']) --}}
@if(($parte ?? 'estilos') === 'estilos')
<style>
    .pv { --pv-ink: #0f1419; --pv-gris: #737373; --pv-linea: #dbdbdb; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    .pv .ms-auto { margin-left: auto; }
    .pv-redes { display: flex; gap: 6px; flex-wrap: wrap; justify-content: center; margin-bottom: 12px; }
    .pv-redes button { border: 1px solid #d9dce2; background: #fff; color: #3f4450; border-radius: 99px; padding: 5px 12px; font-size: 13px; font-weight: 500; display: inline-flex; gap: 6px; align-items: center; cursor: pointer; font-family: inherit; }
    .pv-redes button.activo { background: #13161D; color: #fff; border-color: #13161D; }
    .pv-tel { width: 100%; max-width: 380px; margin: 0 auto; background: #fff; color: var(--pv-ink); border: 1px solid #e3e5e9; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(15,18,25,.10); font-size: 14px; line-height: 1.35; text-align: left; }
    .pv-cab { display: flex; align-items: center; gap: 10px; padding: 10px 12px; }
    .pv-av { width: 34px; height: 34px; border-radius: 50%; background: #e9ebef; color: #3f4450; display: grid; place-items: center; font-size: 12px; font-weight: 700; overflow: hidden; flex: none; }
    .pv-av img { width: 100%; height: 100%; object-fit: cover; }
    .pv-av.aro { box-shadow: 0 0 0 2px #fff, 0 0 0 4px #e1306c; }
    .pv-av.cuadro { border-radius: 6px; width: 46px; height: 46px; }
    .pv-av.mini { width: 30px; height: 30px; font-size: 11px; }
    .pv-quien { flex: 1; min-width: 0; display: flex; flex-direction: column; }
    .pv-quien b { font-weight: 600; font-size: 14px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pv-quien small { color: var(--pv-gris); font-size: 12px; }
    .pv-mas { color: var(--pv-ink); font-size: 16px; }
    .pv-media { position: relative; background: #111; overflow: hidden; width: 100%; }
    .pv-slide, .pv-slide img, .pv-slide video { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: block; }
    .pv-vacio { position: absolute; inset: 0; display: grid; place-content: center; justify-items: center; gap: 6px; color: #9aa0ac; background: #f2f3f5; font-size: 13px; }
    .pv-vacio i { font-size: 30px; }
    .pv-cuenta { position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,.6); color: #fff; font-size: 12px; font-weight: 600; padding: 2px 8px; border-radius: 99px; }
    .pv-flecha { position: absolute; top: 50%; transform: translateY(-50%); width: 28px; height: 28px; border-radius: 50%; border: 0; background: rgba(255,255,255,.85); color: #111; display: grid; place-items: center; cursor: pointer; font-size: 13px; z-index: 3; }
    .pv-flecha.izq { left: 8px; } .pv-flecha.der { right: 8px; }
    .pv-acc { display: flex; align-items: center; gap: 16px; padding: 10px 12px 4px; font-size: 21px; }
    .pv-acc.fb, .pv-acc.li { justify-content: space-around; gap: 4px; font-size: 13px; font-weight: 600; color: #65676b; border-top: 1px solid #e4e6eb; margin: 0 12px; padding: 8px 0; }
    .pv-acc.fb i, .pv-acc.li i { font-size: 16px; margin-right: 4px; }
    .pv-acc.li { color: #5e5e5e; font-size: 12px; }
    .pv-puntos { display: flex; gap: 4px; position: absolute; left: 50%; transform: translateX(-50%); }
    .pv-acc.ig { position: relative; }
    .pv-puntos span { width: 6px; height: 6px; border-radius: 50%; background: #c7c7c7; }
    .pv-puntos span.on { background: #0095f6; }
    .pv-txt { padding: 4px 12px 2px; font-size: 14px; overflow-wrap: anywhere; }
    .pv-txt b { font-weight: 600; }
    .pv-txt.fb { padding: 2px 12px 10px; font-size: 15px; }
    .pv-txt.li { padding: 2px 12px 10px; }
    .pv-tag { color: #00376b; }
    .pv-facebook .pv-tag, .pv-linkedin .pv-tag { color: #0a66c2; font-weight: 600; }
    .pv-vermas { border: 0; background: none; padding: 0 0 0 4px; font: inherit; font-weight: 600; color: var(--pv-ink); cursor: pointer; }
    .pv-vermas.gris { color: var(--pv-gris); font-weight: 400; }
    .pv-vermas.blanco { color: #fff; }
    .pv-fecha { padding: 2px 12px 12px; font-size: 11px; color: var(--pv-gris); text-transform: uppercase; letter-spacing: .02em; }
    .pv-li-seguir { color: #0a66c2; font-weight: 600; font-size: 13px; }
    .pv-cab.li { align-items: flex-start; }

    /* Verticales: reels, historias, TikTok */
    .pv-vert { position: relative; background: #000; color: #fff; }
    .pv-vert .pv-media { aspect-ratio: 9 / 16; }
    .pv-sombra { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,.35) 0, transparent 18%, transparent 55%, rgba(0,0,0,.7) 100%); pointer-events: none; }
    .pv-top { position: absolute; top: 12px; left: 0; right: 0; display: flex; justify-content: center; gap: 18px; font-size: 15px; color: rgba(255,255,255,.75); }
    .pv-top b { color: #fff; border-bottom: 2px solid #fff; padding-bottom: 3px; }
    .pv-top.izq { justify-content: flex-start; padding-left: 14px; }
    .pv-top.izq b { border: 0; font-size: 18px; }
    .pv-lado { position: absolute; right: 8px; bottom: 90px; display: flex; flex-direction: column; gap: 14px; align-items: center; }
    .pv-lado span { display: flex; flex-direction: column; align-items: center; font-size: 26px; text-shadow: 0 1px 3px rgba(0,0,0,.4); }
    .pv-lado small { font-size: 10px; margin-top: 1px; }
    .pv-abajo { position: absolute; left: 12px; right: 60px; bottom: 14px; }
    .pv-quien-v { display: flex; align-items: center; gap: 8px; font-size: 14px; }
    .pv-seguir { border: 1px solid rgba(255,255,255,.8); border-radius: 8px; padding: 1px 8px; font-size: 12px; font-weight: 600; }
    .pv-cap { font-size: 13.5px; margin-top: 6px; overflow-wrap: anywhere; }
    .pv-vert .pv-tag { color: #fff; font-weight: 600; }
    .pv-audio { font-size: 12px; margin-top: 6px; opacity: .9; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pv-hist { position: absolute; inset: 0; pointer-events: none; }
    .pv-barras { position: absolute; top: 8px; left: 8px; right: 8px; display: flex; gap: 3px; }
    .pv-barras span { flex: 1; height: 2.5px; border-radius: 2px; background: rgba(255,255,255,.4); }
    .pv-barras span.on { background: #fff; }
    .pv-hist-cab { position: absolute; top: 20px; left: 10px; display: flex; align-items: center; gap: 8px; font-size: 13.5px; }
    .pv-hist-cab small { opacity: .7; }
    .pv-hist-pie { position: absolute; bottom: 12px; left: 10px; right: 10px; display: flex; align-items: center; gap: 14px; font-size: 22px; }
    .pv-hist-pie span { flex: 1; border: 1px solid rgba(255,255,255,.7); border-radius: 99px; padding: 8px 14px; font-size: 13px; }
    .pv-tiktok .pv-lado .pv-av { border: 2px solid #fff; }
    .pv-nota { text-align: center; font-size: 12px; color: #8a90a0; margin: 10px 0 0; }

    /* Perfil de Instagram (moodboard) */
    .ig-perfil { max-width: 420px; margin: 0 auto; background: #fff; color: #0f1419; border: 1px solid #e3e5e9; border-radius: 16px; overflow: hidden; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    .ig-perfil .usuario { text-align: center; font-weight: 700; font-size: 16px; padding: 12px; border-bottom: 1px solid #efefef; }
    .ig-perfil .datos { display: flex; align-items: center; gap: 18px; padding: 16px; }
    .ig-perfil .datos .pv-av { width: 78px; height: 78px; font-size: 24px; }
    .ig-perfil .cifras { flex: 1; display: grid; grid-template-columns: repeat(3, 1fr); text-align: center; font-size: 13px; }
    .ig-perfil .cifras b { display: block; font-size: 16px; }
    .ig-perfil .bio { padding: 0 16px 12px; font-size: 14px; white-space: pre-line; }
    .ig-perfil .bio b { display: block; }
    .ig-perfil .bio a { color: #00376b; text-decoration: none; font-weight: 600; }
    .ig-perfil .tabs { display: flex; justify-content: space-around; border-top: 1px solid #efefef; font-size: 20px; padding: 8px 0; color: #8e8e8e; }
    .ig-perfil .tabs .on { color: #0f1419; }
    .ig-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2px; }
    .ig-cel { position: relative; aspect-ratio: 3 / 4; background: #eee; overflow: hidden; border: 0; padding: 0; cursor: pointer; display: block; width: 100%; }
    .ig-cel img, .ig-cel video { width: 100%; height: 100%; object-fit: cover; display: block; }
    .ig-cel .ico { position: absolute; top: 6px; right: 6px; color: #fff; font-size: 15px; text-shadow: 0 1px 3px rgba(0,0,0,.5); }
    .ig-cel .vacia { position: absolute; inset: 0; display: grid; place-items: center; color: #9aa0ac; font-size: 22px; }
    .ig-cel .est { position: absolute; left: 6px; bottom: 6px; font-size: 10.5px; font-weight: 700; color: #fff; padding: 1px 7px; border-radius: 99px; }
    .ig-cel.futuro::after { content: ''; position: absolute; inset: 0; box-shadow: inset 0 0 0 2px rgba(47,111,235,.0); }
    .ig-cel.arrastrando { opacity: .4; }
    .ig-cel.destino { outline: 3px solid #2F6FEB; outline-offset: -3px; }
</style>
@else
<script>
// Lógica compartida de la vista previa: se mezcla en componentes Alpine que tengan `post` y `perfiles`
window.redesVista = (redes) => ({
    pvRed: null, pvI: 0, pvAbierto: false, pvToque: null, pvRedesInfo: redes,
    pvRedes() { return ((this.post && this.post.redes) || []).filter((r) => this.pvRedesInfo[r]); },
    pvAjustar() {
        const rs = this.pvRedes();
        if (!rs.includes(this.pvRed)) { this.pvRed = rs[0] || 'instagram'; this.pvI = 0; }
        if (this.pvI >= Math.max(1, this.pvMedios().length)) this.pvI = 0;
    },
    pvInfo(r) { return this.pvRedesInfo[r] || { nombre: r, icono: 'bi-globe' }; },
    pvPerfil() { return (this.perfiles && this.perfiles[this.pvRed]) || { usuario: '', nombre: '', iniciales: '' }; },
    pvFormato() {
        let f = (this.post && this.post.formato) || 'post';
        if (this.pvRed === 'tiktok' && f !== 'carrusel') f = 'video';
        if (this.pvRed === 'linkedin' && (f === 'reel' || f === 'historia')) f = 'video';
        return f;
    },
    pvVertical() {
        const f = this.pvFormato();
        return this.pvRed === 'tiktok' || ((this.pvRed === 'instagram' || this.pvRed === 'facebook') && (f === 'reel' || f === 'historia'));
    },
    pvMedios() { return (this.post && this.post.medios) || []; },
    pvAspecto(vertical) {
        if (vertical) return '9 / 16';
        const m = this.pvMedios()[0];
        let a = m && m.ancho && m.alto ? m.ancho / m.alto : (this.pvRed === 'linkedin' ? 1.2 : 0.8);
        a = this.pvRed === 'instagram' ? Math.min(1.91, Math.max(0.8, a)) : Math.min(1.91, Math.max(0.56, a));
        return a.toFixed(3);
    },
    pvEsc(t) { return String(t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]); },
    pvTexto(n) {
        let t = (this.post && this.post.texto) || '';
        if (!this.pvAbierto && t.length > n) t = t.slice(0, n).trimEnd() + '…';
        return this.pvEsc(t).replace(/(^|\s)([#@][\p{L}\p{N}_.]+)/gu, '$1<span class="pv-tag">$2</span>').replace(/\n/g, '<br>');
    },
    pvLargo(n) { return ((this.post && this.post.texto) || '').length > n; },
    pvCuando(corto) {
        const f = this.post && this.post.fecha ? new Date(this.post.fecha) : new Date();
        if (isNaN(f)) return '';
        return corto ? f.toLocaleDateString('es-MX', { day: 'numeric', month: 'long' })
                     : f.toLocaleDateString('es-MX', { day: 'numeric', month: 'short' }) + ' a las ' + f.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
    },
    pvDeslizar(x) {
        if (this.pvToque === null) return;
        const dx = x - this.pvToque; this.pvToque = null;
        const n = this.pvMedios().length;
        if (Math.abs(dx) > 40 && n > 1) this.pvI = Math.max(0, Math.min(n - 1, this.pvI + (dx < 0 ? 1 : -1)));
    },
});
</script>
@endif
