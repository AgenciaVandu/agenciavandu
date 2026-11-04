// Mapa de la página: marca la sección en la que vas y cambia de color sobre fondos claros.
export default function riel() {
    const riel = document.querySelector('.vx-riel');
    if (!riel) return;
    const enlaces = [...riel.querySelectorAll('[data-riel]')];
    const marcar = (ref) => enlaces.forEach((a) => a.classList.toggle('vx-on', a.dataset.riel === ref));
    const secciones = enlaces.map((a) => document.querySelector(a.dataset.riel)).filter(Boolean);
    const io = new IntersectionObserver((entradas) => entradas.forEach((e) => { if (e.isIntersecting) marcar('#' + e.target.id); }), { rootMargin: '-45% 0px -50% 0px' });
    secciones.forEach((s) => io.observe(s));
    marcar('#inicio');

    const claros = [...document.querySelectorAll('.vx-claro, .vx-contacto')];
    const alMover = () => {
        const medio = window.innerHeight / 2;
        riel.classList.toggle('vx-en-claro', claros.some((s) => { const r = s.getBoundingClientRect(); return r.top < medio && r.bottom > medio; }));
    };
    alMover();
    window.addEventListener('scroll', alMover, { passive: true });
    window.addEventListener('resize', alMover);
}
