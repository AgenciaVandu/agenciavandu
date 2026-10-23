@extends('admin.layout')
@section('titulo', 'Nueva venta')

@php
    $ini = [
        'buscar' => route('admin.productos.buscar'),
        'conIva' => (bool) old('con_iva', $conIva),
        'iva' => (float) config('vandu.iva_porcentaje', 16),
        'envios' => ['modo' => $envios['modo'], 'fijo' => (float) $envios['fijo'], 'base' => (float) $envios['base'], 'por_km' => (float) $envios['por_km'],
                     'minimo' => (float) $envios['minimo'], 'factor' => (float) ($envios['factor'] ?: 1.3), 'lat' => $envios['lat'], 'lng' => $envios['lng']],
        'cliente' => $cliente ? ['id' => $cliente->id, 'nombre' => $cliente->empresa ?: $cliente->nombre, 'telefono' => $cliente->telefono] : null,
    ];
@endphp

@push('head')
<style>
    .pv { display: grid; grid-template-columns: minmax(0, 1fr) 400px; gap: 20px; align-items: start; }
    @media (max-width: 1099.98px) { .pv { grid-template-columns: minmax(0, 1fr); } }
    .pv-buscar { position: relative; }
    .pv-buscar i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); }
    .pv-buscar input { padding-left: 40px; height: 48px; font-size: 16px; }
    .pv-prods { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 10px; margin-top: 12px; }
    .pv-prod { display: grid; gap: 2px; text-align: left; padding: 12px; border-radius: 12px; border: 1px solid var(--line); background: var(--surface, #fff); cursor: pointer; color: inherit; }
    .pv-prod:hover { border-color: var(--marca); } .pv-prod:active { transform: scale(.98); }
    .pv-prod img { width: 100%; aspect-ratio: 4/3; object-fit: cover; border-radius: 8px; background: var(--sunken); margin-bottom: 6px; }
    .pv-prod b { font-size: 14px; line-height: 1.25; } .pv-prod .p { font-weight: 700; font-size: 15px; } .pv-prod .e { font-size: 12px; color: var(--muted); }
    .pv-prod .e.mal { color: var(--red); }
    .pv-cesta > * + * { border-top: 1px solid var(--line); }
    .pv-linea { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 6px 10px; padding: 10px 16px; align-items: center; }
    .pv-linea .n { font-weight: 600; font-size: 14px; } .pv-linea .aviso-ex { grid-column: 1 / -1; font-size: 12px; color: var(--amber); }
    .pv-cant { display: inline-flex; align-items: center; border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
    .pv-cant button { width: 34px; height: 34px; border: 0; background: var(--sunken); font-size: 18px; line-height: 1; }
    .pv-cant input { width: 56px; height: 34px; border: 0; text-align: center; font-weight: 600; }
    .pv-precio { width: 100px; } .pv-imp { font-weight: 700; min-width: 80px; text-align: right; }
    .pv-seg { display: grid; grid-auto-flow: column; grid-auto-columns: 1fr; gap: 6px; }
    .pv-seg button { padding: 9px 6px; border-radius: 10px; border: 1px solid var(--line); background: var(--surface, #fff); font-size: 13.5px; font-weight: 500; color: inherit; }
    .pv-seg button.on { border-color: var(--marca); background: var(--marca-soft); color: var(--marca); font-weight: 600; }
    .pv-tot { display: grid; gap: 4px; font-size: 14px; } .pv-tot > div { display: flex; justify-content: space-between; } .pv-tot .t { font-size: 22px; font-weight: 700; margin-top: 4px; }
    .pv-caja { position: sticky; top: 16px; }
    .pv-vacio { padding: 26px 16px; text-align: center; color: var(--muted); font-size: 14px; }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.pedidos') }}">Pedidos</a> <i class="bi bi-chevron-right small"></i> <span>Nueva venta</span></div>
<div class="page-head"><div><h1>Nueva venta</h1><p class="sub">Toca los productos, cobra y listo. El inventario se descuenta solo.</p></div></div>

@if($errors->any())<div class="aviso aviso-error mb-3" role="alert"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>@endif

<form method="post" action="{{ route('admin.pedidos.store') }}" class="pv" x-data="posVenta({{ Js::from($ini) }})" @submit="enviando = true">
    @csrf
    {{-- Catálogo --}}
    <section>
        <div class="pv-buscar"><i class="bi bi-search"></i>
            <input type="search" class="form-control" placeholder="Busca por nombre o clave" x-model="q" @input.debounce.250ms="buscar()" @keydown.enter.prevent="primero()" aria-label="Buscar producto" autofocus></div>
        <div class="pv-prods">
            <template x-for="p in resultados" :key="p.id">
                <button type="button" class="pv-prod" @click="agregar(p)">
                    <template x-if="p.miniatura"><img :src="p.miniatura" alt=""></template>
                    <b x-text="p.nombre"></b>
                    <span class="p num" x-text="dinero(p.precio)"></span>
                    <span class="e" :class="p.agotado && 'mal'" x-text="p.controla ? (p.agotado ? 'Agotado' : 'Hay ' + p.existencia_texto) : (p.clave || '')"></span>
                </button>
            </template>
        </div>
        <div class="pv-vacio" x-show="! cargando && resultados.length === 0" x-cloak>No encontramos productos con ese nombre.</div>
    </section>

    {{-- Cuenta --}}
    <aside class="pv-caja d-grid gap-3">
        <section class="panel">
            <div class="panel-head"><h2>Venta</h2><span class="ayuda" x-text="lineas.length ? lineas.length + (lineas.length === 1 ? ' producto' : ' productos') : ''"></span></div>
            <div class="pv-cesta">
                <template x-for="(l, i) in lineas" :key="l.id">
                    <div class="pv-linea">
                        <div class="min-w-0"><div class="n text-truncate" x-text="l.nombre"></div>
                            <div class="d-flex align-items-center gap-2 mt-1">
                                <span class="pv-cant"><button type="button" @click="l.cantidad = Math.max(0, +l.cantidad - 1); if (+l.cantidad === 0) lineas.splice(i, 1)" aria-label="Menos">−</button>
                                    <input type="number" step="any" min="0" x-model.number="l.cantidad" :name="`productos[${i}][cantidad]`" aria-label="Cantidad" inputmode="decimal">
                                    <button type="button" @click="l.cantidad = +l.cantidad + 1" aria-label="Más">+</button></span>
                                <span class="secundario small">×</span>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm num pv-precio" x-model.number="l.precio" :name="`productos[${i}][precio]`" aria-label="Precio">
                            </div>
                            <input type="hidden" :name="`productos[${i}][producto_id]`" :value="l.id">
                        </div>
                        <div class="text-end"><div class="pv-imp num" x-text="dinero(l.cantidad * l.precio)"></div>
                            <button type="button" class="btn btn-fantasma btn-sm text-danger px-1" @click="lineas.splice(i, 1)" aria-label="Quitar"><i class="bi bi-x-lg"></i></button></div>
                        <div class="aviso-ex" x-show="l.controla && l.cantidad > l.existencia" x-text="'Solo hay ' + l.existencia + ' en inventario. Se registra igual.'"></div>
                    </div>
                </template>
                <div class="pv-vacio" x-show="lineas.length === 0">Toca un producto para agregarlo.</div>
            </div>
        </section>

        <section class="panel panel-body d-grid gap-3">
            <div>
                <label class="form-label">Cliente <span class="secundario fw-normal" x-text="pago === 'contado' && entrega !== 'envio' ? '(opcional)' : ''"></span></label>
                <template x-if="cliente"><div class="d-flex align-items-center gap-2"><b x-text="cliente.nombre"></b><span class="secundario small" x-text="cliente.telefono || ''"></span>
                    <button type="button" class="btn btn-fantasma btn-sm ms-auto" @click="cliente = null">Cambiar</button><input type="hidden" name="cliente_id" :value="cliente.id"></div></template>
                <template x-if="! cliente"><div class="row g-2">
                    <div class="col-7"><input name="cliente_nombre" class="form-control" placeholder="Mostrador" value="{{ old('cliente_nombre') }}" maxlength="160" aria-label="Nombre del cliente"></div>
                    <div class="col-5"><input name="cliente_telefono" class="form-control num" placeholder="WhatsApp" value="{{ old('cliente_telefono') }}" maxlength="30" inputmode="tel" aria-label="WhatsApp del cliente"></div>
                    <div class="col-12" x-show="pago === 'contado'"><label class="form-check small m-0"><input type="checkbox" name="guardar_cliente" value="1" class="form-check-input" @checked(old('guardar_cliente'))> <span class="form-check-label">Guardarlo en Clientes</span></label></div>
                </div></template>
            </div>

            <div>
                <label class="form-label">Entrega</label>
                <div class="pv-seg">
                    <button type="button" :class="entrega === 'ahora' && 'on'" @click="entrega = 'ahora'">Se lo lleva</button>
                    <button type="button" :class="entrega === 'recoge' && 'on'" @click="entrega = 'recoge'">Recoge después</button>
                    <button type="button" :class="entrega === 'envio' && 'on'" @click="entrega = 'envio'"><i class="bi bi-truck"></i> Envío</button>
                </div>
                <input type="hidden" name="entrega" :value="entrega">
            </div>

            <div x-show="entrega === 'envio'" x-cloak><div class="d-grid gap-2">
                <div class="pv-seg">
                    <button type="button" :class="datos === 'cliente' && 'on'" @click="datos = 'cliente'"><i class="bi bi-whatsapp"></i> Que el cliente llene sus datos</button>
                    <button type="button" :class="datos === 'vendedor' && 'on'" @click="datos = 'vendedor'"><i class="bi bi-pencil"></i> Los lleno yo</button>
                </div>
                <input type="hidden" name="datos" :value="datos">
                <div class="secundario small" x-show="datos === 'cliente'">Al guardar te damos el enlace para mandárselo por WhatsApp: ahí pone su dirección y su ubicación{{ $envios['modo'] === 'km' ? ', y el envío se calcula solo' : '' }}.</div>
                <div x-show="datos === 'vendedor'"><div class="d-grid gap-2">
                    <input name="envio_direccion" class="form-control" placeholder="Calle, número, colonia" maxlength="300" value="{{ old('envio_direccion') }}" aria-label="Dirección">
                    <input name="envio_referencias" class="form-control" placeholder="Referencias (portón negro, frente al parque…)" maxlength="300" value="{{ old('envio_referencias') }}" aria-label="Referencias">
                    <input type="hidden" name="envio_lat" id="nv-lat" value="{{ old('envio_lat') }}" @input="km = distancia()">
                    <input type="hidden" name="envio_lng" id="nv-lng" value="{{ old('envio_lng') }}" @input="km = distancia()">
                    @include('admin.pedidos._mapa', ['id' => 'nv', 'lat' => 'nv-lat', 'lng' => 'nv-lng', 'centro' => $envios['lat'] ? [(float) $envios['lat'], (float) $envios['lng']] : null])
                </div></div>
                <div class="row g-2 align-items-end">
                    <div class="col-6"><label class="form-label small" for="nv-costo">Costo del envío</label>
                        <div class="input-group input-group-sm"><span class="input-group-text">$</span>
                            <input id="nv-costo" name="envio_costo" type="number" step="0.01" min="0" class="form-control num" x-model="costoManual" :placeholder="costoAuto() === null ? 'Con la ubicación' : costoAuto()"></div>
                        <div class="secundario" style="font-size: 11.5px" x-show="km" x-text="km + ' km'"></div></div>
                    @if($equipo->count() > 1)
                    <div class="col-6"><label class="form-label small" for="nv-rep">Lo lleva</label>
                        <select id="nv-rep" name="repartidor_id" class="form-select form-select-sm"><option value="">Sin asignar</option>@foreach($equipo as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select></div>
                    @endif
                </div>
            </div></div>

            <div>
                <label class="form-label">Pago</label>
                <div class="pv-seg">
                    <button type="button" :class="pago === 'contado' && 'on'" @click="pago = 'contado'">De contado</button>
                    <button type="button" :class="pago === 'parcial' && 'on'" @click="pago = 'parcial'">Abono</button>
                    <button type="button" :class="pago === 'credito' && 'on'" @click="pago = 'credito'">A crédito</button>
                </div>
                <input type="hidden" name="pago" :value="pago">
            </div>
            <div class="row g-2" x-show="pago !== 'credito'">
                <div class="col-6"><select name="forma" class="form-select" aria-label="Forma de pago">@foreach(\App\Models\Venta::FORMAS as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></div>
                <div class="col-6" x-show="pago === 'contado'"><div class="input-group"><span class="input-group-text">Recibí</span><input type="number" step="0.01" min="0" class="form-control num" x-model.number="recibido" aria-label="Efectivo recibido"></div></div>
                <div class="col-6" x-show="pago === 'parcial'"><div class="input-group"><span class="input-group-text">$</span><input type="number" name="monto" step="0.01" min="0" class="form-control num" x-model.number="abono" aria-label="Monto del abono"></div></div>
                <div class="col-12 small" x-show="pago === 'contado' && recibido > total()" x-text="'Cambio: ' + dinero(recibido - total())" style="font-weight: 600"></div>
            </div>
            <div x-show="pago !== 'contado'" x-cloak><div class="d-flex align-items-center gap-2">
                <label class="small m-0" for="nv-dias">Paga el resto en</label>
                <input id="nv-dias" name="dias" type="number" min="1" max="365" value="15" class="form-control form-control-sm num" style="width: 70px"><span class="small">días</span>
            </div></div>

            <div class="row g-2 align-items-center">
                <div class="col-6"><div class="input-group input-group-sm"><span class="input-group-text">Descuento $</span><input type="number" name="descuento" step="0.01" min="0" class="form-control num" x-model.number="descuento"></div></div>
                <div class="col-6"><label class="form-check small m-0"><input type="checkbox" name="con_iva" value="1" class="form-check-input" x-model="conIva"> <span class="form-check-label">Sumar IVA {{ rtrim(rtrim(number_format(config('vandu.iva_porcentaje', 16), 2), '0'), '.') }}%</span></label></div>
            </div>

            <div class="pv-tot">
                <div><span class="secundario">Productos</span><span class="num" x-text="dinero(subtotal())"></span></div>
                <div x-show="descuento > 0"><span class="secundario">Descuento</span><span class="num" x-text="'−' + dinero(descuento)"></span></div>
                <div x-show="conIva"><span class="secundario">IVA</span><span class="num" x-text="dinero(iva())"></span></div>
                <div x-show="entrega === 'envio'"><span class="secundario">Envío</span><span class="num" x-text="envio() === null ? 'con la ubicación' : dinero(envio())"></span></div>
                <div class="t"><span>Total</span><span class="num" x-text="dinero(total())"></span></div>
            </div>
            <input type="text" name="notas" class="form-control form-control-sm" placeholder="Nota (opcional)" maxlength="2000" value="{{ old('notas') }}" aria-label="Nota">
            <button class="btn btn-primario btn-lg" :disabled="lineas.length === 0 || enviando">
                <i class="bi bi-check2 me-1"></i> <span x-text="pago === 'contado' ? 'Cobrar ' + dinero(total()) : 'Registrar venta'"></span></button>
        </section>
    </aside>
</form>
@endsection

@push('scripts')
<script>
window.posVenta = function (cfg) {
    return {
        q: '', resultados: [], cargando: false, lineas: [], cliente: cfg.cliente, entrega: 'ahora', datos: 'cliente', pago: 'contado',
        recibido: null, abono: null, descuento: 0, conIva: cfg.conIva, costoManual: '', km: null, enviando: false,
        init() { this.buscar(); },
        dinero(n) { return '$' + (Math.round((+n || 0) * 100) / 100).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        async buscar() {
            this.cargando = true;
            try { const r = await fetch(cfg.buscar + '?q=' + encodeURIComponent(this.q), { headers: { Accept: 'application/json' } }); this.resultados = await r.json(); } catch (e) { this.resultados = []; }
            this.cargando = false;
        },
        primero() { if (this.resultados[0]) { this.agregar(this.resultados[0]); this.q = ''; this.buscar(); } },
        agregar(p) {
            const l = this.lineas.find(x => x.id === p.id);
            if (l) l.cantidad = +l.cantidad + 1;
            else this.lineas.push({ id: p.id, nombre: p.nombre, precio: +p.precio, cantidad: 1, controla: !!p.controla, existencia: +(p.existencia || 0) });
        },
        subtotal() { return this.lineas.reduce((s, l) => s + (+l.cantidad || 0) * (+l.precio || 0), 0); },
        base() { return Math.max(0, this.subtotal() - (+this.descuento || 0)); },
        iva() { return this.conIva ? Math.round(this.base() * cfg.iva) / 100 : 0; },
        distancia() {
            const e = cfg.envios, la = parseFloat(document.getElementById('nv-lat')?.value), lo = parseFloat(document.getElementById('nv-lng')?.value);
            if (!e.lat || !e.lng || isNaN(la) || isNaN(lo)) return null;
            const r = (x) => x * Math.PI / 180, dLa = r(la - e.lat), dLo = r(lo - e.lng);
            const h = Math.sin(dLa / 2) ** 2 + Math.cos(r(e.lat)) * Math.cos(r(la)) * Math.sin(dLo / 2) ** 2;
            return Math.round(2 * 6371 * Math.asin(Math.min(1, Math.sqrt(h))) * e.factor * 10) / 10;
        },
        costoAuto() {
            const e = cfg.envios;
            if (e.modo === 'fijo') return e.fijo;
            if (e.modo === 'km') return (this.datos === 'vendedor' && this.km !== null) ? Math.round(Math.max(e.minimo, e.base + e.por_km * this.km)) : null;
            return 0;
        },
        envio() { if (this.entrega !== 'envio') return 0; if (this.costoManual !== '' && this.costoManual !== null) return +this.costoManual; return this.costoAuto(); },
        total() { return this.base() + this.iva() + (this.envio() || 0); },
    };
};
</script>
@endpush
