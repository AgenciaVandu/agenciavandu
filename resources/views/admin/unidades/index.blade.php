@extends('admin.layout')
@section('titulo', 'Unidades')

@push('head')
<style>
    .rejilla-u { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px; }
    .tarjeta-u { display: block; color: inherit; text-decoration: none; overflow: hidden; transition: box-shadow .15s, transform .15s; }
    .tarjeta-u:hover { box-shadow: 0 10px 28px rgba(16,24,40,.12); transform: translateY(-1px); }
    .tarjeta-u .f { aspect-ratio: 4 / 3; background: var(--sunken); display: grid; place-items: center; color: var(--faint); font-size: 34px; position: relative; }
    .tarjeta-u .f img { width: 100%; height: 100%; object-fit: cover; }
    .tarjeta-u .f .estado { position: absolute; top: 10px; left: 10px; }
    .tarjeta-u .c { padding: 14px 16px; }
    .tarjeta-u .n { font-weight: 600; line-height: 1.25; }
    .tarjeta-u .d { font-size: 13px; color: var(--muted); margin-top: 2px; }
    .tarjeta-u .p { display: flex; justify-content: space-between; align-items: baseline; margin-top: 10px; }
    .tarjeta-u .p b { font-size: 18px; }
    .tarjeta-u .p span { font-size: 12.5px; color: var(--muted); }
    .kpis-u { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 20px; }
    .kpis-u > * { padding: 16px 20px; color: inherit; text-decoration: none; }
    .kpis-u > * + * { border-left: 1px solid var(--line); }
    .kpis-u .k { font-size: 13px; color: var(--muted); } .kpis-u .v { font-size: 22px; font-weight: 600; }
    @media (max-width: 767.98px) { .kpis-u { grid-template-columns: repeat(2, minmax(0, 1fr)); } .kpis-u > *:nth-child(3) { border-left: 0; } .kpis-u > *:nth-child(n+3) { border-top: 1px solid var(--line); } .buscador input { min-width: 0; width: 100%; } .buscador { flex: 1 1 100%; } }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <h1>Unidades</h1>
        <p class="sub">Los autos del lote. Cada uno tiene su página con fotos y botón de WhatsApp para compartir.</p>
    </div>
    @if($admin)<a href="{{ route('admin.unidades.create') }}" class="btn btn-primario"><i class="bi bi-plus-lg me-1"></i> Nueva unidad</a>@endif
</div>

<div class="panel kpis-u">
    <a href="{{ route('admin.unidades.index', ['estado' => 'disponible']) }}"><div class="k">Disponibles</div><div class="v num">{{ $conteo['disponible'] ?? 0 }}</div></a>
    <a href="{{ route('admin.unidades.index', ['estado' => 'apartado']) }}"><div class="k">Apartadas</div><div class="v num">{{ $conteo['apartado'] ?? 0 }}</div></a>
    <a href="{{ route('admin.unidades.index', ['estado' => 'vendido']) }}"><div class="k">Vendidas</div><div class="v num">{{ $conteo['vendido'] ?? 0 }}</div></a>
    <div><div class="k">Por vender (precio)</div><div class="v num">${{ number_format($valor, 0) }}</div></div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <nav class="segmento" aria-label="Filtrar">
        <a href="{{ route('admin.unidades.index', array_filter(['q' => $q, 'sucursal' => $sucursal])) }}" class="{{ ! $estado ? 'activo' : '' }}">En venta</a>
        @foreach(\App\Models\Unidad::ESTADOS as $k => $e)
            <a href="{{ route('admin.unidades.index', array_filter(['estado' => $k, 'q' => $q, 'sucursal' => $sucursal])) }}" class="{{ $estado === $k ? 'activo' : '' }}">{{ $e['texto'] }}</a>
        @endforeach
    </nav>
    <div class="d-flex gap-2 flex-wrap">
        @if($sucursales->count() > 1)
            <form method="get">
                @if($estado)<input type="hidden" name="estado" value="{{ $estado }}">@endif
                <select name="sucursal" class="form-select" onchange="this.form.submit()" aria-label="Sucursal">
                    <option value="">Todas las sucursales</option>
                    @foreach($sucursales as $s)<option value="{{ $s->id }}" @selected($sucursal === $s->id)>{{ $s->nombre }}</option>@endforeach
                </select>
            </form>
        @endif
        <form class="buscador" method="get" role="search">
            @if($estado)<input type="hidden" name="estado" value="{{ $estado }}">@endif
            <i class="bi bi-search"></i>
            <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Marca, modelo, año o placas" aria-label="Buscar unidades">
        </form>
    </div>
</div>

@if($unidades->isEmpty())
    <div class="panel"><div class="vacio">
        <div class="ico"><i class="bi bi-car-front"></i></div>
        <h3>{{ $q || $estado ? 'Ninguna unidad coincide' : 'Todavía no hay unidades' }}</h3>
        <p>{{ $q || $estado ? 'Prueba con otra búsqueda.' : 'Da de alta el primer auto con sus fotos y datos; su página queda lista para compartir.' }}</p>
        @if($admin && ! $q && ! $estado)<a href="{{ route('admin.unidades.create') }}" class="btn btn-primario">Agregar unidad</a>@endif
    </div></div>
@else
    <div class="rejilla-u">
        @foreach($unidades as $u)
            <a href="{{ route('admin.unidades.show', $u) }}" class="panel tarjeta-u">
                <div class="f">
                    @if($u->portada)<img src="{{ $u->portada->miniatura_url }}" alt="" loading="lazy">@else<i class="bi bi-car-front"></i>@endif
                    <span class="estado estado-{{ \App\Models\Unidad::ESTADOS[$u->estado]['clase'] }}">{{ \App\Models\Unidad::ESTADOS[$u->estado]['texto'] }}</span>
                </div>
                <div class="c">
                    <div class="n">{{ $u->marca }} {{ $u->modelo }} {{ $u->anio }}</div>
                    <div class="d">{{ collect([$u->version, $u->kilometraje !== null ? number_format($u->kilometraje) . ' km' : null, $u->transmision])->filter()->join(' · ') ?: 'Sin detalles' }}</div>
                    <div class="p"><b class="num">${{ number_format($u->precio, 0) }}</b>@if($u->sucursal)<span>{{ $u->sucursal->nombre }}</span>@endif</div>
                </div>
            </a>
        @endforeach
    </div>
    @if($unidades->hasPages())<div class="mt-3">{{ $unidades->links('pagination::bootstrap-5') }}</div>@endif
@endif
@endsection
