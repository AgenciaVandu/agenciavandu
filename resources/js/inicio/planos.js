// Planos de la portada: se construyen uno tras otro; con el mouse, el que señalas.
import { reducir, mouse, limitar, n2 } from './util';

export default function planos() {
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
}
