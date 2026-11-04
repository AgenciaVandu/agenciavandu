// Scroll suave con Lenis y enlaces a secciones (#servicios, #contacto…) con espacio para la barra.
import Lenis from 'lenis';
import { reducir } from './util';

export default function scrollSuave() {
    let lenis = null;
    if (!reducir) {
        lenis = new Lenis({ lerp: 0.1, smoothWheel: true });
        window.vxLenis = lenis;   // la barra lo pausa mientras el buscador está abierto
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
        if (irA(a.getAttribute('href'))) e.preventDefault();
    });
}
