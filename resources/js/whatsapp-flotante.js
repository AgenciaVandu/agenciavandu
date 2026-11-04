// Botón flotante de WhatsApp (<x-whatsapp-flotante />): texto que se descifra, panel con temas
// y se hace a un lado cuando se ve la sección #contacto. Los enlaces los atiende whatsapp.js (formulario).
import { mouse, descifrar, olvidar, SIGNOS_MAY, iniciarRelojes, alCargar } from './inicio/util';

alCargar(() => {
    const wa = document.querySelector('[data-wa]');
    if (!wa) return;
    iniciarRelojes();
    const btn = wa.querySelector('[data-wa-btn]');
    const texto = wa.querySelector('[data-wa-texto]');
    let contactoVisible = false;

    const abrir = (si) => {
        wa.classList.toggle('vx-abierto', si);
        btn.setAttribute('aria-expanded', si ? 'true' : 'false');
        btn.setAttribute('aria-label', si ? 'Cerrar opciones de WhatsApp' : 'Escríbenos por WhatsApp');
        olvidar(texto);
        texto.textContent = si ? 'Elige un tema' : 'Escríbenos';
        if (!si) wa.classList.toggle('vx-escondido', contactoVisible);
    };
    btn.addEventListener('click', () => abrir(!wa.classList.contains('vx-abierto')));
    btn.addEventListener('mouseenter', () => {
        if (!mouse || wa.classList.contains('vx-abierto')) return;
        olvidar(texto);
        descifrar(texto, { dur: 500, signos: SIGNOS_MAY });
    });
    wa.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => abrir(false)));
    document.addEventListener('click', (e) => { if (!wa.contains(e.target) && wa.classList.contains('vx-abierto')) abrir(false); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && wa.classList.contains('vx-abierto')) { abrir(false); btn.focus(); } });

    // Si el aviso de cookies está abierto y le queda encima, el botón sube para no taparlo
    const cookies = document.getElementById('ck-barra');
    if (cookies) {
        const acomodar = () => {
            let sube = 0;
            if (!cookies.hidden) {
                const c = cookies.getBoundingClientRect();
                const b = btn.getBoundingClientRect();
                const base = window.innerWidth < 576 ? 14 : 24;   // el mismo margen de abajo que en los estilos
                if (c.right > b.left - 8) sube = (window.innerHeight - c.top) + 12 - base;
            }
            wa.style.setProperty('--vx-wa-sube', Math.max(0, Math.round(sube)) + 'px');
        };
        new MutationObserver(acomodar).observe(cookies, { attributes: true, attributeFilter: ['hidden'] });
        window.addEventListener('resize', acomodar);
        acomodar();
        setTimeout(acomodar, 400);   // después de su animación de entrada
    }

    // En la sección de contacto ya está WhatsApp: el botón se hace a un lado
    const contacto = document.querySelector('#contacto');
    if (contacto) new IntersectionObserver(([e]) => {
        contactoVisible = e.isIntersecting;
        if (!wa.classList.contains('vx-abierto')) wa.classList.toggle('vx-escondido', contactoVisible);
    }, { threshold: 0.3 }).observe(contacto);
});
