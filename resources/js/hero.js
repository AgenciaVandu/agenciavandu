// Hero del index: abanico, texto que se escribe solo y luces verdes.
const iniciarHero = () => {
    const hero = document.querySelector('.vh-hero');
    if (!hero) return;

    const reducir = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Abanico: se abre al cargar
    const abanico = hero.querySelector('.vh-abanico');
    if (abanico) setTimeout(() => abanico.classList.add('vh-abierto'), reducir ? 0 : 350);

    // Texto que se escribe solo
    const texto = hero.querySelector('.vh-escribe');
    if (texto && !reducir) {
        const palabras = JSON.parse(texto.dataset.palabras || '[]');
        let p = 0;
        let i = palabras[0]?.length ?? 0;
        let borrando = true;
        const paso = () => {
            const palabra = palabras[p];
            i += borrando ? -1 : 1;
            texto.textContent = palabra.slice(0, i);
            let espera = borrando ? 35 : 70;
            if (!borrando && i === palabra.length) {
                borrando = true;
                espera = 1800;
            } else if (borrando && i === 0) {
                borrando = false;
                p = (p + 1) % palabras.length;
                espera = 250;
            }
            setTimeout(paso, espera);
        };
        if (palabras.length > 1) setTimeout(paso, 1800);
    }

    // Luces verdes: puntos que flotan y un halo que sigue al cursor
    const lienzo = hero.querySelector('.vh-luces');
    if (!lienzo) return;
    const ctx = lienzo.getContext('2d');
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    let W = 0;
    let H = 0;
    let visible = true;
    const raton = { x: .5, y: .35, tx: .5, ty: .35 };
    const puntos = Array.from({ length: 16 }, () => ({
        x: Math.random(),
        y: Math.random() * .8,
        r: 18 + Math.random() * 46,
        vx: (Math.random() - .5) * .0006,
        vy: (Math.random() - .5) * .0005,
        a: .25 + Math.random() * .5,
        f: Math.random() * 6.28,
    }));

    const medir = () => {
        const caja = hero.getBoundingClientRect();
        W = caja.width;
        H = caja.height;
        lienzo.width = W * dpr;
        lienzo.height = H * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    };

    const brillo = (x, y, r, a) => {
        const g = ctx.createRadialGradient(x, y, 0, x, y, r);
        g.addColorStop(0, `rgba(0,243,133,${a})`);
        g.addColorStop(1, 'rgba(0,243,133,0)');
        ctx.fillStyle = g;
        ctx.beginPath();
        ctx.arc(x, y, r, 0, Math.PI * 2);
        ctx.fill();
    };

    const dibujar = (t) => {
        if (!visible && !reducir) {
            requestAnimationFrame(dibujar);
            return;
        }
        ctx.clearRect(0, 0, W, H);
        raton.x += (raton.tx - raton.x) * .05;
        raton.y += (raton.ty - raton.y) * .05;
        brillo(raton.x * W, raton.y * H, Math.max(W, H) * .32, .10);
        for (const d of puntos) {
            if (!reducir) {
                d.x += d.vx + (raton.x - d.x) * .0009;
                d.y += d.vy + (raton.y - d.y) * .0009;
                if (d.x < -.05 || d.x > 1.05) d.vx *= -1;
                if (d.y < -.05 || d.y > .9) d.vy *= -1;
            }
            const pulso = reducir ? 1 : .75 + .25 * Math.sin(t / 900 + d.f);
            brillo(d.x * W, d.y * H, d.r, d.a * .55 * pulso);
        }
        if (!reducir) requestAnimationFrame(dibujar);
    };

    hero.addEventListener('pointermove', (e) => {
        const caja = hero.getBoundingClientRect();
        raton.tx = (e.clientX - caja.left) / caja.width;
        raton.ty = (e.clientY - caja.top) / caja.height;
    });

    // no gasta batería cuando el hero ya no se ve
    new IntersectionObserver(([entrada]) => { visible = entrada.isIntersecting; }).observe(hero);

    window.addEventListener('resize', medir);
    medir();
    requestAnimationFrame(dibujar);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciarHero);
} else {
    iniciarHero();
}
