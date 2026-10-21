<style>
    @font-face { font-family: 'Geist'; src: url('{{ route('vandu.fuente') }}') format('woff2'); font-weight: 100 900; font-display: swap; }
    /* App del comensal: la tarjeta de sellos de toda la vida, con la identidad de la plataforma */
    :root { --crema: #FFF4EC; --tinta: #1A103D; --tinta-2: #4A4460; --gris: #7A7390; --linea: #EFE2D6; --blanco: #FFFFFF;
            --purpura: #6B3BFF; --purpura-2: #5A2BEE; --purpura-suave: #EFE9FF; --cian: #41C9FF; --amarillo: #FFD447; --rosa: #FF6EDB; --verde: #11A36A; }
    * { box-sizing: border-box; }
    html { -webkit-text-size-adjust: 100%; }
    body { margin: 0; font-family: 'Geist', system-ui, sans-serif; color: var(--tinta); background: var(--crema); font-size: 16px; line-height: 1.45; min-height: 100vh; padding-bottom: 32px; }
    body.con-tabs { padding-bottom: calc(96px + env(safe-area-inset-bottom)); }
    a { color: var(--purpura); }
    :focus-visible { outline: 3px solid var(--purpura); outline-offset: 2px; border-radius: 6px; }
    .num { font-variant-numeric: tabular-nums; }
    .cab { display: flex; align-items: center; justify-content: space-between; gap: 12px; max-width: 560px; margin: 0 auto; padding: calc(14px + env(safe-area-inset-top)) 20px 6px; }
    .cab .marca img { height: 26px; display: block; }
    .cab .yo { width: 38px; height: 38px; border-radius: 50%; background: var(--tinta); color: #fff; display: grid; place-items: center; font-weight: 700; font-size: 13px; text-decoration: none; }
    .cab .entrar { font-weight: 600; text-decoration: none; }
    .doc { max-width: 560px; margin: 0 auto; padding: 10px 20px 24px; }
    h1 { font-size: 34px; line-height: 1.02; letter-spacing: -.035em; font-weight: 800; margin: 14px 0 8px; }
    h2 { font-size: 18px; font-weight: 750; letter-spacing: -.01em; margin: 28px 0 12px; }
    .sub { color: var(--tinta-2); margin: 0; }
    .tarjeta { background: var(--blanco); border: 1px solid var(--linea); border-radius: 22px; padding: 18px; }
    .lista > * + * { margin-top: 12px; }
    .aviso { display: flex; gap: 10px; align-items: flex-start; border-radius: 14px; padding: 12px 14px; margin: 6px 0 14px; font-size: 15px; }
    .aviso.ok { background: #DDF7EA; color: #0B5E3E; } .aviso.error { background: #FFE3E6; color: #A3172B; } .aviso.info { background: #FFF0C2; color: #6B4A00; }
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 52px; padding: 0 22px; border-radius: 16px; border: 0; background: var(--purpura); color: #fff; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; box-shadow: 0 8px 20px rgba(107,59,255,.28); }
    .btn:hover { background: var(--purpura-2); }
    .btn.sec { background: var(--blanco); color: var(--tinta); box-shadow: inset 0 0 0 1.5px var(--linea); }
    .btn.oscuro { background: var(--tinta); box-shadow: 0 8px 20px rgba(26,16,61,.25); }
    .btn.full { width: 100%; }
    .btn.chico { min-height: 40px; padding: 0 14px; font-size: 14px; border-radius: 12px; box-shadow: none; }
    .btn.peligro { background: transparent; color: #A3172B; box-shadow: inset 0 0 0 1.5px #F5C2C9; }
    label.campo { display: block; font-size: 14px; font-weight: 600; margin: 14px 0 6px; }
    input.campo { width: 100%; font: inherit; padding: 14px; border: 1.5px solid var(--linea); border-radius: 14px; background: var(--blanco); color: var(--tinta); }
    input.campo:focus { border-color: var(--purpura); outline: none; box-shadow: 0 0 0 4px rgba(107,59,255,.15); }
    .check { display: flex; gap: 10px; align-items: flex-start; margin-top: 14px; font-size: 14.5px; color: var(--tinta-2); }
    .check input { width: 20px; height: 20px; accent-color: var(--purpura); flex: none; margin-top: 1px; }
    .vacio { text-align: center; padding: 22px 18px; }
    .vacio img { width: 120px; }
    .tabs { position: fixed; left: 50%; transform: translateX(-50%); bottom: calc(12px + env(safe-area-inset-bottom)); z-index: 20; width: min(380px, calc(100% - 24px));
            background: var(--tinta); border-radius: 22px; display: grid; grid-template-columns: repeat(3, 1fr); padding: 6px; box-shadow: 0 16px 36px rgba(26,16,61,.3); }
    .tabs a { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 7px 0 6px; font-size: 12px; color: #B9B2D6; text-decoration: none; border-radius: 16px; }
    .tabs a i { font-size: 20px; } .tabs a.on { color: #fff; background: rgba(255,255,255,.1); font-weight: 600; } .tabs a.on i { color: var(--cian); }

    /* Restaurante con su tarjeta de sellos */
    .resto { display: grid; gap: 14px; }
    .resto .top { display: flex; align-items: center; gap: 12px; text-decoration: none; color: inherit; }
    .resto .logo { width: 52px; height: 52px; border-radius: 16px; background: var(--tinta); color: #fff; display: grid; place-items: center; font-weight: 800; font-size: 18px; overflow: hidden; flex: none; }
    .resto .logo img { width: 100%; height: 100%; object-fit: contain; padding: 6px; }
    .resto .top b { font-size: 18px; font-weight: 750; display: block; line-height: 1.2; }
    .resto .top > div span { font-size: 13.5px; color: var(--gris); }
    .sellos { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; }
    .sello { aspect-ratio: 1; border-radius: 50%; border: 2px dashed #E2D3C5; display: grid; place-items: center; font-size: 13px; font-weight: 700; color: #C7B8A9; position: relative; }
    .sello.lleno { border: 0; background: var(--purpura); color: #fff; transform: rotate(-8deg); box-shadow: inset 0 0 0 3px rgba(255,255,255,.25); }
    .sello.premio { border-color: var(--rosa); color: var(--rosa); }
    .sello.premio i { font-size: 18px; }
    .sello.lleno.premio { background: var(--rosa); color: #fff; }
    .camp-n { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; font-weight: 700; }
    .camp-n span { font-weight: 500; font-size: 13.5px; color: var(--gris); }
    .ganado { display: flex; align-items: center; gap: 10px; background: #FFF0F9; border: 1.5px solid #FFC6EE; border-radius: 14px; padding: 10px 12px; font-size: 14.5px; }
    .ganado i { color: var(--rosa); font-size: 20px; }
    .proximo { font-size: 14px; color: var(--tinta-2); }
    .proximo b { color: var(--tinta); }
    .escalera { display: grid; gap: 8px; }
    .escalera > div { display: flex; align-items: center; gap: 12px; background: var(--blanco); border: 1px solid var(--linea); border-radius: 14px; padding: 10px 12px; }
    .escalera b.n { width: 38px; height: 38px; border-radius: 12px; background: var(--purpura-suave); color: var(--purpura); display: grid; place-items: center; font-size: 15px; flex: none; }
    .escalera .t { font-weight: 650; } .escalera .d { font-size: 13.5px; color: var(--gris); }
</style>
