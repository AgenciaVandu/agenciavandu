@once
@push('head')
<style>
    /* Programa de lealtad (giro Restaurante) */
    .lk-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 20px; }
    .lk-kpis > * { padding: 16px 20px; }
    .lk-kpis > * + * { border-left: 1px solid var(--line); }
    .lk-kpis .k { font-size: 13px; color: var(--muted); display: flex; gap: 6px; align-items: center; }
    .lk-kpis .v { font-size: 26px; font-weight: 700; letter-spacing: -.01em; }
    .lk-kpis .s { font-size: 12.5px; color: var(--muted); }
    @media (max-width: 767.98px) { .lk-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .lk-kpis > *:nth-child(3) { border-left: 0; } .lk-kpis > *:nth-child(n+3) { border-top: 1px solid var(--line); } }
    .lk-camino { display: flex; align-items: center; gap: 0; margin: 10px 0 4px; }
    .lk-camino .p { position: relative; flex: 1; height: 6px; background: var(--line); }
    .lk-camino .p:first-child { border-radius: 99px 0 0 99px; } .lk-camino .p:last-child { border-radius: 0 99px 99px 0; }
    .lk-camino .p.on { background: var(--marca); }
    .lk-premio { display: flex; align-items: center; gap: 10px; padding: 8px 0; }
    .lk-premio + .lk-premio { border-top: 1px dashed var(--line); }
    .lk-premio .n { width: 34px; height: 34px; border-radius: 10px; background: var(--marca-soft); color: var(--marca); display: grid; place-items: center; font-weight: 700; font-size: 13px; flex: none; }
    .lk-estado { font-size: 12px; font-weight: 600; padding: 2px 9px; border-radius: 99px; }
    .lk-estado.vigente { background: #E3FBEF; color: #047A4B; } .lk-estado.proxima { background: var(--marca-soft); color: var(--marca); }
    .lk-estado.canje { background: #FFF3DD; color: #9A5300; } .lk-estado.terminada, .lk-estado.pausada { background: var(--sunken); color: var(--muted); }
    .lk-lista > * + * { border-top: 1px solid var(--line); }
    .lk-barras { display: flex; align-items: flex-end; gap: 3px; height: 90px; padding: 12px 18px 8px; }
    .lk-barras span { flex: 1; background: var(--marca); border-radius: 4px 4px 1px 1px; min-height: 2px; opacity: .85; }
    .lk-barras span.hoy { background: var(--cian); opacity: 1; }
</style>
@endpush
@endonce
