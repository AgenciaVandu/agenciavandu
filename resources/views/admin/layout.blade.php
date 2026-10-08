<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titulo', 'Panel') · Vandu</title>
    <link rel="icon" href="/favi.svg" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <style>
        @font-face { font-family: 'Geist'; src: url('/font/Geist-Variable.woff2') format('woff2'); font-weight: 100 900; font-display: swap; }
        :root {
            --v-ink: #13161D; --v-green: #00F385; --v-green-dark: #00c96e; --v-mist: #F3F3F3; --v-gray: #F9FAFC;
            --bs-body-font-family: 'Geist', system-ui, sans-serif; --bs-body-color: #13161D;
            --bs-link-color-rgb: 19,22,29; --bs-border-radius: .5rem;
        }
        body { background: var(--v-gray); }
        .num { font-variant-numeric: tabular-nums; }
        .v-nav { background: var(--v-ink); }
        .v-nav .nav-link { color: #c9ccd3; border-radius: .4rem; padding: .4rem .8rem; }
        .v-nav .nav-link:hover { color: #fff; }
        .v-nav .nav-link.active { color: var(--v-ink); background: var(--v-green); }
        .btn-v { background: var(--v-ink); color: #fff; border: 1px solid var(--v-ink); }
        .btn-v:hover, .btn-v:focus { background: #000; color: var(--v-green); }
        .btn-verde { background: var(--v-green); color: var(--v-ink); border: 1px solid var(--v-green); font-weight: 600; }
        .btn-verde:hover { background: var(--v-green-dark); border-color: var(--v-green-dark); }
        .card { border-color: #e7e8ec; }
        .card-header { background: #fff; font-weight: 600; }
        .form-label { font-size: .85rem; color: #4a4f5c; margin-bottom: .25rem; }
        .form-control:focus, .form-select:focus { border-color: var(--v-ink); box-shadow: 0 0 0 .2rem rgba(0,243,133,.35); }
        .table > :not(caption) > * > * { padding: .7rem .75rem; }
        .vig-ok { color: #087a46; } .vig-pronto { color: #a15c00; } .vig-vencida { color: #b42318; }
        .estado { font-size: .75rem; padding: .25em .6em; border-radius: 99px; font-weight: 600; }
        .estado-borrador { background: #eceef2; color: #4a4f5c; }
        .estado-enviada { background: #e5efff; color: #1d4ed8; }
        .estado-aceptada { background: #d9fbe9; color: #087a46; }
        .estado-rechazada { background: #fde8e8; color: #b42318; }
        [x-cloak] { display: none !important; }
    </style>
    @stack('head')
</head>
<body>
<nav class="v-nav py-2 mb-4">
    <div class="container d-flex align-items-center gap-3 flex-wrap">
        <a href="{{ route('admin.presupuestos.index') }}" class="me-2 py-1">
            <x-logo-vandu alt="Vandu" height="28" />
        </a>
        <a class="nav-link {{ request()->routeIs('admin.presupuestos.*') ? 'active' : '' }}" href="{{ route('admin.presupuestos.index') }}">Cotizaciones</a>
        <a class="nav-link {{ request()->routeIs('admin.clientes.*') ? 'active' : '' }}" href="{{ route('admin.clientes.index') }}">Clientes</a>
        <a class="nav-link {{ request()->routeIs('cotizar.index') ? 'active' : '' }}" href="{{ route('cotizar.index') }}">Prospectos web</a>
        <a href="{{ route('admin.presupuestos.create') }}" class="btn btn-verde btn-sm ms-auto"><i class="bi bi-plus-lg"></i> Nueva cotización</a>
        <form method="post" action="{{ route('logout') }}" class="m-0">
            @csrf
            <button class="btn btn-sm btn-link nav-link" title="{{ auth()->user()?->email }}"><i class="bi bi-box-arrow-right"></i> Salir</button>
        </form>
    </div>
</nav>

<main class="container pb-5">
    @if(session('ok'))
        <div class="alert alert-success d-flex align-items-center gap-2" role="status"><i class="bi bi-check-circle"></i> {{ session('ok') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Revisa estos campos:</strong>
            <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    @yield('contenido')
</main>

<script>
    // Copiar enlaces al portapapeles: <button data-copiar="texto">
    document.addEventListener('click', async (e) => {
        const b = e.target.closest('[data-copiar]');
        if (!b) return;
        try { await navigator.clipboard.writeText(b.dataset.copiar); } catch { prompt('Copia el enlace:', b.dataset.copiar); return; }
        const t = b.innerHTML; b.innerHTML = '<i class="bi bi-check2"></i> Copiado'; setTimeout(() => b.innerHTML = t, 1500);
    });
</script>
@stack('scripts')
</body>
</html>
