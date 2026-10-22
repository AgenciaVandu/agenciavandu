@extends('admin.layout')
@section('titulo', $g->exists ? 'Editar gasto' : 'Nuevo gasto')

@php
    $ini = [
        'f' => [
            'fecha' => old('fecha', $g->fecha?->toDateString()), 'concepto' => old('concepto', $g->concepto), 'categoria' => old('categoria', $g->categoria ?: 'otros'),
            'proveedor' => old('proveedor', $g->proveedor), 'rfc' => old('rfc', $g->rfc), 'total' => old('total', $g->exists ? $g->total : null),
            'iva' => old('iva', $g->exists && $g->iva > 0 ? $g->iva : null), 'forma_pago' => old('forma_pago', $g->forma_pago),
        ],
        'ia' => $ia, 'quedan' => $quedan, 'token' => csrf_token(),
        'urlLeer' => route('admin.gastos.leer'), 'urlXml' => route('admin.gastos.xml'),
        'tieneFoto' => (bool) $g->foto, 'tieneXml' => (bool) $g->xml,
    ];
@endphp

@push('head')
<style>
    .gx-modos { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
    .gx-modo { position: relative; display: grid; gap: 4px; justify-items: center; text-align: center; padding: 18px 12px; border-radius: 14px; border: 1px solid var(--line); background: var(--surface, #fff); cursor: pointer; color: inherit; }
    .gx-modo:hover { border-color: var(--marca); }
    .gx-modo.on { border-color: var(--marca); box-shadow: 0 0 0 3px var(--marca-soft); }
    .gx-modo i { font-size: 26px; color: var(--marca); }
    .gx-modo b { font-size: 14.5px; } .gx-modo span { font-size: 12.5px; color: var(--muted); line-height: 1.3; }
    .gx-modo .ia { position: absolute; top: 8px; right: 8px; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; padding: 2px 7px; border-radius: 99px; background: var(--marca-soft); color: var(--marca); }
    .gx-modo input[type=file] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    .gx-estado { display: flex; gap: 12px; align-items: center; padding: 12px 14px; border-radius: 12px; background: var(--sunken); font-size: 14px; }
    .gx-estado.ok { background: #E3FBEF; color: #065F46; } .gx-estado.mal { background: #FFF3DD; color: #8A4B00; }
    .gx-estado img { width: 56px; height: 56px; object-fit: cover; border-radius: 8px; flex: none; }
    .gx-giro { width: 18px; height: 18px; border: 2px solid var(--marca-soft); border-top-color: var(--marca); border-radius: 50%; animation: gxg 0.8s linear infinite; flex: none; }
    @keyframes gxg { to { transform: rotate(360deg); } }
    .duda .form-control, .duda .form-select { border-color: #F59E0B; background: #FFFBEB; }
    .duda-txt { font-size: 12px; color: #92400E; margin-top: 3px; }
    @media (max-width: 575.98px) { .gx-modos { grid-template-columns: 1fr; } .gx-modo { grid-template-columns: auto 1fr; justify-items: start; text-align: left; column-gap: 12px; padding: 14px; } .gx-modo i { grid-row: span 2; } }
    @media (prefers-reduced-motion: reduce) { .gx-giro { animation: none; } }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <div class="migas"><a href="{{ route('admin.gastos') }}">Gastos</a> <i class="bi bi-chevron-right small"></i> <span>{{ $g->exists ? 'Editar' : 'Nuevo' }}</span></div>
        <h1>{{ $g->exists ? 'Editar gasto' : 'Nuevo gasto' }}</h1>
        <p class="sub">Lo que pagaste fuera de una orden de compra: gasolina, comidas, papelería, servicios… Se suma en Finanzas.</p>
    </div>
</div>

<form method="post" action="{{ $g->exists ? route('admin.gastos.update', $g) : route('admin.gastos.store') }}" enctype="multipart/form-data"
      class="d-grid gap-3" style="max-width: 820px" x-data="formGasto({{ Js::from($ini) }})">
    @csrf
    @if($g->exists) @method('put') @endif
    <input type="hidden" name="lectura" :value="lectura ? JSON.stringify(lectura) : ''">

    <section class="panel panel-body">
        <h2 class="fs-6 fw-semibold mb-3">{{ $g->exists ? 'Comprobante' : '¿Cómo lo registras?' }}</h2>
        <div class="gx-modos">
            <label class="gx-modo" :class="modo === 'foto' && 'on'">
                @if($ia)<span class="ia">IA</span>@endif
                <i class="bi bi-camera"></i>
                <b>{{ $g->foto ? 'Cambiar foto' : 'Foto del ticket' }}</b>
                <span>{{ $ia ? 'Lo leemos y llenamos los datos por ti' : 'Se guarda como comprobante' }}</span>
                <input type="file" name="foto" accept="image/*" capture="environment" @change="foto($event)" aria-label="Tomar foto del ticket">
            </label>
            <label class="gx-modo" :class="modo === 'xml' && 'on'">
                <i class="bi bi-filetype-xml"></i>
                <b>{{ $g->xml ? 'Cambiar factura' : 'Factura (XML)' }}</b>
                <span>Datos exactos del SAT, sin escribir nada</span>
                <input type="file" name="xml" accept=".xml,text/xml,application/xml" @change="xml($event)" aria-label="Subir el XML de la factura">
            </label>
            <button type="button" class="gx-modo" :class="modo === 'mano' && 'on'" @click="modo = 'mano'; $nextTick(() => $refs.total.focus())">
                <i class="bi bi-pencil-square"></i>
                <b>A mano</b>
                <span>Sin comprobante, o para escribirlo tú</span>
            </button>
        </div>

        <div class="gx-estado mt-3" x-show="estado" x-cloak :class="{ ok: estado === 'ok', mal: estado === 'mal' }" role="status" aria-live="polite">
            <template x-if="vista"><img :src="vista" alt="Foto del ticket"></template>
            <div class="gx-giro" x-show="estado === 'leyendo'"></div>
            <div x-text="mensaje"></div>
        </div>
        @if($ia && ! $g->exists)
            <div class="secundario small mt-2">Quedan {{ $quedan }} lecturas de tickets este mes. Revisa siempre los datos antes de guardar.</div>
        @endif
        @if($g->foto || $g->xml)
            <div class="d-flex flex-wrap gap-2 mt-3">
                @if($g->foto)<a href="{{ route('admin.gastos.archivo', [$g, 'foto']) }}" target="_blank" class="btn btn-borde btn-sm"><i class="bi bi-image me-1"></i> Ver la foto</a>@endif
                @if($g->xml)<a href="{{ route('admin.gastos.archivo', [$g, 'xml']) }}" class="btn btn-borde btn-sm" data-recargar><i class="bi bi-download me-1"></i> XML de la factura</a>@endif
            </div>
        @endif
        @error('foto')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
        @error('xml')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
    </section>

    <section class="panel panel-body">
        <div class="row g-3">
            <div class="col-sm-6" :class="duda('total')">
                <label class="form-label" for="g-total">Total pagado</label>
                <div class="input-group"><span class="input-group-text">$</span>
                    <input id="g-total" x-ref="total" name="total" type="number" step="0.01" min="0.01" inputmode="decimal" class="form-control num @error('total') is-invalid @enderror" x-model="f.total" required></div>
                <div class="duda-txt" x-show="duda('total')" x-cloak>Revísalo: no se leyó con seguridad.</div>
                @error('total')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6" :class="duda('fecha')">
                <label class="form-label" for="g-fecha">Fecha</label>
                <input id="g-fecha" name="fecha" type="date" class="form-control num @error('fecha') is-invalid @enderror" x-model="f.fecha" required>
                <div class="duda-txt" x-show="duda('fecha')" x-cloak>Revísala: no se leyó con seguridad.</div>
                @error('fecha')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-12" :class="duda('concepto')">
                <label class="form-label" for="g-concepto">Concepto</label>
                <input id="g-concepto" name="concepto" class="form-control @error('concepto') is-invalid @enderror" x-model="f.concepto" required maxlength="160" placeholder="Gasolina, comida con cliente, papelería…">
                @error('concepto')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="g-cat">Categoría</label>
                <select id="g-cat" name="categoria" class="form-select" x-model="f.categoria" required>
                    @foreach(\App\Models\Gasto::CATEGORIAS as $k => $c)<option value="{{ $k }}">{{ $c['texto'] }}</option>@endforeach
                </select>
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="g-forma">Forma de pago</label>
                <select id="g-forma" name="forma_pago" class="form-select" x-model="f.forma_pago">
                    <option value="">Sin especificar</option>
                    @foreach(\App\Models\Gasto::FORMAS as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach
                </select>
            </div>
            <div class="col-sm-6" :class="duda('proveedor')">
                <label class="form-label" for="g-prov">Proveedor <span class="secundario fw-normal">(opcional)</span></label>
                <input id="g-prov" name="proveedor" class="form-control" x-model="f.proveedor" maxlength="160" placeholder="OXXO, Pemex, Home Depot…">
            </div>
            <div class="col-sm-3" :class="duda('iva')">
                <label class="form-label" for="g-iva">IVA <span class="secundario fw-normal">(si viene)</span></label>
                <input id="g-iva" name="iva" type="number" step="0.01" min="0" inputmode="decimal" class="form-control num @error('iva') is-invalid @enderror" x-model="f.iva">
                @error('iva')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-3" :class="duda('rfc')">
                <label class="form-label" for="g-rfc">RFC <span class="secundario fw-normal">(opcional)</span></label>
                <input id="g-rfc" name="rfc" class="form-control num text-uppercase @error('rfc') is-invalid @enderror" x-model="f.rfc" maxlength="13" autocapitalize="characters" spellcheck="false">
                @error('rfc')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 secundario small" x-show="Number(f.total) > 0" x-cloak>
                Subtotal <b class="num" x-text="dinero(Number(f.total || 0) - Number(f.iva || 0))"></b> antes de IVA · así se suma en la rentabilidad.
            </div>
            @if($proyectos->isNotEmpty())
                <div class="col-sm-6">
                    <label class="form-label" for="g-proy">Proyecto <span class="secundario fw-normal">(si fue para uno)</span></label>
                    <select id="g-proy" name="proyecto_id" class="form-select">
                        <option value="">Gasto general del negocio</option>
                        @foreach($proyectos as $p)<option value="{{ $p->id }}" @selected((int) old('proyecto_id', $g->proyecto_id) === $p->id)>{{ $p->nombre }}</option>@endforeach
                    </select>
                </div>
            @endif
            @if($sucursales->count() > 1)
                <div class="col-sm-6">
                    <label class="form-label" for="g-suc">Sucursal</label>
                    <select id="g-suc" name="sucursal_id" class="form-select">
                        <option value="">Sin sucursal</option>
                        @foreach($sucursales as $s)<option value="{{ $s->id }}" @selected((int) old('sucursal_id', $g->sucursal_id ?? auth()->user()->sucursal_id) === $s->id)>{{ $s->nombre }}</option>@endforeach
                    </select>
                </div>
            @endif
            <div class="col-12">
                <label class="form-label" for="g-notas">Notas <span class="secundario fw-normal">(opcional)</span></label>
                <textarea id="g-notas" name="notas" rows="2" class="form-control" maxlength="2000">{{ old('notas', $g->notas) }}</textarea>
            </div>
        </div>
    </section>

    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-primario px-4" :disabled="estado === 'leyendo'"><i class="bi bi-check2 me-1"></i> Guardar gasto</button>
        <a href="{{ route('admin.gastos') }}" class="btn btn-borde">Cancelar</a>
        @if($g->exists)
            <button class="btn btn-fantasma text-danger ms-auto" form="borrar-gasto">Eliminar</button>
        @endif
    </div>
</form>
@if($g->exists)
    <form id="borrar-gasto" method="post" action="{{ route('admin.gastos.destroy', $g) }}" onsubmit="return confirm('¿Eliminar este gasto y su comprobante?')">@csrf @method('delete')</form>
@endif
@endsection

@push('scripts')
<script>
window.formGasto = function (cfg) {
    return {
        f: cfg.f, modo: cfg.tieneXml ? 'xml' : (cfg.tieneFoto ? 'foto' : null), estado: null, mensaje: '', vista: null, dudas: [], lectura: null,
        dinero(n) { return '$' + (Math.round(n * 100) / 100).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        duda(k) { return this.dudas.includes(k) ? 'duda' : ''; },
        llenar(d) {
            for (const k of ['fecha', 'concepto', 'categoria', 'proveedor', 'rfc', 'total', 'iva', 'forma_pago']) {
                if (d[k] !== null && d[k] !== undefined && d[k] !== '') this.f[k] = d[k];
            }
        },
        async enviar(url, campo, archivo) {
            const fd = new FormData();
            fd.append(campo, archivo);
            const r = await fetch(url, { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.token } });
            let j = {};
            try { j = await r.json(); } catch (e) {}
            if (r.status === 422 && j.errors) j = { ok: false, mensaje: Object.values(j.errors)[0][0] };
            return j;
        },
        async foto(e) {
            const a = e.target.files[0];
            if (!a) return;
            this.modo = 'foto'; this.dudas = []; this.lectura = null;
            if (this.vista) URL.revokeObjectURL(this.vista);
            this.vista = URL.createObjectURL(a);
            if (!cfg.ia) { this.estado = 'ok'; this.mensaje = 'Foto lista. Captura los datos del ticket.'; return; }
            this.estado = 'leyendo'; this.mensaje = 'Leyendo el ticket…';
            try {
                const j = await this.enviar(cfg.urlLeer, 'foto', a);
                if (j.ok) {
                    this.llenar(j.datos); this.dudas = j.dudas || []; this.lectura = Object.assign({}, j.datos, { dudas: this.dudas });
                    this.estado = this.dudas.length ? 'mal' : 'ok';
                    this.mensaje = this.dudas.length ? 'Listo. Revisa lo marcado en amarillo: no se leyó con seguridad.' : 'Listo. Revisa que todo esté bien y guarda.';
                } else { this.estado = 'mal'; this.mensaje = j.mensaje || 'No pudimos leer el ticket. Captura los datos.'; }
            } catch (err) { this.estado = 'mal'; this.mensaje = 'No pudimos leer el ticket. Captura los datos.'; }
        },
        async xml(e) {
            const a = e.target.files[0];
            if (!a) return;
            this.modo = 'xml'; this.dudas = []; this.lectura = null; this.vista = null;
            this.estado = 'leyendo'; this.mensaje = 'Leyendo la factura…';
            try {
                const j = await this.enviar(cfg.urlXml, 'xml', a);
                if (j.ok) {
                    this.llenar(j.datos);
                    this.estado = j.repetido ? 'mal' : 'ok';
                    this.mensaje = j.repetido ? 'Esta factura ya se registró el ' + j.repetido.fecha + '. Si la guardas otra vez, el sistema no la aceptará.'
                        : 'Factura de ' + (j.datos.proveedor || j.datos.rfc) + (j.datos.receptor ? ' a nombre de ' + j.datos.receptor : '') + '. Revisa la categoría y guarda.';
                } else { this.estado = 'mal'; this.mensaje = j.mensaje || 'Ese archivo no es el XML de una factura.'; e.target.value = ''; }
            } catch (err) { this.estado = 'mal'; this.mensaje = 'No pudimos leer el archivo.'; }
        },
    };
};
</script>
@endpush
