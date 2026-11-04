// Contacto paso a paso: qué necesitas, nombre del negocio y para cuándo; al final arma el mensaje.
import {  } from './util';

export default function contacto() {
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
}
