// Inicio de agenciavandu.com: scroll suave, telón, textos que aparecen, reel que crece,
// servicios que se recorren de lado, contadores, cursor "Ver" y videos al pasar el cursor.
import Lenis from 'lenis';

const html = document.documentElement;
const reducir = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const tactil = window.matchMedia('(hover: none)').matches;
const limitar = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
window.vxListo = true;

const iniciar = () => {
    // ---------- Telón (una vez por visita) ----------
    if (html.classList.contains('vx-telon-on')) {
        try { sessionStorage.setItem('vx-telon', '1'); } catch (e) {}
        setTimeout(() => html.classList.remove('vx-telon-on'), reducir ? 0 : 1000);
    }

    // ---------- Scroll suave ----------
    let lenis = null;
    if (!reducir) {
        lenis = new Lenis({ lerp: 0.09, smoothWheel: true });
        const raf = (t) => { lenis.raf(t); requestAnimationFrame(raf); };
        requestAnimationFrame(raf);
        document.querySelectorAll('a[href^="#"]').forEach((a) => a.addEventListener('click', (e) => {
            const destino = document.querySelector(a.getAttribute('href'));
            if (!destino) return;
            e.preventDefault();
            lenis.scrollTo(destino, { offset: -20 });
        }));
    }

    // ---------- Titular: palabras que suben y la foto que se asoma ----------
    const titular = document.querySelector('[data-palabras]');
    if (titular) {
        titular.querySelectorAll('.vx-p').forEach((p, i) => {
            const s = document.createElement('span');
            s.className = 'vx-palabra';
            s.style.transitionDelay = (0.15 + i * 0.12) + 's';
            while (p.firstChild) s.appendChild(p.firstChild);
            p.appendChild(s);
        });
    }

    // ---------- Encabezados: cada palabra sube en cascada (sin medir renglones, así nunca se rompen) ----------
    const escapar = (t) => t.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c]));
    const encabezados = [...document.querySelectorAll('[data-lineas]')];
    encabezados.forEach((el) => {
        const palabras = el.textContent.trim().split(/\s+/);
        el.innerHTML = palabras.map((w, i) => `<span class="vx-linea"><span style="transition-delay:${Math.min(i, 14) * 0.035}s">${escapar(w)}</span></span>`).join(' ');
    });

    // ---------- Apariciones ----------
    const observar = new IntersectionObserver((entradas) => {
        entradas.forEach(({ target, isIntersecting }) => {
            if (!isIntersecting) return;
            target.classList.add('vx-dentro');
            observar.unobserve(target);
            const cuenta = target.querySelector('[data-cuenta]');
            if (cuenta) contar(cuenta);
        });
    }, { threshold: 0.18, rootMargin: '0px 0px -6% 0px' });
    document.querySelectorAll('[data-aparece]').forEach((el) => {
        // Hermanos que aparecen juntos llegan uno tras otro
        const hermanos = [...el.parentElement.children].filter((x) => x.hasAttribute('data-aparece'));
        const i = hermanos.indexOf(el);
        if (i > 0) el.style.transitionDelay = Math.min(i, 6) * 0.08 + 's';
        observar.observe(el);
    });
    encabezados.forEach((h) => observar.observe(h));
    if (titular) setTimeout(() => titular.classList.add('vx-dentro'), html.classList.contains('vx-telon-on') ? 700 : 80);

    // ---------- Contadores ----------
    function contar(el) {
        const fin = parseInt(el.dataset.cuenta, 10) || 0;
        if (reducir) { el.textContent = fin; return; }
        const t0 = performance.now();
        const paso = (t) => {
            const k = limitar((t - t0) / 1400);
            el.textContent = Math.round(fin * (1 - Math.pow(1 - k, 3)));
            if (k < 1) requestAnimationFrame(paso);
        };
        el.textContent = '0';
        requestAnimationFrame(paso);
    }

    // ---------- Videos: cargan al verse; los de proyectos, al pasar el cursor ----------
    const videos = new IntersectionObserver((entradas) => {
        entradas.forEach(({ target: v, isIntersecting }) => {
            if (isIntersecting) {
                if (!v.src) v.src = v.dataset.src || v.dataset.hoverSrc;
                if (!reducir) v.play().catch(() => {});
            } else {
                v.pause();
            }
        });
    }, { threshold: 0.25 });
    document.querySelectorAll('video[data-src]').forEach((v) => videos.observe(v));
    document.querySelectorAll('video[data-hover-src]').forEach((v) => {
        if (tactil) { videos.observe(v); return; }   // en celular se reproducen al verse
        const caja = v.closest('[data-ver]') || v;
        caja.addEventListener('mouseenter', () => { if (!v.src) v.src = v.dataset.hoverSrc; v.play().catch(() => {}); });
        caja.addEventListener('mouseleave', () => v.pause());
    });

    // ---------- Cursor "Ver" ----------
    const cursor = document.querySelector('.vx-cursor');
    if (cursor && !tactil) {
        let x = 0, y = 0, cx = 0, cy = 0;
        window.addEventListener('mousemove', (e) => { x = e.clientX; y = e.clientY; }, { passive: true });
        const mover = () => {
            cx += (x - cx) * 0.2; cy += (y - cy) * 0.2;
            cursor.style.left = cx + 'px'; cursor.style.top = cy + 'px';
            requestAnimationFrame(mover);
        };
        requestAnimationFrame(mover);
        document.querySelectorAll('[data-ver]').forEach((el) => {
            el.addEventListener('mouseenter', () => cursor.classList.add('vx-visible'));
            el.addEventListener('mouseleave', () => cursor.classList.remove('vx-visible'));
        });
    }

    // ---------- Reel que crece y servicios que se recorren de lado ----------
    const reel = document.querySelector('[data-reel]');
    const zonaReel = reel?.closest('.vx-reel-zona');
    const carril = document.querySelector('[data-carril]');
    const pista = document.querySelector('[data-pista]');
    const seccionServicios = carril?.closest('section');
    let recorrido = 0;

    const medirCarril = () => {
        if (!carril || !pista) return;
        const usar = !reducir && window.innerWidth >= 992;
        seccionServicios.classList.toggle('vx-sin-carril', !usar);
        if (!usar) { carril.style.height = ''; pista.style.transform = ''; recorrido = 0; return; }
        recorrido = Math.max(0, pista.scrollWidth - window.innerWidth);
        carril.style.height = (recorrido + window.innerHeight) + 'px';
    };

    const alMover = () => {
        if (reel && zonaReel && !reducir) {
            const r = zonaReel.getBoundingClientRect();
            const p = limitar(-r.top / (window.innerHeight * 0.85));
            reel.style.setProperty('--p', p.toFixed(4));
        }
        if (recorrido > 0) {
            const r = carril.getBoundingClientRect();
            const p = limitar(-r.top / (carril.offsetHeight - window.innerHeight));
            pista.style.transform = `translate3d(${-p * recorrido}px, 0, 0)`;
        }
    };

    medirCarril();
    alMover();
    window.addEventListener('resize', () => { medirCarril(); alMover(); });
    if (lenis) lenis.on('scroll', alMover); else window.addEventListener('scroll', alMover, { passive: true });
    window.addEventListener('load', () => { medirCarril(); alMover(); });
};

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
else iniciar();
