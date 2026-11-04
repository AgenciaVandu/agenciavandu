// Nerby: pestañas y la demo de lectura del ticket con IA.
import { reducir } from './util';

export default function nerby() {
    const nerby = document.querySelector('[data-nerby]');
    if (nerby) {
        const tabs = [...nerby.querySelectorAll('[data-ntab]')];
        const paneles = [...nerby.querySelectorAll('[data-npanel]')];
        const nombreVentana = nerby.querySelector('[data-nerby-ventana]');
        const elegir = (k, enfocar) => {
            tabs.forEach((t, i) => { t.classList.toggle('vx-on', i === k); t.setAttribute('aria-selected', i === k ? 'true' : 'false'); t.tabIndex = i === k ? 0 : -1; });
            paneles.forEach((p, i) => { p.hidden = i !== k; });
            if (nombreVentana) nombreVentana.textContent = tabs[k].dataset.ventana;
            if (enfocar) tabs[k].focus();
        };
        tabs.forEach((t, k) => {
            t.tabIndex = k === 0 ? 0 : -1;
            t.addEventListener('click', () => elegir(k));
            t.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowDown' || e.key === 'ArrowRight') { e.preventDefault(); elegir((k + 1) % tabs.length, true); }
                if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') { e.preventDefault(); elegir((k + tabs.length - 1) % tabs.length, true); }
            });
        });

        const ticket = nerby.querySelector('[data-ticket]');
        const hoja = ticket?.querySelector('.vx-ticket');
        const campos = [...(ticket?.querySelectorAll('[data-campo]') || [])];
        const aviso = ticket?.querySelector('[data-ticket-aviso]');
        const leer = ticket?.querySelector('[data-ticket-leer]');
        const ok = ticket?.querySelector('[data-ticket-ok]');
        let leyendo = false;
        const escribir = (el, texto, ms) => new Promise((listo) => {
            if (reducir || !texto) { el.textContent = texto; return listo(); }
            let i = 0;
            const t = setInterval(() => { el.textContent = texto.slice(0, ++i); if (i >= texto.length) { clearInterval(t); listo(); } }, ms / Math.max(1, texto.length));
        });
        const esperar = (ms) => new Promise((r) => setTimeout(r, reducir ? 0 : ms));
        const leerTicket = async () => {
            if (leyendo || !ticket) return;
            leyendo = true;
            leer.disabled = true; leer.textContent = 'Leyendo…'; ok.hidden = true;
            aviso.textContent = 'Leyendo el ticket…';
            campos.forEach((c) => { c.textContent = ''; c.classList.remove('vx-lleno', 'vx-duda'); });
            hoja.classList.remove('vx-leyendo'); void hoja.offsetWidth; hoja.classList.add('vx-leyendo');
            for (const c of campos) {
                await esperar(260);
                if (!c.dataset.valor) continue;
                c.classList.add('vx-lleno');
                await escribir(c, c.dataset.valor, 280);
            }
            await esperar(300);
            campos.filter((c) => !c.dataset.valor).forEach((c) => { c.textContent = '—'; c.classList.add('vx-duda'); });
            aviso.textContent = 'La forma de pago no se lee bien en la foto: revísala antes de guardar.';
            hoja.classList.remove('vx-leyendo');
            leer.disabled = false; leer.textContent = 'Leer otro ticket'; ok.hidden = false;
            leyendo = false;
        };
        leer?.addEventListener('click', leerTicket);
        ok?.addEventListener('click', () => { aviso.textContent = 'Listo: así se guarda el gasto en Nerby, con la foto adjunta.'; ok.hidden = true; });
        // La primera lectura arranca sola cuando la ventana se ve
        if (ticket) {
            const auto = new IntersectionObserver(([e]) => {
                if (!e.isIntersecting) return;
                auto.disconnect();
                setTimeout(leerTicket, 700);
            }, { threshold: 0.5 });
            auto.observe(ticket);
        }
    }
}
