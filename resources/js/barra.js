// Barra flotante del sitio (<x-barra />): luz que se desliza, texto que se descifra, menú de servicios,
// menú en celular, avance de la página, hora de Mérida y buscador con la tecla /.
import { html, descifrar, SIGNOS_MAY, limitar, iniciarRelojes, alCargar } from './inicio/util';

alCargar(() => {
    const cab = document.querySelector('[data-cab]');
    if (!cab) return;
    iniciarRelojes();

    // ---------- Menú: luz, texto que se descifra y panel de servicios ----------
    const menu = cab.querySelector('[data-menu]');
    const mega = cab.querySelector('[data-mega]');
    const disparadorMega = menu?.querySelector('[data-menu-item="0"]');
    const abrirMega = (si) => {
        if (!mega) return;
        mega.classList.toggle('vx-abierto', si);
        disparadorMega?.setAttribute('aria-expanded', si ? 'true' : 'false');
    };
    const apagar = () => { menu?.classList.remove('vx-con-luz'); abrirMega(false); };
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
    // Al salir se cierra con una pausa corta, para poder bajar del menú al panel sin que se cierre
    let cierre = null;
    const cerrarLuego = () => { cierre = setTimeout(apagar, 180); };
    cab.addEventListener('mouseleave', cerrarLuego);
    cab.addEventListener('mouseenter', () => clearTimeout(cierre));
    mega?.addEventListener('mouseenter', () => clearTimeout(cierre));
    mega?.addEventListener('mouseleave', cerrarLuego);
    cab.addEventListener('focusout', (e) => { if (!cab.contains(e.relatedTarget)) apagar(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && mega?.classList.contains('vx-abierto')) { abrirMega(false); disparadorMega?.focus(); } });

    // ---------- Menú en celular ----------
    const hamburguesa = cab.querySelector('[data-movil-abrir]');
    const movil = cab.querySelector('[data-movil]');
    const abrirMovil = (si) => {
        movil?.classList.toggle('vx-abierto', si);
        hamburguesa?.setAttribute('aria-expanded', si ? 'true' : 'false');
        hamburguesa?.setAttribute('aria-label', si ? 'Cerrar menú' : 'Abrir menú');
    };
    hamburguesa?.addEventListener('click', () => abrirMovil(!movil.classList.contains('vx-abierto')));
    // Al elegir cualquier enlace de la barra, los menús se cierran
    cab.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => { abrirMega(false); abrirMovil(false); }));

    // ---------- Avance de la página ----------
    const avance = cab.querySelector('[data-avance-pag]');
    const medir = () => {
        const max = document.documentElement.scrollHeight - window.innerHeight;
        avance?.style.setProperty('--avance', limitar(window.scrollY / Math.max(1, max)).toFixed(4));
    };
    medir();
    window.addEventListener('scroll', medir, { passive: true });
    window.addEventListener('resize', medir);

    // ---------- Buscador rápido (tecla /) ----------
    const paleta = document.querySelector('[data-paleta]');
    if (!paleta) return;
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
        window.vxLenis?.stop();
        setTimeout(() => q.focus(), 10);
    };
    const soltar = () => { paleta.hidden = true; window.vxLenis?.start(); };
    const cerrar = () => { soltar(); antes?.focus?.({ preventScroll: true }); };
    document.querySelectorAll('[data-paleta-abrir]').forEach((b) => b.addEventListener('click', abrir));
    document.addEventListener('keydown', (e) => {
        const escribiendo = e.target.closest?.('input, textarea, select, [contenteditable="true"]');
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
    ops.forEach((o) => o.addEventListener('click', soltar));
    paleta.addEventListener('click', (e) => { if (e.target === paleta) cerrar(); });
});
