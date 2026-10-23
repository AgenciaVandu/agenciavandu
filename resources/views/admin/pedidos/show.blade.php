@extends('admin.layout')
@section('titulo', $v->folio . ' · ' . $v->quien)

@php
    $d = fn ($n) => \App\Models\Venta::dinero((float) $n);
    $tz = config('vandu.zona_horaria');
    $negocio = \App\Support\Cuentas::actual()?->nombre;
    $nombreCli = $v->envio_nombre ?: ($v->cliente?->nombre ?: $v->cliente_nombre);
    $saludo = 'Hola' . ($nombreCli ? ' ' . \Illuminate\Support\Str::before($nombreCli, ' ') : '');
    $msjNota = "$saludo, gracias por tu compra en $negocio. Tu nota {$v->folio} por " . $d($v->total) . ': ' . $v->url_cliente;
    $msjDatos = "$saludo, para enviarte tu pedido {$v->folio} de $negocio, llena aquí tu dirección y tu ubicación: " . $v->url_cliente;
    $pasos = ['por_surtir', 'listo', 'en_camino', 'entregada'];
    if (! $v->lleva_envio) $pasos = ['por_surtir', 'listo', 'entregada'];
    $idx = array_search($v->estado, $pasos, true);
@endphp

@push('head')
<style>
    .pd { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 20px; align-items: start; }
    @media (max-width: 1099.98px) { .pd { grid-template-columns: minmax(0, 1fr); } }
    .pd-badge { font-size: 12.5px; font-weight: 600; padding: 3px 10px; border-radius: 99px; background: var(--sunken); color: var(--muted); }
    .pd-badge.ok { background: #E3FBEF; color: #047A4B; } .pd-badge.pend { background: #FFF3DD; color: #9A5300; } .pd-badge.azul { background: var(--marca-soft); color: var(--marca); } .pd-badge.mal { background: #FDECEC; color: #B42318; }
    .pd-listo { padding: 18px 20px; border-radius: 16px; background: linear-gradient(135deg, #E9FCF2, #F5FFF9); border: 1px solid #BDF2D6; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 20px; }
    .pd-pasos { display: flex; gap: 6px; padding: 14px 20px; flex-wrap: wrap; }
    .pd-pasos form { flex: 1; min-width: 110px; }
    .pd-pasos button { width: 100%; padding: 10px 8px; border-radius: 10px; border: 1px solid var(--line); background: var(--surface, #fff); font-size: 13px; font-weight: 500; color: var(--muted); }
    .pd-pasos button.hecho { background: #E3FBEF; border-color: #A7F3D0; color: #047A4B; }
    .pd-pasos button.actual { background: var(--marca); border-color: var(--marca); color: #fff; font-weight: 600; }
    .pd-dato { display: grid; gap: 2px; } .pd-dato .k { font-size: 12.5px; color: var(--muted); }
    .pd-esperando { padding: 14px 16px; border-radius: 12px; background: #FFF7ED; color: #9A3412; font-size: 14px; }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.pedidos') }}">Pedidos</a> <i class="bi bi-chevron-right small"></i> <span>{{ $v->folio }}</span></div>
<div class="page-head">
    <div>
        <h1 class="d-flex flex-wrap align-items-center gap-2">{{ $v->quien }} <span class="pd-badge {{ $v->estado_clase }}">{{ $v->estado_texto }}</span> <span class="pd-badge {{ $v->pago_clase }}">{{ $v->pago_texto }}</span></h1>
        <p class="sub num">{{ $v->folio }} · {{ $v->fecha->locale('es')->isoFormat('D MMM YYYY') }} · vendió {{ $v->vendedor?->name ?? '—' }}@if($v->presupuesto) · de la cotización <a href="{{ route('admin.presupuestos.edit', $v->presupuesto) }}" class="text-reset">{{ $v->presupuesto->folio }}</a>@endif</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.pedidos.nota', $v) }}" target="_blank" class="btn btn-borde"><i class="bi bi-file-earmark-pdf me-1"></i> Nota</a>
        @if($v->whatsapp)<a href="https://wa.me/{{ $v->whatsapp }}?text={{ rawurlencode($msjNota) }}" target="_blank" rel="noopener" class="btn btn-borde"><i class="bi bi-whatsapp me-1"></i> Mandar nota</a>@endif
        <a href="{{ route('admin.pedidos.create') }}" class="btn btn-primario"><i class="bi bi-plus-lg me-1"></i> Nueva venta</a>
    </div>
</div>

@if($nueva)
    <div class="pd-listo">
        <i class="bi bi-check-circle-fill" style="font-size: 26px; color: #047A4B"></i>
        <div class="me-auto"><b>Venta registrada: {{ $d($v->total) }}</b><div class="secundario small">{{ $v->pagada ? 'Pagada' : ($v->pagado > 0 ? 'Abonó ' . $d($v->pagado) . ', resta ' . $d($v->saldo) : 'Por cobrar ' . $d($v->saldo)) }}{{ $v->lleva_envio ? ' · lleva envío' : '' }}</div></div>
        @if($v->lleva_envio && ! $v->datos_at && $v->whatsapp)
            <a href="https://wa.me/{{ $v->whatsapp }}?text={{ rawurlencode($msjDatos) }}" target="_blank" rel="noopener" class="btn btn-primario"><i class="bi bi-whatsapp me-1"></i> Pedirle sus datos de envío</a>
        @elseif($v->whatsapp)
            <a href="https://wa.me/{{ $v->whatsapp }}?text={{ rawurlencode($msjNota) }}" target="_blank" rel="noopener" class="btn btn-borde"><i class="bi bi-whatsapp me-1"></i> Mandarle su nota</a>
        @endif
    </div>
@endif

<div class="pd">
    <div class="d-grid gap-4">
        @if($v->estado !== 'cancelada')
        <section class="panel">
            <div class="panel-head"><h2>Entrega</h2>@if($v->entregada_at)<span class="ayuda">Entregado {{ $v->entregada_at->setTimezone($tz)->locale('es')->isoFormat('D MMM, h:mm a') }}</span>@endif</div>
            <div class="pd-pasos">
                @foreach($pasos as $i => $e)
                    <form method="post" action="{{ route('admin.pedidos.estado', $v) }}">@csrf @method('patch')
                        <input type="hidden" name="estado" value="{{ $e }}">
                        <button class="{{ $idx !== false && $i < $idx ? 'hecho' : ($e === $v->estado ? 'actual' : '') }}"><i class="bi {{ \App\Models\Venta::ESTADOS[$e]['icono'] }} me-1"></i> {{ \App\Models\Venta::ESTADOS[$e]['texto'] }}</button>
                    </form>
                @endforeach
            </div>
        </section>
        @endif

        @if($v->lleva_envio)
        <section class="panel" x-data="{ editar: {{ $errors->has('envio_direccion') ? 'true' : 'false' }} }">
            <div class="panel-head"><h2><i class="bi bi-truck me-1"></i> Envío</h2>
                <span class="ayuda">{{ $v->datos_at ? 'Datos ' . ($v->datos_por === 'cliente' ? 'que mandó el cliente' : 'capturados por el equipo') : 'Faltan los datos' }}</span></div>
            <div class="panel-body d-grid gap-3">
                @if(! $v->datos_at)
                    <div class="pd-esperando"><i class="bi bi-hourglass-split me-1"></i> Esperando la dirección y ubicación del cliente.
                        @if($v->whatsapp)<a href="https://wa.me/{{ $v->whatsapp }}?text={{ rawurlencode($msjDatos) }}" target="_blank" rel="noopener" class="fw-semibold ms-1">Mandarle el enlace por WhatsApp</a>@endif
                        <span class="d-block mt-1 small">Si no puede, llénalos tú abajo.</span></div>
                    <div class="d-flex gap-2" x-data="{ ok: false }"><input class="form-control form-control-sm num" value="{{ $v->url_cliente }}" readonly @focus="$el.select()" aria-label="Enlace del cliente">
                        <button type="button" class="btn btn-borde btn-sm text-nowrap" @click="navigator.clipboard.writeText('{{ $v->url_cliente }}'); ok = true; setTimeout(() => ok = false, 1500)" x-text="ok ? 'Copiado' : 'Copiar'"></button></div>
                @else
                    <div class="row g-3">
                        <div class="col-sm-6 pd-dato"><span class="k">Recibe</span><b>{{ $v->envio_nombre }}</b>@if($v->envio_telefono)<span class="num">{{ $v->envio_telefono }}</span>@endif</div>
                        <div class="col-sm-6 pd-dato"><span class="k">Costo del envío</span><b class="num">{{ $d($v->envio_costo) }}</b><span class="secundario small">{{ $v->envio_km ? $v->envio_km . ' km' : '' }}{{ $v->envio_costo_manual ? ' · puesto a mano' : '' }}</span></div>
                        <div class="col-12 pd-dato"><span class="k">Dirección</span><span>{{ $v->envio_direccion }}</span>@if($v->envio_referencias)<span class="secundario small">{{ $v->envio_referencias }}</span>@endif</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @if($v->mapa_url)<a href="{{ $v->mapa_url }}" target="_blank" rel="noopener" class="btn btn-borde btn-sm"><i class="bi bi-geo-alt me-1"></i> Abrir en Google Maps</a>@endif
                        @if($v->waze_url)<a href="{{ $v->waze_url }}" target="_blank" rel="noopener" class="btn btn-borde btn-sm"><i class="bi bi-sign-turn-right me-1"></i> Waze</a>@endif
                        <span class="secundario small align-self-center">{{ $v->repartidor ? 'Lo lleva ' . $v->repartidor->name : 'Sin repartidor asignado' }}</span>
                    </div>
                @endif
                <button type="button" class="btn btn-fantasma btn-sm justify-self-start px-0" @click="editar = ! editar"><i class="bi bi-pencil me-1"></i> {{ $v->datos_at ? 'Corregir datos, costo o repartidor' : 'Llenar yo los datos' }}</button>
                <div x-show="editar" x-cloak><form method="post" action="{{ route('admin.pedidos.envio', $v) }}" class="d-grid gap-2">
                    @csrf @method('put')
                    <div class="row g-2">
                        <div class="col-sm-7"><input name="envio_nombre" class="form-control" placeholder="Quién recibe" value="{{ old('envio_nombre', $v->envio_nombre ?: $nombreCli) }}" maxlength="160" aria-label="Quién recibe"></div>
                        <div class="col-sm-5"><input name="envio_telefono" class="form-control num" placeholder="Teléfono" value="{{ old('envio_telefono', $v->envio_telefono ?: ($v->cliente_telefono ?: $v->cliente?->telefono)) }}" maxlength="30" aria-label="Teléfono"></div>
                    </div>
                    <input name="envio_direccion" class="form-control @error('envio_direccion') is-invalid @enderror" placeholder="Calle, número, colonia" value="{{ old('envio_direccion', $v->envio_direccion) }}" maxlength="300" aria-label="Dirección">
                    <input name="envio_referencias" class="form-control" placeholder="Referencias" value="{{ old('envio_referencias', $v->envio_referencias) }}" maxlength="300" aria-label="Referencias">
                    <input type="hidden" name="envio_lat" id="pd-lat" value="{{ old('envio_lat', $v->envio_lat) }}">
                    <input type="hidden" name="envio_lng" id="pd-lng" value="{{ old('envio_lng', $v->envio_lng) }}">
                    @include('admin.pedidos._mapa', ['id' => 'pd', 'lat' => 'pd-lat', 'lng' => 'pd-lng', 'centro' => $envios['lat'] ? [(float) $envios['lat'], (float) $envios['lng']] : null])
                    <div class="row g-2 align-items-end">
                        <div class="col-sm-5"><label class="form-label small" for="pd-costo">Costo del envío <span class="secundario fw-normal">(vacío = según tu tarifa)</span></label>
                            <div class="input-group"><span class="input-group-text">$</span><input id="pd-costo" name="envio_costo" type="number" step="0.01" min="0" class="form-control num" value="{{ $v->envio_costo_manual ? $v->envio_costo : '' }}"></div></div>
                        @if($equipo->count() > 1)
                        <div class="col-sm-4"><label class="form-label small" for="pd-rep">Lo lleva</label>
                            <select id="pd-rep" name="repartidor_id" class="form-select"><option value="">Sin asignar</option>@foreach($equipo as $m)<option value="{{ $m->id }}" @selected($v->repartidor_id === $m->id)>{{ $m->name }}</option>@endforeach</select></div>
                        @endif
                        <div class="col-sm-3"><button class="btn btn-primario w-100">Guardar</button></div>
                    </div>
                </form></div>
            </div>
        </section>
        @endif

        <section class="panel">
            <div class="panel-head"><h2>Productos</h2><span class="ayuda">{{ $v->conceptos->count() }} {{ $v->conceptos->count() === 1 ? 'producto' : 'productos' }}</span></div>
            <div class="table-responsive"><table class="tabla">
                <thead><tr><th>Producto</th><th class="text-center">Cantidad</th><th class="text-end">Precio</th><th class="text-end">Importe</th></tr></thead>
                <tbody>
                @foreach($v->conceptos as $c)
                    <tr><td>@if($c->producto_id)<a href="{{ route('admin.productos.show', $c->producto_id) }}" class="text-reset">{{ $c->descripcion }}</a>@else{{ $c->descripcion }}@endif</td>
                        <td class="text-center num">{{ $c->cantidad_texto }}</td><td class="text-end num">{{ $d($c->precio) }}</td><td class="text-end num fw-medium">{{ $d($c->importe) }}</td></tr>
                @endforeach
                <tr class="tot"><td colspan="3" class="text-end secundario">Productos</td><td class="text-end num">{{ $d($v->subtotal) }}</td></tr>
                @if($v->descuento > 0)<tr><td colspan="3" class="text-end secundario">Descuento</td><td class="text-end num">−{{ $d($v->descuento) }}</td></tr>@endif
                @if($v->con_iva)<tr><td colspan="3" class="text-end secundario">IVA {{ rtrim(rtrim(number_format($v->iva_porcentaje, 2), '0'), '.') }}%</td><td class="text-end num">{{ $d($v->iva) }}</td></tr>@endif
                @if($v->lleva_envio)<tr><td colspan="3" class="text-end secundario">Envío</td><td class="text-end num">{{ $d($v->envio_costo) }}</td></tr>@endif
                <tr><td colspan="3" class="text-end fw-semibold">Total</td><td class="text-end num fw-semibold">{{ $d($v->total) }}</td></tr>
                </tbody>
            </table></div>
            @if($v->notas)<div class="panel-body border-top secundario small">{{ $v->notas }}</div>@endif
        </section>
    </div>

    <aside class="d-grid gap-4">
        <section class="panel">
            <div class="panel-head"><h2>Cobro</h2><span class="ayuda">{{ $v->credito ? 'A crédito' . ($v->vence_el ? ' · vence ' . $v->vence_el->locale('es')->isoFormat('D MMM') : '') : 'De contado' }}</span></div>
            <div class="panel-body d-grid gap-2">
                <div class="d-flex justify-content-between"><span class="secundario">Total</span><b class="num">{{ $d($v->total) }}</b></div>
                <div class="d-flex justify-content-between"><span class="secundario">Pagado</span><span class="num">{{ $d($v->pagado) }}</span></div>
                <div class="d-flex justify-content-between" style="font-size: 18px"><span>Resta</span><b class="num" style="color: {{ $v->saldo > 0 ? ($v->vencida ? 'var(--red)' : 'var(--amber)') : '#047A4B' }}">{{ $d($v->saldo) }}</b></div>
            </div>
            @if($v->pagos->isNotEmpty())
                <div class="border-top">
                    @foreach($v->pagos as $pg)
                        <div class="d-flex justify-content-between px-3 py-2 small border-bottom"><span>{{ $pg->fecha->locale('es')->isoFormat('D MMM') }} · {{ \App\Models\Venta::FORMAS[$pg->forma] ?? $pg->forma }}{{ $pg->usuario ? ' · ' . $pg->usuario->name : '' }}</span><b class="num">{{ $d($pg->monto) }}</b></div>
                    @endforeach
                </div>
            @endif
            @if($v->saldo > 0 && $v->estado !== 'cancelada')
                <form method="post" action="{{ route('admin.pedidos.abonar', $v) }}" class="panel-body border-top d-grid gap-2">
                    @csrf
                    <div class="row g-2">
                        <div class="col-6"><div class="input-group"><span class="input-group-text">$</span><input name="monto" type="number" step="0.01" min="0.01" max="{{ $v->saldo }}" value="{{ $v->saldo }}" class="form-control num @error('monto') is-invalid @enderror" required aria-label="Monto"></div></div>
                        <div class="col-6"><select name="forma" class="form-select" aria-label="Forma de pago">@foreach(\App\Models\Venta::FORMAS as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></div>
                    </div>
                    @error('monto')<div class="text-danger small">{{ $message }}</div>@enderror
                    <button class="btn btn-primario"><i class="bi bi-cash-coin me-1"></i> Registrar pago</button>
                </form>
            @endif
        </section>

        <section class="panel panel-body d-grid gap-2">
            <div class="pd-dato"><span class="k">Cliente</span>
                @if($v->cliente)<a href="{{ route('admin.clientes.show', $v->cliente) }}" class="fw-semibold">{{ $v->quien }}</a>@else<b>{{ $v->quien }}</b>@endif
                @if($v->cliente_telefono ?: $v->cliente?->telefono)<span class="num secundario small">{{ $v->cliente_telefono ?: $v->cliente?->telefono }}</span>@endif</div>
            @if($v->estado !== 'cancelada')
                <form method="post" action="{{ route('admin.pedidos.cancelar', $v) }}" onsubmit="return confirm({{ Js::from('¿Cancelar la venta ' . $v->folio . '?' . ($v->presupuesto_id ? '' : ' La mercancía regresa al inventario.')) }})" class="mt-2">@csrf
                    <button class="btn btn-fantasma btn-sm text-danger px-0"><i class="bi bi-x-circle me-1"></i> Cancelar venta</button></form>
            @else
                <div class="secundario small">Cancelada {{ $v->cancelada_at?->setTimezone($tz)->locale('es')->isoFormat('D MMM, h:mm a') }}.</div>
            @endif
        </section>
    </aside>
</div>
@endsection
