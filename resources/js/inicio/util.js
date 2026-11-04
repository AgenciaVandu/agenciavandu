// Herramientas compartidas por el inicio, la barra y el botón de WhatsApp.
export const html = document.documentElement;
export const reducir = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
export const mouse = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
export const limitar = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
export const n2 = (n) => String(n).padStart(2, '0');

// Letras al azar que se van acomodando hasta dejar el texto real
export const SIGNOS_MIN = 'abcdefghijklmnopqrstuvwxyz0123456789';
export const SIGNOS_MAY = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789/<>_#';
const fijo = /[\s.,;:¿?¡!·()"“”'—–-]/;
const mezclar = (texto, p, signos) => {
    const quedan = Math.floor(texto.length * p);
    let r = '';
    for (let k = 0; k < texto.length; k++) {
        const c = texto[k];
        r += (k < quedan || fijo.test(c)) ? c : signos[Math.floor(Math.random() * signos.length)];
    }
    return r;
};

// Descifra los nodos de texto de un elemento (sus etiquetas internas, como el punto verde, se respetan)
export const descifrar = (el, { dur, signos = SIGNOS_MIN, alTerminar } = {}) => {
    if (el._vxAnim) cancelAnimationFrame(el._vxAnim);
    if (!el._vxTextos) {
        const nodos = [];
        const w = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
        while (w.nextNode()) if (w.currentNode.nodeValue.trim()) nodos.push(w.currentNode);
        el._vxTextos = nodos.map((n) => [n, n.nodeValue]);
    }
    const total = el._vxTextos.reduce((s, [, t]) => s + t.length, 0);
    const duracion = dur ?? Math.min(1500, 450 + total * 22);
    if (reducir) { alTerminar?.(); return; }
    const t0 = performance.now();
    let ultimo = 0;
    const paso = (t) => {
        if (!el._vxTextos) return;
        const p = limitar((t - t0) / duracion);
        if (t - ultimo > 45 || p === 1) {          // ~22 cambios por segundo: se lee como "decodificando"
            ultimo = t;
            el._vxTextos.forEach(([n, original]) => { n.nodeValue = p === 1 ? original : mezclar(original, p, signos); });
        }
        if (p < 1) el._vxAnim = requestAnimationFrame(paso);
        else { el._vxAnim = null; alTerminar?.(); }
    };
    el._vxTextos.forEach(([n, original]) => { n.nodeValue = mezclar(original, 0, signos); });
    el._vxAnim = requestAnimationFrame(paso);
};

// Olvida el texto guardado (cuando el elemento cambia de texto a propósito)
export const olvidar = (el) => {
    if (el._vxAnim) { cancelAnimationFrame(el._vxAnim); el._vxAnim = null; }
    el._vxTextos = null;
};

// Hora de Mérida en todos los [data-reloj] (data-reloj="segundos" la muestra con segundos). Un solo reloj por página.
export const iniciarRelojes = () => {
    if (window.vxRelojes) return;
    window.vxRelojes = true;
    const hora = (seg) => {
        try { return new Date().toLocaleTimeString('es-MX', { timeZone: 'America/Merida', hour: '2-digit', minute: '2-digit', ...(seg ? { second: '2-digit' } : {}), hour12: false }); }
        catch (e) { return ''; }
    };
    const tic = () => document.querySelectorAll('[data-reloj]').forEach((r) => { r.textContent = hora(r.dataset.reloj === 'segundos'); });
    tic();
    setInterval(tic, 1000);
};

// Arranca una función cuando el DOM está listo
export const alCargar = (fn) => {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
};
