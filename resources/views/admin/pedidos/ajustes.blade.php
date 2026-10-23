@extends('admin.layout')
@section('titulo', 'Envíos e IVA')

@section('contenido')
<div class="migas"><a href="{{ route('admin.pedidos') }}">Pedidos</a> <i class="bi bi-chevron-right small"></i> <span>Envíos e IVA</span></div>
<div class="page-head"><div><h1>Envíos e IVA</h1><p class="sub">Cómo cobras tus envíos y si tus ventas suman IVA. Cada venta se puede ajustar a mano.</p></div></div>

<form method="post" action="{{ route('admin.pedidos.ajustes.guardar') }}" class="d-grid gap-3" style="max-width: 760px" x-data="{ modo: {{ Js::from(old('modo', $e['modo'])) }} }">
    @csrf
    <section class="panel panel-body">
        <h2 class="fs-6 fw-semibold mb-2">IVA</h2>
        <label class="form-check m-0"><input type="checkbox" name="iva" value="1" class="form-check-input" @checked(old('iva', $conIva))>
            <span class="form-check-label">Las ventas suman IVA al precio del catálogo <span class="secundario small d-block">Apágalo si tus precios ya lo incluyen o no facturas. En cada venta se puede cambiar.</span></span></label>
    </section>

    <section class="panel panel-body d-grid gap-3">
        <h2 class="fs-6 fw-semibold m-0">Cómo cobras el envío</h2>
        <nav class="segmento" aria-label="Modo de cobro">
            @foreach(\App\Support\Ventas\Envios::MODOS as $k => $t)
                <a href="#" :class="modo === '{{ $k }}' && 'activo'" @click.prevent="modo = '{{ $k }}'">{{ $t }}</a>
            @endforeach
        </nav>
        <input type="hidden" name="modo" :value="modo">
        <div x-show="modo === 'fijo'" x-cloak class="row g-2"><div class="col-sm-4"><label class="form-label" for="aj-fijo">Costo por envío</label>
            <div class="input-group"><span class="input-group-text">$</span><input id="aj-fijo" name="fijo" type="number" step="0.01" min="0" class="form-control num" value="{{ old('fijo', $e['fijo']) }}"></div></div></div>
        <div x-show="modo === 'km'" x-cloak class="row g-2">
            <div class="col-sm-4"><label class="form-label" for="aj-base">Banderazo</label><div class="input-group"><span class="input-group-text">$</span><input id="aj-base" name="base" type="number" step="0.01" min="0" class="form-control num" value="{{ old('base', $e['base']) }}"></div></div>
            <div class="col-sm-4"><label class="form-label" for="aj-km">Por kilómetro</label><div class="input-group"><span class="input-group-text">$</span><input id="aj-km" name="por_km" type="number" step="0.01" min="0" class="form-control num" value="{{ old('por_km', $e['por_km']) }}"></div></div>
            <div class="col-sm-4"><label class="form-label" for="aj-min">Mínimo</label><div class="input-group"><span class="input-group-text">$</span><input id="aj-min" name="minimo" type="number" step="0.01" min="0" class="form-control num" value="{{ old('minimo', $e['minimo']) }}"></div></div>
            <div class="col-12 secundario small">La distancia se mide desde tu negocio hasta la ubicación del cliente, ajustada por calles. Ejemplo: banderazo $30 + $8 por km, mínimo $40.</div>
        </div>
        <div x-show="modo === 'gratis'" class="secundario small">Tus envíos no se cobran aparte. Igual guardas la dirección y la ubicación de cada pedido.</div>
    </section>

    <section class="panel panel-body d-grid gap-2">
        <h2 class="fs-6 fw-semibold m-0">Dónde está tu negocio</h2>
        <p class="secundario small m-0">De aquí sale el cálculo de kilómetros y es donde se centra el mapa.</p>
        <input name="direccion" class="form-control" placeholder="Dirección del negocio (opcional)" maxlength="300" value="{{ old('direccion', $e['direccion']) }}" aria-label="Dirección del negocio">
        <input type="hidden" name="lat" id="aj-lat" value="{{ old('lat', $e['lat']) }}">
        <input type="hidden" name="lng" id="aj-lng" value="{{ old('lng', $e['lng']) }}">
        @include('admin.pedidos._mapa', ['id' => 'aj', 'lat' => 'aj-lat', 'lng' => 'aj-lng'])
        @error('lat')<div class="text-danger small">{{ $message }}</div>@enderror
    </section>
    <div class="d-flex justify-content-end"><button class="btn btn-primario px-4">Guardar</button></div>
</form>
@endsection
