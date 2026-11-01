{{-- Estilos y búsqueda del catálogo del SAT (una sola vez por página) --}}
@once
@push('head')
<style>
    .sat { position: relative; }
    .sat-lista { position: absolute; z-index: 30; left: 0; right: 0; top: calc(100% + 4px); background: var(--surface, #fff); border: 1px solid var(--line); border-radius: 10px; box-shadow: 0 10px 28px rgba(16,24,40,.14); max-height: 280px; overflow: auto; }
    .sat-lista button { display: flex; gap: 10px; width: 100%; text-align: left; border: 0; background: none; padding: 8px 12px; font-size: 13.5px; color: inherit; }
    .sat-lista button:hover, .sat-lista button.on { background: #F4F5F7; }
    .sat-lista .k { font-family: ui-monospace, monospace; font-weight: 600; flex: none; min-width: 74px; }
    .sat-lista span:last-child { flex: 1; min-width: 0; }
    .sat-vacio { padding: 10px 12px; font-size: 13px; color: var(--muted); }
</style>
@endpush
@push('scripts')
<script>
window.satBuscar = window.satBuscar || (function () {
    const url = @json(route('admin.sat.buscar', '__T__'));
    return async function (tipo, q) {
        try { const r = await fetch(url.replace('__T__', tipo) + '?q=' + encodeURIComponent(q || ''), { headers: { Accept: 'application/json' } }); return r.ok ? await r.json() : []; }
        catch (e) { return []; }
    };
})();
/* Un campo: escribe palabras o la clave; elige de la lista; también acepta la clave tecleada */
window.satCampo = window.satCampo || function (cfg) {
    return {
        tipo: cfg.tipo, valor: cfg.valor || '', texto: cfg.texto || '', q: cfg.valor ? (cfg.valor + (cfg.texto ? ' · ' + cfg.texto : '')) : '',
        abierto: false, opciones: [], i: 0, buscando: false,
        async buscar() {
            this.abierto = true; this.buscando = true;
            const q = this.q.split(' · ')[0];
            this.opciones = await window.satBuscar(this.tipo, q);
            this.i = 0; this.buscando = false;
            // Si tecleó una clave completa, se toma aunque no la elija de la lista
            const limpio = q.trim().toUpperCase();
            const forma = this.tipo === 'unidades' ? /^[A-Z0-9]{1,3}$/ : /^\d{8}$/;
            if (forma.test(limpio)) { const o = this.opciones.find(x => x.clave === limpio); this.valor = limpio; this.texto = o ? o.texto : ''; }
            else if (! q.trim()) { this.valor = ''; this.texto = ''; }
        },
        elegir(o) { this.valor = o.clave; this.texto = o.texto; this.q = o.clave + ' · ' + o.texto; this.abierto = false; this.$dispatch('sat-elegido', { tipo: this.tipo, clave: o.clave, texto: o.texto }); },
        tecla(e) {
            if (! this.abierto) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); this.i = Math.min(this.i + 1, this.opciones.length - 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); this.i = Math.max(this.i - 1, 0); }
            else if (e.key === 'Enter' && this.opciones[this.i]) { e.preventDefault(); this.elegir(this.opciones[this.i]); }
            else if (e.key === 'Escape') this.abierto = false;
        },
    };
};
</script>
@endpush
@endonce
