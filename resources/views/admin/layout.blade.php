@php
    $usuario = auth()->user();
    $iniciales = collect(explode(' ', trim($usuario?->name ?? 'V')))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->join('');
    $vigentesNav = \App\Models\Presupuesto::where(fn ($q) => $q->where('estado', 'negociacion')
        ->orWhere(fn ($q2) => $q2->where('vigente_hasta', '>', now())->whereNotIn('estado', ['aceptada', 'rechazada'])))->count();
    $activosNav = \App\Models\Proyecto::where('estado', 'activo')->count();
    try { $redesNav = \App\Models\RedesPost::where('estado', 'cambios')->count(); } catch (\Throwable $e) { $redesNav = 0; }
    try { $nuevosNav = \App\Models\Cliente::where('nuevo', true)->count(); } catch (\Throwable $e) { $nuevosNav = 0; } // antes de migrar
    $cuentaNav = \App\Support\Cuentas::actual();
    $viendoOtra = $usuario?->plataforma && $cuentaNav && $cuentaNav->id !== $usuario->cuenta_id;
    // Cada quien ve solo las secciones de su rol
    $puede = fn ($s) => $usuario?->puede($s) ?? false;
    try { $tareasNav = \App\Models\Tarea::where('asignada_a', $usuario?->id)->abiertas()->count(); } catch (\Throwable $e) { $tareasNav = 0; }
    $nav = array_values(array_filter([
        ['ruta' => 'admin.resumen',             'activo' => 'admin.resumen',          'icono' => 'bi-grid-1x2',        'texto' => 'Resumen', 'seccion' => 'resumen'],
        ['ruta' => 'admin.tareas.index',        'activo' => 'admin.tareas.*',         'icono' => 'bi-check2-square',   'texto' => $puede('tareas') ? 'Tareas' : 'Mis tareas', 'cuenta' => $tareasNav, 'seccion' => null],
        ['ruta' => 'admin.presupuestos.index',  'activo' => 'admin.presupuestos.*',   'icono' => 'bi-file-earmark-text','texto' => 'Cotizaciones', 'cuenta' => $vigentesNav, 'seccion' => 'cotizaciones'],
        ['ruta' => 'admin.proyectos.index',     'activo' => 'admin.proyectos.*',      'icono' => 'bi-kanban',          'texto' => 'Proyectos', 'cuenta' => $activosNav, 'seccion' => 'proyectos'],
        ['ruta' => 'admin.redes',               'activo' => 'admin.redes*',           'icono' => 'bi-grid-3x3-gap',    'texto' => 'Redes sociales', 'cuenta' => $redesNav ?? 0, 'seccion' => 'redes'],
        ['ruta' => 'admin.clientes.index',      'activo' => 'admin.clientes.*',       'icono' => 'bi-people',          'texto' => 'Clientes', 'cuenta' => $nuevosNav, 'nuevos' => true, 'seccion' => 'clientes'],
        ['ruta' => 'admin.finanzas',            'activo' => 'admin.finanzas*',        'icono' => 'bi-graph-up-arrow',  'texto' => 'Finanzas', 'seccion' => 'finanzas'],
    ], fn ($i) => $i['seccion'] === null || $puede($i['seccion'])));
    $inicioNav = $usuario ? \App\Support\Permisos::inicio($usuario) : route('admin.resumen');
    // Barra inferior del celular: hasta 4 accesos (+ el botón de nueva cotización si la puede hacer)
    $tabs = array_slice(array_values(array_filter([
        $puede('resumen') ? ['url' => route('admin.resumen'), 'activo' => 'admin.resumen', 'icono' => 'bi-grid-1x2', 'lleno' => 'bi-grid-1x2-fill', 'texto' => 'Inicio'] : null,
        $puede('cotizaciones') ? ['url' => route('admin.presupuestos.index'), 'activo' => 'admin.presupuestos.*', 'icono' => 'bi-file-earmark-text', 'lleno' => 'bi-file-earmark-text-fill', 'texto' => 'Cotizaciones', 'cuenta' => $vigentesNav] : null,
        $puede('proyectos') ? ['url' => route('admin.proyectos.index'), 'activo' => 'admin.proyectos.*', 'icono' => 'bi-kanban', 'lleno' => 'bi-kanban-fill', 'texto' => 'Proyectos'] : null,
        ['url' => route('admin.tareas.index'), 'activo' => 'admin.tareas.*', 'icono' => 'bi-check2-square', 'lleno' => 'bi-check2-square', 'texto' => 'Tareas', 'cuenta' => $tareasNav],
        $puede('finanzas') ? ['url' => route('admin.finanzas'), 'activo' => 'admin.finanzas*', 'icono' => 'bi-graph-up-arrow', 'lleno' => 'bi-graph-up-arrow', 'texto' => 'Finanzas'] : null,
        $puede('redes') ? ['url' => route('admin.redes'), 'activo' => 'admin.redes*', 'icono' => 'bi-grid-3x3-gap', 'lleno' => 'bi-grid-3x3-gap-fill', 'texto' => 'Redes'] : null,
        ['url' => route('admin.notificaciones'), 'activo' => 'admin.notificaciones*', 'icono' => 'bi-bell', 'lleno' => 'bi-bell-fill', 'texto' => 'Avisos'],
    ])), 0, 4);
    $conMas = $puede('cotizaciones');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titulo', 'Panel') · {{ \App\Support\Cuentas::esPrincipal() ? 'Vandu' : \App\Support\Cuentas::actual()?->nombre }}</title>
    <link rel="icon" href="/favi.svg" type="image/svg+xml">
    <link rel="manifest" href="{{ route('app.manifiesto') }}">
    <meta name="theme-color" content="#13161D">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Vandu">
    <link rel="apple-touch-icon" href="{{ route('app.icono', 'apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ route('app.icono', 'favicon-32.png') }}">
    <link rel="preload" href="{{ route('vandu.fuente') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <style id="estilos-base">
        @font-face { font-family: 'Geist'; src: url('{{ route('vandu.fuente') }}') format('woff2'); font-weight: 100 900; font-display: swap; }

        /* ===================== Tokens ===================== */
        :root {
            --ink: #13161D;  --ink-2: #1B1F28; --ink-3: #262B36; --ink-line: #2C313D;
            --canvas: #F4F5F7; --surface: #FFFFFF; --sunken: #F8F9FA;
            --line: #E5E7EB; --line-strong: #D5D8DE;
            --text: #13161D; --text-2: #454A57; --muted: #6B7080; --faint: #9AA0AC;
            --green: #00F385; --green-ink: #047A4B; --green-soft: #E3FBEF;
            --blue: #2557D6; --blue-soft: #E8EEFC;
            --amber: #A35A00; --amber-soft: #FFF3DD;
            --red: #C2322B; --red-soft: #FDECEA;
            --radius: 12px; --radius-sm: 8px;
            --side: 252px;

            --bs-body-font-family: 'Geist', system-ui, -apple-system, sans-serif;
            --bs-body-color: var(--text); --bs-body-bg: var(--canvas); --bs-body-font-size: .9375rem;
            --bs-border-color: var(--line); --bs-border-radius: var(--radius-sm);
            --bs-link-color-rgb: 19,22,29; --bs-link-hover-color-rgb: 0,0,0;
            --bs-emphasis-color: var(--text); --bs-secondary-color: var(--muted);
        }
        body { -webkit-font-smoothing: antialiased; }
        .num { font-variant-numeric: tabular-nums; }
        :focus-visible { outline: 2px solid var(--ink); outline-offset: 2px; }

        /* ===================== Shell ===================== */
        .side { position: fixed; inset: 0 auto 0 0; width: var(--side); background: var(--ink); color: #C9CDD6;
                display: flex; flex-direction: column; z-index: 1040; overflow: hidden; }
        .side-brand { padding: 22px 22px 18px; display: flex; align-items: center; justify-content: space-between; }
        .side-brand img { height: 26px; width: auto; }
        .side-cta { margin: 6px 16px 18px; }
        .side-cta a { display: flex; align-items: center; justify-content: center; gap: 8px; height: 40px; border-radius: 10px;
                      background: var(--green); color: var(--ink); font-weight: 600; font-size: 14px; text-decoration: none; }
        .side-cta a:hover { background: #2bffa0; }
        .side-label { padding: 0 22px 8px; font-size: 12px; color: #727887; }
        .side nav a { position: relative; display: flex; align-items: center; gap: 12px; margin: 2px 12px; padding: 9px 12px;
                      border-radius: 8px; color: #C9CDD6; text-decoration: none; font-size: 14.5px; }
        .side nav a i { font-size: 17px; width: 18px; text-align: center; color: #8D93A1; }
        .side nav a:hover { background: var(--ink-2); color: #fff; }
        .side nav a.activo { background: var(--ink-3); color: #fff; }
        .side nav a.activo i { color: var(--green); }
        .side nav a.activo::before { content: ''; position: absolute; left: -12px; top: 8px; bottom: 8px; width: 3px; border-radius: 0 3px 3px 0; background: var(--green); }
        .side nav .cuenta.nuevos { background: var(--green); color: var(--ink); font-weight: 600; }
        .side nav .cuenta { margin-left: auto; font-size: 12px; padding: 1px 8px; border-radius: 99px; background: var(--ink-line); color: #E4E6EB; }
        .side-foot { margin-top: auto; padding: 14px 16px; border-top: 1px solid var(--ink-line); display: flex; align-items: center; gap: 10px; position: relative; z-index: 1; }
        .side-foot .yo { min-width: 0; flex: 1; line-height: 1.25; }
        .side-foot .yo b { display: block; color: #fff; font-weight: 600; font-size: 14px; }
        .side-foot .yo span { display: block; font-size: 12.5px; color: #8D93A1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .side-foot button { border: 0; background: transparent; color: #8D93A1; width: 34px; height: 34px; border-radius: 8px; }
        .side-foot button:hover { background: var(--ink-2); color: #fff; }
        .side-eco { position: absolute; right: -46px; bottom: 40px; width: 190px; opacity: .05; pointer-events: none; }

        .avatar { flex: none; width: 34px; height: 34px; border-radius: 50%; display: inline-grid; place-items: center;
                  font-size: 13px; font-weight: 600; letter-spacing: .02em; text-transform: uppercase; }
        .avatar.av-yo { background: var(--green); color: var(--ink); }

        .main { margin-left: var(--side); min-height: 100vh; }
        .page { max-width: 1240px; margin: 0 auto; padding: 32px 36px 64px; }
        .page-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 24px; }
        .page-head h1 { font-size: 26px; font-weight: 600; letter-spacing: -.02em; margin: 0; }
        .page-head .sub { color: var(--muted); margin: 4px 0 0; }
        .migas { font-size: 13.5px; color: var(--muted); margin-bottom: 6px; display: flex; gap: 6px; align-items: center; }
        .migas a { color: var(--muted); text-decoration: none; }
        .migas a:hover { color: var(--text); }

        .topbar-m { display: none; }

        /* ===================== Componentes ===================== */
        .btn { font-weight: 500; border-radius: var(--radius-sm); padding: .5rem .95rem; font-size: 14.5px; }
        .btn-sm { padding: .3rem .65rem; font-size: 13.5px; }
        .btn-primario { background: var(--ink); color: #fff; border: 1px solid var(--ink); }
        .btn-primario:hover, .btn-primario:focus { background: #000; color: #fff; border-color: #000; }
        .btn-acento { background: var(--green); color: var(--ink); border: 1px solid var(--green); font-weight: 600; }
        .btn-acento:hover { background: #2bffa0; border-color: #2bffa0; color: var(--ink); }
        .btn-borde { background: var(--surface); color: var(--text); border: 1px solid var(--line-strong); }
        .btn-borde:hover { background: var(--sunken); border-color: #C3C7CF; color: var(--text); }
        .btn-fantasma { background: transparent; color: var(--text-2); border: 1px solid transparent; }
        .btn-fantasma:hover { background: #ECEEF1; color: var(--text); }
        .btn-icono { width: 34px; height: 34px; padding: 0; display: inline-grid; place-items: center; }

        .panel { background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius); }
        .panel-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 20px; border-bottom: 1px solid var(--line); }
        .panel-head h2 { font-size: 15.5px; font-weight: 600; margin: 0; }
        .panel-head .ayuda { font-size: 13px; color: var(--muted); }
        .panel-body { padding: 20px; }

        .form-label { font-size: 13.5px; font-weight: 500; color: var(--text-2); margin-bottom: 6px; }
        .form-control, .form-select { background-color: var(--surface); border-color: var(--line-strong); border-radius: var(--radius-sm); padding: .55rem .8rem; font-size: 14.5px; }
        .form-control::placeholder { color: var(--faint); }
        .form-control[readonly] { background-color: var(--sunken); }
        .form-control:focus, .form-select:focus { border-color: var(--ink); box-shadow: 0 0 0 3px rgba(19,22,29,.12); }
        .input-group-text { background: var(--sunken); border-color: var(--line-strong); color: var(--muted); }
        .form-check-input:checked { background-color: var(--ink); border-color: var(--ink); }
        .form-check-input:focus { box-shadow: 0 0 0 3px rgba(19,22,29,.12); border-color: var(--ink); }

        /* Tablas */
        .tabla { width: 100%; margin: 0; border-collapse: separate; border-spacing: 0; }
        .tabla th { font-size: 12.5px; font-weight: 500; color: var(--muted); padding: 11px 16px; background: var(--sunken);
                    border-bottom: 1px solid var(--line); white-space: nowrap; }
        .tabla td { padding: 14px 16px; border-bottom: 1px solid var(--line); vertical-align: middle; }
        .tabla tbody tr:last-child td { border-bottom: 0; }
        .tabla tbody tr { transition: background .12s; }
        .tabla tbody tr:hover { background: #FAFBFC; }
        .tabla .fila-link { color: inherit; text-decoration: none; }
        .tabla .fila-link:hover .principal { text-decoration: underline; text-underline-offset: 3px; }
        .principal { font-weight: 600; color: var(--text); }
        .secundario { font-size: 13.5px; color: var(--muted); }
        .persona { display: flex; align-items: center; gap: 12px; min-width: 0; }
        .persona > div { min-width: 0; }

        /* Estados y vigencia */
        .estado { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 500; padding: 3px 10px 3px 8px; border-radius: 99px; white-space: nowrap; }
        .estado::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .estado-borrador { background: #EEF0F3; color: #4E5463; }
        .estado-enviada { background: var(--blue-soft); color: var(--blue); }
        .estado-aceptada { background: var(--green-soft); color: var(--green-ink); }
        .estado-rechazada { background: var(--red-soft); color: var(--red); }
        .estado-negociacion { background: #F3E8FD; color: #6E32B5; }
        .vig { display: inline-flex; align-items: center; gap: 6px; font-size: 13.5px; white-space: nowrap; }
        .vig-ok { color: var(--text-2); } .vig-ok i { color: var(--green-ink); }
        .vig-pronto { color: var(--amber); }
        .vig-vencida { color: var(--red); }

        /* Filtros tipo segmento */
        .segmento { display: inline-flex; background: #E9EBEE; border-radius: 10px; padding: 3px; gap: 2px; flex-wrap: wrap; }
        .segmento a { padding: 6px 12px; border-radius: 8px; font-size: 14px; color: var(--text-2); text-decoration: none; display: inline-flex; gap: 6px; align-items: center; }
        .segmento a:hover { color: var(--text); }
        .segmento a.activo { background: var(--surface); color: var(--text); font-weight: 500; box-shadow: 0 1px 2px rgba(16,24,40,.08); }
        .segmento .n { font-size: 12px; color: var(--muted); }

        .buscador { position: relative; }
        .buscador i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--faint); pointer-events: none; }
        .buscador input { padding-left: 36px; min-width: 260px; background: var(--surface); }

        .vacio { text-align: center; padding: 56px 24px; }
        .vacio .ico { width: 52px; height: 52px; border-radius: 14px; background: var(--sunken); border: 1px solid var(--line); display: inline-grid; place-items: center; font-size: 22px; color: var(--muted); margin-bottom: 14px; }
        .vacio h3 { font-size: 16px; font-weight: 600; margin: 0 0 4px; }
        .vacio p { color: var(--muted); margin: 0 0 18px; }

        .aviso { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 14.5px; }
        .aviso-ok { background: var(--green-soft); color: var(--green-ink); border: 1px solid #BDF2D6; }
        /* El aviso de "guardado" flota abajo a la derecha y se va solo */
        .page > .aviso-ok { position: fixed; right: 24px; bottom: 24px; z-index: 1080; margin: 0; max-width: min(420px, calc(100vw - 32px));
                            background: var(--ink); color: #fff; border: 0; box-shadow: 0 12px 32px rgba(16,24,40,.25); animation: toast 4s ease forwards; }
        .page > .aviso-ok i { color: var(--green); }
        @keyframes toast { 0% { opacity: 0; transform: translateY(8px); } 6%, 85% { opacity: 1; transform: none; } 100% { opacity: 0; transform: translateY(8px); visibility: hidden; } }
        .cargando-barra { position: fixed; top: 0; left: 0; height: 3px; width: 0; background: var(--green); z-index: 1090; transition: width .4s ease, opacity .3s; opacity: 0; }
        .cargando-barra.activa { opacity: 1; width: 70%; transition: width 4s cubic-bezier(.1,.6,.2,1); }
        .cargando-barra.lista { opacity: 0; width: 100%; transition: width .2s, opacity .3s .2s; }
        form.enviando button[type=submit], form.enviando button:not([type]) { pointer-events: none; opacity: .7; }
        @media (max-width: 575.98px) { .page > .aviso-ok { left: 16px; right: 16px; bottom: 16px; } }
        .aviso-error { background: var(--red-soft); color: var(--red); border: 1px solid #F6CFCB; }
        .aviso-error ul { margin: 4px 0 0; padding-left: 18px; }

        .dropdown-menu { border-color: var(--line); border-radius: 10px; box-shadow: 0 12px 32px rgba(16,24,40,.12); padding: 6px; font-size: 14px; }
        .dropdown-item { border-radius: 6px; padding: 7px 10px; display: flex; align-items: center; gap: 10px; }
        .dropdown-item i { color: var(--muted); width: 16px; }
        .dropdown-item:active { background: var(--ink); }
        .dropdown-item:active i { color: #fff; }
        .dropdown-divider { margin: 6px 0; border-color: var(--line); }

        .pagination { --bs-pagination-color: var(--text); --bs-pagination-active-bg: var(--ink); --bs-pagination-active-border-color: var(--ink); --bs-pagination-border-color: var(--line); }
        [x-cloak] { display: none !important; }
        main.page.cambiando { opacity: .55; transition: opacity .15s ease .12s; pointer-events: none; }
        main.page.entrando { animation: entrar .22s ease-out; }
        @keyframes entrar { from { opacity: .4; transform: translateY(4px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) { main.page.entrando { animation: none; } }
        /* Las tablas ocultas para lectores de pantalla no deben ensanchar la página en el celular */
        table.visually-hidden { display: block; }
        body { overflow-x: clip; }
        .main, .page, .panel { min-width: 0; }
        .table-responsive { max-width: 100%; position: relative; } /* que lo oculto para lectores no se salga del scroll */

        .aviso-push { display: flex; align-items: center; gap: 12px; padding: 12px 14px 12px 16px; border-radius: 12px; margin-bottom: 20px; background: var(--surface); border: 1px solid var(--line); }
        .aviso-push > i { font-size: 20px; color: var(--ink); }
        .aviso-contactos { border-color: #BDF2D6; background: var(--green-soft); }
        .aviso-contactos > i:first-child { color: var(--green-ink); }
        .aviso-contactos:hover { border-color: var(--green-ink); }
        .aviso-push .t { flex: 1; min-width: 0; font-size: 14px; }
        .aviso-push .t b { display: block; font-weight: 600; }
        .aviso-push .t span { color: var(--muted); font-size: 13px; }
        @media (max-width: 575.98px) { .aviso-push { flex-wrap: wrap; } .aviso-push .t { flex-basis: calc(100% - 80px); } .aviso-push .btn-primario { margin-left: 32px; } }

        /* ---------- Tablas en el celular: cada fila es una tarjeta, de 5 en 5 ---------- */
        .pager-m { display: none; }
        @media (max-width: 991.98px) {
            table.tabla-m, .tabla-m tbody { display: block; width: 100%; }
            .tabla-m thead { display: none; }
            .tabla-m tbody tr { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 10px; padding: 14px 16px; border-bottom: 1px solid var(--line); }
            .tabla-m tbody tr:last-child { border-bottom: 0; }
            .tabla-m tbody tr.fuera-m { display: none; }
            .tabla-m td { display: flow-root; flex: 1 1 100%; min-width: 0 !important; max-width: 100%; padding: 0; border: 0 !important; text-align: right !important; font-size: 14px; }
            .tabla-m td::before { content: attr(data-k); float: left; margin-right: 12px; font-size: 12.5px; color: var(--muted); font-weight: 400; line-height: 1.9; }
            .tabla-m td > .d-flex { justify-content: flex-end; }
            .tabla-m td.m-titulo { text-align: left !important; padding-bottom: 4px; }
            .tabla-m td.m-titulo::before, .tabla-m td.m-sin-k::before { content: none; }
            .tabla-m td.m-sin-k { flex: 0 0 auto; margin-left: auto; }
            .tabla-m td.m-sin-k + td.m-sin-k { margin-left: 0; }
            .tabla-m td .text-truncate { max-width: 100%; }
            .pager-m { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; border-top: 1px solid var(--line); background: var(--sunken); }
            .pager-m button { width: 40px; height: 36px; border: 1px solid var(--line-strong); background: var(--surface); border-radius: 10px; color: var(--text); display: grid; place-items: center; }
            .pager-m button:disabled { opacity: .35; }
            .pager-m span { font-size: 13.5px; color: var(--text-2); }
        }

        /* ---------- Ventana de correo ---------- */
        .correo-velo { position: fixed; inset: 0; z-index: 1080; background: rgba(15, 18, 25, .55); display: flex; align-items: flex-start; justify-content: center; padding: 32px 16px; overflow-y: auto; }
        .correo-ventana { background: var(--surface); border-radius: 16px; width: min(1180px, 100%); box-shadow: 0 24px 60px rgba(0,0,0,.25); display: flex; flex-direction: column; }
        .correo-cabeza { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding: 18px 22px; border-bottom: 1px solid var(--line); }
        .correo-cabeza h2 { font-size: 17px; font-weight: 600; margin: 0; }
        .correo-cuerpo { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.05fr); }
        .correo-campos { padding: 18px 22px; display: grid; gap: 14px; align-content: start; }
        .correo-plantillas { display: flex; flex-wrap: wrap; gap: 6px; }
        .correo-plantillas button { border: 1px solid var(--line-strong); background: var(--surface); border-radius: 99px; padding: 5px 12px; font-size: 13.5px; color: var(--text-2); display: inline-flex; gap: 6px; align-items: center; }
        .correo-plantillas button:hover { border-color: var(--ink); color: var(--text); }
        .correo-plantillas button.activo { background: var(--ink); border-color: var(--ink); color: #fff; }
        .correo-opciones { display: grid; gap: 8px; padding: 12px 14px; background: var(--sunken); border-radius: 10px; }
        .correo-opciones .form-check { margin: 0; font-size: 14px; }
        .correo-previa { padding: 18px 22px; background: var(--sunken); border-left: 1px solid var(--line); border-radius: 0 0 0 0; display: flex; flex-direction: column; }
        .correo-previa iframe { width: 100%; flex: 1; min-height: 560px; border: 1px solid var(--line); border-radius: 12px; background: #EEF0F3; }
        .correo-pie { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; padding: 14px 22px; border-top: 1px solid var(--line); }
        .correo-adjuntos { border: 1px dashed var(--line-strong); border-radius: 10px; padding: 10px 12px; }
        .correo-adjuntos.pide { border-color: #E8B04B; background: #FFF9EC; }
        .correo-archivos { list-style: none; margin: 8px 0 0; padding: 0; display: grid; gap: 4px; }
        .correo-archivos li { display: flex; align-items: center; gap: 8px; font-size: 13.5px; background: var(--sunken); border-radius: 8px; padding: 4px 4px 4px 10px; }
        .correo-archivos .n { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .correos-lista summary { cursor: pointer; list-style: none; }
        .correos-lista summary::-webkit-details-marker { display: none; }
        .correos-lista summary:hover { background: var(--sunken); }
        @media (max-width: 991.98px) {
            .correo-velo { padding: 0; }
            .correo-ventana { border-radius: 0; min-height: 100%; }
            .correo-cuerpo { grid-template-columns: minmax(0, 1fr); }
            .correo-previa { border-left: 0; border-top: 1px solid var(--line); }
            .correo-previa iframe { min-height: 480px; }
        }
        .d-grid { grid-template-columns: minmax(0, 1fr); }
        .min-w-0 { min-width: 0; }

        /* ===================== Móvil ===================== */
        @media (max-width: 991.98px) {
            .side { transform: translateX(-100%); transition: transform .2s ease; box-shadow: 0 0 40px rgba(0,0,0,.3); }
            .side.abierta { transform: none; }
            .velo { position: fixed; inset: 0; background: rgba(19,22,29,.45); z-index: 1035; }
            .main { margin-left: 0; }
            .topbar-m { display: flex; align-items: center; justify-content: space-between; gap: 12px; position: sticky; top: 0; z-index: 1030;
                        background: var(--ink); padding: 10px 16px; }
            .topbar-m img { height: 22px; }
            .topbar-m button { border: 0; background: transparent; color: #fff; font-size: 22px; width: 40px; height: 40px; }
            .page { padding: 22px 16px calc(96px + env(safe-area-inset-bottom)); }
            .buscador input { min-width: 0; width: 100%; }
            .topbar-m { padding-top: calc(10px + env(safe-area-inset-top)); }
            .side { padding-top: env(safe-area-inset-top); }

            /* Barra inferior tipo app */
            .tabbar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 1030; display: grid; grid-template-columns: repeat(5, 1fr); align-items: end;
                      background: rgba(255,255,255,.96); backdrop-filter: blur(10px); border-top: 1px solid var(--line);
                      padding: 6px 4px calc(6px + env(safe-area-inset-bottom)); }
            .tabbar a { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 4px 0; font-size: 11px; font-weight: 500; color: var(--muted); text-decoration: none; position: relative; }
            .tabbar a i { font-size: 21px; line-height: 1; }
            .tabbar a.activo { color: var(--ink); }
            .tabbar a.activo i { color: var(--ink); }
            .tabbar a .burbuja { position: absolute; top: 0; left: calc(50% + 6px); min-width: 16px; height: 16px; border-radius: 99px; background: var(--ink); color: #fff; font-size: 10px; display: grid; place-items: center; padding: 0 4px; }
            .tabbar .mas { align-self: center; }
            .tabbar .mas span { width: 50px; height: 50px; margin-top: -22px; border-radius: 16px; background: var(--ink); color: var(--green); display: grid; place-items: center;
                                font-size: 26px; box-shadow: 0 6px 18px rgba(19,22,29,.28); border: 3px solid #fff; }
        }
        @media (min-width: 992px) { .tabbar { display: none; } }
        @media (display-mode: standalone) { .solo-navegador { display: none !important; } }
        .ios-instalar { position: relative; z-index: 1040; background: #0B0D12; color: #fff; padding: calc(10px + env(safe-area-inset-top)) 12px 10px; }
        .ios-instalar .ios-in { display: flex; gap: 12px; align-items: flex-start; max-width: 640px; margin: 0 auto; font-size: 13.5px; line-height: 1.45; }
        .ios-instalar img { border-radius: 10px; flex: none; }
        .ios-instalar .t { flex: 1; min-width: 0; color: #C9CDD6; }
        .ios-instalar .t > b { display: block; color: #fff; font-size: 14.5px; }
        .ios-instalar .t span b { color: #fff; }
        .ios-instalar .t i { color: #00F385; }
        .ios-instalar button { border: 0; background: transparent; color: #9AA0AC; font-size: 16px; padding: 2px 4px; }
        .instalar { margin: 0 12px 10px; display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; border: 1px dashed var(--ink-line, #2C313D); color: #C9CDD6; font-size: 13px; background: transparent; text-align: left; width: calc(100% - 24px); }
        .instalar:hover { border-color: var(--green); color: #fff; }
        .instalar i { color: var(--green); font-size: 18px; }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
    </style>
    @stack('head')
</head>
<body x-data="{ menu: false }" @keydown.escape="menu = false">
<div class="cargando-barra" id="cargando" aria-hidden="true"></div>

{{-- iPhone: cómo instalar la app (Apple no deja mostrar un botón de "Instalar") --}}
<div class="ios-instalar" x-data="avisoIos()" x-show="visible" x-cloak role="note">
    <div class="ios-in">
        <img src="{{ route('app.icono', 'icono-192.png') }}" alt="" width="40" height="40">
        <div class="t">
            <b>Instala Vandu en tu iPhone</b>
            <template x-if="paso === 'safari'"><span>Toca <i class="bi bi-box-arrow-up"></i> <b>Compartir</b> abajo y luego <b>«Agregar a inicio»</b>.</span></template>
            <template x-if="paso === 'chrome'"><span>Toca <i class="bi bi-box-arrow-up"></i> <b>Compartir</b> en la barra de arriba y luego <b>«Agregar a pantalla de inicio»</b>.</span></template>
            <template x-if="paso === 'abrir'"><span>Esta ventana no permite instalar. Abre <b>agenciavandu.com/admin</b> en <b>Safari</b> y ahí sigue los pasos.</span></template>
        </div>
        <button type="button" @click="cerrar()" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
    </div>
</div>

<header class="topbar-m">
    <button type="button" @click="menu = true" aria-label="Abrir menú"><i class="bi bi-list"></i></button>
    <a href="{{ $inicioNav }}"><x-logo-vandu alt="Vandu" height="22" /></a>
    <span style="width:40px"></span>
</header>
<div class="velo" x-show="menu" x-cloak @click="menu = false"></div>

<aside class="side" :class="{ abierta: menu }" aria-label="Navegación del panel">
    <div class="side-brand">
        <a href="{{ $inicioNav }}"><x-logo-vandu alt="Vandu" /></a>
        <button type="button" class="btn btn-sm text-white d-lg-none" @click="menu = false" aria-label="Cerrar menú"><i class="bi bi-x-lg"></i></button>
    </div>
    @if($conMas)
        <div class="side-cta">
            <a href="{{ route('admin.presupuestos.create') }}"><i class="bi bi-plus-lg"></i> Nueva cotización</a>
        </div>
    @elseif($puede('tareas'))
        <div class="side-cta">
            <a href="{{ route('admin.tareas.create') }}"><i class="bi bi-plus-lg"></i> Nueva tarea</a>
        </div>
    @endif
    <div class="side-label">Panel</div>
    <nav>
        @foreach($nav as $item)
            <a href="{{ route($item['ruta']) }}" class="{{ request()->routeIs($item['activo']) ? 'activo' : '' }}"
               @if(request()->routeIs($item['activo'])) aria-current="page" @endif>
                <i class="bi {{ $item['icono'] }}"></i> {{ $item['texto'] }}
                @if(! empty($item['cuenta']))<span class="cuenta num {{ ! empty($item['nuevos']) ? 'nuevos' : '' }}" @if(! empty($item['nuevos'])) title="{{ $item['cuenta'] }} {{ $item['cuenta'] === 1 ? 'contacto nuevo' : 'contactos nuevos' }}" @endif>{{ ! empty($item['nuevos']) ? $item['cuenta'] . ' ' . ($item['cuenta'] === 1 ? 'nuevo' : 'nuevos') : $item['cuenta'] }}</span>@endif
            </a>
        @endforeach
    </nav>
    <div class="side-label mt-4">Ajustes</div>
    <nav>
        @if($cuentaNav && $cuentaNav->id === \App\Support\Cuentas::principalId())
            <a href="/" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> {{ config('vandu.marca.sitio') }}</a>
        @elseif(config('vandu.marca.sitio'))
            <a href="https://{{ preg_replace('#^https?://#', '', config('vandu.marca.sitio')) }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> {{ preg_replace('#^https?://#', '', config('vandu.marca.sitio')) }}</a>
        @endif
        @php $dbxNav = \App\Support\Dropbox\Dropbox::conectado(); @endphp
        @if($puede('archivos'))
        <a href="{{ route('admin.archivos') }}" class="{{ request()->routeIs('admin.archivos*') ? 'activo' : '' }}">
            <i class="bi {{ request()->routeIs('admin.archivos*') ? 'bi-folder-fill' : 'bi-folder2-open' }}"></i> Archivos
        </a>
        @endif
        @if($puede('configuracion'))
        <a href="{{ route('admin.dropbox') }}" class="{{ request()->routeIs('admin.dropbox*') ? 'activo' : '' }}">
            <i class="bi bi-dropbox"></i> Dropbox
            <span class="ms-auto" title="{{ $dbxNav ? 'Conectado' : 'Sin conectar' }}" style="width:8px;height:8px;border-radius:50%;background:{{ $dbxNav ? 'var(--green, #00C46A)' : '#E5484D' }}"></span>
        </a>
        <a href="{{ route('admin.tipos') }}" class="{{ request()->routeIs('admin.tipos*') ? 'activo' : '' }}">
            <i class="bi bi-diagram-3"></i> Tipos de proyecto
        </a>
        <a href="{{ route('admin.correos.plantillas') }}" class="{{ request()->routeIs('admin.correos.plantillas*') ? 'activo' : '' }}">
            <i class="bi {{ request()->routeIs('admin.correos.plantillas*') ? 'bi-envelope-paper-fill' : 'bi-envelope-paper' }}"></i> Plantillas de correo
        </a>
        @endif
        @if($puede('configuracion'))
        <a href="{{ route('admin.negocio') }}" class="{{ request()->routeIs('admin.negocio*') ? 'activo' : '' }}">
            <i class="bi bi-shop"></i> Mi negocio
        </a>
        @endif
        @if($puede('usuarios'))
        <a href="{{ route('admin.usuarios') }}" class="{{ request()->routeIs('admin.usuarios*') ? 'activo' : '' }}">
            <i class="bi {{ request()->routeIs('admin.usuarios*') ? 'bi-person-gear' : 'bi-person-gear' }}"></i> Usuarios
        </a>
        @endif
        @if($usuario?->plataforma)
        <a href="{{ route('admin.plataforma') }}" class="{{ request()->routeIs('admin.plataforma*') ? 'activo' : '' }}">
            <i class="bi bi-buildings"></i> Plataforma
        </a>
        @endif
        <a href="{{ route('admin.notificaciones') }}" class="{{ request()->routeIs('admin.notificaciones*') ? 'activo' : '' }}">
            <i class="bi {{ request()->routeIs('admin.notificaciones*') ? 'bi-bell-fill' : 'bi-bell' }}"></i> Notificaciones
        </a>
    </nav>

    <x-logo-vandu archivo="icono-vandu.svg" alt="" class="side-eco" aria-hidden="true" />

    <div x-data="instalarApp()" x-show="visible" x-cloak class="solo-navegador">
        <button type="button" class="instalar" @click="instalar()"><i class="bi bi-phone"></i> <span><b class="d-block text-white" style="font-weight:600">Instalar la app</b><span x-text="ios ? 'En Safari: Compartir → Agregar a inicio' : 'Abre el panel como app, con su ícono'"></span></span></button>
    </div>

    <div class="side-foot">
        <span class="avatar av-yo">{{ $iniciales }}</span>
        <a href="{{ route('admin.cuenta') }}" class="yo text-decoration-none" title="Mi cuenta"><b>{{ $usuario?->name }}</b><span>{{ $usuario?->puesto ?: $usuario?->email }}</span></a>
        <form method="post" action="{{ route('logout') }}" class="m-0">
            @csrf
            <button type="submit" title="Cerrar sesión" aria-label="Cerrar sesión"><i class="bi bi-box-arrow-right"></i></button>
        </form>
    </div>
</aside>

<div class="main">
    <main class="page">
        @if($viendoOtra)
            <div class="aviso" style="background:#EEF0FF; color:#2F3A8F; display:flex; align-items:center; gap:10px; flex-wrap:wrap">
                <i class="bi bi-eye"></i> <span>Estás viendo el panel de <b>{{ $cuentaNav->nombre }}</b>. Lo que hagas queda en su cuenta.</span>
                <form method="post" action="{{ route('admin.plataforma.salir') }}" class="ms-auto">@csrf<button class="btn btn-sm btn-borde">Volver a mi cuenta</button></form>
            </div>
        @endif
        @if(session('ok'))
            <div class="aviso aviso-ok" role="status"><i class="bi bi-check-circle-fill"></i> {{ session('ok') }}</div>
        @endif
        @if($errors->any())
            <div class="aviso aviso-error" role="alert">
                <i class="bi bi-exclamation-circle-fill align-self-start mt-1"></i>
                <div><strong>Revisa estos campos</strong>
                    <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            </div>
        @endif

        @yield('contenido')
    </main>
</div>

<nav class="tabbar" aria-label="Navegación rápida" style="grid-template-columns: repeat({{ count($tabs) + ($conMas ? 1 : 0) }}, 1fr)">
    @foreach($tabs as $k => $tab)
        @if($conMas && $k === intdiv(count($tabs), 2))
            <a href="{{ route('admin.presupuestos.create') }}" class="mas" aria-label="Nueva cotización"><span><i class="bi bi-plus-lg"></i></span></a>
        @endif
        <a href="{{ $tab['url'] }}" class="{{ request()->routeIs($tab['activo']) ? 'activo' : '' }}"><i class="bi {{ request()->routeIs($tab['activo']) ? $tab['lleno'] : $tab['icono'] }}"></i> {{ $tab['texto'] }}
            @if(! empty($tab['cuenta']))<span class="burbuja num">{{ $tab['cuenta'] }}</span>@endif</a>
    @endforeach
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script id="vandu-base">
    // Menús dentro de tablas con scroll: que se dibujen por encima y no queden recortados
    function prepararPagina() {
        document.querySelectorAll('.table-responsive [data-bs-toggle="dropdown"]').forEach((b) => {
            b.setAttribute('data-bs-popper-config', '{"strategy":"fixed"}');
        });
        prepararTablas();
    }

    // En el celular cada fila se ve como tarjeta: cada celda lleva el nombre de su columna
    function prepararTablas() {
        document.querySelectorAll('table.tabla').forEach((t) => {
            const nombres = [];
            [...(t.tHead?.rows[0]?.cells || [])].forEach((th) => {
                const c = th.cloneNode(true);
                c.querySelectorAll('.visually-hidden').forEach((n) => n.remove());
                for (let i = 0; i < (th.colSpan || 1); i++) nombres.push(c.textContent.trim());
            });
            t.classList.add('tabla-m');
            [...t.tBodies].forEach((tb) => [...tb.rows].forEach((tr) => {
                let col = 0;
                [...tr.cells].forEach((td, j) => {
                    if (!td.hasAttribute('data-k')) td.setAttribute('data-k', nombres[col] || '');
                    td.classList.toggle('m-titulo', j === 0);
                    td.classList.toggle('m-sin-k', j > 0 && !td.getAttribute('data-k'));
                    col += td.colSpan || 1;
                });
            }));
            paginarMovil(t);
        });
    }

    // Más de 5 filas: en el celular se muestran de 5 en 5 (en computadora no cambia nada)
    const POR_PAGINA_MOVIL = 5;
    function paginarMovil(t) {
        const filas = [...t.tBodies].flatMap((tb) => [...tb.rows]);
        const caja = t.closest('.table-responsive') || t;
        let pager = caja.nextElementSibling?.classList.contains('pager-m') ? caja.nextElementSibling : null;
        if (filas.length <= POR_PAGINA_MOVIL) {
            pager?.remove();
            filas.forEach((f) => f.classList.remove('fuera-m'));
            return;
        }
        if (!pager) {
            pager = document.createElement('nav');
            pager.className = 'pager-m';
            pager.setAttribute('aria-label', 'Páginas de la tabla');
            caja.after(pager);
        }
        const paginas = Math.ceil(filas.length / POR_PAGINA_MOVIL);
        let pag = Math.min(+(t.dataset.pagM || 1), paginas);
        const pintar = () => {
            t.dataset.pagM = pag;
            filas.forEach((f, i) => f.classList.toggle('fuera-m', Math.floor(i / POR_PAGINA_MOVIL) + 1 !== pag));
            const de = (pag - 1) * POR_PAGINA_MOVIL + 1, a = Math.min(pag * POR_PAGINA_MOVIL, filas.length);
            pager.innerHTML = `<button type="button" data-pm="-1" aria-label="Anteriores" ${pag === 1 ? 'disabled' : ''}><i class="bi bi-chevron-left"></i></button>`
                + `<span class="num">${de}–${a} de ${filas.length}</span>`
                + `<button type="button" data-pm="1" aria-label="Siguientes" ${pag === paginas ? 'disabled' : ''}><i class="bi bi-chevron-right"></i></button>`;
        };
        pager.onclick = (e) => {
            const b = e.target.closest('[data-pm]');
            if (!b || b.disabled) return;
            pag = Math.max(1, Math.min(paginas, pag + +b.dataset.pm));
            pintar();
            const arriba = caja.getBoundingClientRect().top;
            if (arriba < 70) window.scrollBy({ top: arriba - 80 });
        };
        pintar();
    }
    prepararPagina();

    // Copiar enlaces al portapapeles: <button data-copiar="texto">
    document.addEventListener('click', async (e) => {
        const b = e.target.closest('[data-copiar]');
        if (!b) return;
        try { await navigator.clipboard.writeText(b.dataset.copiar); } catch { prompt('Copia el enlace:', b.dataset.copiar); return; }
        const t = b.innerHTML; b.innerHTML = '<i class="bi bi-check2"></i> Copiado'; setTimeout(() => b.innerHTML = t, 1500);
    });

    // App instalable: service worker (pantalla sin conexión y carga rápida)
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register(@json(route('app.sw')), { scope: '/admin' }).catch(() => {}));
    }
    let promptInstalar = null;
    window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); promptInstalar = e; window.dispatchEvent(new Event('vandu-instalable')); });
    // Invitación a activar notificaciones (solo si el dispositivo puede y aún no se ha decidido)
    window.avisoPush = () => ({
        visible: false,
        init() {
            let cerrado = false; try { cerrado = localStorage.getItem('vandu-aviso-push') === '1'; } catch {}
            if (cerrado || !('PushManager' in window) || !('Notification' in window) || Notification.permission !== 'default') return;
            this.visible = true;
        },
        cerrar() { this.visible = false; try { localStorage.setItem('vandu-aviso-push', '1'); } catch {} },
    });

    window.avisoIos = () => ({
        visible: false, paso: 'safari',
        init() {
            const ua = navigator.userAgent;
            const ios = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            const instalada = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
            let cerrado = false; try { cerrado = localStorage.getItem('vandu-aviso-ios') === '1'; } catch {}
            if (!ios || instalada || cerrado) return;
            // Navegadores dentro de otras apps (WhatsApp, Instagram, Gmail…) no pueden instalar
            if (/FBAN|FBAV|Instagram|Line\/|GSA\/|WhatsApp|Snapchat|LinkedInApp/i.test(ua)) this.paso = 'abrir';
            else if (/CriOS|EdgiOS|FxiOS/i.test(ua)) this.paso = 'chrome';
            this.visible = true;
        },
        cerrar() { this.visible = false; try { localStorage.setItem('vandu-aviso-ios', '1'); } catch {} },
    });
    window.instalarApp = () => ({
        ios: /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream,
        visible: false,
        init() {
            const instalada = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
            this.visible = !instalada && (this.ios || !!promptInstalar);
            window.addEventListener('vandu-instalable', () => { this.visible = !instalada; });
            window.addEventListener('appinstalled', () => { this.visible = false; });
        },
        async instalar() {
            if (promptInstalar) { promptInstalar.prompt(); await promptInstalar.userChoice; promptInstalar = null; this.visible = false; }
        },
    });

    // Abrir la ventana de correo desde cualquier botón con data-correo="plantilla"
    document.addEventListener('click', (e) => {
        const b = e.target.closest('[data-correo]'); if (!b) return;
        e.preventDefault();
        window.dispatchEvent(new CustomEvent('abrir-correo', { detail: b.dataset.correo || '' }));
    });

    /** Editor de correo (admin/correos/_modal) */
    window.correoVandu = function (init) {
        return {
            plantillas: init.plantillas, previaUrl: init.previa,
            abierto: false, cargando: false, enviando: false, conCopia: false,
            clave: '', para: '', cc: '', asunto: '', titulo: '', cuerpo: '', boton: '',
            conBoton: false, resumen: false, banco: false, pdf: false, miniaturas: false, ultimo: '',
            archivos: [], pideAdjuntos: false, codigo: false,
            init() {
                // ?correo=recordatorio_pago abre la ventana con esa plantilla
                const u = new URL(location.href), q = u.searchParams.get('correo');
                if (q !== null) {
                    u.searchParams.delete('correo'); history.replaceState(null, '', u.href);
                    this.$nextTick(() => this.abrir(q));
                }
            },
            abrir(clave) {
                const claves = Object.keys(this.plantillas);
                const k = claves.find((c) => c === clave) || claves.find((c) => clave && c.startsWith(clave)) || claves[0];
                this._lista = []; this.archivos = []; if (this.$refs.adjuntos) this.$refs.adjuntos.value = '';
                this.usar(k, true);
                this.abierto = true;
                this.$nextTick(() => document.getElementById(this.para ? 'correo-asunto' : 'correo-para')?.focus());
            },
            usar(k, primeraVez = false) {
                const pl = this.plantillas[k]; if (!pl) return;
                this.clave = k;
                if (primeraVez || !this.para) this.para = pl.para || this.para;
                this.asunto = pl.asunto; this.titulo = pl.titulo; this.cuerpo = pl.cuerpo;
                this.boton = pl.boton || pl.boton_por_defecto || ''; this.conBoton = !!pl.boton;
                this.resumen = !!pl.resumen; this.banco = !!pl.banco; this.pdf = !!pl.pdf; this.miniaturas = !!pl.miniaturas;
                this.pideAdjuntos = !!pl.adjuntos; this.codigo = !!pl.codigo;
                this.$nextTick(() => this.previsualizar());
            },
            cerrar() { this.abierto = false; },
            // Adjuntos: se pueden elegir en varias tandas y quitar uno por uno
            elegirArchivos() {
                const dt = new DataTransfer();
                (this._lista || []).forEach((f) => dt.items.add(f));
                [...this.$refs.adjuntos.files].forEach((f) => { if (!(this._lista || []).some((g) => g.name === f.name && g.size === f.size)) dt.items.add(f); });
                this.$refs.adjuntos.files = dt.files; this._lista = [...dt.files]; this.pintarArchivos();
            },
            quitarArchivo(i) {
                const dt = new DataTransfer();
                this._lista.filter((_, j) => j !== i).forEach((f) => dt.items.add(f));
                this.$refs.adjuntos.files = dt.files; this._lista = [...dt.files]; this.pintarArchivos();
            },
            pintarArchivos() {
                const peso = (b) => b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
                const icono = (e) => ({ pdf: 'bi-file-earmark-pdf', xml: 'bi-filetype-xml', zip: 'bi-file-earmark-zip', png: 'bi-file-earmark-image', jpg: 'bi-file-earmark-image', jpeg: 'bi-file-earmark-image' })[e] || 'bi-file-earmark';
                this.archivos = this._lista.map((f) => { const ext = (f.name.split('.').pop() || '').toLowerCase(); return { nombre: f.name, peso: peso(f.size), ext, icono: icono(ext) }; });
            },
            revisarAdjuntos(e) {
                if (this.pideAdjuntos && !this.archivos.length && !confirm('No adjuntaste la factura. ¿Enviar el correo sin archivos?')) { e.preventDefault(); e.stopImmediatePropagation(); }
            },
            async previsualizar() {
                if (!this.abierto && !this.clave) return;
                const datos = new FormData(this.$refs.form);
                datos.delete('adjuntos[]'); // los archivos solo viajan al enviar
                const firma = new URLSearchParams(datos).toString();
                if (firma === this.ultimo) return;
                this.ultimo = firma; this.cargando = true;
                try {
                    const r = await fetch(this.previaUrl, { method: 'POST', body: datos, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } });
                    if (r.ok) this.$refs.previa.srcdoc = await r.text();
                } finally { this.cargando = false; }
            },
        };
    };

    /*
     * Guardar sin recargar la página.
     * Los formularios del panel se envían en segundo plano. Si la respuesta es la misma página,
     * solo se reemplaza el contenido (te quedas donde estabas). Si lleva a otra página, se navega normal.
     * Para excluir un formulario: <form data-recargar>.
     */
    (function () {
        const barra = document.getElementById('cargando');
        const progreso = (on) => {
            if (on) { barra.classList.remove('lista'); void barra.offsetWidth; barra.classList.add('activa'); }
            else { barra.classList.remove('activa'); barra.classList.add('lista'); }
        };
        const mismaPagina = (url) => { const u = new URL(url, location.href); return u.pathname === location.pathname; };

        /*
         * Navegación tipo app: al tocar un enlace del panel se trae la página en segundo plano y solo se cambia
         * el contenido. La barra lateral y la de abajo se quedan quietas (no "parpadea" todo).
         * Si algo no es una página del panel (PDF, descarga, Dropbox…), se navega normal.
         */
        const NO_INTERCEPTAR = /\/(pdf|exportar|conectar|abrir|descargar|sw\.js|manifest\.webmanifest)(\/|\?|$)|\/archivos\/(\d+|ver|miniatura)|\/constancias\/\d+|\/whatsapp(\/|\?|$)|\/logout/;
        const precargas = new Map();
        const esInterno = (a) => {
            if (!a || !a.href || a.hasAttribute('download') || a.hasAttribute('data-recargar') || a.hasAttribute('data-correo')) return false;
            if (a.target && a.target !== '_self') return false;
            const u = new URL(a.href, location.href);
            if (u.origin !== location.origin || !u.pathname.startsWith('/admin') || NO_INTERCEPTAR.test(u.pathname)) return false;
            if (u.pathname === location.pathname && u.search === location.search && u.hash) return false; // ancla en la misma página
            return u;
        };
        const pedir = (url) => fetch(url, { headers: { 'X-Requested-With': 'fetch', 'Accept': 'text/html' }, credentials: 'same-origin' });

        // Precarga al tocar (antes de soltar el dedo): la página llega más rápido
        document.addEventListener('pointerdown', (e) => {
            const u = esInterno(e.target.closest('a[href]')); if (!u) return;
            if (!precargas.has(u.href)) { precargas.set(u.href, pedir(u.href)); setTimeout(() => precargas.delete(u.href), 8000); }
        }, { passive: true });

        document.addEventListener('click', (e) => {
            if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            const a = e.target.closest('a[href]'); const u = esInterno(a); if (!u) return;
            e.preventDefault();
            // Respuesta inmediata en la barra de abajo
            if (a.closest('.tabbar')) { document.querySelectorAll('.tabbar a').forEach((x) => x.classList.toggle('activo', x === a)); }
            navegar(u.href, true);
        });

        window.addEventListener('popstate', () => navegar(location.href, false));

        let navegacion = 0;
        async function navegar(url, nueva) {
            const yo = ++navegacion;
            progreso(true);
            document.querySelector('main.page')?.classList.add('cambiando');
            try {
                const r = await (precargas.get(url) || pedir(url));
                precargas.delete(url);
                if (yo !== navegacion) return; // ya se tocó otra cosa
                const tipo = r.headers.get('content-type') || '';
                if (!r.ok && r.status !== 422 || !tipo.includes('text/html')) { location.href = url; return; }
                const html = await r.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                if (!doc.querySelector('main.page')) { location.href = r.url; return; } // p. ej. la sesión venció
                aplicar(doc, r.url, nueva);
            } catch (err) {
                location.href = url;
            } finally {
                if (yo === navegacion) { progreso(false); document.querySelector('main.page')?.classList.remove('cambiando'); }
            }
        }

        /** Pone otra página del panel sin recargar: estilos y scripts de la página, contenido, menús y título */
        function aplicar(doc, url, nueva) {
            // 1. Estilos propios de la página
            document.querySelectorAll('head style:not(#estilos-base)').forEach((s) => s.remove());
            doc.querySelectorAll('head style:not(#estilos-base)').forEach((s) => document.head.appendChild(s.cloneNode(true)));
            // 2. Scripts propios de la página (antes del contenido, para que Alpine encuentre sus funciones)
            doc.querySelectorAll('body > script:not([src]):not(#vandu-base)').forEach((s) => {
                const n = document.createElement('script');
                n.textContent = '{\n' + s.textContent + '\n}'; // en bloque: se puede volver a cargar sin chocar
                document.body.appendChild(n); n.remove();
            });
            // 3. Contenido, menú lateral y barra de abajo
            document.querySelectorAll('.dropdown-menu.show').forEach((m) => m.classList.remove('show'));
            const main = document.querySelector('main.page');
            main.innerHTML = doc.querySelector('main.page').innerHTML;
            const navs = document.querySelectorAll('.side nav'), nuevos = doc.querySelectorAll('.side nav');
            navs.forEach((n, i) => { if (nuevos[i]) n.innerHTML = nuevos[i].innerHTML; });
            const tab = document.querySelector('.tabbar'), tabNuevo = doc.querySelector('.tabbar');
            if (tab && tabNuevo) tab.innerHTML = tabNuevo.innerHTML;
            document.title = doc.title || document.title;
            const u = new URL(url, location.href);
            if (nueva) history.pushState(null, '', u.href); else if (u.href !== location.href) history.replaceState(null, '', u.href);
            // 4. Cerrar el menú del celular, subir al inicio y acomodar
            try { if (window.Alpine) Alpine.$data(document.body).menu = false; } catch (e) {}
            if (nueva) window.scrollTo({ top: 0 });
            main.classList.remove('cambiando'); main.classList.add('entrando');
            setTimeout(() => main.classList.remove('entrando'), 250);
            prepararPagina();
            const err = main.querySelector('.aviso-error');
            if (err) err.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // Al ir a otra página después de guardar: se cambia sin recargar y el aviso ya viene en el contenido
        function irA(url, html) {
            try {
                const doc = new DOMParser().parseFromString(html || '', 'text/html');
                if (doc.querySelector('main.page')) { aplicar(doc, url, true); return; }
            } catch (e) {}
            location.href = url;
        }
        try {
            const pendiente = sessionStorage.getItem('vanduAviso');
            sessionStorage.removeItem('vanduAviso');
            if (pendiente && !document.querySelector('main.page > .aviso-ok')) {
                const a = document.createElement('div');
                a.className = 'aviso aviso-ok'; a.setAttribute('role', 'status');
                a.innerHTML = '<i class="bi bi-check-circle-fill"></i> ';
                a.append(pendiente);
                document.querySelector('main.page').prepend(a);
            }
        } catch (e) {}

        function reemplazar(html, url) {
            const doc = new DOMParser().parseFromString(html, 'text/html');
            const nuevoMain = doc.querySelector('main.page'), nuevoNav = doc.querySelector('.side');
            if (!nuevoMain) { location.href = url; return; }
            document.querySelectorAll('.dropdown-menu.show').forEach((m) => m.classList.remove('show'));
            document.querySelector('main.page').innerHTML = nuevoMain.innerHTML;
            if (nuevoNav) {
                const navs = document.querySelectorAll('.side nav'), nuevos = nuevoNav.querySelectorAll('nav');
                navs.forEach((n, i) => { if (nuevos[i]) n.innerHTML = nuevos[i].innerHTML; });
            }
            if (doc.title) document.title = doc.title;
            const u = new URL(url, location.href);
            if (u.href !== location.href) history.replaceState(null, '', u.href);
            prepararPagina();
            // Si hubo errores, llévalos a la vista
            const err = document.querySelector('main.page .aviso-error');
            if (err) err.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        async function refrescar(url = location.href) {
            progreso(true);
            try {
                const r = await fetch(url, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
                const html = await r.text();
                if (!mismaPagina(r.url)) { irA(r.url, html); return; }
                reemplazar(html, r.url);
            } finally { progreso(false); }
        }
        window.vanduRefrescar = refrescar;

        document.addEventListener('submit', async (e) => {
            const f = e.target;
            if (e.defaultPrevented || f.hasAttribute('data-recargar') || (f.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
            if (f.target && f.target !== '_self') return;
            e.preventDefault();
            if (f.classList.contains('enviando')) return;

            const datos = new FormData(f, e.submitter || undefined);
            f.classList.add('enviando'); progreso(true);
            try {
                const r = await fetch(f.action, { method: 'POST', body: datos, credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'fetch', 'Accept': 'text/html' } });
                if (r.status === 419) { location.reload(); return; }          // sesión vencida
                if (!r.ok && r.status !== 422) throw new Error(r.status);
                const html = await r.text();
                if (!mismaPagina(r.url)) { irA(r.url, html); return; }       // p. ej. al crear: va a otra página
                reemplazar(html, r.url);
            } catch (err) {
                const aviso = document.createElement('div');
                aviso.className = 'aviso aviso-error'; aviso.setAttribute('role', 'alert');
                aviso.innerHTML = '<i class="bi bi-exclamation-circle-fill"></i> No se pudo guardar. Revisa tu conexión e inténtalo de nuevo.';
                document.querySelector('main.page').prepend(aviso); aviso.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } finally {
                f.classList.remove('enviando'); progreso(false);
            }
        });
    })();
</script>
@stack('scripts')
</body>
</html>
