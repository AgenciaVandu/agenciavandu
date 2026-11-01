@extends('admin.layout')
@section('titulo', 'Nueva factura')

@php
    $aj = \App\Support\Facturas\Timbrado::ajustes();
    $cl = $clientes->map(fn ($c) => [
        'id' => $c->id, 'nombre' => $c->empresa ?: $c->nombre, 'rfc' => mb_strtoupper((string) $c->rfc), 'razon' => $c->razon_social, 'regimen' => $c->regimen_fiscal,
        'cp' => $c->cp_fiscal, 'uso' => $c->uso_cfdi, 'correo' => $c->email_factura ?: $c->email,
        'completo' => filled($c->rfc) && filled($c->razon_social) && filled($c->regimen_fiscal) && filled($c->cp_fiscal),
        'razones' => $c->razones->map(fn ($r) => ['id' => $r->id, 'nombre' => $r->razon_social ?: $r->nombre, 'rfc' => $r->rfc, 'completo' => $r->fiscales_completos])->values(),
        'editar' => route('admin.clientes.edit', $c),
    ])->values();
    $ini = [
        'clientes' => $cl, 'cliente' => old('cliente_id', $cliente?->id), 'razon' => old('razon_id'),
        'buscar' => route('admin.productos.buscar'), 'conIva' => (bool) old('con_iva', $conIva), 'iva' => (float) config('vandu.iva_porcentaje', 16),
        'metodo' => old('metodo_pago', 'PUE'), 'uso' => old('uso_cfdi'),
        'claveProd' => $aj['clave_prod'], 'claveUnidad' => $aj['clave_unidad'],
        'conceptos' => old('conceptos') ? array_values(old('conceptos')) : [],
    ];
@endphp

