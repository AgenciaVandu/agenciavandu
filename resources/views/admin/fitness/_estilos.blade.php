@once
@push('head')
<style>
    /* Gimnasio: piezas que comparten agenda, clases, atletas, escáner y reportes */
    .fx-pastilla { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 500; padding: 2px 9px; border-radius: 99px; background: var(--sunken); color: var(--text-2); white-space: nowrap; }
    .fx-pastilla .pt { width: 8px; height: 8px; border-radius: 50%; }
    .fx-ocupacion { display: grid; gap: 4px; min-width: 110px; }
    .fx-ocupacion .barra { height: 6px; border-radius: 99px; background: #E9EBEE; overflow: hidden; }
    .fx-ocupacion .barra span { display: block; height: 100%; border-radius: 99px; background: var(--ink); }
    .fx-ocupacion .barra.llena span { background: var(--red); }
    .fx-ocupacion .barra.casi span { background: var(--amber); }
    .fx-ocupacion .t { font-size: 12.5px; color: var(--muted); display: flex; justify-content: space-between; gap: 8px; }
    .fx-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex: none; background: var(--sunken); display: grid; place-items: center; font-weight: 600; font-size: 14px; color: var(--text-2); }
    .fx-avatar.g { width: 64px; height: 64px; font-size: 20px; }
    .fx-color { width: 12px; height: 12px; border-radius: 4px; flex: none; display: inline-block; }
    .fx-grid-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; }
    .fx-lista > * + * { border-top: 1px solid var(--line); }
    .fx-fila { display: flex; align-items: center; gap: 12px; padding: 12px 20px; }
    .fx-fila .principal { font-weight: 550; }
    details.panel > summary { list-style: none; } details.panel > summary::-webkit-details-marker { display: none; }
    details.fx-editar > summary { list-style: none; cursor: pointer; } details.fx-editar > summary::-webkit-details-marker { display: none; }
    details.fx-editar[open] > summary .ver { display: none; }
    .fx-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 20px; }
    .fx-kpis > * { padding: 16px 20px; color: inherit; text-decoration: none; }
    .fx-kpis > * + * { border-left: 1px solid var(--line); }
    .fx-kpis .k { font-size: 13px; color: var(--muted); display: flex; gap: 6px; align-items: center; } .fx-kpis .v { font-size: 24px; font-weight: 650; letter-spacing: -.01em; }
    .fx-kpis .s { font-size: 12.5px; color: var(--muted); }
    @media (max-width: 767.98px) { .fx-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .fx-kpis > *:nth-child(3) { border-left: 0; } .fx-kpis > *:nth-child(n+3) { border-top: 1px solid var(--line); } }
</style>
@endpush
@endonce
