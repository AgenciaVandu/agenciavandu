// Botones de WhatsApp: antes de abrir WhatsApp se piden unos datos, se guarda el prospecto
// en el portal y se abre la conversación con el mensaje ya escrito.
const GUARDADO = 'vandu-wa-datos';

const iniciarWhatsapp = () => {
    const modal = document.getElementById('wa-modal');
    if (!modal || typeof modal.showModal !== 'function') return; // navegador muy viejo: los enlaces siguen funcionando normal

    const form = modal.querySelector('#wa-form');
    const vista = modal.querySelector('#wa-vista-texto');
    const error = modal.querySelector('#wa-error');
    const enviar = modal.querySelector('#wa-enviar');
    const directo = modal.querySelector('[data-wa-directo]');
    const llaveRecaptcha = modal.dataset.recaptcha;
    const campos = ['nombre', 'telefono', 'empresa', 'email'];

    // ---- mensaje (mismo formato que arma el servidor) ----
    const valor = (n) => (form.elements[n]?.value || '').trim();
    const servicio = () => form.querySelector('input[name="servicio"]:checked')?.value || '';
    const mensaje = () => {
        const nombre = valor('nombre') || '…';
        const lineas = [`Hola, soy ${nombre}${valor('empresa') ? ` de ${valor('empresa')}` : ''}.`];
        lineas.push(`Me interesa: ${servicio() || '…'}.`);
        if (valor('mensaje')) lineas.push('', valor('mensaje'));
        if (valor('email')) lineas.push('', `Mi correo: ${valor('email')}`);
        lineas.push('', '(Te escribo desde agenciavandu.com)');
        return lineas.join('\n');
    };
    const actualizarVista = () => { vista.textContent = mensaje(); };
    form.addEventListener('input', actualizarVista);
    form.addEventListener('change', actualizarVista);

    // ---- datos recordados en este navegador ----
    const recordar = () => {
        try { localStorage.setItem(GUARDADO, JSON.stringify(Object.fromEntries(campos.map((c) => [c, valor(c)])))); } catch (e) { /* sin almacenamiento */ }
    };
    const recuperar = () => {
        try {
            const d = JSON.parse(localStorage.getItem(GUARDADO) || '{}');
            campos.forEach((c) => { if (d[c] && !form.elements[c].value) form.elements[c].value = d[c]; });
        } catch (e) { /* sin almacenamiento */ }
    };

    // ---- reCAPTCHA v3, solo se carga si alguien abre el formulario ----
    let recaptchaListo = null;
    const cargarRecaptcha = () => {
        if (!llaveRecaptcha) return Promise.resolve(false);
        if (window.grecaptcha?.execute) return Promise.resolve(true);
        if (!recaptchaListo) {
            recaptchaListo = new Promise((ok) => {
                const s = document.createElement('script');
                s.src = `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(llaveRecaptcha)}`;
                s.async = true;
                s.onload = () => ok(true);
                s.onerror = () => ok(false);
                document.head.appendChild(s);
            });
        }
        return recaptchaListo;
    };
    const tokenRecaptcha = async () => {
        if (!(await cargarRecaptcha()) || !window.grecaptcha) return '';
        return new Promise((ok) => {
            const limite = setTimeout(() => ok(''), 4000);
            window.grecaptcha.ready(() => {
                window.grecaptcha.execute(llaveRecaptcha, { action: 'whatsapp' })
                    .then((t) => { clearTimeout(limite); ok(t); })
                    .catch(() => { clearTimeout(limite); ok(''); });
            });
        });
    };

    // ---- abrir y cerrar ----
    const abrir = (servicioInicial) => {
        error.hidden = true;
        recuperar();
        form.elements.pagina.value = window.location.pathname;
        if (servicioInicial) {
            const op = [...form.querySelectorAll('input[name="servicio"]')].find((r) => r.value === servicioInicial);
            if (op) op.checked = true;
        }
        actualizarVista();
        modal.showModal();
        document.documentElement.classList.add('wa-abierto');
        cargarRecaptcha();
        setTimeout(() => (valor('nombre') ? form.elements.telefono : form.elements.nombre).focus(), 50);
    };
    const cerrar = () => modal.close();
    modal.addEventListener('close', () => document.documentElement.classList.remove('wa-abierto'));
    modal.querySelectorAll('[data-wa-cerrar]').forEach((b) => b.addEventListener('click', cerrar));
    modal.addEventListener('click', (e) => { if (e.target === modal) cerrar(); }); // clic en el fondo

    // Cualquier enlace a wa.me del sitio abre el formulario (menos el enlace directo de adentro)
    document.addEventListener('click', (e) => {
        const enlace = e.target.closest('a[href*="wa.me/"]');
        if (!enlace || enlace.hasAttribute('data-wa-directo') || e.metaKey || e.ctrlKey || e.shiftKey) return;
        e.preventDefault();
        abrir(enlace.dataset.servicio);
    });

    // ---- enviar ----
    const mostrarError = (texto) => { error.textContent = texto; error.hidden = false; };
    const urlDirecta = () => `${directo.href.split('?')[0]}?text=${encodeURIComponent(mensaje())}`;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        error.hidden = true;

        if (!valor('nombre')) return mostrarError('Escribe tu nombre.');
        if (valor('telefono').replace(/\D/g, '').length < 8) return mostrarError('Escribe tu WhatsApp para poder responderte.');
        if (!servicio()) return mostrarError('Elige qué necesitas.');
        if (form.elements.email.value && !form.elements.email.checkValidity()) return mostrarError('Revisa tu correo, parece que le falta algo.');

        recordar();
        enviar.disabled = true;
        enviar.textContent = 'Abriendo WhatsApp…';

        // La pestaña se abre en el clic para que el navegador no la bloquee; luego se le pone la dirección
        const ventana = window.open('', '_blank');
        const ir = (url) => {
            if (ventana && !ventana.closed) ventana.location.href = url;
            else window.location.href = url;
        };

        try {
            form.elements['g-recaptcha-response'].value = await tokenRecaptcha();
            const r = await fetch(modal.dataset.endpoint, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': form.elements._token.value,
                },
            });
            const datos = await r.json().catch(() => ({}));

            if (r.status === 422) {
                if (ventana) ventana.close();
                const primero = Object.values(datos.errors || {})[0];
                return mostrarError(Array.isArray(primero) ? primero[0] : 'Revisa tus datos.');
            }

            ir(datos.whatsapp || urlDirecta());
            if (typeof window.gtag === 'function') {
                window.gtag('event', 'generate_lead', { method: 'whatsapp', servicio: servicio() });
            }
            cerrar();
        } catch (err) {
            // Si el servidor no responde, igual abrimos WhatsApp para no perder al cliente
            ir(urlDirecta());
            cerrar();
        } finally {
            enviar.disabled = false;
            enviar.textContent = 'Abrir WhatsApp';
        }
    });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciarWhatsapp);
} else {
    iniciarWhatsapp();
}
