@extends('admin.layout')
@php
    /** @var \App\Models\Presupuesto $p */
    $p = $presupuesto;
    $nuevo = ! $p->exists;

    $vacio = ['titulo' => '', 'descripcion' => '', 'cantidad' => 1, 'precio' => '', 'costo_proveedor' => '', 'gasolina' => '', 'utilidad' => '', 'utilidad_modo' => 'pct'];
    $conceptos = collect(old('conceptos', $p->conceptos->map->only(['titulo', 'descripcion', 'cantidad', 'precio', 'costo_proveedor', 'gasolina', 'utilidad', 'utilidad_modo'])->values()->all()))
        ->map(fn ($c) => array_merge($vacio, array_map(fn ($x) => $x ?? '', $c)))->all() ?: [$vacio];
    $tipos = config('vandu.proyectos');
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
        'estadoSel'       => old('estado', $p->estado),
        'tipo'            => old('tipo', $p->tipo ?? \App\Models\Proyecto::tipoSugerido($p)),
        'tiposCosteo'     => collect($tipos)->filter(fn ($m) => ! empty($m['costeo']))->keys()->values(),
        'vacio'           => $vacio,
        'observaciones'   => (string) old('observaciones', $p->observaciones),
        'obsBase'         => collect($tipos)->map(fn ($m) => $m['observaciones'] ?? '')->filter(),
    ];
    $wa = $p->cliente?->whatsapp;
@endphp
@section('titulo', $nuevo ? 'Nueva cotización' : $p->folio)

