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

    // En la sección de contacto ya está WhatsApp: el botón se hace a un lado
    const contacto = document.querySelector('#contacto');
    if (contacto) new IntersectionObserver(([e]) => {
        contactoVisible = e.isIntersecting;
        if (!wa.classList.contains('vx-abierto')) wa.classList.toggle('vx-escondido', contactoVisible);
    }, { threshold: 0.3 }).observe(contacto);
});
