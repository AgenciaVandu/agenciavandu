@php
    $usuario = auth()->user();
    $iniciales = collect(explode(' ', trim($usuario?->name ?? 'V')))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->join('');
    $vigentesNav = \App\Models\Presupuesto::where(fn ($q) => $q->where('estado', 'negociacion')
        ->orWhere(fn ($q2) => $q2->where('vigente_hasta', '>', now())->whereNotIn('estado', ['aceptada', 'rechazada'])))->count();
    $activosNav = \App\Models\Proyecto::where('estado', 'activo')->count();
    $nav = [
        ['ruta' => 'admin.resumen',             'activo' => 'admin.resumen',          'icono' => 'bi-grid-1x2',        'texto' => 'Resumen'],
        ['ruta' => 'admin.presupuestos.index',  'activo' => 'admin.presupuestos.*',   'icono' => 'bi-file-earmark-text','texto' => 'Cotizaciones', 'cuenta' => $vigentesNav],
        ['ruta' => 'admin.proyectos.index',     'activo' => 'admin.proyectos.*',      'icono' => 'bi-kanban',          'texto' => 'Proyectos', 'cuenta' => $activosNav],
        ['ruta' => 'admin.clientes.index',      'activo' => 'admin.clientes.*',       'icono' => 'bi-people',          'texto' => 'Clientes'],
        ['ruta' => 'admin.finanzas',            'activo' => 'admin.finanzas*',        'icono' => 'bi-graph-up-arrow',  'texto' => 'Finanzas'],
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titulo', 'Panel') · Vandu</title>
    <link rel="icon" href="/favi.svg" type="image/svg+xml">
    <link rel="preload" href="{{ route('vandu.fuente') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <style>
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
        .correos-lista summary { cursor: pointer; list-style: none; }
        .correos-lista summary::-webkit-details-marker { display: none; }
        .correos-lista summary:hover { background: var(--sunken); }
        @media (max-width: 991.98px) {
            .correo-velo { padding: 0; }
            .correo-ventana { border-radius: 0; min-height: 100%; }
            .correo-cuerpo { grid-template-columns: 1fr; }
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
            .page { padding: 22px 16px 48px; }
            .buscador input { min-width: 0; width: 100%; }
        }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
    </style>
    @stack('head')
</head>
<body x-data="{ menu: false }" @keydown.escape="menu = false">
<div class="cargando-barra" id="cargando" aria-hidden="true"></div>

<header class="topbar-m">
    <button type="button" @click="menu = true" aria-label="Abrir menú"><i class="bi bi-list"></i></button>
    <a href="{{ route('admin.resumen') }}"><x-logo-vandu alt="Vandu" height="22" /></a>
    <a href="{{ route('admin.presupuestos.create') }}" class="btn btn-acento btn-sm btn-icono" aria-label="Nueva cotización"><i class="bi bi-plus-lg"></i></a>
</header>
<div class="velo" x-show="menu" x-cloak @click="menu = false"></div>

<aside class="side" :class="{ abierta: menu }" aria-label="Navegación del panel">
    <div class="side-brand">
        <a href="{{ route('admin.resumen') }}"><x-logo-vandu alt="Vandu" /></a>
        <button type="button" class="btn btn-sm text-white d-lg-none" @click="menu = false" aria-label="Cerrar menú"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="side-cta">
        <a href="{{ route('admin.presupuestos.create') }}"><i class="bi bi-plus-lg"></i> Nueva cotización</a>
    </div>
    <div class="side-label">Panel</div>
    <nav>
        @foreach($nav as $item)
            <a href="{{ route($item['ruta']) }}" class="{{ request()->routeIs($item['activo']) ? 'activo' : '' }}"
               @if(request()->routeIs($item['activo'])) aria-current="page" @endif>
                <i class="bi {{ $item['icono'] }}"></i> {{ $item['texto'] }}
                @if(! empty($item['cuenta']))<span class="cuenta num">{{ $item['cuenta'] }}</span>@endif
            </a>
        @endforeach
    </nav>
    <div class="side-label mt-4">Sitio</div>
    <nav>
        <a href="/" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> agenciavandu.com</a>
    </nav>

    <x-logo-vandu archivo="icono-vandu.svg" alt="" class="side-eco" aria-hidden="true" />

    <div class="side-foot">
        <span class="avatar av-yo">{{ $iniciales }}</span>
        <div class="yo"><b>{{ $usuario?->name }}</b><span>{{ $usuario?->email }}</span></div>
        <form method="post" action="{{ route('logout') }}" class="m-0">
            @csrf
            <button type="submit" title="Cerrar sesión" aria-label="Cerrar sesión"><i class="bi bi-box-arrow-right"></i></button>
        </form>
    </div>
</aside>

<div class="main">
    <main class="page">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Menús dentro de tablas con scroll: que se dibujen por encima y no queden recortados
    function prepararPagina() {
        document.querySelectorAll('.table-responsive [data-bs-toggle="dropdown"]').forEach((b) => {
            b.setAttribute('data-bs-popper-config', '{"strategy":"fixed"}');
        });
    }
    prepararPagina();

    // Copiar enlaces al portapapeles: <button data-copiar="texto">
    document.addEventListener('click', async (e) => {
        const b = e.target.closest('[data-copiar]');
        if (!b) return;
        try { await navigator.clipboard.writeText(b.dataset.copiar); } catch { prompt('Copia el enlace:', b.dataset.copiar); return; }
        const t = b.innerHTML; b.innerHTML = '<i class="bi bi-check2"></i> Copiado'; setTimeout(() => b.innerHTML = t, 1500);
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
            conBoton: false, resumen: false, banco: false, pdf: false, ultimo: '',
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
                this.resumen = !!pl.resumen; this.banco = !!pl.banco; this.pdf = !!pl.pdf;
                this.$nextTick(() => this.previsualizar());
            },
            cerrar() { this.abierto = false; },
            async previsualizar() {
                if (!this.abierto && !this.clave) return;
                const datos = new FormData(this.$refs.form);
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

        // Al ir a otra página, el aviso ("Cliente creado", etc.) viaja con ella
        function irA(url, html) {
            try {
                const aviso = new DOMParser().parseFromString(html || '', 'text/html').querySelector('main.page > .aviso-ok');
                if (aviso) sessionStorage.setItem('vanduAviso', aviso.textContent.trim());
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
            if (f.querySelector('input[type=file]')) return;
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
