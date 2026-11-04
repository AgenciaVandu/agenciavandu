// Inicio de agenciavandu.com: barra flotante, titulares que se descifran al aparecer, planos que se
// construyen, demo de Nerby, contacto paso a paso, mapa de la página, buscador (tecla /),
// botón de WhatsApp y cursor propio.
import Lenis from 'lenis';

const html = document.documentElement;
const reducir = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const mouse = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
const limitar = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
const n2 = (n) => String(n).padStart(2, '0');
window.vxListo = true;

// Letras al azar que se van acomodando hasta dejar el texto real
const SIGNOS_MIN = 'abcdefghijklmnopqrstuvwxyz0123456789';
const SIGNOS_MAY = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789/<>_#';
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
const descifrar = (el, { dur, signos = SIGNOS_MIN, alTerminar } = {}) => {
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

const iniciar = () => {
    // ---------- Scroll suave ----------
    let lenis = null;
    if (!reducir) {
        lenis = new Lenis({ lerp: 0.1, smoothWheel: true });
        const raf = (t) => { lenis.raf(t); requestAnimationFrame(raf); };
        requestAnimationFrame(raf);
    }
    const irA = (selector) => {
        const destino = selector === '#inicio' ? 0 : document.querySelector(selector);
        if (destino === null) return false;
        if (lenis) lenis.scrollTo(destino, { offset: -90 });
        else if (destino === 0) window.scrollTo({ top: 0 });
        else destino.scrollIntoView();
        return true;
    };
    document.addEventListener('click', (e) => {
        const a = e.target.closest('a[href^="#"]');
        if (!a || a.getAttribute('href').length < 2) return;
        if (irA(a.getAttribute('href'))) { e.preventDefault(); cerrarMenus(); }
    });

    // ---------- Reloj de Mérida ----------
    const relojes = document.querySelectorAll('[data-reloj]');
    const hora = (seg) => {
        try { return new Date().toLocaleTimeString('es-MX', { timeZone: 'America/Merida', hour: '2-digit', minute: '2-digit', ...(seg ? { second: '2-digit' } : {}), hour12: false }); }
        catch (e) { return ''; }
    };
    const tic = () => relojes.forEach((r) => { r.textContent = hora(!r.closest('[data-wa]')); });
    tic(); setInterval(tic, 1000);

    // ---------- Barra: luz que se desliza, texto que se descifra y menú de servicios ----------
    const cab = document.querySelector('[data-cab]');
    const menu = cab?.querySelector('[data-menu]');
    const mega = cab?.querySelector('[data-mega]');
    const disparadorMega = menu?.querySelector('[data-menu-item="0"]');
    const abrirMega = (si) => {
        if (!mega) return;
        mega.classList.toggle('vx-abierto', si);
        disparadorMega?.setAttribute('aria-expanded', si ? 'true' : 'false');
    };
    menu?.querySelectorAll('[data-menu-item]').forEach((a) => {
        const k = Number(a.dataset.menuItem);
        const texto = a.querySelector('[data-descifra-hover]');
        const encender = () => {
            menu.style.setProperty('--luz', (k * 100) + '%');
            menu.classList.add('vx-con-luz');
            if (texto && a._vxK !== true) { a._vxK = true; descifrar(texto, { dur: 450, signos: SIGNOS_MAY }); }
            abrirMega(k === 0);
        };
        a.addEventListener('mouseenter', encender);
        a.addEventListener('focus', encender);
        a.addEventListener('mouseleave', () => { a._vxK = false; });
        a.addEventListener('blur', () => { a._vxK = false; });
    });
    // Al salir de la barra se cierra con una pausa corta, para poder bajar del menú al panel sin que se cierre
    let cierre = null;
    cab?.addEventListener('mouseleave', () => { cierre = setTimeout(() => { menu?.classList.remove('vx-con-luz'); abrirMega(false); }, 180); });
    cab?.addEventListener('mouseenter', () => clearTimeout(cierre));
    mega?.addEventListener('mouseenter', () => clearTimeout(cierre));
    mega?.addEventListener('mouseleave', () => { cierre = setTimeout(() => { menu?.classList.remove('vx-con-luz'); abrirMega(false); }, 180); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && mega?.classList.contains('vx-abierto')) { abrirMega(false); disparadorMega?.focus(); } });
    cab?.addEventListener('focusout', (e) => { if (!cab.contains(e.relatedTarget)) { menu?.classList.remove('vx-con-luz'); abrirMega(false); } });

    // Menú en celular
    const hamburguesa = cab?.querySelector('[data-movil-abrir]');
    const movil = cab?.querySelector('[data-movil]');
    hamburguesa?.addEventListener('click', () => {
        const abrir = !movil.classList.contains('vx-abierto');
        movil.classList.toggle('vx-abierto', abrir);
        hamburguesa.setAttribute('aria-expanded', abrir ? 'true' : 'false');
        hamburguesa.setAttribute('aria-label', abrir ? 'Cerrar menú' : 'Abrir menú');
    });
    function cerrarMenus() {
        abrirMega(false);
        movil?.classList.remove('vx-abierto');
        hamburguesa?.setAttribute('aria-expanded', 'false');
    }

    // ---------- Titulares h1–h5: se descifran una sola vez, cuando aparecen ----------
    const titulares = [...document.querySelectorAll('[data-descifra]')];
    const verTitulares = new IntersectionObserver((entradas) => entradas.forEach(({ target, isIntersecting }) => {
        if (!isIntersecting) return;
        verTitulares.unobserve(target);
        target.classList.add('vx-listo');
        descifrar(target);
    }), { threshold: 0.35 });
    titulares.forEach((t) => {
        t.setAttribute('aria-label', t.textContent.replace(/\s+/g, ' ').trim()); // lectores de pantalla leen el texto real
        verTitulares.observe(t);
    });
    const etiqueta = document.querySelector('[data-descifra-carga]');
    if (etiqueta) descifrar(etiqueta, { dur: 1100, signos: SIGNOS_MAY });

    // ---------- Apariciones ----------
    const contar = new IntersectionObserver((entradas) => entradas.forEach(({ target, isIntersecting }) => {
        if (!isIntersecting) return;
        target.classList.add('vx-dentro');
        contar.unobserve(target);
    }), { threshold: 0, rootMargin: '0px 0px -8% 0px' });   // umbral 0: las piezas que se destapan empiezan casi recortadas
    document.querySelectorAll('[data-aparece]').forEach((el) => {
        const hermanos = [...el.parentElement.children].filter((x) => x.hasAttribute('data-aparece'));
        const i = hermanos.indexOf(el);
        if (i > 0) el.style.transitionDelay = Math.min(i, 6) * 0.07 + 's';
        contar.observe(el);
    });

    // ---------- Videos: cargan y se reproducen solo cuando se ven ----------
    const videos = new IntersectionObserver((entradas) => entradas.forEach(({ target: v, isIntersecting }) => {
        if (isIntersecting) { if (!v.src) v.src = v.dataset.src; if (!reducir) v.play().catch(() => {}); }
        else v.pause();
    }), { threshold: 0.25 });
    document.querySelectorAll('video[data-src]').forEach((v) => videos.observe(v));

    // ---------- Planos que se construyen ----------
    const planos = [...document.querySelectorAll('[data-plano]')];
    const contador = document.querySelector('[data-planos-contador]');
    const avance = document.querySelector('[data-planos-avance]');
    const DUR = 3800;
    let actual = 0, encima = -1, t0 = performance.now(), planosVisibles = false;
    const pintar = () => {
        const activo = encima >= 0 ? encima : actual;
        planos.forEach((p, k) => p.classList.toggle('vx-on', k === activo));
        if (contador) contador.textContent = n2(activo + 1) + ' / ' + n2(planos.length);
    };
    planos.forEach((p, k) => {
        p.addEventListener('mouseenter', () => { if (!mouse) return; encima = k; pintar(); });
        p.addEventListener('mouseleave', () => { if (!mouse) return; encima = -1; actual = k; t0 = performance.now(); pintar(); });
    });
    if (planos.length) {
        new IntersectionObserver(([e]) => { planosVisibles = e.isIntersecting; }, { threshold: 0.1 }).observe(planos[0].closest('[data-planos]'));
        pintar();
        if (reducir) { planos.forEach((p) => p.classList.add('vx-on')); }
        else {
            const vuelta = (t) => {
                if (encima >= 0 || !planosVisibles || document.hidden) t0 = t - (avance ? Number(avance.dataset.p || 0) * DUR : 0);
                const p = limitar((t - t0) / DUR);
                if (avance) { avance.dataset.p = p; avance.style.setProperty('--p', p.toFixed(4)); }
                if (p >= 1) { actual = (actual + 1) % planos.length; t0 = t; if (avance) avance.dataset.p = 0; pintar(); }
                requestAnimationFrame(vuelta);
            };
            requestAnimationFrame(vuelta);
        }
    }

    // ---------- Nerby: pestañas y lectura del ticket ----------
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

    // ---------- Contacto paso a paso ----------
    const conv = document.querySelector('[data-conv]');
    if (conv) {
        const pasos = [...conv.querySelectorAll('[data-paso]')];
        const rayas = [...conv.querySelectorAll('.vx-conv-pasos i')];
        const sig = conv.querySelector('[data-conv-sig]');
        const atras = conv.querySelector('[data-conv-atras]');
        const negocio = conv.querySelector('[data-negocio]');
        const burbuja = conv.querySelector('[data-conv-mensaje]');
        const enviar = conv.querySelector('[data-conv-enviar]');
        const estado = { paso: 0, necesidad: null, servicio: null, cuando: null };
        const valido = () => (estado.paso === 0 && estado.necesidad) || (estado.paso === 1 && negocio.value.trim()) || (estado.paso === 2 && estado.cuando);
        const mostrar = (k, enfocar = true) => {
            estado.paso = k;
            pasos.forEach((p) => { p.hidden = Number(p.dataset.paso) !== k; });
            rayas.forEach((r, i) => r.classList.toggle('vx-ok', i < Math.min(k, 3)));
            atras.hidden = k === 0;
            sig.hidden = k >= 3;
            sig.disabled = !valido();
            if (k === 3) {
                const nombre = negocio.value.trim();
                const nec = estado.necesidad === 'Nerby' ? 'conocer Nerby' : estado.necesidad.toLowerCase();
                const texto = `Hola, soy de ${nombre}.\nNecesito: ${nec}.\nPara: ${estado.cuando.toLowerCase()}.`;
                burbuja.textContent = texto;
                enviar.dataset.servicio = estado.servicio;
                enviar.dataset.empresa = nombre;
                enviar.dataset.mensaje = `Necesito: ${nec}. Para: ${estado.cuando.toLowerCase()}.`;
                enviar.href = 'https://wa.me/529991460310?text=' + encodeURIComponent(texto.replace(/\n/g, ' '));
            }
            if (enfocar) {
                const destino = k === 1 ? negocio : pasos[k]?.querySelector('button, a, input');
                destino?.focus({ preventScroll: true });
            }
        };
        const elegirEn = (selector, clave, extra) => conv.querySelectorAll(selector).forEach((b) => b.addEventListener('click', () => {
            conv.querySelectorAll(selector).forEach((x) => x.setAttribute('aria-pressed', x === b ? 'true' : 'false'));
            estado[clave] = b.dataset[clave];
            extra?.(b);
            mostrar(estado.paso + 1);
        }));
        elegirEn('[data-necesidad]', 'necesidad', (b) => { estado.servicio = b.dataset.servicio; });
        elegirEn('[data-cuando]', 'cuando');
        negocio.addEventListener('input', () => { sig.disabled = !valido(); });
        negocio.addEventListener('keydown', (e) => { if (e.key === 'Enter' && valido()) { e.preventDefault(); mostrar(2); } });
        sig.addEventListener('click', () => { if (valido()) mostrar(estado.paso + 1); });
        atras.addEventListener('click', () => mostrar(estado.paso === 4 ? 3 : Math.max(0, estado.paso - 1)));
        conv.querySelector('[data-llamar]')?.addEventListener('click', () => mostrar(4));
        mostrar(0, false);
    }

    // ---------- Mapa de la página ----------
    const riel = document.querySelector('.vx-riel');
    if (riel) {
        const enlaces = [...riel.querySelectorAll('[data-riel]')];
        const marcar = (ref) => enlaces.forEach((a) => a.classList.toggle('vx-on', a.dataset.riel === ref));
        const secciones = enlaces.map((a) => document.querySelector(a.dataset.riel)).filter(Boolean);
        const io = new IntersectionObserver((entradas) => entradas.forEach((e) => { if (e.isIntersecting) marcar('#' + e.target.id); }), { rootMargin: '-45% 0px -50% 0px' });
        secciones.forEach((s) => io.observe(s));
        marcar('#inicio');
    }

    // ---------- Buscador rápido ----------
    const paleta = document.querySelector('[data-paleta]');
    if (paleta) {
        const q = paleta.querySelector('[data-paleta-q]');
        const ops = [...paleta.querySelectorAll('[data-paleta-op]')];
        const nada = paleta.querySelector('[data-paleta-nada]');
        let sel = 0, antes = null;
        const visibles = () => ops.filter((o) => !o.hidden);
        const marcar = () => visibles().forEach((o, i) => { o.classList.toggle('vx-on', i === sel); if (i === sel) o.scrollIntoView({ block: 'nearest' }); });
        const normal = (t) => t.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
        const filtrar = () => {
            const t = normal(q.value);
            ops.forEach((o) => { o.hidden = !!t && !o.dataset.texto.includes(t); o.classList.remove('vx-on'); });
            nada.hidden = visibles().length > 0;
            sel = 0; marcar();
        };
        const abrir = () => {
            antes = document.activeElement;
            paleta.hidden = false; q.value = ''; filtrar();
            lenis?.stop();
            setTimeout(() => q.focus(), 10);
        };
        const cerrar = () => { paleta.hidden = true; lenis?.start(); antes?.focus?.({ preventScroll: true }); };
        document.querySelectorAll('[data-paleta-abrir]').forEach((b) => b.addEventListener('click', abrir));
        document.addEventListener('keydown', (e) => {
            const escribiendo = e.target.closest('input, textarea, select, [contenteditable="true"]');
            if (e.key === '/' && !escribiendo && paleta.hidden && !html.classList.contains('wa-abierto')) { e.preventDefault(); abrir(); }
        });
        q.addEventListener('input', filtrar);
        q.addEventListener('keydown', (e) => {
            const lista = visibles();
            if (e.key === 'Escape') { e.preventDefault(); cerrar(); }
            else if (e.key === 'ArrowDown') { e.preventDefault(); sel = Math.min(lista.length - 1, sel + 1); marcar(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); sel = Math.max(0, sel - 1); marcar(); }
            else if (e.key === 'Enter' && lista[sel]) { e.preventDefault(); lista[sel].click(); }
            else if (e.key === 'Tab') { e.preventDefault(); }
        });
        ops.forEach((o) => o.addEventListener('click', () => { paleta.hidden = true; lenis?.start(); }));
        paleta.addEventListener('click', (e) => { if (e.target === paleta) cerrar(); });
    }

    // ---------- Botón flotante de WhatsApp ----------
    const wa = document.querySelector('[data-wa]');
    if (wa) {
        let contactoVisible = false;
        const btn = wa.querySelector('[data-wa-btn]');
        const texto = wa.querySelector('[data-wa-texto]');
        const abrirWa = (si) => {
            wa.classList.toggle('vx-abierto', si);
            btn.setAttribute('aria-expanded', si ? 'true' : 'false');
            btn.setAttribute('aria-label', si ? 'Cerrar opciones de WhatsApp' : 'Escríbenos por WhatsApp');
            if (texto._vxAnim) { cancelAnimationFrame(texto._vxAnim); texto._vxAnim = null; }
            texto._vxTextos = null;
            texto.textContent = si ? 'Elige un tema' : 'Escríbenos';
            if (!si) wa.classList.toggle('vx-escondido', contactoVisible);
        };
        btn.addEventListener('click', () => abrirWa(!wa.classList.contains('vx-abierto')));
        btn.addEventListener('mouseenter', () => { if (mouse && !wa.classList.contains('vx-abierto')) { texto._vxTextos = null; descifrar(texto, { dur: 500, signos: SIGNOS_MAY }); } });
        wa.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => abrirWa(false)));
        document.addEventListener('click', (e) => { if (!wa.contains(e.target)) abrirWa(false); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && wa.classList.contains('vx-abierto')) { abrirWa(false); btn.focus(); } });
        // En la sección de contacto ya está WhatsApp: el botón se hace a un lado
        const contacto = document.querySelector('#contacto');
        if (contacto) new IntersectionObserver(([e]) => {
            contactoVisible = e.isIntersecting;
            if (!wa.classList.contains('vx-abierto')) wa.classList.toggle('vx-escondido', contactoVisible);
        }, { threshold: 0.3 }).observe(contacto);
    }

    // ---------- Cursor propio (solo con mouse) ----------
    const mira = document.querySelector('[data-mira]');
    if (mira && mouse && !reducir) {
        html.classList.add('vx-mira-on');
        const etq = mira.querySelector('[data-mira-etq]');
        let x = -100, y = -100, cx = -100, cy = -100;
        window.addEventListener('mousemove', (e) => { x = e.clientX; y = e.clientY; mira.classList.add('vx-visible'); }, { passive: true });
        document.addEventListener('mouseleave', () => mira.classList.remove('vx-visible'));
        document.addEventListener('mouseover', (e) => {
            const t = e.target;
            const conEtq = t.closest('[data-cur]');
            const texto = conEtq ? conEtq.dataset.cur : '';
            if (etq.textContent !== texto) etq.textContent = texto;
            mira.classList.toggle('vx-con-etq', !!texto);
            mira.classList.toggle('vx-grande', !texto && !!t.closest('a, button, summary, label, input'));
            mira.classList.toggle('vx-en-claro', !!t.closest('.vx-claro, .vx-contacto') && !t.closest('.vx-conv'));
            mira.style.visibility = t.closest('.wa-modal') ? 'hidden' : '';
        });
        const mover = () => {
            cx += (x - cx) * 0.25; cy += (y - cy) * 0.25;
            mira.style.transform = `translate3d(${cx}px, ${cy}px, 0)`;
            requestAnimationFrame(mover);
        };
        requestAnimationFrame(mover);
    }

    // ---------- Al mover la página: avance en la barra, línea del proceso y riel sobre fondos claros ----------
    const avancePag = document.querySelector('[data-avance-pag]');
    const lineaProceso = document.querySelector('[data-proceso-linea]');
    const claros = [...document.querySelectorAll('.vx-claro, .vx-contacto')];
    const alMover = () => {
        const max = document.documentElement.scrollHeight - window.innerHeight;
        if (avancePag) avancePag.style.setProperty('--avance', limitar(window.scrollY / Math.max(1, max)).toFixed(4));
        if (lineaProceso) {
            const r = lineaProceso.getBoundingClientRect();
            lineaProceso.style.setProperty('--p', reducir ? 1 : limitar((window.innerHeight * 0.85 - r.top) / (window.innerHeight * 0.5)).toFixed(4));
        }
        if (riel) {
            const medio = window.innerHeight / 2;
            riel.classList.toggle('vx-en-claro', claros.some((s) => { const r = s.getBoundingClientRect(); return r.top < medio && r.bottom > medio; }));
        }
    };
    alMover();
    if (lenis) lenis.on('scroll', alMover); else window.addEventListener('scroll', alMover, { passive: true });
    window.addEventListener('resize', alMover);
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
else iniciar();
