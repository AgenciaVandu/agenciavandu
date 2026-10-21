<style>
    @font-face { font-family: 'Geist'; src: url('{{ route('vandu.fuente') }}') format('woff2'); font-weight: 100 900; font-display: swap; }
    /* Portal del atleta: tablero de rendimiento. Números en condensada (cronómetro / dorsal), cada clase con su color */
    :root {
        --noche: #0D1124; --placa: #151A31; --placa-2: #1B2140; --riel: #252B47; --tiza: #EEF0F7; --niebla: #8C93B0;
        --senal: #5B8CFF; --senal-2: #8DB0FF; --calor: #FF7A3D; --bien: #3DDC97; --mal: #FF5C6C;
        --num: 'Barlow Condensed', 'Geist', system-ui, sans-serif;
    }
    * { box-sizing: border-box; }
    html { -webkit-text-size-adjust: 100%; background: var(--noche); }
    body { margin: 0; font-family: 'Geist', system-ui, sans-serif; color: var(--tiza); font-size: 16px; line-height: 1.45; min-height: 100vh;
           background: radial-gradient(120% 60% at 50% -10%, #1B2550 0%, transparent 60%), var(--noche); background-attachment: fixed;
           padding-bottom: calc(96px + env(safe-area-inset-bottom)); }
    body.sin-tabs { padding-bottom: 32px; }
    a { color: inherit; }
    :focus-visible { outline: 2px solid var(--senal-2); outline-offset: 3px; border-radius: 6px; }
    .num { font-variant-numeric: tabular-nums; }
    .cifra { font-family: var(--num); font-weight: 700; font-style: italic; letter-spacing: .01em; font-variant-numeric: tabular-nums; line-height: .95; }

    /* Cabecera */
    .cab { position: sticky; top: 0; z-index: 10; padding: calc(10px + env(safe-area-inset-top)) 18px 10px; background: rgba(13,17,36,.78); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); border-bottom: 1px solid rgba(37,43,71,.7); }
    .cab .in { max-width: 560px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 40px; }
    .cab .logo { max-height: 30px; max-width: 150px; } .cab .marca { font-family: var(--num); font-size: 22px; font-weight: 700; letter-spacing: .02em; }
    .cab .yo { width: 36px; height: 36px; border-radius: 50%; overflow: hidden; background: var(--placa-2); border: 1px solid var(--riel); display: grid; place-items: center; text-decoration: none; font-size: 13px; font-weight: 600; }
    .cab .yo img { width: 100%; height: 100%; object-fit: cover; }

    .doc { max-width: 560px; margin: 0 auto; padding: 18px 18px 24px; }
    h1 { font-family: var(--num); font-size: 38px; font-weight: 700; letter-spacing: .005em; line-height: 1; margin: 8px 0 6px; }
    h2 { font-size: 15px; margin: 30px 0 12px; font-weight: 600; display: flex; justify-content: space-between; align-items: baseline; gap: 8px; color: var(--tiza); }
    h2 a { font-size: 14px; font-weight: 500; color: var(--senal-2); text-decoration: none; }
    .sub { color: var(--niebla); margin: 0; }
    .tarjeta { background: var(--placa); border: 1px solid var(--riel); border-radius: 18px; padding: 18px; }
    .tarjeta + .tarjeta { margin-top: 12px; }
    .lista > * + * { margin-top: 10px; }

    .aviso { display: flex; gap: 10px; align-items: flex-start; border-radius: 14px; padding: 12px 14px; margin-bottom: 14px; font-size: 15px; border: 1px solid; }
    .aviso.ok { background: rgba(61,220,151,.1); border-color: rgba(61,220,151,.3); color: #9FF0CB; }
    .aviso.error { background: rgba(255,92,108,.1); border-color: rgba(255,92,108,.35); color: #FFB3BB; }
    .aviso.info { background: rgba(255,122,61,.1); border-color: rgba(255,122,61,.35); color: #FFC3A6; }

    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 48px; padding: 0 20px; border-radius: 14px; border: 0; background: var(--senal); color: #fff;
           font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; transition: background .15s, transform .1s; }
    .btn:hover { background: #4A7BF0; } .btn:active { transform: scale(.98); }
    .btn.sec { background: var(--placa-2); color: var(--tiza); box-shadow: inset 0 0 0 1px var(--riel); }
    .btn.sec:hover { background: #222951; }
    .btn.peligro { background: transparent; color: var(--mal); box-shadow: inset 0 0 0 1px rgba(255,92,108,.45); }
    .btn.chico { min-height: 38px; padding: 0 14px; font-size: 14px; border-radius: 11px; }
    .btn.full { width: 100%; }
    .btn[disabled] { opacity: .45; cursor: not-allowed; }
    label.campo { display: block; font-size: 14px; font-weight: 500; margin: 16px 0 6px; color: var(--niebla); }
    input.campo, select.campo { width: 100%; font: inherit; color: var(--tiza); padding: 13px 14px; border: 1px solid var(--riel); border-radius: 12px; background: var(--noche); }
    input.campo:focus { border-color: var(--senal); outline: none; box-shadow: 0 0 0 3px rgba(91,140,255,.25); }
    input.campo:disabled { color: var(--niebla); }

    /* Próxima clase: el boleto con cuenta regresiva */
    .boleto { position: relative; overflow: hidden; border-radius: 22px; padding: 20px 20px 18px; background: linear-gradient(140deg, color-mix(in srgb, var(--c) 30%, var(--placa)) 0%, var(--placa) 62%); border: 1px solid color-mix(in srgb, var(--c) 45%, var(--riel)); text-decoration: none; display: block; }
    .boleto::after { content: ''; position: absolute; right: -60px; top: -60px; width: 200px; height: 200px; border-radius: 50%; background: radial-gradient(circle, color-mix(in srgb, var(--c) 40%, transparent) 0%, transparent 70%); pointer-events: none; }
    .boleto .que { font-size: 14px; color: var(--niebla); }
    .boleto .nombre { font-family: var(--num); font-size: 30px; font-weight: 700; line-height: 1.05; margin: 2px 0 2px; }
    .boleto .cuando { font-size: 15px; }
    .boleto .reloj { display: flex; align-items: baseline; gap: 10px; margin-top: 16px; }
    .boleto .reloj .cifra { font-size: 54px; }
    .boleto .reloj .eti { font-size: 14px; color: var(--niebla); }
    .boleto.pronto .reloj .cifra { color: var(--calor); }
    .boleto .pie { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-top: 14px; padding-top: 14px; border-top: 1px dashed color-mix(in srgb, var(--c) 35%, var(--riel)); font-size: 14px; color: var(--niebla); }

    /* Paquete: anillo con un segmento por clase */
    .paquete { display: grid; grid-template-columns: 132px minmax(0, 1fr); gap: 18px; align-items: center; }
    .anillo { position: relative; width: 132px; height: 132px; }
    .anillo svg { width: 100%; height: 100%; transform: rotate(-90deg); }
    .anillo .centro { position: absolute; inset: 0; display: grid; place-content: center; text-align: center; }
    .anillo .centro .cifra { font-size: 46px; }
    .anillo .centro small { font-size: 12.5px; color: var(--niebla); }
    .paquete .dato { font-size: 14px; color: var(--niebla); }
    .paquete .dato b { color: var(--tiza); font-weight: 600; }
    .paquete .nombre-p { font-weight: 600; font-size: 17px; margin-bottom: 6px; }
    .paquete .fila { display: flex; justify-content: space-between; gap: 10px; padding: 6px 0; border-top: 1px solid var(--riel); font-size: 14px; }
    .paquete .fila span:first-child { color: var(--niebla); }
    .paquete .fila.alerta span:last-child { color: var(--calor); font-weight: 600; }
    @media (max-width: 359.98px) { .paquete { grid-template-columns: 1fr; justify-items: center; } }

    /* Clase en lista: hora grande a la izquierda, riel con su color y cupo por lugar */
    .clase { display: grid; grid-template-columns: 62px 3px minmax(0, 1fr) auto; gap: 12px; align-items: center; background: var(--placa); border: 1px solid var(--riel); border-radius: 16px; padding: 14px 14px 14px 12px; }
    .clase .hora { text-align: right; }
    .clase .hora .cifra { font-size: 26px; display: block; }
    .clase .hora small { font-size: 12px; color: var(--niebla); }
    .clase .riel { align-self: stretch; border-radius: 3px; background: var(--c); box-shadow: 0 0 12px color-mix(in srgb, var(--c) 60%, transparent); }
    .clase .info { text-decoration: none; min-width: 0; }
    .clase .n { font-weight: 650; font-size: 16.5px; line-height: 1.2; }
    .clase .m { font-size: 13px; color: var(--niebla); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .clase .dia { font-size: 12.5px; color: var(--senal-2); font-weight: 600; }
    .clase form { margin: 0; }
    .clase .accion { display: grid; gap: 6px; justify-items: end; }
    .cupo { display: flex; gap: 2px; margin-top: 8px; max-width: 180px; }
    .cupo i { flex: 1; height: 5px; border-radius: 2px; background: var(--riel); }
    .cupo i.o { background: var(--niebla); } .cupo i.yo { background: var(--c); }
    .cupo.continuo { height: 5px; border-radius: 3px; background: var(--riel); overflow: hidden; }
    .cupo.continuo span { display: block; height: 100%; background: var(--niebla); }
    .pill { display: inline-flex; align-items: center; gap: 5px; font-size: 12.5px; font-weight: 600; padding: 3px 10px; border-radius: 99px; background: var(--placa-2); color: var(--niebla); white-space: nowrap; }
    .pill.ok { background: rgba(61,220,151,.14); color: var(--bien); } .pill.llena { background: rgba(255,92,108,.14); color: var(--mal); } .pill.pocos { background: rgba(255,122,61,.14); color: var(--calor); }
    .pill.vivo::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: var(--bien); box-shadow: 0 0 0 0 rgba(61,220,151,.7); animation: latido 1.6s infinite; }
    @keyframes latido { 70% { box-shadow: 0 0 0 7px rgba(61,220,151,0); } 100% { box-shadow: 0 0 0 0 rgba(61,220,151,0); } }

    /* Días y filtros */
    .dias { display: flex; gap: 8px; overflow-x: auto; scrollbar-width: none; margin: 0 -18px; padding: 4px 18px 12px; }
    .dias::-webkit-scrollbar { display: none; }
    .dias a { flex: none; width: 58px; text-align: center; text-decoration: none; background: var(--placa); border: 1px solid var(--riel); border-radius: 16px; padding: 8px 0 9px; }
    .dias a b { display: block; font-family: var(--num); font-style: italic; font-size: 26px; font-weight: 700; line-height: 1.05; } .dias a span { font-size: 12px; color: var(--niebla); text-transform: capitalize; }
    .dias a.on { background: var(--senal); border-color: var(--senal); } .dias a.on span { color: #DCE6FF; }
    .chips { display: flex; gap: 6px; overflow-x: auto; scrollbar-width: none; margin: 0 -18px 14px; padding: 0 18px; }
    .chips::-webkit-scrollbar { display: none; }
    .chips a { flex: none; text-decoration: none; font-size: 13.5px; padding: 7px 13px; border-radius: 99px; background: var(--placa); border: 1px solid var(--riel); color: var(--niebla); display: inline-flex; align-items: center; gap: 7px; }
    .chips a i { width: 8px; height: 8px; border-radius: 50%; background: var(--c, var(--niebla)); }
    .chips a.on { color: var(--tiza); border-color: var(--tiza); }

    .vacio { text-align: center; color: var(--niebla); padding: 28px 18px; background: var(--placa); border: 1px dashed var(--riel); border-radius: 18px; }

    /* Barra inferior flotante */
    .tabs { position: fixed; left: 50%; transform: translateX(-50%); bottom: calc(12px + env(safe-area-inset-bottom)); z-index: 20; width: min(420px, calc(100% - 24px));
            background: rgba(21,26,49,.86); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid var(--riel); border-radius: 22px;
            display: grid; grid-template-columns: repeat(4, 1fr); padding: 6px; box-shadow: 0 16px 40px rgba(0,0,0,.45); }
    .tabs a { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 7px 0 6px; font-size: 11.5px; color: var(--niebla); text-decoration: none; border-radius: 16px; }
    .tabs a i { font-size: 20px; } .tabs a.on { color: var(--tiza); background: var(--placa-2); font-weight: 600; }
    .tabs a.on i { color: var(--senal-2); }

    .bloque { padding: 12px 0; } .bloque + .bloque { border-top: 1px solid var(--riel); }
    .bloque .bn { display: flex; justify-content: space-between; gap: 8px; font-weight: 600; }
    .bloque .bn span { font-weight: 400; color: var(--niebla); font-size: 14px; }
    .bloque ol { list-style: none; margin: 8px 0 0; padding: 0; display: grid; gap: 6px; }
    .bloque li { display: grid; grid-template-columns: 70px minmax(0, 1fr); gap: 10px; font-size: 15px; }
    .bloque li .cant { font-family: var(--num); font-style: italic; font-weight: 700; font-size: 18px; text-align: right; color: var(--c, var(--senal-2)); }
    .bloque li .nota { color: var(--niebla); font-size: 13px; }

    /* Credencial: QR en placa blanca con marcas de escaneo */
    .credencial { position: relative; margin: 18px auto 0; width: min(84vw, 340px); padding: 18px; border-radius: 26px; background: #fff; color: var(--noche); }
    .credencial::before, .credencial::after { content: ''; position: absolute; width: 36px; height: 36px; border: 3px solid var(--senal); }
    .credencial::before { top: -8px; left: -8px; border-right: 0; border-bottom: 0; border-radius: 14px 0 0 0; }
    .credencial::after { bottom: -8px; right: -8px; border-left: 0; border-top: 0; border-radius: 0 0 14px 0; }
    .credencial #qr canvas, .credencial #qr img { width: 100% !important; height: auto !important; display: block; }
    .credencial .quien { display: flex; justify-content: space-between; align-items: end; gap: 10px; margin-top: 12px; }
    .credencial .quien b { font-size: 17px; line-height: 1.2; } .credencial .quien span { font-size: 12.5px; color: #5d6270; }
    .credencial .quien .cifra { font-size: 30px; color: var(--noche); }

    /* Entrar */
    .entrada { max-width: 400px; margin: 4vh auto 0; }
    .entrada .marca-g { display: grid; place-items: center; margin-bottom: 22px; }
    .entrada .marca-g img { max-height: 54px; max-width: 200px; }
    .entrada .marca-g b { font-family: var(--num); font-size: 34px; font-style: italic; }

    @media (prefers-reduced-motion: reduce) { .pill.vivo::before { animation: none; } .btn { transition: none; } }
</style>
