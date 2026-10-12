@extends('admin.layout')
@section('titulo', $q ? 'Buscar: ' . $q : 'Buscar')

@push('head')
<style>
    .res-grupo + .res-grupo { margin-top: 20px; }
    .res-item { display: flex; gap: 12px; align-items: center; padding: 12px 20px; border-top: 1px solid var(--line); text-decoration: none; color: inherit; }
    .res-item:hover { background: #FAFBFC; }
    .res-item .ic { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; background: #F3F4F6; flex: none; }
    .res-item .t { font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .res-item .s { font-size: 13px; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .res-item .eti { margin-left: auto; font-size: 12px; color: var(--muted); white-space: nowrap; }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <h1>Buscar</h1>
        <p class="sub">Clientes, proyectos, proveedores, cotizaciones y órdenes de compra.</p>
    </div>
</div>

<form method="get" class="buscador mb-4" role="search" style="max-width: 560px">
    <i class="bi bi-search"></i>
    <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Nombre, empresa, folio, teléfono…" aria-label="Buscar en todo el panel" autofocus>
</form>

@if($q !== '' && empty($grupos))
    <div class="panel"><div class="vacio">
        <div class="ico"><i class="bi bi-search"></i></div>
        <h3>Nada coincide con “{{ $q }}”</h3>
        <p>Prueba con otra palabra, solo el apellido, el folio (CT-0012, OC-0003) o los últimos dígitos del teléfono.</p>
    </div></div>
@endif

@foreach($grupos as $g)
    <section class="panel res-grupo">
        <div class="panel-head"><h2><i class="bi {{ $g['icono'] }} me-1"></i> {{ $g['titulo'] }}</h2><span class="ayuda num">{{ $g['total'] > count($g['items']) ? count($g['items']) . ' de ' . $g['total'] : $g['total'] }}</span></div>
        @foreach($g['items'] as $it)
            <a href="{{ $it['url'] }}" class="res-item">
                <span class="ic"><i class="bi {{ $g['icono'] }}"></i></span>
                <span style="min-width: 0"><span class="t d-block">{{ $it['titulo'] }}</span><span class="s d-block">{{ $it['sub'] }}</span></span>
                @if(! empty($it['etiqueta']))<span class="eti">{{ $it['etiqueta'] }}</span>@endif
            </a>
        @endforeach
    </section>
@endforeach
@endsection
