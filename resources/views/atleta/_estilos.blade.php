<style>
    @font-face { font-family: 'Geist'; src: url('{{ route('vandu.fuente') }}') format('woff2'); font-weight: 100 900; font-display: swap; }
    :root { --ink: #13161D; --line: #E3E4E8; --muted: #5d6270; --mist: #F3F4F6; --green: #00F385; --green-ink: #047A4B; --red: #B42318; --amber: #9A5300; --amber-soft: #FFF3DD; }
    * { box-sizing: border-box; }
    html { -webkit-text-size-adjust: 100%; }
    body { margin: 0; font-family: 'Geist', system-ui, sans-serif; color: var(--ink); background: #F6F7F8; font-size: 16px; line-height: 1.45; padding-bottom: calc(76px + env(safe-area-inset-bottom)); }
    a { color: inherit; }
    :focus-visible { outline: 3px solid var(--green); outline-offset: 2px; }
    .num { font-variant-numeric: tabular-nums; }
    .cab { background: var(--ink); color: #fff; padding: calc(12px + env(safe-area-inset-top)) 16px 12px; position: sticky; top: 0; z-index: 10; }
    .cab .in { max-width: 640px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 40px; }
    .cab .logo { max-height: 34px; max-width: 150px; } .cab .marca { font-size: 17px; }
    .cab .yo { width: 36px; height: 36px; border-radius: 50%; overflow: hidden; background: rgba(255,255,255,.14); display: grid; place-items: center; text-decoration: none; font-size: 13px; font-weight: 600; }
    .cab .yo img { width: 100%; height: 100%; object-fit: cover; }
    .doc { max-width: 640px; margin: 0 auto; padding: 18px 16px 24px; }
    h1 { font-size: 26px; letter-spacing: -.02em; margin: 6px 0 4px; font-weight: 650; }
    h2 { font-size: 17px; margin: 26px 0 10px; font-weight: 600; display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
    h2 a { font-size: 14px; font-weight: 500; color: var(--muted); }
    .sub { color: var(--muted); margin: 0; }
    .tarjeta { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 16px; }
    .tarjeta + .tarjeta { margin-top: 10px; }
    .lista > * + * { margin-top: 10px; }
    .aviso { display: flex; gap: 10px; align-items: flex-start; border-radius: 12px; padding: 12px 14px; margin-bottom: 14px; font-size: 15px; }
    .aviso.ok { background: #E3FBEF; color: #035F3A; } .aviso.error { background: #FDECEC; color: var(--red); } .aviso.info { background: var(--amber-soft); color: #6B3B00; }
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 46px; padding: 0 18px; border-radius: 12px; border: 1.5px solid var(--ink); background: var(--ink); color: #fff;
           font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; }
    .btn.sec { background: #fff; color: var(--ink); border-color: var(--line); }
    .btn.peligro { background: #fff; color: var(--red); border-color: #F3C4C4; }
    .btn.chico { min-height: 36px; padding: 0 12px; font-size: 14px; border-radius: 10px; }
    .btn.full { width: 100%; }
    .btn[disabled] { opacity: .5; cursor: not-allowed; }
    label.campo { display: block; font-size: 14px; font-weight: 500; margin: 14px 0 6px; }
    input.campo, select.campo { width: 100%; font: inherit; padding: 12px 14px; border: 1.5px solid var(--line); border-radius: 12px; background: #fff; }
    input.campo:focus { border-color: var(--ink); outline: none; }
    .clase { display: grid; grid-template-columns: 6px minmax(0, 1fr) auto; gap: 12px; align-items: center; background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 14px 14px 14px 0; overflow: hidden; text-decoration: none; }
    .clase .c { align-self: stretch; border-radius: 0 6px 6px 0; }
    .clase .h { font-size: 13.5px; color: var(--muted); }
    .clase .n { font-weight: 650; font-size: 16.5px; line-height: 1.2; margin: 1px 0 3px; }
    .clase .m { font-size: 13px; color: var(--muted); }
    .clase form { margin: 0; }
    .pill { display: inline-flex; align-items: center; gap: 5px; font-size: 12.5px; font-weight: 600; padding: 3px 10px; border-radius: 99px; background: var(--mist); color: var(--muted); white-space: nowrap; }
    .pill.ok { background: #E3FBEF; color: var(--green-ink); } .pill.llena { background: #FDECEC; color: var(--red); } .pill.pocos { background: var(--amber-soft); color: var(--amber); }
    .dias { display: flex; gap: 8px; overflow-x: auto; padding: 4px 2px 10px; scrollbar-width: none; margin: 0 -16px; padding-left: 16px; padding-right: 16px; }
    .dias::-webkit-scrollbar { display: none; }
    .dias a { flex: none; width: 56px; text-align: center; text-decoration: none; background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 8px 0; }
    .dias a b { display: block; font-size: 19px; } .dias a span { font-size: 12px; color: var(--muted); text-transform: capitalize; }
    .dias a.on { background: var(--ink); color: #fff; border-color: var(--ink); } .dias a.on span { color: #c9ccd3; }
    .chips { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px; }
    .chips a { text-decoration: none; font-size: 13.5px; padding: 6px 12px; border-radius: 99px; background: #fff; border: 1px solid var(--line); }
    .chips a.on { background: var(--ink); color: #fff; border-color: var(--ink); }
    .creditos { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 4px 14px; align-items: center; background: var(--ink); color: #fff; border-radius: 18px; padding: 18px; }
    .creditos .k { font-size: 13.5px; color: #c9ccd3; } .creditos .v { font-size: 34px; font-weight: 700; line-height: 1; }
    .creditos .barra { grid-column: 1 / -1; height: 8px; background: rgba(255,255,255,.15); border-radius: 99px; overflow: hidden; margin-top: 10px; }
    .creditos .barra span { display: block; height: 100%; background: var(--green); }
    .vacio { text-align: center; color: var(--muted); padding: 26px 16px; background: #fff; border: 1px dashed var(--line); border-radius: 16px; }
    .tabs { position: fixed; left: 0; right: 0; bottom: 0; z-index: 20; background: #fff; border-top: 1px solid var(--line); display: grid; grid-template-columns: repeat(4, 1fr); padding-bottom: env(safe-area-inset-bottom); }
    .tabs a { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 9px 0 8px; font-size: 11.5px; color: var(--muted); text-decoration: none; }
    .tabs a i { font-size: 21px; } .tabs a.on { color: var(--ink); font-weight: 600; }
    .bloque { padding: 10px 0; } .bloque + .bloque { border-top: 1px solid var(--line); }
    .bloque ul { margin: 4px 0 0; padding-left: 18px; }
    .qr-grande { display: grid; place-items: center; background: #fff; border-radius: 22px; padding: 22px; }
    .qr-grande canvas, .qr-grande img { width: min(72vw, 300px) !important; height: min(72vw, 300px) !important; }
</style>
