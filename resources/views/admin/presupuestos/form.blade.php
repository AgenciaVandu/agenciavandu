@extends('admin.layout')
@php
    /** @var \App\Models\Presupuesto $p */
    $p = $presupuesto;
    $nuevo = ! $p->exists;

    $conceptos = old('conceptos', $p->conceptos->map->only(['descripcion', 'cantidad', 'precio'])->values()->all())
        ?: [['descripcion' => '', 'cantidad' => 1, 'precio' => '']];
    $consideraciones = old('consideraciones', $p->consideraciones ?? []);
    $estado = [
        'conceptos'       => array_values($conceptos),
        'consideraciones' => collect($consideraciones)->map(fn ($s) => ['titulo' => $s['titulo'] ?? '', 'items' => array_values($s['items'] ?? []) ?: ['']])->values(),
        'modoIva'         => old('modo_iva', $p->modo_iva),
        'ivaPct'          => (float) old('iva_porcentaje', $p->iva_porcentaje),
        'vigencia'        => old('vigente_hasta', $p->vigencia_local->format('Y-m-d\TH:i')),
        'mostrarPago'     => (bool) old('mostrar_pago', $p->mostrar_pago),
        'clienteId'       => (string) old('cliente_id', $p->cliente_id),
        'clienteNombre'   => old('cliente_nombre', $p->cliente_nombre),
        'clienteEmpresa'  => old('cliente_empresa', $p->cliente_empresa),
        'clientes'        => $clientes->keyBy('id'),
    ];
    $wa = $p->cliente?->whatsapp;
@endphp
@section('titulo', $nuevo ? 'Nueva cotización' : $p->folio)

