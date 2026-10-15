// Preferencias de cookies + Consent Mode v2 de Google.
// El estado inicial (por región) se declara en el <head> del layout, antes de cargar Analytics y Ads.
const LLAVE = 'vandu-cookies';

const leer = () => { try { return localStorage.getItem(LLAVE); } catch (e) { return null; } };
const guardar = (v) => { try { localStorage.setItem(LLAVE, v); } catch (e) { /* sin almacenamiento */ } };

const aplicar = (eleccion) => {
    if (typeof window.gtag !== 'function') return;
    const estado = eleccion === 'todas' ? 'granted' : 'denied';
    window.gtag('consent', 'update', {
        analytics_storage: estado,
        ad_storage: estado,
        ad_user_data: estado,
        ad_personalization: estado,
    });
};

const iniciarCookies = () => {
    const barra = document.getElementById('ck-barra');
    if (!barra) return;
    const raiz = document.documentElement;

    const mostrar = () => {
        barra.hidden = false;
        raiz.classList.add('ck-visible');
        raiz.style.setProperty('--ck-alto', `${barra.offsetHeight + 16}px`);
    };
    const ocultar = () => {
        barra.hidden = true;
        raiz.classList.remove('ck-visible');
        raiz.style.removeProperty('--ck-alto');
    };

    barra.querySelectorAll('[data-cookies]').forEach((b) => b.addEventListener('click', () => {
        const eleccion = b.dataset.cookies;
        guardar(eleccion);
        aplicar(eleccion);
        ocultar();
    }));

    // Cualquier botón o enlace con data-cookies-abrir vuelve a mostrar la barra (footer, aviso de privacidad)
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-cookies-abrir]')) {
            e.preventDefault();
            mostrar();
            barra.querySelector('.ck-primario').focus();
        }
    });

    if (!leer()) mostrar();
    window.addEventListener('resize', () => { if (!barra.hidden) mostrar(); });
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciarCookies);
} else {
    iniciarCookies();
}
