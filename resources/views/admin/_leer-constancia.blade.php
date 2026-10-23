{{-- Lee la Constancia de Situación Fiscal al elegir el archivo y llena el formulario.
     Uso: en el <input type=file> poner data-leer-constancia='{"rfc":"rfc","razon_social":"razon_social",…}' (dato → id del campo)
     y un elemento con id="<id del input>-estado" para el aviso. --}}
@once
@push('head')
<style>
    .lc-estado { display: none; align-items: center; gap: 10px; margin-top: 8px; padding: 10px 12px; border-radius: 10px; font-size: 13.5px; background: var(--sunken); }
    .lc-estado.si { display: flex; } .lc-estado.ok { background: #ECFDF5; color: #065F46; } .lc-estado.mal { background: #FFF7ED; color: #9A3412; }
    .lc-giro { width: 16px; height: 16px; border: 2px solid var(--marca-soft); border-top-color: var(--marca); border-radius: 50%; animation: lcg .8s linear infinite; flex: none; }
    @keyframes lcg { to { transform: rotate(360deg); } }
    .lc-lleno { box-shadow: 0 0 0 3px #A7F3D0 !important; transition: box-shadow .6s; }
</style>
@endpush
@push('scripts')
<script>
window.leerConstancia = window.leerConstancia || (function () {
    const url = @json(route('admin.documentos.constancia')), token = @json(csrf_token());
    async function leer(input) {
        const a = input.files[0];
        const est = document.getElementById(input.id + '-estado');
        if (!a || !est) return;
        const campos = JSON.parse(input.dataset.leerConstancia || '{}');
        est.className = 'lc-estado si'; est.innerHTML = '<span class="lc-giro"></span> Leyendo la constancia…';
        const fd = new FormData(); fd.append('archivo', a);
        let j = {};
        try {
            const r = await fetch(url, { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token } });
            j = await r.json();
            if (r.status === 422 && j.errors) j = { ok: false, mensaje: Object.values(j.errors)[0][0] };
        } catch (e) { j = { ok: false, mensaje: 'No pudimos leer la constancia. Captura los datos.' }; }
        if (!j.ok) { est.className = 'lc-estado si mal'; est.textContent = j.mensaje || 'No pudimos leer la constancia.'; return; }
        const d = j.datos, llenos = [];
        for (const [dato, id] of Object.entries(campos)) {
            const el = document.getElementById(id);
            let v = d[dato];
            if (dato === 'empresa') { if (el && el.value.trim()) continue; v = d.nombre_comercial || d.razon_social; }
            if (!el || v === null || v === undefined || v === '') continue;
            el.value = v; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true }));
            el.classList.add('lc-lleno'); setTimeout(() => el.classList.remove('lc-lleno'), 2500);
            llenos.push(dato);
        }
        est.className = 'lc-estado si ok';
        est.textContent = 'Listo: ' + d.rfc + (d.razon_social ? ' · ' + d.razon_social : '') + '. Revisa los datos marcados en verde' + (j.origen === 'ia' ? ' (leídos con IA)' : '') + '.';
    }
    document.addEventListener('change', (e) => { if (e.target.matches && e.target.matches('input[type=file][data-leer-constancia]')) leer(e.target); });
    return leer;
})();
</script>
@endpush
@endonce
