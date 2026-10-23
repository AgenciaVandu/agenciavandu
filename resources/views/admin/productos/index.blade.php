@extends('admin.layout')
@section('titulo', 'Productos')

@push('head')
<style>
    .prod { display: flex; align-items: center; gap: 12px; text-decoration: none; color: inherit; min-width: 0; }
    .prod .foto { width: 44px; height: 44px; border-radius: 10px; flex: none; background: var(--sunken); border: 1px solid var(--line);
                  display: grid; place-items: center; color: var(--faint); overflow: hidden; }
    .prod .foto img { width: 100%; height: 100%; object-fit: cover; }
    .prod .principal { font-weight: 500; }
    .clave { font-size: 12px; color: var(--muted); font-variant-numeric: tabular-nums; }
    .exis { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 500; padding: 3px 10px; border-radius: 99px; white-space: nowrap; background: var(--sunken); color: var(--text-2); }
    .exis.ok { background: var(--green-soft); color: var(--green-ink); }
    .exis.bajo { background: var(--amber-soft); color: var(--amber); }
    .exis.agotado { background: var(--red-soft); color: var(--red); }
    .resumen-inv { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 20px; }
    .resumen-inv > a, .resumen-inv > div { padding: 16px 20px; color: inherit; text-decoration: none; }
    .resumen-inv > * + * { border-left: 1px solid var(--line); }
    .resumen-inv a:hover { background: var(--sunken); }
    .resumen-inv .k { font-size: 13px; color: var(--muted); }
    .resumen-inv .v { font-size: 22px; font-weight: 600; letter-spacing: -.01em; }
    .resumen-inv .v.alerta { color: var(--amber); }
    .resumen-inv .v.mal { color: var(--red); }
    .margen { font-size: 12px; color: var(--muted); }
    @media (max-width: 767.98px) {
        .resumen-inv { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .resumen-inv > *:nth-child(3) { border-left: 0; }
        .resumen-inv > *:nth-child(n+3) { border-top: 1px solid var(--line); }
        .buscador input { min-width: 0; width: 100%; }
        .buscador { flex: 1 1 100%; }
    }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <h1>Productos</h1>
        <p class="sub">{{ $admin ? 'Catálogo, existencias y costos. Cada producto tiene su página para compartir.' : 'Consulta precios y existencias, y comparte la página de cada producto.' }}</p>
    </div>
    @if($admin)
        <div class="d-flex flex-wrap gap-2">
            <div class="dropdown">
                <button class="btn btn-borde" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-globe2 me-1"></i> Página pública</button>
                <div class="dropdown-menu dropdown-menu-end p-3" style="width: 300px">
                    <form method="post" action="{{ route('admin.productos.ajustes') }}">
                        @csrf
                        <p class="small text-secondary mb-2">¿Las páginas de tus productos muestran el precio?</p>
                        <div class="d-grid gap-1">
                            <label class="d-flex gap-2 align-items-center"><input type="radio" name="mostrar_precio" value="1" @checked($mostrarPrecio) onchange="this.form.submit()"> Sí, mostrar el precio</label>
                            <label class="d-flex gap-2 align-items-center"><input type="radio" name="mostrar_precio" value="0" @checked(! $mostrarPrecio) onchange="this.form.submit()"> No, que lo pidan por WhatsApp</label>
                        </div>
                        <p class="small text-secondary mt-2 mb-0">Cada producto puede cambiarlo en su ficha.</p>
                    </form>
                </div>
            </div>
            @if(\App\Support\Cuentas::actual()?->tiene('ia'))<a href="{{ route('admin.productos.lista') }}" class="btn btn-borde"><i class="bi bi-stars me-1"></i> Lista de precios</a>@endif
            <a href="{{ route('admin.productos.importar') }}" class="btn btn-borde"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Importar de Excel</a>
            <a href="{{ route('admin.productos.create') }}" class="btn btn-primario"><i class="bi bi-plus-lg me-1"></i> Nuevo producto</a>
        </div>
    @endif
</div>

@if($ejemplos)
    <div class="aviso" style="background: var(--blue-soft); color: var(--blue); border: 1px solid #cddcff; flex-wrap: wrap">
        <i class="bi bi-lightbulb"></i>
        <span class="flex-grow-1">Tienes {{ $ejemplos }} productos de ejemplo para que pruebes cotizar, mover existencias y compartir. Cuando cargues los tuyos, quítalos.</span>
        <form method="post" action="{{ route('admin.productos.ejemplos.quitar') }}" onsubmit="return confirm('¿Quitar los productos de ejemplo?')">@csrf @method('delete')
            <button class="btn btn-borde btn-sm">Quitar ejemplos</button></form>
    </div>
@endif

@if($resumen['activos'])
    <div class="panel resumen-inv">
        <a href="{{ route('admin.productos.index') }}"><div class="k">Productos activos</div><div class="v num">{{ number_format($resumen['activos']) }}</div></a>
        <a href="{{ route('admin.productos.index', ['estado' => 'bajos']) }}"><div class="k">En su mínimo</div><div class="v num {{ $resumen['bajos'] ? 'alerta' : '' }}">{{ $resumen['bajos'] }}</div></a>
        <a href="{{ route('admin.productos.index', ['estado' => 'agotados']) }}"><div class="k">Sin existencia</div><div class="v num {{ $resumen['agotados'] ? 'mal' : '' }}">{{ $resumen['agotados'] }}</div></a>
        @if($admin)
            <div><div class="k">Inventario a costo</div><div class="v num">${{ number_format($resumen['valor'], 0) }}</div></div>
        @else
            <div><div class="k">Categorías</div><div class="v num">{{ $categorias->where('productos_count', '>', 0)->count() }}</div></div>
        @endif
    </div>
@endif

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <nav class="segmento" aria-label="Filtrar">
        <a href="{{ route('admin.productos.index', ['q' => $q ?: null]) }}" class="{{ ! $categoria && ! $estado ? 'activo' : '' }}">Todos</a>
        @foreach($categorias->where('productos_count', '>', 0) as $c)
            <a href="{{ route('admin.productos.index', ['categoria' => $c->id, 'q' => $q ?: null]) }}" class="{{ $categoria === $c->id ? 'activo' : '' }}">{{ $c->nombre }} <span class="n num">{{ $c->productos_count }}</span></a>
        @endforeach
        @if($estado === 'bajos')<a href="#" class="activo">En su mínimo</a>@endif
        @if($estado === 'agotados')<a href="#" class="activo">Sin existencia</a>@endif
        @if($admin && $resumen['inactivos'])
            <a href="{{ route('admin.productos.index', ['estado' => 'inactivos']) }}" class="{{ $estado === 'inactivos' ? 'activo' : '' }}">Desactivados <span class="n num">{{ $resumen['inactivos'] }}</span></a>
        @endif
    </nav>
    <form class="buscador" method="get" role="search">
        @if($categoria)<input type="hidden" name="categoria" value="{{ $categoria }}">@endif
        @if($estado)<input type="hidden" name="estado" value="{{ $estado }}">@endif
        <i class="bi bi-search"></i>
        <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Nombre, clave o categoría" aria-label="Buscar productos">
    </form>
</div>

<div class="panel">
    @if($productos->isEmpty())
        <div class="vacio">
            <div class="ico"><i class="bi {{ $q ? 'bi-search' : 'bi-box-seam' }}"></i></div>
            @if($q || $categoria || $estado)
                <h3>Ningún producto coincide</h3>
                <p>Prueba con otra palabra o quita el filtro.</p>
                <a href="{{ route('admin.productos.index') }}" class="btn btn-borde">Ver todos</a>
            @elseif($admin)
                <h3>Tu catálogo está vacío</h3>
                <p>Sube el Excel que ya usas y en un minuto tienes todo tu catálogo, o agrega tus productos uno por uno.</p>
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="{{ route('admin.productos.importar') }}" class="btn btn-primario"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Importar de Excel</a>
                    <a href="{{ route('admin.productos.create') }}" class="btn btn-borde">Agregar uno</a>
                </div>
                <form method="post" action="{{ route('admin.productos.ejemplos') }}" class="mt-3">@csrf
                    <button class="btn btn-link btn-sm text-secondary">O carga 10 productos de ejemplo para probar</button></form>
            @else
                <h3>Todavía no hay productos</h3>
                <p>Cuando el equipo cargue el catálogo, aquí vas a ver precios y existencias.</p>
            @endif
        </div>
    @else
        <div class="table-responsive">
            <table class="tabla">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th class="text-end">Precio</th>
                        @if($admin)<th class="text-end">Costo</th>@endif
                        <th>Existencia</th>
                        <th class="text-end"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($productos as $p)
                    <tr @class(['opacity-50' => ! $p->activo])>
                        <td style="min-width: 260px">
                            <a href="{{ route('admin.productos.show', $p) }}" class="prod">
                                <span class="foto">@if($p->miniatura_url)<img src="{{ $p->miniatura_url }}" alt="" loading="lazy">@else<i class="bi bi-image"></i>@endif</span>
                                <span class="d-block" style="min-width: 0">
                                    <span class="principal d-block text-truncate">{{ $p->nombre }}</span>
                                    <span class="clave d-block text-truncate">{{ collect([$p->clave, $p->categoria?->nombre])->filter()->join(' · ') ?: 'Sin clave' }}</span>
                                </span>
                            </a>
                        </td>
                        <td class="text-end num text-nowrap">${{ number_format($p->precio, 2) }}<div class="margen">por {{ $p->unidad }}</div></td>
                        @if($admin)
                            <td class="text-end num text-nowrap">
                                @if($p->costo !== null)${{ number_format($p->costo, 2) }}@if($p->margen !== null)<div class="margen">margen {{ $p->margen }}%</div>@endif
                                @else<span class="secundario">—</span>@endif
                            </td>
                        @endif
                        <td>
                            @if(! $p->controla_existencia)
                                <span class="exis">Sin inventario</span>
                            @else
                                <span @class(['exis', 'agotado' => $p->agotado, 'bajo' => ! $p->agotado && $p->bajo_minimo, 'ok' => ! $p->agotado && ! $p->bajo_minimo])>{{ $p->existencia_texto }}</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if($p->publico && $p->activo)
                                <button type="button" class="btn btn-borde btn-sm" data-copiar="{{ $p->url }}?v={{ auth()->user()->codigo_vendedor }}" title="Copiar el enlace de su página"><i class="bi bi-link-45deg"></i> Copiar enlace</button>
                            @endif
                            @if($admin)
                                <a href="{{ route('admin.productos.edit', $p) }}" class="btn btn-fantasma btn-icono" title="Editar" aria-label="Editar {{ $p->nombre }}"><i class="bi bi-pencil"></i></a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@if($productos->hasPages())
    <div class="mt-3">{{ $productos->links('pagination::bootstrap-5') }}</div>
@endif

@endsection
