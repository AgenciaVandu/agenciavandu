// Apariciones al hacer scroll y videos que solo cargan y se reproducen cuando se ven.
import { reducir } from './util';

export default function apariciones() {
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
}
