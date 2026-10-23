@php
    $c = \App\Support\Cuentas::actual();
    $logo = $c?->logo();
    $d = fn ($n) => '$' . number_format((float) $n, 2);
    $nombre = $v->envio_nombre ?: ($v->cliente?->nombre ?: $v->cliente_nombre);
    $tel = $v->envio_telefono ?: ($v->cliente_telefono ?: $v->cliente?->telefono);
    $paso = array_search($v->estado, ['por_surtir', 'listo', 'en_camino', 'entregada'], true);
    $centro = $envios['lat'] ? [(float) $envios['lat'], (float) $envios['lng']] : null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>Tu pedido {{ $v->folio }} · {{ $c?->nombre }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <style>
        :root { --ink: #1A103D; --marca: #6B3BFF; --suave: #F1ECFF; --linea: #E6E4EE; --gris: #6B6880; --fondo: #F6F5FA; --ok: #047A4B; }
        * { box-sizing: border-box; }
        html { touch-action: manipulation; -webkit-text-size-adjust: 100%; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--fondo); color: var(--ink); font-size: 16px; line-height: 1.45; }
        .cab { background: #fff; border-bottom: 1px solid var(--linea); padding: 14px 18px; display: flex; align-items: center; gap: 12px; }
        .cab img { max-height: 40px; max-width: 160px; } .cab b { font-size: 17px; }
        main { max-width: 560px; margin: 0 auto; padding: 18px 16px 48px; display: grid; gap: 14px; }
        .caja { background: #fff; border: 1px solid var(--linea); border-radius: 16px; padding: 18px; }
        h1 { font-size: 22px; margin: 0 0 4px; } h2 { font-size: 16px; margin: 0 0 12px; }
        .sub { color: var(--gris); font-size: 14px; margin: 0; }
        label { display: block; font-size: 14px; font-weight: 600; margin: 12px 0 6px; }
        input, textarea { width: 100%; font: inherit; font-size: 16px; padding: 12px 14px; border: 1px solid var(--linea); border-radius: 12px; background: #fff; color: inherit; }
        input:focus, textarea:focus { outline: 2px solid var(--suave); border-color: var(--marca); }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 13px 18px; border-radius: 12px; border: 1px solid var(--linea); background: #fff; font: inherit; font-weight: 600; color: inherit; text-decoration: none; cursor: pointer; }
        .btn.prim { background: var(--marca); border-color: var(--marca); color: #fff; width: 100%; font-size: 17px; margin-top: 16px; }
        .gps { width: 100%; margin-top: 4px; }
        #mapa { height: 260px; border-radius: 12px; border: 1px solid var(--linea); margin-top: 10px; z-index: 0; }
        .estado-gps { font-size: 13px; color: var(--gris); margin-top: 6px; }
        .linea { display: flex; justify-content: space-between; gap: 12px; padding: 7px 0; font-size: 15px; } .linea + .linea { border-top: 1px dashed var(--linea); }
        .total { font-size: 19px; font-weight: 700; border-top: 1px solid var(--linea) !important; padding-top: 10px; }
        .ok { background: #E3FBEF; color: #065F46; border: 1px solid #A7F3D0; border-radius: 12px; padding: 12px 14px; font-weight: 600; }
        .err { background: #FDECEC; color: #B42318; border-radius: 12px; padding: 12px 14px; font-size: 14px; }
        .pasos { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; text-align: center; font-size: 12px; color: var(--gris); }
        .pasos div { padding: 8px 4px; border-radius: 10px; background: var(--fondo); } .pasos .si { background: var(--suave); color: var(--marca); font-weight: 700; }
        .pie { text-align: center; font-size: 12.5px; color: var(--gris); }
    </style>
</head>
<body>
<header class="cab">@if($logo)<img src="{{ $logo }}" alt="{{ $c?->nombre }}">@else<b>{{ $c?->nombre }}</b>@endif</header>
<main>
    <div>
        <h1>Tu pedido {{ $v->folio }}</h1>
        <p class="sub">{{ $v->fecha->locale('es')->isoFormat('D [de] MMMM') }} · {{ $d($v->total) }}{{ $v->pagada ? ' · pagado' : '' }}</p>
    </div>

    @if(session('ok'))<div class="ok" role="status">✓ {{ session('ok') }}</div>@endif
    @if($errors->any())<div class="err" role="alert">{{ $errors->first() }}</div>@endif

    @if($v->estado === 'cancelada')
        <div class="caja"><b>Este pedido se canceló.</b><p class="sub">Si tienes dudas, escríbenos.</p></div>
    @elseif($v->lleva_envio)
        <div class="pasos" aria-label="Estado del pedido">
            @foreach(['Recibido', 'Listo', 'En camino', 'Entregado'] as $i => $t)<div class="{{ $paso !== false && $i <= $paso ? 'si' : '' }}">{{ $t }}</div>@endforeach
        </div>
    @endif

    @if($abierto)
        <form method="post" action="{{ route('pedido.publico.guardar', $v->token) }}" class="caja">
            @csrf
            <h2>{{ $v->datos_at ? 'Tus datos de envío' : '¿A dónde te lo llevamos?' }}</h2>
            <p class="sub">{{ $v->datos_at ? 'Ya los tenemos. Si algo cambió, corrígelo y vuelve a enviar.' : 'Llena tus datos y marca tu ubicación para que el repartidor llegue directo.' }}</p>
            <label for="nombre">Tu nombre</label>
            <input id="nombre" name="nombre" value="{{ old('nombre', $nombre) }}" required maxlength="160" autocomplete="name">
            <label for="telefono">Tu WhatsApp</label>
            <input id="telefono" name="telefono" value="{{ old('telefono', $tel) }}" required maxlength="30" inputmode="tel" autocomplete="tel">
            <label for="direccion">Dirección</label>
            <input id="direccion" name="direccion" value="{{ old('direccion', $v->envio_direccion) }}" required maxlength="300" placeholder="Calle, número, colonia" autocomplete="street-address">
            <label for="referencias">Referencias <span style="font-weight: 400; color: var(--gris)">(opcional)</span></label>
            <input id="referencias" name="referencias" value="{{ old('referencias', $v->envio_referencias) }}" maxlength="300" placeholder="Portón negro, frente al parque…">
            <label>Tu ubicación</label>
            <button type="button" class="btn gps" id="gps">📍 Usar mi ubicación</button>
            <div id="mapa"></div>
            <div class="estado-gps" id="estado">También puedes tocar el mapa o arrastrar el pin hasta tu puerta.</div>
            <input type="hidden" name="lat" id="lat" value="{{ old('lat', $v->envio_lat) }}">
            <input type="hidden" name="lng" id="lng" value="{{ old('lng', $v->envio_lng) }}">
            <button class="btn prim">{{ $v->datos_at ? 'Actualizar mis datos' : 'Enviar mis datos' }}</button>
        </form>
    @elseif($v->lleva_envio && $v->datos_at)
        <div class="caja"><h2>Entrega</h2><p class="sub">{{ $v->envio_nombre }} · {{ $v->envio_direccion }}@if($v->envio_referencias) · {{ $v->envio_referencias }}@endif</p></div>
    @endif

    <div class="caja">
        <h2>Tu compra</h2>
        @foreach($v->conceptos as $cc)<div class="linea"><span>{{ $cc->cantidad_texto }} · {{ $cc->descripcion }}</span><span>{{ $d($cc->importe) }}</span></div>@endforeach
        @if($v->descuento > 0)<div class="linea"><span>Descuento</span><span>−{{ $d($v->descuento) }}</span></div>@endif
        @if($v->con_iva)<div class="linea"><span>IVA</span><span>{{ $d($v->iva) }}</span></div>@endif
        @if($v->lleva_envio)<div class="linea"><span>Envío{{ $v->envio_km ? ' (' . $v->envio_km . ' km)' : '' }}</span><span>{{ $v->datos_at || $v->envio_costo > 0 || $envios['modo'] !== 'km' ? $d($v->envio_costo) : 'con tu ubicación' }}</span></div>@endif
        <div class="linea total"><span>Total</span><span>{{ $d($v->total) }}</span></div>
        @if(! $v->pagada && $v->estado !== 'cancelada')<div class="linea"><span>Por pagar</span><b>{{ $d($v->saldo) }}</b></div>@endif
        <a href="{{ route('pedido.publico.nota', $v->token) }}" target="_blank" class="btn" style="margin-top: 12px; width: 100%">Ver mi nota (PDF)</a>
    </div>
    <p class="pie">{{ $c?->nombre }} · tus datos solo se usan para entregarte este pedido.@if(Route::has('privacidad')) <a href="{{ route('privacidad') }}">Aviso de privacidad</a>@endif</p>
</main>
@if($abierto)
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
@include('pedidos._mapa-js')
<script>window.nerbyMapa({ el: 'mapa', campoLat: 'lat', campoLng: 'lng', centro: @json($centro), boton: 'gps', estado: 'estado' });</script>
@endif
</body>
</html>