@section('contenido')
<form method="post" action="{{ $nuevo ? route('admin.presupuestos.store') : route('admin.presupuestos.update', $p) }}"
      x-data="editor({{ Js::from($estado) }})" @keydown.ctrl.s.prevent="$el.requestSubmit()" @keydown.meta.s.prevent="$el.requestSubmit()">
    @csrf
    @unless($nuevo) @method('put') @endunless

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <a href="{{ route('admin.presupuestos.index') }}" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left"></i> Cotizaciones</a>
            <h1 class="h3 mb-0">{{ $nuevo ? 'Nueva cotización' : $p->folio }}</h1>
        </div>
        <div class="d-flex gap-2">
            @unless($nuevo)
                <a href="{{ route('admin.presupuestos.pdf', $p) }}" target="_blank" class="btn btn-outline-dark"><i class="bi bi-file-earmark-pdf"></i> Ver PDF</a>
            @endunless
            <button class="btn btn-verde px-4">{{ $nuevo ? 'Crear cotización' : 'Guardar cambios' }}</button>
        </div>
    </div>

    <div class="row g-4">
        {{-- ================= Documento ================= --}}
        <div class="col-lg-8">

            <div class="card mb-4">
                <div class="card-header">Cliente</div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <label class="form-label" for="cliente_id">Cliente</label>
                        <div class="input-group">
                            <select name="cliente_id" id="cliente_id" class="form-select" x-model="clienteId" @change="elegirCliente()" required>
                                <option value="">Elige un cliente…</option>
                                @foreach($clientes as $c)
                                    <option value="{{ $c->id }}">{{ $c->nombre }}{{ $c->empresa ? ' — ' . $c->empresa : '' }}</option>
                                @endforeach
                            </select>
                            <a href="{{ route('admin.clientes.create') }}" class="btn btn-outline-dark">Nuevo cliente</a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="cliente_nombre">Nombre que aparece en la cotización</label>
                        <input name="cliente_nombre" id="cliente_nombre" class="form-control" x-model="clienteNombre" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="cliente_empresa">Empresa</label>
                        <input name="cliente_empresa" id="cliente_empresa" class="form-control" x-model="clienteEmpresa">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="fecha">Fecha</label>
                        <input type="date" name="fecha" id="fecha" class="form-control" value="{{ old('fecha', $p->fecha->format('Y-m-d')) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="titulo">Título</label>
                        <input name="titulo" id="titulo" class="form-control" value="{{ old('titulo', $p->titulo) }}" required>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">Conceptos</div>
                <div class="card-body">
                    <template x-for="(c, i) in conceptos" :key="i">
                        <div class="row g-2 align-items-start pb-3 mb-3 border-bottom">
                            <div class="col-12 col-md-7">
                                <label class="form-label" :for="'desc'+i">Concepto</label>
                                <textarea class="form-control" rows="3" :id="'desc'+i" :name="`conceptos[${i}][descripcion]`" x-model="c.descripcion" required></textarea>
                            </div>
                            <div class="col-4 col-md-2">
                                <label class="form-label" :for="'cant'+i">Cantidad</label>
                                <input type="number" step="0.01" min="0" class="form-control num" :id="'cant'+i" :name="`conceptos[${i}][cantidad]`" x-model.number="c.cantidad" required>
                            </div>
                            <div class="col-8 col-md-3">
                                <label class="form-label" :for="'precio'+i">Precio unitario</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0" class="form-control num" :id="'precio'+i" :name="`conceptos[${i}][precio]`" x-model.number="c.precio" required>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <span class="small text-muted num text-nowrap" x-text="'Costo ' + dinero(importe(c))"></span>
                                    <span class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-light" @click="mover(conceptos, i, -1)" :disabled="i === 0" aria-label="Subir"><i class="bi bi-arrow-up"></i></button>
                                        <button type="button" class="btn btn-light" @click="mover(conceptos, i, 1)" :disabled="i === conceptos.length - 1" aria-label="Bajar"><i class="bi bi-arrow-down"></i></button>
                                        <button type="button" class="btn btn-light text-danger" @click="conceptos.splice(i, 1)" :disabled="conceptos.length === 1" aria-label="Quitar concepto"><i class="bi bi-trash"></i></button>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </template>
                    <button type="button" class="btn btn-outline-dark btn-sm" @click="conceptos.push({descripcion: '', cantidad: 1, precio: ''})"><i class="bi bi-plus-lg"></i> Agregar concepto</button>

                    <div class="row g-3 mt-2">
                        <div class="col-md-8">
                            <label class="form-label" for="modo_iva">Cómo mostrar el IVA</label>
                            <select name="modo_iva" id="modo_iva" class="form-select" x-model="modoIva">
                                @foreach(\App\Models\Presupuesto::MODOS_IVA as $k => $label)
                                    <option value="{{ $k }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="iva_porcentaje">IVA %</label>
                            <input type="number" step="0.01" min="0" max="100" name="iva_porcentaje" id="iva_porcentaje" class="form-control num" x-model.number="ivaPct">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    Consideraciones
                    <span class="small text-muted fw-normal">Se numeran solas en el PDF</span>
                </div>
                <div class="card-body">
                    <template x-for="(s, i) in consideraciones" :key="i">
                        <div class="border rounded p-3 mb-3 bg-light">
                            <div class="d-flex gap-2 align-items-center mb-2">
                                <span class="fw-semibold num" x-text="(i + 1) + '.'"></span>
                                <input class="form-control fw-semibold" :name="`consideraciones[${i}][titulo]`" x-model="s.titulo" placeholder="Título, p. ej. Tiempos de entrega" aria-label="Título de la sección">
                                <span class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-light" @click="mover(consideraciones, i, -1)" :disabled="i === 0" aria-label="Subir"><i class="bi bi-arrow-up"></i></button>
                                    <button type="button" class="btn btn-light" @click="mover(consideraciones, i, 1)" :disabled="i === consideraciones.length - 1" aria-label="Bajar"><i class="bi bi-arrow-down"></i></button>
                                    <button type="button" class="btn btn-light text-danger" @click="consideraciones.splice(i, 1)" aria-label="Quitar sección"><i class="bi bi-trash"></i></button>
                                </span>
                            </div>
                            <template x-for="(item, j) in s.items" :key="j">
                                <div class="d-flex gap-2 align-items-center mb-2 ps-4">
                                    <span aria-hidden="true">•</span>
                                    <input class="form-control form-control-sm" :name="`consideraciones[${i}][items][${j}]`" x-model="s.items[j]" placeholder="Viñeta" aria-label="Viñeta">
                                    <button type="button" class="btn btn-sm btn-light text-danger" @click="s.items.splice(j, 1)" aria-label="Quitar viñeta"><i class="bi bi-x-lg"></i></button>
                                </div>
                            </template>
                            <button type="button" class="btn btn-link btn-sm ps-4 text-dark" @click="s.items.push('')"><i class="bi bi-plus"></i> Viñeta</button>
                        </div>
                    </template>
                    <button type="button" class="btn btn-outline-dark btn-sm" @click="consideraciones.push({titulo: '', items: ['']})"><i class="bi bi-plus-lg"></i> Agregar sección</button>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Confirmación y datos de pago <span class="small text-muted fw-normal" x-show="mostrarPago" x-text="'(sección ' + (consideraciones.length + 1) + ')'"></span></span>
                    <div class="form-check form-switch mb-0">
                        <input type="hidden" name="mostrar_pago" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="mostrar_pago" name="mostrar_pago" value="1" x-model="mostrarPago">
                        <label class="form-check-label small fw-normal" for="mostrar_pago">Mostrar</label>
                    </div>
                </div>
                <div class="card-body row g-3" x-show="mostrarPago">
                    <div class="col-12">
                        <label class="form-label" for="pago_intro">Texto antes de los datos bancarios</label>
                        <input name="pago_intro" id="pago_intro" class="form-control" value="{{ old('pago_intro', $p->pago_intro) }}">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="clabe">CLABE</label>
                        <input name="clabe" id="clabe" class="form-control num" value="{{ old('clabe', $p->clabe) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="banco">Banco</label>
                        <input name="banco" id="banco" class="form-control" value="{{ old('banco', $p->banco) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="beneficiario">Beneficiario</label>
                        <input name="beneficiario" id="beneficiario" class="form-control" value="{{ old('beneficiario', $p->beneficiario) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="nota_comprobante">Nota sobre el comprobante (va en negritas)</label>
                        <textarea name="nota_comprobante" id="nota_comprobante" rows="2" class="form-control">{{ old('nota_comprobante', $p->nota_comprobante) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="nota_factura">Nota de facturación</label>
                        <textarea name="nota_factura" id="nota_factura" rows="2" class="form-control">{{ old('nota_factura', $p->nota_factura) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">Encabezado (tus datos)</div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="emisor_nombre">Nombre</label>
                        <input name="emisor_nombre" id="emisor_nombre" class="form-control" value="{{ old('emisor_nombre', $p->emisor_nombre) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="emisor_telefono">Teléfono</label>
                        <input name="emisor_telefono" id="emisor_telefono" class="form-control" value="{{ old('emisor_telefono', $p->emisor_telefono) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="emisor_sitio">Sitio web</label>
                        <input name="emisor_sitio" id="emisor_sitio" class="form-control" value="{{ old('emisor_sitio', $p->emisor_sitio) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="emisor_email">Correo</label>
                        <input name="emisor_email" id="emisor_email" class="form-control" value="{{ old('emisor_email', $p->emisor_email) }}">
                    </div>
                </div>
            </div>
        </div>

        {{-- ================= Panel lateral ================= --}}
        <div class="col-lg-4">
            <div class="position-sticky" style="top: 1rem">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between num"><span class="text-muted">Subtotal</span><span x-text="dinero(subtotal)"></span></div>
                        <div class="d-flex justify-content-between num" x-show="modoIva !== 'sin_iva'"><span class="text-muted" x-text="'IVA ' + ivaPct + '%'"></span><span x-text="dinero(iva)"></span></div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between fs-5 fw-semibold num"><span>Total</span><span x-text="dinero(total)"></span></div>
                        <p class="small text-muted mb-0 mt-1" x-show="modoIva === 'mas_iva'">El cliente ve los precios como “+ IVA”, igual que tu formato.</p>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">Vigencia</div>
                    <div class="card-body">
                        <label class="form-label" for="vigente_hasta">El enlace funciona hasta</label>
                        <input type="datetime-local" name="vigente_hasta" id="vigente_hasta" class="form-control num" x-model="vigencia" required>
                        <div class="d-flex flex-wrap gap-1 mt-2">
                            @foreach([3, 7, 15, 30] as $d)
                                <button type="button" class="btn btn-sm btn-light" @click="sumarDias({{ $d }})">{{ $d }} días</button>
                            @endforeach
                        </div>
                        <p class="small mt-2 mb-0" :class="restante.clase" x-text="restante.texto"></p>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <label class="form-label" for="estado">Estado</label>
                        <select name="estado" id="estado" class="form-select mb-3">
                            @foreach(\App\Models\Presupuesto::ESTADOS as $k => $label)
                                <option value="{{ $k }}" @selected(old('estado', $p->estado) === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <label class="form-label" for="notas_internas">Notas internas (el cliente no las ve)</label>
                        <textarea name="notas_internas" id="notas_internas" rows="3" class="form-control">{{ old('notas_internas', $p->notas_internas) }}</textarea>
                    </div>
                </div>

                @unless($nuevo)
                    <div class="card mb-3">
                        <div class="card-header">Enlace para el cliente</div>
                        <div class="card-body">
                            <input class="form-control form-control-sm mb-2" value="{{ $p->url_publica }}" readonly aria-label="Enlace del cliente" onclick="this.select()">
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-v" data-copiar="{{ $p->url_publica }}"><i class="bi bi-link-45deg"></i> Copiar</button>
                                <a class="btn btn-sm btn-outline-dark" target="_blank" href="{{ $p->url_publica }}?vista_previa=1"><i class="bi bi-eye"></i> Ver como cliente</a>
                                @if($wa)
                                    <a class="btn btn-sm btn-outline-success" target="_blank"
                                       href="https://wa.me/{{ $wa }}?text={{ rawurlencode("Hola {$p->cliente_nombre}, te comparto la cotización {$p->folio}: {$p->url_publica}") }}"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                                @endif
                            </div>
                            <p class="small text-muted mt-2 mb-0">
                                {{ $p->vistas ? "Abierta {$p->vistas} " . ($p->vistas === 1 ? 'vez' : 'veces') . ', la última ' . $p->ultima_vista_at->locale('es')->diffForHumans() . '.' : 'El cliente aún no la abre.' }}
                            </p>
                        </div>
                    </div>
                @endunless

                <button class="btn btn-verde w-100 py-2">{{ $nuevo ? 'Crear cotización' : 'Guardar cambios' }}</button>
                <p class="small text-muted text-center mt-2">Atajo: Ctrl/⌘ + S</p>
            </div>
        </div>
    </div>
</form>

@unless($nuevo)
    <div class="d-flex gap-2 mt-2">
        <form method="post" action="{{ route('admin.presupuestos.duplicar', $p) }}">@csrf
            <button class="btn btn-sm btn-outline-dark"><i class="bi bi-copy"></i> Duplicar</button>
        </form>
        <form method="post" action="{{ route('admin.presupuestos.destroy', $p) }}" onsubmit="return confirm('¿Eliminar {{ $p->folio }}? El enlace del cliente dejará de funcionar.')">@csrf @method('delete')
            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Eliminar</button>
        </form>
    </div>
@endunless
@endsection

@push('scripts')
<script>
function editor(init) {
    return {
        ...init,
        ahora: Date.now(),
        init() { setInterval(() => this.ahora = Date.now(), 30000); },
        importe(c) { return Math.round((+c.cantidad || 0) * (+c.precio || 0) * 100) / 100; },
        get subtotal() { return this.conceptos.reduce((s, c) => s + this.importe(c), 0); },
        get iva() { return this.modoIva === 'sin_iva' ? 0 : Math.round(this.subtotal * (+this.ivaPct || 0)) / 100; },
        get total() { return this.subtotal + this.iva; },
        dinero(n) { return '$' + (+n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        mover(lista, i, d) { const [x] = lista.splice(i, 1); lista.splice(i + d, 0, x); },
        elegirCliente() {
            const c = this.clientes[this.clienteId];
            if (c) { this.clienteNombre = c.nombre; this.clienteEmpresa = c.empresa || ''; }
        },
        sumarDias(d) {
            const f = new Date(); f.setDate(f.getDate() + d); f.setHours(23, 59, 0, 0);
            const p = (n) => String(n).padStart(2, '0');
            this.vigencia = `${f.getFullYear()}-${p(f.getMonth() + 1)}-${p(f.getDate())}T23:59`;
        },
        get restante() {
            const ms = new Date(this.vigencia).getTime() - this.ahora;
            if (isNaN(ms)) return { texto: '', clase: '' };
            if (ms <= 0) return { texto: 'Vencida: el cliente verá un aviso y no podrá descargarla.', clase: 'vig-vencida' };
            const h = Math.floor(ms / 36e5), d = Math.floor(h / 24);
            return { texto: 'Faltan ' + (d ? d + ' d ' : '') + (h % 24) + ' h', clase: h <= 48 ? 'vig-pronto' : 'vig-ok' };
        },
    };
}
</script>
@endpush
