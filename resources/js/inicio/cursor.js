// Cursor propio (solo con mouse): dice "Construir" o "Ver" según lo que señala.
import { html, reducir, mouse } from './util';

export default function cursor() {
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
}
