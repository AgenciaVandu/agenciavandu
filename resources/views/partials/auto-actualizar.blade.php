{{--
    La página se actualiza sola cuando hay novedades (comentarios, archivos, cambios de estado).
    Pregunta cada 30 s, solo con la pestaña visible. Si la persona está escribiendo o eligió archivos,
    no recarga: muestra un aviso para que actualice cuando quiera.
    En el panel (que cambia de página sin recargar) se incluye dentro de @push('scripts'):
    al salir de la página deja de preguntar sola.
    Uso: @include('partials.auto-actualizar', ['url' => ..., 'version' => ...])
--}}
<script>
(() => {
    const url = @json($url);
    const version = @json($version);
    const pagina = location.pathname;
    const cada = 30000;
    const clave = 'scroll:' + pagina;

    // Si veníamos de otra página con su propio aviso, se apaga
    if (window.vanduNovedades) window.vanduNovedades.apagar();
    document.getElementById('novedades')?.remove();

    const aviso = document.createElement('div');
    aviso.id = 'novedades';
    aviso.setAttribute('role', 'status');
    aviso.setAttribute('aria-live', 'polite');
    aviso.style.cssText = 'position: fixed; left: 50%; bottom: calc(20px + env(safe-area-inset-bottom)); transform: translateX(-50%); z-index: 1080; display: none; gap: 12px; align-items: center;'
        + 'background: #13161D; color: #fff; padding: 10px 12px 10px 18px; border-radius: 99px; box-shadow: 0 8px 24px rgba(0,0,0,.18); font-size: 14.5px; max-width: calc(100vw - 32px);';
    aviso.innerHTML = '<span>Hay novedades en esta página</span><button type="button" style="border: 0; border-radius: 99px; background: #00F385; color: #13161D; font: inherit; font-weight: 600; padding: 6px 14px; cursor: pointer;">Actualizar</button>';
    document.body.appendChild(aviso);

    // Al recargar por novedades, vuelve a donde estaba
    try {
        const y = sessionStorage.getItem(clave);
        if (y !== null) { sessionStorage.removeItem(clave); window.scrollTo(0, +y); }
    } catch (e) {}

    const recargar = () => {
        try { sessionStorage.setItem(clave, String(window.scrollY)); } catch (e) {}
        location.reload();
    };
    aviso.querySelector('button').addEventListener('click', recargar);

    // ¿Está a media acción? (escribiendo un comentario o con archivos elegidos)
    const ocupado = () => {
        const a = document.activeElement;
        if (a && a.matches('input, textarea, select')) return true;
        if ([...document.querySelectorAll('textarea')].some(t => t.value.trim() !== '')) return true;
        return [...document.querySelectorAll('input[type=file]')].some(f => f.files && f.files.length);
    };

    let enCurso = false, reloj;
    const apagar = () => { clearInterval(reloj); document.removeEventListener('visibilitychange', alVolver); aviso.remove(); window.vanduNovedades = null; };
    const revisar = async () => {
        if (location.pathname !== pagina) { apagar(); return; } // ya se fue a otra página del panel
        if (enCurso || document.hidden || aviso.style.display !== 'none') return;
        enCurso = true;
        try {
            const r = await fetch(url, { headers: { 'Accept': 'application/json' }, cache: 'no-store', credentials: 'same-origin' });
            if (! r.ok) return;
            const { v } = await r.json();
            if (v && v !== version && location.pathname === pagina) {
                if (ocupado()) aviso.style.display = 'flex';
                else recargar();
            }
        } catch (e) { /* sin conexión: se intenta en la siguiente vuelta */ }
        finally { enCurso = false; }
    };
    const alVolver = () => { if (! document.hidden) revisar(); };

    reloj = setInterval(revisar, cada);
    document.addEventListener('visibilitychange', alVolver);
    window.vanduNovedades = { apagar };
})();
</script>