@push('head')
<style>
    .nf { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 20px; align-items: start; }
    @media (max-width: 1099.98px) { .nf { grid-template-columns: minmax(0, 1fr); } }
    .nf-cli { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 6px 16px; background: var(--sunken); border-radius: 12px; padding: 12px 14px; font-size: 13.5px; }
    .nf-cli .k { display: block; color: var(--muted); font-size: 12px; }
    .nf-con { display: grid; grid-template-columns: minmax(0, 1fr) 96px 70px 84px 104px 96px 36px; gap: 8px; align-items: start; padding: 12px 16px; }
    .nf-con + .nf-con { border-top: 1px solid var(--line); }
    .nf-con .imp { font-weight: 700; text-align: right; padding-top: 7px; }
    .nf-cab { padding: 8px 16px; font-size: 12px; color: var(--muted); border-bottom: 1px solid var(--line); }
    .nf-sug { position: absolute; z-index: 20; left: 0; right: 0; top: 100%; background: var(--surface, #fff); border: 1px solid var(--line); border-radius: 10px; box-shadow: 0 8px 24px rgba(16,24,40,.12); max-height: 260px; overflow: auto; }
    .nf-sug button { display: block; width: 100%; text-align: left; border: 0; background: none; padding: 8px 12px; font-size: 13.5px; }
    .nf-sug button:hover { background: #F4F5F7; }
    .nf-tot > div { display: flex; justify-content: space-between; font-size: 14px; } .nf-tot .t { font-size: 22px; font-weight: 700; }
    .nf-seg { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
    .nf-seg button { padding: 9px 6px; border-radius: 10px; border: 1px solid var(--line); background: var(--surface, #fff); font-size: 13.5px; color: inherit; }
    .nf-seg button.on { border-color: var(--marca); background: var(--marca-soft); color: var(--marca); font-weight: 600; }
    @media (max-width: 767.98px) { .nf-con { grid-template-columns: 1fr 1fr 1fr; } .nf-con > :first-child { grid-column: 1 / -1; } .nf-cab { display: none; } }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.facturas') }}">Facturas</a> <i class="bi bi-chevron-right small"></i> <span>Nueva factura</span></div>
<div class="page-head"><div><h1>Nueva factura</h1><p class="sub">Elige al cliente, agrega lo que le vendiste y timbra. Se guarda con sus pagos para llevar los complementos.</p></div></div>

@if(! $timbrar)
    <div class="aviso aviso-error mb-3"><i class="bi bi-exclamation-circle-fill"></i> Para timbrar desde Nerby, primero activa la facturación en <a href="{{ route('admin.facturacion') }}">Configuración › Facturación</a>.</div>
@endif
@if($timbres && $timbres['disponibles'] <= \App\Support\Facturas\Timbres::AVISO)
    <div class="aviso mb-3" style="background: var(--amber-soft); color: #7A4A00"><i class="bi bi-exclamation-triangle"></i> Te quedan {{ $timbres['disponibles'] }} timbres. <a href="{{ route('admin.facturacion') }}#timbres">Comprar más</a></div>
@endif
@if($errors->any())<div class="aviso aviso-error mb-3" role="alert"><i class="bi bi-exclamation-circle-fill"></i> {{ $errors->first() }}</div>@endif

<form method="post" action="{{ route('admin.facturas.guardar') }}" class="nf" x-data="nuevaFactura({{ Js::from($ini) }})" @submit="enviando = true">
    @csrf
    <div class="d-grid gap-4">
        <section class="panel panel-body d-grid gap-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label" for="nf-cliente">Cliente</label>
                    <select id="nf-cliente" name="cliente_id" class="form-select" x-model="cliente" @change="razon = ''; if (sel() && sel().uso) uso = sel().uso" required>
                        <option value="">Elige a quién le facturas…</option>
                        <template x-for="c in cfg.clientes" :key="c.id"><option :value="c.id" x-text="c.nombre + (c.rfc ? ' · ' + c.rfc : ' · sin RFC')" :selected="c.id == cliente"></option></template>
                    </select>
                </div>
                <div class="col-md-4"><a href="{{ route('admin.clientes.create') }}" class="btn btn-borde w-100"><i class="bi bi-person-plus me-1"></i> Cliente nuevo</a></div>
            </div>
            <template x-if="sel()">
                <div class="d-grid gap-2">
                    <template x-if="sel().razones.length">
                        <div><label class="form-label small" for="nf-razon">Razón social</label>
                            <select id="nf-razon" name="razon_id" class="form-select form-select-sm" x-model="razon">
                                <option value="" x-text="(sel().razon || sel().nombre) + ' (principal)'"></option>
                                <template x-for="r in sel().razones" :key="r.id"><option :value="r.id" x-text="r.nombre + (r.rfc ? ' · ' + r.rfc : '')"></option></template>
                            </select></div>
                    </template>
                    <div class="nf-cli" x-show="! razon">
                        <div><span class="k">Razón social</span><span x-text="sel().razon || '—'"></span></div>
                        <div><span class="k">RFC</span><span class="num" x-text="sel().rfc || '—'"></span></div>
                        <div><span class="k">Régimen · C.P.</span><span x-text="(sel().regimen || '—') + ' · ' + (sel().cp || '—')"></span></div>
                        <div><span class="k">Correo</span><span x-text="sel().correo || 'sin correo'"></span></div>
                    </div>
                    <div class="small" style="color: var(--red)" x-show="! completo()">Le faltan datos fiscales. <a :href="sel().editar">Complétalos en su ficha</a> (puedes subir su constancia).</div>
                </div>
            </template>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Conceptos</h2><button type="button" class="btn btn-borde btn-sm" @click="agregar()"><i class="bi bi-plus-lg me-1"></i> Agregar concepto</button></div>
            <div class="nf-con nf-cab"><span>Descripción</span><span>Clave SAT</span><span>Unidad</span><span>Cantidad</span><span>Precio sin IVA</span><span class="text-end">Importe</span><span></span></div>
            <template x-for="(l, i) in lineas" :key="l.k">
                <div class="nf-con">
                    <div style="position: relative">
                        <input class="form-control form-control-sm" x-model="l.descripcion" :name="`conceptos[${i}][descripcion]`" maxlength="1000" required placeholder="Busca en tu catálogo o escribe el concepto"
                               @input="l.producto_id = null" @input.debounce.250ms="buscar(l)" @focus="buscar(l)" @keydown.escape="l.sug = []" @blur="setTimeout(() => l.sug = [], 200)" aria-label="Descripción">
                        <div class="nf-sug" x-show="l.sug.length" x-cloak>
                            <template x-for="p in l.sug" :key="p.id"><button type="button" @mousedown.prevent="elegir(l, p)"><b x-text="p.nombre"></b> <span class="secundario" x-text="'· ' + dinero(p.precio) + (p.clave_sat ? ' · ' + p.clave_sat : '')"></span></button></template>
                        </div>
                        <input type="hidden" :name="`conceptos[${i}][producto_id]`" :value="l.producto_id || ''">
                        <label class="form-check small m-0 mt-1" x-show="! l.producto_id"><input type="checkbox" :name="`conceptos[${i}][guardar]`" value="1" class="form-check-input" x-model="l.guardar"> <span class="form-check-label">Guardar en mi catálogo</span></label>
                        <div class="small secundario mt-1" x-show="l.producto_id"><i class="bi bi-box-seam"></i> De tu catálogo</div>
                    </div>
                    <input class="form-control form-control-sm num" x-model="l.clave_sat" :name="`conceptos[${i}][clave_sat]`" maxlength="8" inputmode="numeric" :placeholder="cfg.claveProd" aria-label="Clave SAT">
                    <input class="form-control form-control-sm text-uppercase" x-model="l.unidad_sat" :name="`conceptos[${i}][unidad_sat]`" maxlength="3" :placeholder="cfg.claveUnidad" aria-label="Unidad SAT">
                    <input type="number" step="any" min="0.0001" class="form-control form-control-sm num" x-model.number="l.cantidad" :name="`conceptos[${i}][cantidad]`" required aria-label="Cantidad">
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm num" x-model.number="l.precio" :name="`conceptos[${i}][precio]`" required aria-label="Precio">
                    <div class="imp num" x-text="dinero(l.cantidad * l.precio)"></div>
                    <button type="button" class="btn btn-fantasma btn-sm text-danger" @click="lineas.splice(i, 1)" :disabled="lineas.length === 1" aria-label="Quitar concepto"><i class="bi bi-x-lg"></i></button>
                </div>
            </template>
            <div class="panel-body border-top small secundario">Si no pones clave SAT ni unidad se usan las de Configuración ({{ $aj['clave_prod'] }} · {{ $aj['clave_unidad'] }}). Lo que escribas aquí se guarda en el producto para la próxima.</div>
        </section>

        <section class="panel panel-body">
            <label class="form-label" for="nf-notas">Notas internas <span class="secundario fw-normal">(no salen en la factura)</span></label>
            <input id="nf-notas" name="notas" maxlength="1000" class="form-control" value="{{ old('notas') }}">
        </section>
    </div>

    <aside class="panel panel-body d-grid gap-3" style="position: sticky; top: 16px">
        @if($series->count() > 1)
            <div><label class="form-label small" for="nf-serie">Serie</label>
                <select id="nf-serie" name="serie_id" class="form-select">@foreach($series as $s)<option value="{{ $s->id }}" @selected(old('serie_id') == $s->id)>{{ $s->serie }} · {{ $s->descripcion }} (sigue {{ $s->siguiente }})</option>@endforeach</select></div>
        @elseif($series->count() === 1)
            <input type="hidden" name="serie_id" value="{{ $series->first()->id }}"><div class="small secundario">Serie <b>{{ $series->first()->serie }}</b> · folio {{ $series->first()->siguiente }}</div>
        @endif
        <div>
            <label class="form-label small">Pago</label>
            <div class="nf-seg"><button type="button" :class="metodo === 'PUE' && 'on'" @click="metodo = 'PUE'">Ya pagó (PUE)</button><button type="button" :class="metodo === 'PPD' && 'on'" @click="metodo = 'PPD'">Paga después (PPD)</button></div>
            <input type="hidden" name="metodo_pago" :value="metodo">
            <div class="small secundario mt-1" x-show="metodo === 'PPD'">Cuando te pague, registra el pago y Nerby timbra su complemento.</div>
        </div>
        <div x-show="metodo === 'PUE'"><label class="form-label small" for="nf-forma">Cómo pagó</label>
            <select id="nf-forma" name="forma_pago" class="form-select" :disabled="metodo !== 'PUE'">@foreach(\App\Support\Facturas\Timbrado::FORMAS_SAT as $k => $t)@if($k !== '99')<option value="{{ $k }}" @selected(old('forma_pago', '03') === $k)>{{ $k }} · {{ $t }}</option>@endif @endforeach</select></div>
        <div><label class="form-label small" for="nf-uso">Uso del CFDI</label>
            <select id="nf-uso" name="uso_cfdi" class="form-select" x-model="uso">@foreach(config('vandu.sat.usos_cfdi') as $k => $t)<option value="{{ $k }}">{{ $k }} · {{ $t }}</option>@endforeach</select></div>
        <div class="row g-2 align-items-center">
            <div class="col-7"><div class="input-group input-group-sm"><span class="input-group-text">Descuento $</span><input type="number" name="descuento" step="0.01" min="0" class="form-control num" x-model.number="descuento"></div></div>
            <div class="col-5"><label class="form-check small m-0"><input type="checkbox" name="con_iva" value="1" class="form-check-input" x-model="conIva"> <span class="form-check-label">IVA {{ rtrim(rtrim(number_format(config('vandu.iva_porcentaje', 16), 2), '0'), '.') }}%</span></label></div>
        </div>
        <div class="nf-tot d-grid gap-1">
            <div><span class="secundario">Subtotal</span><span class="num" x-text="dinero(subtotal())"></span></div>
            <div x-show="descuento > 0"><span class="secundario">Descuento</span><span class="num" x-text="'−' + dinero(descuento)"></span></div>
            <div x-show="conIva"><span class="secundario">IVA</span><span class="num" x-text="dinero(iva())"></span></div>
            <div class="t mt-1"><span>Total</span><span class="num" x-text="dinero(total())"></span></div>
        </div>
        <label class="form-check small m-0"><input type="checkbox" name="enviar" value="1" class="form-check-input" checked> <span class="form-check-label">Enviársela por correo al timbrar</span></label>
        <button class="btn btn-primario btn-lg" :disabled="enviando || ! completo() || lineas.length === 0" @if(! $timbrar) disabled @endif>
            <i class="bi bi-receipt me-1"></i> <span x-text="enviando ? 'Timbrando…' : 'Timbrar factura'"></span></button>
        @if($timbres)<div class="small secundario text-center">Te quedan {{ number_format($timbres['disponibles']) }} timbres</div>@endif
    </aside>
</form>
@endsection

@push('scripts')
<script>
window.nuevaFactura = function (cfg) {
    let k = 1;
    const linea = (x = {}) => ({ k: k++, producto_id: x.producto_id || null, descripcion: x.descripcion || '', clave_sat: x.clave_sat || '', unidad_sat: x.unidad_sat || '',
        cantidad: x.cantidad !== undefined ? +x.cantidad : 1, precio: x.precio !== undefined && x.precio !== '' ? +x.precio : null, guardar: !!x.guardar, sug: [] });
    return {
        cfg, cliente: cfg.cliente ? String(cfg.cliente) : '', razon: cfg.razon ? String(cfg.razon) : '', metodo: cfg.metodo, uso: cfg.uso || 'G03',
        conIva: cfg.conIva, descuento: 0, enviando: false,
        lineas: cfg.conceptos.length ? cfg.conceptos.map(linea) : [linea()],
        init() { const s = this.sel(); if (s && s.uso && ! cfg.uso) this.uso = s.uso; },
        sel() { return this.cfg.clientes.find(c => String(c.id) === String(this.cliente)) || null; },
        completo() { const s = this.sel(); if (! s) return false; if (this.razon) { const r = s.razones.find(x => String(x.id) === String(this.razon)); return r ? r.completo : false; } return s.completo; },
        dinero(n) { return '$' + (Math.round((+n || 0) * 100) / 100).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        agregar() { this.lineas.push(linea()); },
        async buscar(l) {
            if (l.producto_id) return;
            try { const r = await fetch(cfg.buscar + '?q=' + encodeURIComponent(l.descripcion || ''), { headers: { Accept: 'application/json' } }); l.sug = (await r.json()).slice(0, 8); } catch (e) { l.sug = []; }
        },
        elegir(l, p) { Object.assign(l, { producto_id: p.id, descripcion: p.nombre, precio: +p.precio, clave_sat: p.clave_sat || l.clave_sat, unidad_sat: p.unidad_sat || l.unidad_sat, guardar: false, sug: [] }); },
        subtotal() { return this.lineas.reduce((s, l) => s + (+l.cantidad || 0) * (+l.precio || 0), 0); },
        base() { return Math.max(0, this.subtotal() - (+this.descuento || 0)); },
        iva() { return this.conIva ? Math.round(this.base() * cfg.iva) / 100 : 0; },
        total() { return this.base() + this.iva(); },
    };
};
</script>
@endpush
