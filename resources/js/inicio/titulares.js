// Titulares h1–h5: se descifran una sola vez, cuando aparecen en pantalla.
import { descifrar, SIGNOS_MAY } from './util';

export default function titulares() {
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
}