@push('head')
<style>
    .editor { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 24px; align-items: start; }
    .lateral { position: sticky; top: 24px; display: grid; gap: 16px; }
    .titulo-doc { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }

    /* Conceptos como tabla editable */
    .conceptos-head, .concepto { display: grid; grid-template-columns: 22px minmax(0, 1fr) 76px 136px 104px 34px; gap: 12px; align-items: start; }
    .conceptos-head { padding: 10px 20px; background: var(--sunken); border-bottom: 1px solid var(--line); font-size: 12.5px; color: var(--muted); }
    .concepto { padding: 14px 20px; border-bottom: 1px solid var(--line); }
    .concepto .n { padding-top: 9px; color: var(--faint); font-size: 13px; }
    .concepto .costo { padding-top: 9px; text-align: right; font-weight: 500; }
    .concepto .ops { display: flex; flex-direction: column; gap: 2px; }
    .concepto textarea { resize: vertical; min-height: 42px; }
    .tipos { display: flex; flex-wrap: wrap; gap: 6px; padding: 14px 20px; border-bottom: 1px solid var(--line); align-items: center; }
    .tipos .lbl { font-size: 13px; color: var(--muted); margin-right: 4px; }
    .tipos label { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border: 1px solid var(--line); border-radius: 999px; font-size: 13.5px; cursor: pointer; background: var(--surface, #fff); }
    .tipos label:has(input:checked) { border-color: var(--ink, #111); background: var(--ink, #111); color: #fff; }
    .tipos input { position: absolute; opacity: 0; pointer-events: none; }
    .tipos label:has(input:focus-visible) { outline: 2px solid var(--blue); outline-offset: 2px; }
    .costeo { grid-column: 2 / -1; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)) auto; gap: 10px 12px; align-items: end;
              background: var(--sunken); border: 1px dashed var(--line); border-radius: 10px; padding: 10px 12px; margin-top: -2px; }
    .costeo .form-label { font-size: 12px; margin-bottom: 4px; color: var(--muted); }
    .costeo .form-control, .costeo .form-select, .costeo .input-group-text { height: 36px; font-size: 14px; }
    .costeo .res { font-size: 12.5px; color: var(--muted); text-align: right; white-space: nowrap; padding-bottom: 8px; }
    .costeo .res b { color: var(--green-ink); font-weight: 600; }
    .costeo .res b.neg { color: var(--red); }
    .costeo .priv { grid-column: 1 / -1; font-size: 12px; color: var(--muted); display: flex; gap: 6px; align-items: center; }
    .precio-auto { background: var(--sunken) !important; }
    .lateral .utilidad { margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--line); display: grid; gap: 4px; font-size: 13.5px; }
    .lateral .utilidad div { display: flex; justify-content: space-between; }
    .agregar { display: flex; align-items: center; gap: 8px; width: 100%; padding: 12px 20px; border: 0; background: transparent; color: var(--text-2); font-weight: 500; text-align: left; }
    .agregar:hover { background: var(--sunken); color: var(--text); }
    .totales { padding: 16px 20px 20px; border-top: 1px solid var(--line); display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 24px; align-items: end; }
    .totales dl { margin: 0; }
    .totales dl div { display: flex; justify-content: space-between; padding: 5px 0; }
    .totales dt { font-weight: 400; color: var(--muted); }
    .totales dd { margin: 0; }
    .totales .gran { border-top: 1px solid var(--line); margin-top: 6px; padding-top: 10px; font-size: 18px; font-weight: 600; }
    .totales .gran dt { color: var(--text); font-weight: 600; }

    /* Consideraciones */
    .seccion-c { border: 1px solid var(--line); border-radius: 10px; background: var(--sunken); }
    .seccion-c + .seccion-c { margin-top: 12px; }
    .seccion-c .top { display: flex; align-items: center; gap: 10px; padding: 10px 10px 10px 14px; }
    .seccion-c .num-s { width: 26px; height: 26px; border-radius: 7px; background: var(--surface); border: 1px solid var(--line); display: grid; place-items: center; font-size: 13px; font-weight: 600; flex: none; }
    .seccion-c .top input { font-weight: 600; background: var(--surface); }
    .seccion-c .items { padding: 0 14px 12px 50px; }
    .seccion-c .item { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
    .seccion-c .item::before { content: ''; width: 5px; height: 5px; border-radius: 50%; background: var(--faint); flex: none; }

    .chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .chips button { border: 1px solid var(--line-strong); background: var(--surface); border-radius: 99px; padding: 4px 12px; font-size: 13.5px; color: var(--text-2); }
    .chips button:hover { border-color: var(--ink); color: var(--text); }
    .restante { display: flex; align-items: center; gap: 8px; margin-top: 12px; padding: 10px 12px; border-radius: 8px; font-size: 14px; background: var(--sunken); }
    .restante.vig-pronto { background: var(--amber-soft); }
    .restante.vig-vencida { background: var(--red-soft); }
    .restante.vig-ok { color: var(--green-ink); background: var(--green-soft); }

    .estados { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
    .estados label { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border: 1px solid var(--line-strong); border-radius: 8px; cursor: pointer; font-size: 14px; }
    .estados input { position: absolute; opacity: 0; pointer-events: none; }
    .estados label:has(input:checked) { border-color: var(--ink); box-shadow: inset 0 0 0 1px var(--ink); }
    .estados label:has(input:focus-visible) { outline: 2px solid var(--ink); outline-offset: 2px; }
    .estados .pt { width: 8px; height: 8px; border-radius: 50%; }

    .enlace { display: flex; gap: 6px; }
    .enlace input { font-size: 13px; background: var(--sunken); }
    .total-lateral { font-size: 28px; font-weight: 600; letter-spacing: -.02em; }

    .colapsable summary { list-style: none; cursor: pointer; }
    .colapsable summary::-webkit-details-marker { display: none; }
    .colapsable summary .bi-chevron-down { transition: transform .15s; }
    .colapsable[open] summary .bi-chevron-down { transform: rotate(180deg); }
    .colapsable:not([open]) .panel-head { border-bottom: 0; }

    @media (max-width: 1199.98px) { .editor { grid-template-columns: minmax(0, 1fr); } .lateral { position: static; } }
    @media (max-width: 767.98px) {
        .conceptos-head { display: none; }
        .concepto { grid-template-columns: 1fr 1fr; }
        .concepto .n { display: none; }
        .concepto .desc { grid-column: 1 / -1; }
        .concepto .costo { grid-column: 1; text-align: left; padding-top: 0; }
        .concepto .ops { grid-column: 2; flex-direction: row; justify-content: flex-end; }
        .costeo { grid-column: 1 / -1; grid-template-columns: 1fr 1fr; }
        .costeo .res { grid-column: 1 / -1; text-align: left; padding-bottom: 0; }
        .totales { grid-template-columns: minmax(0, 1fr); }
    }
</style>
@endpush

@section('contenido')
<form method="post" action="{{ $nuevo ? route('admin.presupuestos.store') : route('admin.presupuestos.update', $p) }}"
      x-data="editor({{ Js::from($estado) }})" @keydown.ctrl.s.prevent="$el.requestSubmit()" @keydown.meta.s.prevent="$el.requestSubmit()">
    @csrf
    @unless($nuevo) @method('put') @endunless

    <div class="migas"><a href="{{ route('admin.presupuestos.index') }}">Cotizaciones</a> <i class="bi bi-chevron-right small"></i> <span>{{ $nuevo ? 'Nueva' : $p->folio }}</span></div>
    <div class="page-head">
        <div>
            <div class="titulo-doc">
                <h1>{{ $nuevo ? 'Nueva cotización' : $p->folio }}</h1>
                @unless($nuevo)<span class="estado estado-{{ $p->estado }}">{{ \App\Models\Presupuesto::ESTADOS[$p->estado] }}</span>@endunless
            </div>
            <p class="sub">{{ $nuevo ? 'Llena los datos; el folio se asigna al guardar.' : 'Creada el ' . $p->created_at->locale('es')->isoFormat('D [de] MMMM') . ' · Última edición ' . $p->updated_at->locale('es')->diffForHumans() }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            @unless($nuevo)
                <a href="{{ $p->url_publica }}?vista_previa=1" target="_blank" class="btn btn-borde"><i class="bi bi-eye me-1"></i> Ver como cliente</a>
                <a href="{{ route('admin.presupuestos.pdf', $p) }}" target="_blank" class="btn btn-borde"><i class="bi bi-file-earmark-pdf me-1"></i> PDF</a>
            @endunless
            <button class="btn btn-primario px-4">{{ $nuevo ? 'Crear cotización' : 'Guardar cambios' }}</button>
        </div>
    </div>

    <div class="editor">
        <div class="d-grid gap-4">

            {{-- Cliente --}}
            <section class="panel">
                <div class="panel-head"><h2>Cliente y fecha</h2><span class="ayuda">Así aparece en el encabezado</span></div>
                <div class="panel-body row g-3">
                    <div class="col-12">
                        <label class="form-label" for="cliente_id">Cliente</label>
                        <div class="input-group">
                            <select name="cliente_id" id="cliente_id" class="form-select" x-model="clienteId" @change="elegirCliente()" required>
                                <option value="">Elige un cliente…</option>
                                @foreach($clientes as $c)
                                    <option value="{{ $c->id }}">{{ $c->empresa ? $c->empresa . ' — ' . $c->nombre : $c->nombre }}</option>
                                @endforeach
                            </select>
                            <a href="{{ route('admin.clientes.create') }}" class="btn btn-borde"><i class="bi bi-person-plus"></i> Nuevo</a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="cliente_nombre">Nombre</label>
                        <input name="cliente_nombre" id="cliente_nombre" class="form-control" x-model="clienteNombre" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="cliente_empresa">Empresa</label>
                        <input name="cliente_empresa" id="cliente_empresa" class="form-control" x-model="clienteEmpresa">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="fecha">Fecha</label>
                        <input type="date" name="fecha" id="fecha" class="form-control num" value="{{ old('fecha', $p->fecha->format('Y-m-d')) }}" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="titulo">Título del documento</label>
                        <input name="titulo" id="titulo" class="form-control" value="{{ old('titulo', $p->titulo) }}" required>
                    </div>
                </div>
            </section>

            {{-- Conceptos --}}
            <section class="panel">
                <div class="panel-head"><h2>Conceptos</h2><span class="ayuda" x-text="conceptos.length + (conceptos.length === 1 ? ' concepto' : ' conceptos')"></span></div>
                <div class="tipos" role="radiogroup" aria-label="Tipo de servicio" @change="sugerirObservaciones()">
                    <span class="lbl">Tipo de servicio</span>
                    @foreach($tipos as $k => $m)
                        <label><input type="radio" name="tipo" value="{{ $k }}" x-model="tipo"><i class="bi {{ $m['icono'] }}"></i> {{ $m['nombre'] }}</label>
                    @endforeach
                </div>
                <div class="conceptos-head" aria-hidden="true"><span>#</span><span>Concepto</span><span>Cantidad</span><span>Precio unitario</span><span class="text-end">Costo</span><span></span></div>
                <template x-for="(c, i) in conceptos" :key="i">
                    <div class="concepto">
                        <span class="n num" x-text="i + 1"></span>
                        <div class="desc">
                            <label class="visually-hidden" :for="'tit'+i">Título</label>
                            <input class="form-control fw-semibold mb-2" :id="'tit'+i" :name="`conceptos[${i}][titulo]`" x-model="c.titulo" placeholder="Título, p. ej. Caja personalizada" :required="!c.descripcion">
                            <label class="visually-hidden" :for="'desc'+i">Descripción</label>
                            <textarea class="form-control" rows="2" :id="'desc'+i" :name="`conceptos[${i}][descripcion]`" x-model="c.descripcion" placeholder="Descripción (opcional): medidas, alcance, detalles…"></textarea>
                        </div>
                        <div>
                            <label class="form-label d-md-none" :for="'cant'+i">Cantidad</label>
                            <input type="number" step="0.01" min="0" class="form-control num" :id="'cant'+i" :name="`conceptos[${i}][cantidad]`" x-model.number="c.cantidad" required>
                        </div>
                        <div>
                            <label class="form-label d-md-none" :for="'precio'+i">Precio unitario</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0" class="form-control num" :id="'precio'+i" :name="`conceptos[${i}][precio]`" x-model.number="c.precio" required placeholder="0.00"
                                       :readonly="conCosteo(c)" :class="conCosteo(c) && 'precio-auto'" :title="conCosteo(c) ? 'Se calcula con proveedor + gasolina + utilidad' : ''">
                            </div>
                        </div>
                        <div class="costo num" x-text="dinero(importe(c))"></div>
                        <div class="ops">
                            <div class="dropdown">
                                <button type="button" class="btn btn-fantasma btn-icono" data-bs-toggle="dropdown" :aria-label="'Opciones del concepto ' + (i + 1)"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><button type="button" class="dropdown-item" @click="mover(conceptos, i, -1)" :disabled="i === 0"><i class="bi bi-arrow-up"></i> Subir</button></li>
                                    <li><button type="button" class="dropdown-item" @click="mover(conceptos, i, 1)" :disabled="i === conceptos.length - 1"><i class="bi bi-arrow-down"></i> Bajar</button></li>
                                    <li><button type="button" class="dropdown-item" @click="conceptos.splice(i + 1, 0, {...c})"><i class="bi bi-copy"></i> Duplicar</button></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><button type="button" class="dropdown-item text-danger" @click="conceptos.splice(i, 1)" :disabled="conceptos.length === 1"><i class="bi bi-trash text-danger"></i> Quitar</button></li>
                                </ul>
                            </div>
                        </div>
                        <template x-if="usaCosteo">
                            <div class="costeo" x-effect="if (conCosteo(c)) c.precio = precioCosteo(c)">
                                <div>
                                    <label class="form-label" :for="'prov'+i">Proveedor (c/u)</label>
                                    <div class="input-group"><span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0" class="form-control num" :id="'prov'+i" :name="`conceptos[${i}][costo_proveedor]`" x-model="c.costo_proveedor" placeholder="0.00"></div>
                                </div>
                                <div>
                                    <label class="form-label" :for="'gas'+i">Gasolina (total)</label>
                                    <div class="input-group"><span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0" class="form-control num" :id="'gas'+i" :name="`conceptos[${i}][gasolina]`" x-model="c.gasolina" placeholder="0.00"></div>
                                </div>
                                <div>
                                    <label class="form-label" :for="'util'+i">Utilidad</label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" class="form-control num" :id="'util'+i" :name="`conceptos[${i}][utilidad]`" x-model="c.utilidad" placeholder="0">
                                        <select class="form-select flex-grow-0" style="width: 64px" :name="`conceptos[${i}][utilidad_modo]`" x-model="c.utilidad_modo" aria-label="Utilidad en porcentaje o monto">
                                            <option value="pct">%</option><option value="monto">$</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="res num" x-show="conCosteo(c)">
                                    Costo <span x-text="dinero(costo(c))"></span><br>
                                    Utilidad <b :class="importe(c) - costo(c) < 0 && 'neg'" x-text="dinero(importe(c) - costo(c))"></b>
                                </div>
                                <div class="priv"><i class="bi bi-lock"></i> Solo tú lo ves. El cliente ve el precio unitario y el importe.</div>
                            </div>
                        </template>
                    </div>
                </template>
                <button type="button" class="agregar" @click="conceptos.push({...vacio}); $nextTick(() => document.getElementById('tit' + (conceptos.length - 1)).focus())">
                    <i class="bi bi-plus-circle"></i> Agregar concepto
                </button>

                <div class="totales">
                    <div class="row g-2">
                        <div class="col-8">
                            <label class="form-label" for="modo_iva">IVA en el documento</label>
                            <select name="modo_iva" id="modo_iva" class="form-select" x-model="modoIva">
                                @foreach(\App\Models\Presupuesto::MODOS_IVA as $k => $label)
                                    <option value="{{ $k }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label" for="iva_porcentaje">Tasa</label>
                            <div class="input-group">
                                <input type="number" step="0.01" min="0" max="100" name="iva_porcentaje" id="iva_porcentaje" class="form-control num" x-model.number="ivaPct" :readonly="modoIva === 'sin_iva'">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                    </div>
                    <dl class="num">
                        <div><dt>Subtotal</dt><dd x-text="dinero(subtotal)"></dd></div>
                        <div x-show="modoIva !== 'sin_iva'"><dt x-text="'IVA ' + ivaPct + '%'"></dt><dd x-text="dinero(iva)"></dd></div>
                        <div class="gran"><dt>Total</dt><dd x-text="dinero(total)"></dd></div>
                    </dl>
                </div>
            </section>

            {{-- Observaciones --}}
            <section class="panel">
                <div class="panel-head"><h2>Observaciones</h2><span class="ayuda">Debajo de los conceptos · opcional</span></div>
                <div class="panel-body">
                    <label class="visually-hidden" for="observaciones">Observaciones</label>
                    <textarea name="observaciones" id="observaciones" rows="4" class="form-control" x-model="observaciones"
                              placeholder="Aclaraciones para el cliente: medidas, materiales, archivos, instalación…"></textarea>
                    <div class="d-flex justify-content-between align-items-center mt-2 gap-2 flex-wrap">
                        <span class="secundario" style="font-size:12.5px">Cada renglón se muestra como un párrafo en el PDF y en el enlace del cliente.</span>
                        <button type="button" class="btn btn-fantasma btn-sm" x-show="obsBase[tipo] && observaciones.trim() !== obsBase[tipo]" @click="observaciones = obsBase[tipo]"><i class="bi bi-arrow-counterclockwise me-1"></i> Usar texto base</button>
                    </div>
                </div>
            </section>

            {{-- Consideraciones --}}
            <section class="panel">
                <div class="panel-head"><h2>Consideraciones</h2><span class="ayuda">Se numeran solas en el documento</span></div>
                <div class="panel-body">
                    <template x-for="(s, i) in consideraciones" :key="i">
                        <div class="seccion-c">
                            <div class="top">
                                <span class="num-s num" x-text="i + 1"></span>
                                <input class="form-control" :name="`consideraciones[${i}][titulo]`" x-model="s.titulo" placeholder="Título, p. ej. Tiempos de entrega" aria-label="Título de la sección">
                                <div class="dropdown">
                                    <button type="button" class="btn btn-fantasma btn-icono" data-bs-toggle="dropdown" :aria-label="'Opciones de la sección ' + (i + 1)"><i class="bi bi-three-dots-vertical"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><button type="button" class="dropdown-item" @click="mover(consideraciones, i, -1)" :disabled="i === 0"><i class="bi bi-arrow-up"></i> Subir</button></li>
                                        <li><button type="button" class="dropdown-item" @click="mover(consideraciones, i, 1)" :disabled="i === consideraciones.length - 1"><i class="bi bi-arrow-down"></i> Bajar</button></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><button type="button" class="dropdown-item text-danger" @click="consideraciones.splice(i, 1)"><i class="bi bi-trash text-danger"></i> Quitar sección</button></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="items">
                                <template x-for="(item, j) in s.items" :key="j">
                                    <div class="item">
                                        <input class="form-control form-control-sm" :name="`consideraciones[${i}][items][${j}]`" x-model="s.items[j]" placeholder="Escribe un punto" aria-label="Viñeta">
                                        <button type="button" class="btn btn-fantasma btn-sm" @click="s.items.splice(j, 1)" aria-label="Quitar viñeta"><i class="bi bi-x-lg"></i></button>
                                    </div>
                                </template>
                                <button type="button" class="btn btn-fantasma btn-sm" @click="s.items.push('')"><i class="bi bi-plus"></i> Agregar punto</button>
                            </div>
                        </div>
                    </template>
                    <button type="button" class="btn btn-borde btn-sm mt-3" @click="consideraciones.push({titulo: '', items: ['']})"><i class="bi bi-plus-lg"></i> Agregar sección</button>
                </div>
            </section>

            {{-- Pago --}}
            <section class="panel">
                <div class="panel-head">
                    <div><h2>Confirmación y datos de pago</h2>
                        <span class="ayuda" x-show="mostrarPago" x-text="'Aparece como sección ' + (consideraciones.length + 1)"></span>
                        <span class="ayuda" x-show="!mostrarPago" x-cloak>No aparece en el documento</span></div>
                    <div class="form-check form-switch mb-0">
                        <input type="hidden" name="mostrar_pago" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="mostrar_pago" name="mostrar_pago" value="1" x-model="mostrarPago">
                        <label class="form-check-label small" for="mostrar_pago">Mostrar</label>
                    </div>
                </div>
                <div class="panel-body row g-3" x-show="mostrarPago">
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
                        <label class="form-label" for="nota_comprobante">Nota sobre el comprobante <span class="text-secondary fw-normal">(va en negritas)</span></label>
                        <textarea name="nota_comprobante" id="nota_comprobante" rows="2" class="form-control">{{ old('nota_comprobante', $p->nota_comprobante) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="nota_factura">Nota de facturación</label>
                        <textarea name="nota_factura" id="nota_factura" rows="2" class="form-control">{{ old('nota_factura', $p->nota_factura) }}</textarea>
                    </div>
                </div>
            </section>

            {{-- Encabezado --}}
            <details class="panel colapsable" @if($errors->hasAny(['emisor_nombre', 'emisor_telefono', 'emisor_sitio', 'emisor_email'])) open @endif>
                <summary class="panel-head">
                    <div><h2>Tus datos en el encabezado</h2><span class="ayuda">{{ $p->emisor_nombre }} · {{ $p->emisor_telefono }}</span></div>
                    <i class="bi bi-chevron-down text-secondary"></i>
                </summary>
                <div class="panel-body row g-3">
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
            </details>

            @unless($nuevo)
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" form="duplicar" class="btn btn-fantasma"><i class="bi bi-copy me-1"></i> Duplicar cotización</button>
                    <button type="submit" form="eliminar" class="btn btn-fantasma text-danger"><i class="bi bi-trash me-1"></i> Eliminar</button>
                </div>
            @endunless
        </div>

        {{-- ================= Lateral ================= --}}
        <aside class="lateral">
            <section class="panel panel-body">
                <div class="secundario">Total</div>
                <div class="total-lateral num" x-text="dinero(total)"></div>
                <div class="secundario num" x-show="modoIva !== 'sin_iva'">
                    <span x-text="dinero(subtotal)"></span> + IVA <span x-text="dinero(iva)"></span>
                </div>
                <div class="utilidad num" x-show="usaCosteo && costoTotal > 0" x-cloak>
                    <div><span class="secundario">Costo (proveedor + gasolina)</span> <span x-text="dinero(costoTotal)"></span></div>
                    <div><span class="secundario">Tu utilidad <span title="Porcentaje sobre el costo">(s/costo)</span></span> <b :style="utilidadTotal < 0 ? 'color: var(--red)' : 'color: var(--green-ink)'" x-text="dinero(utilidadTotal) + (margen !== null ? ' · ' + margen + '%' : '')"></b></div>
                </div>
                <div class="secundario mt-2" x-show="modoIva === 'mas_iva'"><i class="bi bi-info-circle me-1"></i>El cliente ve los precios con “+ IVA”.</div>
                <button class="btn btn-primario w-100 mt-3">{{ $nuevo ? 'Crear cotización' : 'Guardar cambios' }}</button>
                <div class="secundario text-center mt-2" style="font-size:12.5px">Atajo: Ctrl / ⌘ + S</div>
            </section>

            <section class="panel">
                <div class="panel-head"><h2>Vigencia</h2></div>
                <div class="panel-body">
                    <label class="form-label" for="vigente_hasta">El enlace funciona hasta</label>
                    <input type="datetime-local" name="vigente_hasta" id="vigente_hasta" class="form-control num" x-model="vigencia" required>
                    <div class="chips mt-2" role="group" aria-label="Atajos de vigencia">
                        @foreach([3, 7, 15, 30] as $d)
                            <button type="button" @click="sumarDias({{ $d }})">{{ $d }} días</button>
                        @endforeach
                    </div>
                    <div class="restante" :class="restante.clase" x-show="restante.texto">
                        <i class="bi" :class="restante.clase === 'vig-vencida' ? 'bi-x-circle' : (restante.clase === 'vig-pronto' ? 'bi-hourglass-split' : 'bi-clock')"></i>
                        <span x-text="restante.texto"></span>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head"><h2>Estado</h2></div>
                <div class="panel-body">
                    <div class="estados" role="radiogroup" aria-label="Estado">
                        @php $colores = ['borrador' => '#8A90A0', 'enviada' => 'var(--blue)', 'negociacion' => '#6E32B5', 'aceptada' => 'var(--green-ink)', 'rechazada' => 'var(--red)']; @endphp
                        @foreach(\App\Models\Presupuesto::ESTADOS as $k => $label)
                            <label><input type="radio" name="estado" value="{{ $k }}" x-model="estadoSel"><span class="pt" style="background: {{ $colores[$k] }}"></span> {{ $label }}</label>
                        @endforeach
                    </div>
                    <div class="mt-3" x-show="estadoSel === 'aceptada'" x-cloak>
                        <label class="form-label" for="aceptada_el">Aceptada el</label>
                        <input type="date" name="aceptada_el" id="aceptada_el" class="form-control num" value="{{ old('aceptada_el', $p->aceptada_el?->toDateString()) }}" :disabled="estadoSel !== 'aceptada'">
                        <div class="secundario mt-1" style="font-size:12.5px">Si la dejas vacía se usa la fecha de hoy.</div>
                    </div>
                    <label class="form-label mt-3" for="notas_internas">Notas internas</label>
                    <textarea name="notas_internas" id="notas_internas" rows="3" class="form-control" placeholder="El cliente no las ve">{{ old('notas_internas', $p->notas_internas) }}</textarea>
                </div>
            </section>

            @unless($nuevo)
                @include('admin.presupuestos._respuesta', ['p' => $p])
            @endunless

            @unless($nuevo)
                <section class="panel">
                    <div class="panel-head"><h2>Enlace del cliente</h2>
                        <span class="ayuda d-inline-flex align-items-center gap-1"><i class="bi bi-eye"></i> {{ $p->vistas }}</span></div>
                    <div class="panel-body">
                        <div class="enlace">
                            <input class="form-control num" value="{{ $p->url_publica }}" readonly aria-label="Enlace del cliente" onclick="this.select()">
                            <button type="button" class="btn btn-primario btn-icono flex-none" style="width:40px;height:40px" data-copiar="{{ $p->url_publica }}" title="Copiar" aria-label="Copiar enlace"><i class="bi bi-copy"></i></button>
                        </div>
                        @if($wa)
                            <a class="btn btn-borde w-100 mt-2" target="_blank" rel="noopener" href="{{ route('admin.presupuestos.whatsapp', $p) }}"
                               title="Incluye el código de verificación para aceptar en línea"><i class="bi bi-whatsapp me-1"></i> Enviar por WhatsApp</a>
                        @endif
                        <button type="button" class="btn btn-borde w-100 mt-2" data-correo="{{ $p->vigente && now()->diffInHours($p->vigente_hasta, false) <= 72 && ! in_array($p->estado, ['aceptada', 'rechazada']) ? 'por_vencer' : 'cotizacion' }}"><i class="bi bi-envelope me-1"></i> Enviar por correo</button>
                        <p class="secundario mt-3 mb-0">
                            @if($p->vistas)
                                Abierta {{ $p->vistas }} {{ $p->vistas === 1 ? 'vez' : 'veces' }}; la última {{ $p->ultima_vista_at->locale('es')->diffForHumans() }}.
                            @else
                                El cliente aún no la abre.
                            @endif
                        </p>
                    </div>
                </section>
            @endunless
            @unless($nuevo)
                @include('admin.correos._historial', ['correos' => \App\Models\Correo::where('presupuesto_id', $p->id)->latest()->take(8)->get(), 'plantilla' => 'cotizacion'])
            @endunless
            @if(! $nuevo && $p->cliente)
                @php $cl = $p->cliente; $csf = $cl->constancias()->first(); @endphp
                <section class="panel">
                    <div class="panel-head"><h2>Facturación</h2>
                        @if($cl->fiscales_completos)<span class="estado estado-aceptada">Completos</span>
                        @elseif($cl->rfc || $cl->razon_social)<span class="estado estado-enviada">Incompletos</span>
                        @else<span class="estado estado-borrador">Sin datos</span>@endif
                    </div>
                    <div class="panel-body">
                        @if($cl->rfc || $cl->razon_social)
                            <div class="principal num">{{ $cl->rfc }}</div>
                            <div class="secundario text-truncate">{{ $cl->razon_social }}</div>
                            <div class="secundario" style="font-size:12.5px">{{ collect([$cl->regimen_fiscal ? 'Régimen ' . $cl->regimen_fiscal : null, $cl->cp_fiscal ? 'C.P. ' . $cl->cp_fiscal : null, $cl->uso_cfdi, $cl->metodo_pago_texto])->filter()->join(' · ') }}</div>
                        @else
                            <div class="secundario">Aún no capturas los datos fiscales de {{ $cl->empresa ?: $cl->nombre }}.</div>
                        @endif
                        <div class="d-grid gap-2 mt-3">
                            @if($cl->rfc || $cl->razon_social)
                                <button type="button" class="btn btn-borde btn-sm" data-copiar="{{ $cl->fiscales_texto }}"><i class="bi bi-clipboard me-1"></i> Copiar datos</button>
                            @endif
                            @if($csf)
                                <a href="{{ route('admin.clientes.constancias.show', [$cl, $csf]) }}" target="_blank" class="btn btn-borde btn-sm"><i class="bi bi-file-earmark-pdf me-1"></i> Ver constancia</a>
                            @endif
                            <a href="{{ route('admin.clientes.edit', $cl) }}#rfc" class="btn btn-fantasma btn-sm">{{ $cl->rfc || $cl->razon_social ? 'Editar datos' : 'Capturar datos y constancia' }}</a>
                        </div>
                    </div>
                </section>
            @endif
            @unless($nuevo)
                @if($p->proyecto)
                    <section class="panel">
                        <div class="panel-head"><h2>Proyecto</h2><span class="estado estado-aceptada">{{ $p->proyecto->tipo_nombre }}</span></div>
                        <div class="panel-body">
                            <div class="principal">{{ $p->proyecto->nombre }}</div>
                            <div class="secundario mb-3">{{ $p->proyecto->siguiente_paso }}</div>
                            <a href="{{ route('admin.proyectos.show', $p->proyecto) }}" class="btn btn-borde w-100"><i class="bi bi-kanban me-1"></i> Abrir proyecto</a>
                        </div>
                    </section>
                @elseif($p->estado === 'aceptada')
                    @php $sugerido = \App\Models\Proyecto::tipoSugerido($p); @endphp
                    <section class="panel" style="border-color: #BDF2D6">
                        <div class="panel-head"><h2>Convertir en proyecto</h2></div>
                        <div class="panel-body">
                            <p class="secundario mt-0">Crea la línea del tiempo, los pagos y el enlace de seguimiento para el cliente.</p>
                            <div class="estados mb-3" role="radiogroup" aria-label="Tipo de proyecto">
                                @foreach(config('vandu.proyectos') as $k => $m)
                                    <label><input type="radio" name="tipo" value="{{ $k }}" form="convertir" @checked($k === $sugerido)><i class="bi {{ $m['icono'] }}"></i> {{ $m['nombre'] }}</label>
                                @endforeach
                            </div>
                            <p class="secundario" style="font-size:13px">En el siguiente paso revisas y ajustas las fechas de cada etapa y pago. Si el trabajo ya terminó, ahí lo registras.</p>
                            <button type="submit" form="convertir" class="btn btn-acento w-100">Continuar <i class="bi bi-arrow-right ms-1"></i></button>
                        </div>
                    </section>
                @endif
            @endunless
        </aside>
    </div>
</form>

@unless($nuevo)
    <form id="convertir" method="get" action="{{ route('admin.proyectos.create', $p) }}"></form>
    <form id="duplicar" method="post" action="{{ route('admin.presupuestos.duplicar', $p) }}">@csrf</form>
    <form id="generar-codigo" method="post" action="{{ route('admin.presupuestos.codigo', $p) }}">@csrf</form>
    <form id="eliminar" method="post" action="{{ route('admin.presupuestos.destroy', $p) }}" onsubmit="return confirm('¿Eliminar {{ $p->folio }}? El enlace del cliente dejará de funcionar.')">@csrf @method('delete')</form>
    @include('admin.correos._modal', ['ctxTipo' => 'presupuesto', 'ctxId' => $p->id])
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
        sugerirObservaciones() {
            // Al cambiar a un tipo con texto base, solo se llena si está vacío o tenía otro texto base
            const base = this.obsBase[this.tipo];
            const actual = this.observaciones.trim();
            const eraBase = Object.values(this.obsBase).includes(actual);
            if (base && (actual === '' || eraBase)) this.observaciones = base;
            else if (! base && eraBase) this.observaciones = '';   // no dejar el texto de otro tipo sin tocar
        },
        get usaCosteo() { return this.tiposCosteo.includes(this.tipo); },
        conCosteo(c) { return this.usaCosteo && (c.costo_proveedor !== '' && c.costo_proveedor !== null || c.gasolina !== '' && c.gasolina !== null); },
        costo(c) { return Math.round(((+c.cantidad || 0) * (+c.costo_proveedor || 0) + (+c.gasolina || 0)) * 100) / 100; },
        // Misma fórmula que PresupuestoConcepto::precioDesdeCosteo
        precioCosteo(c) {
            const cant = +c.cantidad || 0, costo = cant * (+c.costo_proveedor || 0) + (+c.gasolina || 0);
            const total = c.utilidad_modo === 'monto' ? costo + (+c.utilidad || 0) : costo * (1 + (+c.utilidad || 0) / 100);
            return cant > 0 ? Math.round(total / cant * 100) / 100 : 0;
        },
        get costoTotal() { return this.conceptos.filter(c => this.conCosteo(c)).reduce((s, c) => s + this.costo(c), 0); },
        get utilidadTotal() { return this.conceptos.filter(c => this.conCosteo(c)).reduce((s, c) => s + this.importe(c) - this.costo(c), 0); },
        // % sobre el costo, igual que como capturas la utilidad
        get margen() { return this.costoTotal > 0 ? Math.round(this.utilidadTotal / this.costoTotal * 100) : null; },
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
            const f = new Date(); f.setDate(f.getDate() + d);
            const p = (n) => String(n).padStart(2, '0');
            this.vigencia = `${f.getFullYear()}-${p(f.getMonth() + 1)}-${p(f.getDate())}T23:59`;
        },
        get restante() {
            const ms = new Date(this.vigencia).getTime() - this.ahora;
            if (isNaN(ms)) return { texto: '', clase: '' };
            if (ms <= 0) return { texto: 'Vencida: el cliente ve un aviso y no puede descargarla.', clase: 'vig-vencida' };
            const h = Math.floor(ms / 36e5), d = Math.floor(h / 24);
            return { texto: 'Quedan ' + (d ? d + (d === 1 ? ' día ' : ' días ') : '') + (h % 24) + ' h', clase: h <= 72 ? 'vig-pronto' : 'vig-ok' };
        },
    };
}
</script>
@endpush
