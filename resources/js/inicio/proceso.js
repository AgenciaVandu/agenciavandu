// Cómo trabajamos: la línea de arriba de los pasos avanza conforme bajas.
import { reducir, limitar } from './util';

export default function proceso() {
    const linea = document.querySelector('[data-proceso-linea]');
    if (!linea) return;
    const alMover = () => {
        const r = linea.getBoundingClientRect();
        linea.style.setProperty('--p', reducir ? 1 : limitar((window.innerHeight * 0.85 - r.top) / (window.innerHeight * 0.5)).toFixed(4));
    };
    alMover();
    window.addEventListener('scroll', alMover, { passive: true });
    window.addEventListener('resize', alMover);
}
