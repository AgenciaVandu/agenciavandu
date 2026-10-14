@extends('admin.layout')
@php
    /** @var \App\Models\Presupuesto $p */
    $p = $presupuesto;
    $nuevo = ! $p->exists;

    // Costeo: los componentes del negocio + los que traiga el concepto aunque ya no estén configurados
    $componentes = \App\Support\Costeo::componentes();
    $armarCostos = function (array $guardados) use ($componentes) {
        $porClave = collect($guardados)->keyBy('clave');
        $lista = collect($componentes)->map(fn ($k) => $k + ['monto' => $porClave[$k['clave']]['monto'] ?? '']);
        $extra = collect($guardados)->reject(fn ($g) => collect($componentes)->contains('clave', $g['clave'] ?? null))
            ->map(fn ($g) => ['clave' => $g['clave'] ?? '', 'nombre' => $g['nombre'] ?? 'Costo', 'modo' => $g['modo'] ?? 'fijo', 'clase' => $g['clase'] ?? 'operativo', 'monto' => $g['monto'] ?? '']);
        return $lista->concat($extra)->map(fn ($k) => array_merge($k, ['monto' => $k['monto'] === null ? '' : $k['monto']]))->values()->all();
    };
    $vacio = ['titulo' => '', 'descripcion' => '', 'cantidad' => 1, 'precio' => '', 'utilidad' => '', 'utilidad_modo' => 'pct', 'costear' => false, 'costos' => $armarCostos([])];
    $conceptos = collect(old('conceptos', $p->conceptos->map(fn ($c) => $c->only(['titulo', 'descripcion', 'cantidad', 'precio', 'utilidad', 'utilidad_modo']) + ['costos' => $c->costos_lista])->values()->all()))
        ->map(function ($c) use ($vacio, $armarCostos) {
            $costos = $armarCostos(array_values((array) ($c['costos'] ?? [])));
            $base = array_merge($vacio, array_map(fn ($x) => $x ?? '', \Illuminate\Support\Arr::except($c, ['costos'])));
            return array_merge($base, ['costos' => $costos, 'costear' => collect($costos)->contains(fn ($k) => $k['monto'] !== '' && $k['monto'] !== null)]);
        })->all() ?: [$vacio];
    $tipos = config('vandu.proyectos');
    // Para quién: un cliente, o un prospecto que todavía no es cliente ("p12")
    $cliSel = old('cliente_id', $p->cliente_id);
    $proSel = old('prospecto_id', $p->prospecto_id);
    $consideraciones = old('consideraciones', $p->consideraciones ?? []);
    $estado = [
        'conceptos'       => array_values($conceptos),
        'consideraciones' => collect($consideraciones)->map(fn ($s) => ['titulo' => $s['titulo'] ?? '', 'items' => array_values($s['items'] ?? []) ?: ['']])->values(),
        'modoIva'         => old('modo_iva', $p->modo_iva),
        'ivaPct'          => (float) old('iva_porcentaje', $p->iva_porcentaje),
        'vigencia'        => old('vigente_hasta', $p->vigencia_local->format('Y-m-d\TH:i')),
        'mostrarPago'     => (bool) old('mostrar_pago', $p->mostrar_pago),
        'clienteId'       => $cliSel ? (string) $cliSel : ($proSel ? 'p' . $proSel : ''),
        'prospectoLigado' => $cliSel && $proSel ? (string) $proSel : '',
        'clienteNombre'   => old('cliente_nombre', $p->cliente_nombre),
        'clienteEmpresa'  => old('cliente_empresa', $p->cliente_empresa),
        'clientes'        => $clientes->keyBy('id')->union(($prospectos ?? collect())->keyBy(fn ($x) => 'p' . $x->id)),
        'estadoSel'       => old('estado', $p->estado),
        'tipo'            => old('tipo', $p->tipo ?? \App\Models\Proyecto::tipoSugerido($p)),
        'tiposCosteo'     => collect($tipos)->filter(fn ($m) => ! empty($m['costeo']))->keys()->values(),
        'utilidadBase'    => \App\Support\Costeo::utilidad(),
        'vacio'           => $vacio,
        'observaciones'   => (string) old('observaciones', $p->observaciones),
        'obsBase'         => collect($tipos)->map(fn ($m) => $m['observaciones'] ?? '')->filter(),
    ];
    $wa = $p->contacto?->whatsapp;
@endphp
@php
    // Secciones del editor (como en Finanzas y Proyectos); las de seguimiento solo existen ya guardada
    $seccionesCot = ['contenido' => ['Contenido', 'bi-list-ul'], 'condiciones' => ['Condiciones', 'bi-card-text'], 'pago' => ['Pago y encabezado', 'bi-bank']];
    if (! $nuevo) $seccionesCot += ['envio' => ['Envío y respuesta', 'bi-send'], 'factura' => ['Factura y proyecto', 'bi-receipt']];
    // Si hay errores, abre la sección donde está el primero
    $camposSeccion = ['observaciones' => 'condiciones', 'consideraciones' => 'condiciones', 'notas_internas' => 'condiciones',
        'pago_intro' => 'pago', 'banco' => 'pago', 'clabe' => 'pago', 'beneficiario' => 'pago', 'nota_comprobante' => 'pago', 'nota_factura' => 'pago',
        'emisor_nombre' => 'pago', 'emisor_telefono' => 'pago', 'emisor_sitio' => 'pago', 'emisor_email' => 'pago', 'facturas' => 'factura', 'complemento' => 'factura', 'pago_id' => 'factura', 'fecha_pago' => 'factura', 'metodo_sat' => 'factura', 'codigo' => 'envio'];
    $primerError = collect($errors->keys())->first();
    $seccionInicial = $primerError ? ($camposSeccion[\Illuminate\Support\Str::before($primerError, '.')] ?? 'contenido') : request('ver', 'contenido');
    if (! isset($seccionesCot[$seccionInicial])) $seccionInicial = 'contenido';
    $estado['seccion'] = $seccionInicial;
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
    .costeo { grid-column: 2 / -1; background: var(--sunken); border: 1px dashed var(--line); border-radius: 10px; padding: 12px; margin-top: -2px; display: grid; gap: 10px; }
    .costeo .comps { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px 12px; }
    .costeo .form-label { font-size: 12px; margin-bottom: 4px; color: var(--muted); display: flex; justify-content: space-between; gap: 6px; }
    .costeo .form-label .cl { font-size: 10.5px; text-transform: uppercase; letter-spacing: .03em; opacity: .8; }
    .costeo .form-control, .costeo .form-select, .costeo .input-group-text { height: 36px; font-size: 14px; }
    .costeo .pie { display: flex; flex-wrap: wrap; gap: 10px 16px; align-items: end; justify-content: space-between; }
    .costeo .res { font-size: 13px; color: var(--muted); text-align: right; line-height: 1.5; }
    .costeo .res b { color: var(--green-ink); font-weight: 600; }
    .costeo .res b.neg { color: var(--red); }
    .costeo .priv { font-size: 12px; color: var(--muted); display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
    .costear { grid-column: 2 / -1; margin-top: -6px; }
    .costear button { border: 0; background: none; padding: 2px 0; font-size: 13px; color: var(--muted); text-decoration: underline; text-underline-offset: 3px; }
    .costear button:hover { color: var(--text); }
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

    /* Encabezado y secciones */
    .min-w-0 { min-width: 0; }
    .sec-cot { display: grid; gap: 1.5rem; }
    .cot-head { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 14px; margin-bottom: 18px; }
    .cot-head .sub { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .subnav { position: sticky; top: 0; z-index: 6; background: var(--canvas); display: flex; gap: 4px; border-bottom: 1px solid var(--line); margin: 0 0 22px; overflow-x: auto; scrollbar-width: none; }
    .subnav::-webkit-scrollbar { display: none; }
    .subnav a { display: inline-flex; align-items: center; gap: 7px; padding: 11px 14px; color: var(--muted); text-decoration: none; font-weight: 500; font-size: 14.5px;
                border-bottom: 2px solid transparent; margin-bottom: -1px; white-space: nowrap; }
    .subnav a:hover { color: var(--text); }
    .subnav a.activo { color: var(--text); border-bottom-color: var(--ink); }
    .subnav a.activo i { color: var(--green-ink); }
    .subnav .n { font-size: 12px; background: var(--surface); border: 1px solid var(--line); color: var(--text-2); border-radius: 99px; padding: 0 7px; line-height: 18px; }
    /* Siguientes pasos al aceptarse */
    .siguientes { background: linear-gradient(135deg, #E9FCF2 0%, #F5FFF9 60%, #FFFFFF 100%); border: 1px solid #BDF2D6; border-radius: 16px; padding: 16px 18px 18px; margin: -4px 0 20px; display: grid; gap: 14px; }
    .sig-titulo { display: flex; align-items: center; gap: 12px; }
    .sig-titulo b { display: block; font-size: 16px; }
    .sig-titulo div span { display: block; font-size: 13px; color: var(--muted); }
    .sig-ico { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: var(--green); color: var(--ink); flex: none; }
    .sig-ico i { font-size: 19px; line-height: 1; }
    .sig-pasos { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
    .sig-paso { display: flex; align-items: center; gap: 12px; background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 12px 14px; min-width: 0; }
    .sig-paso.hecho { background: rgba(255,255,255,.65); }
    .sig-n { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: 600; background: var(--ink); color: #fff; flex: none; }
    .sig-paso.hecho .sig-n { background: var(--green-ink); }
    .sig-txt { flex: 1; min-width: 0; }
    .sig-txt b { display: block; font-size: 14.5px; font-weight: 600; }
    .sig-paso.hecho .sig-txt b { color: var(--green-ink); }
    .sig-txt span { display: block; font-size: 12.5px; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sig-acc { display: flex; gap: 4px; flex: none; }
    .sig-acc .btn { white-space: nowrap; }
    @media (max-width: 991.98px) { .sig-pasos { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 575.98px) { .sig-paso { flex-wrap: wrap; } .sig-acc { width: 100%; padding-left: 40px; } .sig-acc .btn:first-child { flex: 1; } }

    /* Factura: método SAT y complementos */
    .metodo-sat { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .metodo-sat label { display: flex; flex-direction: column; padding: 9px 12px; border: 1px solid var(--line-strong); border-radius: 10px; cursor: pointer; }
    .metodo-sat label b { font-size: 14px; } .metodo-sat label span { font-size: 12px; color: var(--muted); }
    .metodo-sat label.activo { border-color: var(--ink); box-shadow: inset 0 0 0 1px var(--ink); }
    .metodo-sat input { position: absolute; opacity: 0; pointer-events: none; }
    .comp-lista { list-style: none; margin: 0; padding: 0; }
    .comp-lista li { display: flex; align-items: center; gap: 12px; padding: 12px 20px; border-bottom: 1px solid var(--line); }
    .comp-lista .t { flex: 1; min-width: 0; }
    .comp-lista .t b { display: block; font-size: 14px; font-weight: 600; }
    .comp-lista .t > span { display: block; font-size: 12.5px; color: var(--muted); overflow-wrap: anywhere; }
    .comp-ic { width: 30px; height: 30px; border-radius: 9px; display: flex; align-items: center; justify-content: center; background: var(--sunken); color: var(--text-2); flex: none; }
    .comp-ic.verde { background: var(--green-soft); color: var(--green-ink); } .comp-ic.azul { background: #EAF1FE; color: #2456C8; } .comp-ic.ambar { background: var(--amber-soft); color: var(--amber); }
    .comp-x { border: 0; background: none; padding: 0 6px 0 0; color: var(--muted); margin-left: -6px; }
    .comp-x:hover { color: var(--red); }
    .comp-subir { background: var(--sunken); border-radius: 0 0 var(--radius, 12px) var(--radius, 12px); }

    .punto-est { width: 8px; height: 8px; border-radius: 50%; background: #C9CDD4; }
    .punto-est.verde { background: var(--green-ink); } .punto-est.azul { background: var(--blue); } .punto-est.ambar { background: var(--amber); }

    @media (max-width: 1199.98px) { .editor { grid-template-columns: minmax(0, 1fr); } .lateral { position: static; } }
    @media (max-width: 767.98px) {
        .subnav { position: static; flex-wrap: wrap; border-bottom: 0; gap: 6px; background: none; }
        .subnav a { border: 1px solid var(--line); border-radius: 99px; padding: 6px 12px; font-size: 13.5px; margin: 0; background: var(--surface); }
        .subnav a.activo { background: var(--ink); color: #fff; border-color: var(--ink); } .subnav a.activo i { color: var(--green); }
        .cot-acciones { width: 100%; } .cot-acciones > * { flex: 1 1 auto; } .cot-acciones .dropdown .btn { width: 100%; }
        .conceptos-head { display: none; }
        .concepto { grid-template-columns: 1fr 1fr; }
        .concepto .n { display: none; }
        .concepto .desc { grid-column: 1 / -1; }
        .concepto .costo { grid-column: 1; text-align: left; padding-top: 0; }
        .concepto .ops { grid-column: 2; flex-direction: row; justify-content: flex-end; }
        .costeo, .costear { grid-column: 1 / -1; }
        .costeo .comps { grid-template-columns: 1fr 1fr; }
        .costeo .res { text-align: left; }
        .totales { grid-template-columns: minmax(0, 1fr); }
    }
</style>
@endpush

@section('contenido')
<form method="post" action="{{ $nuevo ? route('admin.presupuestos.store') : route('admin.presupuestos.update', $p) }}"
      x-data="editor({{ Js::from($estado) }})" @invalid.capture="alInvalido($event)" @keydown.ctrl.s.prevent="$el.requestSubmit()" @keydown.meta.s.prevent="$el.requestSubmit()">
    @csrf
    @unless($nuevo) @method('put') @endunless
    <input type="hidden" name="cliente_id" :value="clienteId.startsWith('p') ? '' : clienteId">
    <input type="hidden" name="prospecto_id" :value="clienteId.startsWith('p') ? clienteId.slice(1) : prospectoLigado">

    <div class="migas"><a href="{{ route('admin.presupuestos.index') }}">Cotizaciones</a> <i class="bi bi-chevron-right small"></i> <span>{{ $nuevo ? 'Nueva' : $p->folio }}</span></div>
    <div class="cot-head">
        <div class="min-w-0">
            <div class="titulo-doc">
                <h1>{{ $nuevo ? 'Nueva cotización' : $p->folio }}</h1>
                @unless($nuevo)<span class="estado estado-{{ $p->estado }}">{{ \App\Models\Presupuesto::ESTADOS[$p->estado] }}</span>@endunless
            </div>
            <p class="sub mb-0">
                <span x-text="clienteEmpresa || clienteNombre || 'Sin cliente todavía'"></span>
                · <span class="num" x-text="dinero(total)"></span>
                @unless($nuevo) · editada {{ $p->updated_at->locale('es')->diffForHumans() }}@endunless
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2 cot-acciones">
            @unless($nuevo)
                <a href="{{ $p->url_publica }}?vista_previa=1" target="_blank" class="btn btn-borde"><i class="bi bi-eye me-1"></i> Ver como cliente</a>
                <div class="dropdown">
                    <button type="button" class="btn btn-borde dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-send me-1"></i> Enviar</button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><button type="button" class="dropdown-item" data-correo="{{ $p->vigente && now()->diffInHours($p->vigente_hasta, false) <= 72 && ! in_array($p->estado, ['aceptada', 'rechazada']) ? 'por_vencer' : 'cotizacion' }}"><i class="bi bi-envelope me-2"></i>Por correo</button></li>
                        @if($wa)<li><a class="dropdown-item" target="_blank" rel="noopener" href="{{ route('admin.presupuestos.whatsapp', $p) }}"><i class="bi bi-whatsapp me-2"></i>Por WhatsApp</a></li>@endif
                        <li><button type="button" class="dropdown-item" data-copiar="{{ $p->url_publica }}"><i class="bi bi-link-45deg me-2"></i>Copiar enlace</button></li>
                        <li><a class="dropdown-item" href="{{ route('admin.presupuestos.pdf', $p) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-2"></i>Descargar PDF</a></li>
                        @if($p->facturas->isNotEmpty())<li><hr class="dropdown-divider"></li><li><button type="button" class="dropdown-item" data-correo="factura"><i class="bi bi-receipt me-2"></i>Enviar factura</button></li>@endif
                    </ul>
                </div>
            @endunless
            <button class="btn btn-primario px-4">{{ $nuevo ? 'Crear cotización' : 'Guardar' }}</button>
        </div>
    </div>

    {{-- Aceptada: los siguientes pasos, a la vista en cualquier sección --}}
    @if(! $nuevo && $p->estado === 'aceptada')
        @php
            $sigFacturas = $p->facturas_ordenadas; $sigEnviada = $sigFacturas->max('enviada_at');
            $sigTipo = \App\Models\Proyecto::tipoSugerido($p);
            $sigListo = $p->proyecto && $sigEnviada;
        @endphp
        @unless($sigListo)
            <section class="siguientes" aria-label="Siguientes pasos">
                <div class="sig-titulo">
                    <span class="sig-ico"><i class="bi bi-trophy"></i></span>
                    <div><b>¡Cotización aceptada!</b><span>Aceptada {{ $p->aceptada_el ? 'el ' . \App\Models\Presupuesto::fechaLarga($p->aceptada_el) : '' }} · sigue con esto:</span></div>
                </div>
                <div class="sig-pasos">
                    {{-- 1. Proyecto --}}
                    <div class="sig-paso {{ $p->proyecto ? 'hecho' : '' }}">
                        <span class="sig-n">@if($p->proyecto)<i class="bi bi-check-lg"></i>@else 1 @endif</span>
                        <div class="sig-txt">
                            @if($p->proyecto)
                                <b>Proyecto creado</b><span>{{ $p->proyecto->nombre }} · {{ $p->proyecto->siguiente_paso ?? 'Terminado' }}</span>
                            @else
                                <b>Crear el proyecto</b><span>Etapas, pagos y el enlace de avance para el cliente · {{ config("vandu.proyectos.$sigTipo.nombre") }}</span>
                            @endif
                        </div>
                        <div class="sig-acc">
                        @if($p->proyecto)
                            <a href="{{ route('admin.proyectos.show', $p->proyecto) }}" class="btn btn-borde btn-sm">Abrir</a>
                        @else
                                <a href="{{ route('admin.proyectos.create', [$p, 'tipo' => $sigTipo]) }}" class="btn btn-acento btn-sm"><i class="bi bi-kanban me-1"></i> Crear proyecto</a>
                                <a href="?ver=factura" class="btn btn-fantasma btn-sm" @click.prevent="irA('factura')" data-recargar title="Elegir otro tipo de proyecto">Otro tipo</a>
                        @endif
                        </div>
                    </div>
                    {{-- 2. Factura --}}
                    <div class="sig-paso {{ $sigEnviada ? 'hecho' : '' }}">
                        <span class="sig-n">@if($sigEnviada)<i class="bi bi-check-lg"></i>@else 2 @endif</span>
                        <div class="sig-txt">
                            @if($sigEnviada)
                                <b>Factura enviada</b><span>{{ $sigEnviada->locale('es')->diffForHumans() }}</span>
                            @elseif($sigFacturas->isNotEmpty())
                                <b>Enviar la factura</b><span>{{ $sigFacturas->pluck('nombre')->join(' · ') }}</span>
                            @else
                                <b>Subir la factura</b><span>El PDF y el XML; el cliente la descarga desde su enlace</span>
                            @endif
                        </div>
                        <div class="sig-acc">
                        @if($sigEnviada)
                            <a href="?ver=factura" class="btn btn-borde btn-sm" @click.prevent="irA('factura')" data-recargar>Ver</a>
                        @elseif($sigFacturas->isNotEmpty())
                            <button type="button" class="btn btn-acento btn-sm" data-correo="factura"><i class="bi bi-send me-1"></i> Enviar factura</button>
                        @else
                            <label class="btn btn-acento btn-sm mb-0"><i class="bi bi-upload me-1"></i> Subir PDF y XML
                                <input type="file" name="facturas[]" form="subir-factura" multiple accept=".pdf,.xml,.zip" class="d-none" onchange="this.files.length && this.form.requestSubmit()">
                            </label>
                        @endif
                        </div>
                    </div>
                </div>
            </section>
        @endunless
    @endif

    <nav class="subnav" aria-label="Secciones de la cotización">
        @foreach($seccionesCot as $k => [$texto, $icono])
            <a href="?ver={{ $k }}" :class="{ activo: seccion === '{{ $k }}' }" @click.prevent="irA('{{ $k }}')" data-recargar>
                <i class="bi {{ $icono }}"></i> {{ $texto }}
                @if($k === 'contenido')<span class="n num" x-text="conceptos.length"></span>@endif
                @if($k === 'envio' && ! $nuevo && $p->vistas)<span class="n num" title="Veces que la abrió"><i class="bi bi-eye"></i> {{ $p->vistas }}</span>@endif
                @if($k === 'factura' && ! $nuevo)<span class="punto-est {{ $p->facturas->isEmpty() ? ($p->estado === 'aceptada' ? 'ambar' : '') : ($p->facturas->max('enviada_at') ? 'verde' : 'azul') }}"></span>@endif
            </a>
        @endforeach
    </nav>

    <div class="editor">
        <div class="min-w-0">
        <div class="sec-cot" data-seccion="contenido" x-show="seccion === 'contenido'" @if($seccionInicial !== 'contenido') x-cloak @endif>
            {{-- Cliente --}}
            <section class="panel">
                <div class="panel-head"><h2>Cliente y fecha</h2><span class="ayuda">Así aparece en el encabezado</span></div>
                <div class="panel-body row g-3">
                    <div class="col-12">
                        <label class="form-label" for="cliente_id">Cliente o prospecto</label>
                        <div class="input-group">
                            <select id="cliente_id" class="form-select @error('cliente_id') is-invalid @enderror" x-model="clienteId" @change="prospectoLigado = ''; elegirCliente()" required>
                                <option value="">Elige un cliente o prospecto…</option>
                                <optgroup label="Clientes">
                                    @foreach($clientes as $c)
                                        <option value="{{ $c->id }}">{{ $c->empresa ? $c->empresa . ' — ' . $c->nombre : $c->nombre }}</option>
                                    @endforeach
                                </optgroup>
                                @if(($prospectos ?? collect())->isNotEmpty())
                                    <optgroup label="Prospectos (aún no son clientes)">
                                        @foreach($prospectos as $x)
                                            <option value="p{{ $x->id }}">{{ $x->empresa ? $x->empresa . ' — ' . $x->nombre : $x->nombre }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>
                            <a href="{{ route('admin.clientes.create') }}" class="btn btn-borde"><i class="bi bi-person-plus"></i> Nuevo</a>
                        </div>
                        @error('cliente_id')<div class="text-danger mt-1" style="font-size:13px">Elige un cliente o un prospecto.</div>@enderror
                        <div class="secundario mt-2" style="font-size:13px" x-show="clienteId.startsWith('p')" x-cloak>
                            <i class="bi bi-funnel me-1"></i>Es un prospecto de Ventas. Cuando acepte la cotización (o crees su proyecto) se vuelve cliente automáticamente.
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
                            <input type="number" step="0.01" min="0" class="form-control num" :id="'cant'+i" :name="`conceptos[${i}][cantidad]`" x-model.number="c.cantidad" required @input="if (conCosteo(c)) recalcularPrecio(c)">
                        </div>
                        <div>
                            <label class="form-label d-md-none" :for="'precio'+i">Precio unitario</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0" class="form-control num" :id="'precio'+i" :name="`conceptos[${i}][precio]`" x-model.number="c.precio" required placeholder="0.00"
                                       @input="if (conCosteo(c)) recalcularUtilidad(c)" :title="conCosteo(c) ? 'Escribe el precio y la utilidad se ajusta sola' : ''">
                            </div>
                        </div>
                        <div class="costo num" x-text="dinero(importe(c))"></div>
                        <div class="ops">
                            <div class="dropdown">
                                <button type="button" class="btn btn-fantasma btn-icono" data-bs-toggle="dropdown" :aria-label="'Opciones del concepto ' + (i + 1)"><i class="bi bi-three-dots-vertical"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><button type="button" class="dropdown-item" @click="mover(conceptos, i, -1)" :disabled="i === 0"><i class="bi bi-arrow-up"></i> Subir</button></li>
                                    <li><button type="button" class="dropdown-item" @click="mover(conceptos, i, 1)" :disabled="i === conceptos.length - 1"><i class="bi bi-arrow-down"></i> Bajar</button></li>
                                    <li><button type="button" class="dropdown-item" @click="conceptos.splice(i + 1, 0, clon(c))"><i class="bi bi-copy"></i> Duplicar</button></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><button type="button" class="dropdown-item text-danger" @click="conceptos.splice(i, 1)" :disabled="conceptos.length === 1"><i class="bi bi-trash text-danger"></i> Quitar</button></li>
                                </ul>
                            </div>
                        </div>
                        <template x-if="! c.costear">
                            <div class="costear"><button type="button" @click="activarCosteo(c)"><i class="bi bi-calculator me-1"></i>Calcular precio con costos</button></div>
                        </template>
                        <template x-if="c.costear">
                            <div class="costeo">
                                <div class="comps">
                                    <template x-for="(k, j) in c.costos" :key="k.clave + j">
                                        <div>
                                            <label class="form-label" :for="'cos'+i+'_'+j"><span x-text="k.nombre + (k.modo === 'unidad' ? ' (c/u)' : ' (total)')"></span><span class="cl" x-text="k.clase === 'tercero' ? 'Tercero' : 'Operativo'"></span></label>
                                            <div class="input-group"><span class="input-group-text">$</span>
                                                <input type="number" step="0.01" min="0" class="form-control num" :id="'cos'+i+'_'+j" :name="`conceptos[${i}][costos][${j}][monto]`" x-model="k.monto" placeholder="0.00" @input="recalcularPrecio(c)"></div>
                                            <input type="hidden" :name="`conceptos[${i}][costos][${j}][clave]`" :value="k.clave">
                                            <input type="hidden" :name="`conceptos[${i}][costos][${j}][nombre]`" :value="k.nombre">
                                            <input type="hidden" :name="`conceptos[${i}][costos][${j}][modo]`" :value="k.modo">
                                            <input type="hidden" :name="`conceptos[${i}][costos][${j}][clase]`" :value="k.clase">
                                        </div>
                                    </template>
                                    <div>
                                        <label class="form-label" :for="'util'+i">Utilidad</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" class="form-control num" :id="'util'+i" :name="`conceptos[${i}][utilidad]`" x-model="c.utilidad" placeholder="0" @input="recalcularPrecio(c)">
                                            <select class="form-select flex-grow-0" style="width: 64px" :name="`conceptos[${i}][utilidad_modo]`" x-model="c.utilidad_modo" aria-label="Utilidad en porcentaje sobre el costo o monto" @change="recalcularUtilidad(c)">
                                                <option value="pct">%</option><option value="monto">$</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="pie">
                                    <div class="priv"><i class="bi bi-lock"></i> Solo tú lo ves. El cliente ve el precio unitario y el importe.
                                        <button type="button" class="btn btn-link btn-sm p-0 text-secondary" @click="quitarCosteo(c)">Quitar costeo</button></div>
                                    <div class="res num" x-show="conCosteo(c)">
                                        Costo <span x-text="dinero(costo(c))"></span>
                                        <template x-if="costoClase(c, 'tercero') > 0 && costoClase(c, 'operativo') > 0"><span class="d-block" style="font-size: 12px" x-text="'Terceros ' + dinero(costoClase(c, 'tercero')) + ' · Operativo ' + dinero(costoClase(c, 'operativo'))"></span></template>
                                        <span class="d-block">Utilidad <b :class="importe(c) - costo(c) < 0 && 'neg'" x-text="dinero(importe(c) - costo(c)) + (importe(c) > 0 ? ' · ' + Math.round((importe(c) - costo(c)) / importe(c) * 100) + '% de margen' : '')"></b></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
                <button type="button" class="agregar" @click="conceptos.push(nuevoConcepto()); $nextTick(() => document.getElementById('tit' + (conceptos.length - 1)).focus())">
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
        </div>
        <div class="sec-cot" data-seccion="condiciones" x-show="seccion === 'condiciones'" @if($seccionInicial !== 'condiciones') x-cloak @endif>
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

            {{-- Notas internas --}}
            <section class="panel">
                <div class="panel-head"><h2>Notas internas</h2><span class="ayuda">Solo las ve tu equipo</span></div>
                <div class="panel-body">
                    <label class="visually-hidden" for="notas_internas">Notas internas</label>
                    <textarea name="notas_internas" id="notas_internas" rows="3" class="form-control" placeholder="El cliente no las ve">{{ old('notas_internas', $p->notas_internas) }}</textarea>
                </div>
            </section>
        </div>
        <div class="sec-cot" data-seccion="pago" x-show="seccion === 'pago'" @if($seccionInicial !== 'pago') x-cloak @endif>
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
            <details class="panel colapsable" open>
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
        </div>
        @unless($nuevo)
        <div class="sec-cot" data-seccion="envio" x-show="seccion === 'envio'" @if($seccionInicial !== 'envio') x-cloak @endif>
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
                @include('admin.presupuestos._respuesta', ['p' => $p])
                @include('admin.correos._historial', ['correos' => \App\Models\Correo::where('presupuesto_id', $p->id)->latest()->take(8)->get(), 'plantilla' => 'cotizacion'])
            @endunless
        </div>
        <div class="sec-cot" data-seccion="factura" x-show="seccion === 'factura'" @if($seccionInicial !== 'factura') x-cloak @endif>
            @unless($nuevo)
                @php $facturas = $p->facturas_ordenadas; $enviada = $facturas->max('enviada_at'); @endphp
                @php
                    $lotes = $p->complementos_lotes;
                    $pagosPr = $p->proyecto?->pagos ?? collect();
                    $lotePorPago = $lotes->filter(fn ($c) => $c['pago'])->keyBy(fn ($c) => $c['pago']->id);
                    $sueltos = $lotes->filter(fn ($c) => ! $c['pago'] || ! $pagosPr->contains('id', $c['pago']->id));
                    $pagadosSinComp = $pagosPr->filter(fn ($x) => $x->pagado && ! $lotePorPago->has($x->id));
                @endphp
                <div class="sec-cot" x-data="{ sat: {{ Js::from(old('metodo_sat', $p->metodo_sat_efectivo)) }} }">
                <section class="panel" id="factura">
                    <div class="panel-head"><h2>Factura</h2>
                        @if($facturas->isEmpty())<span class="estado estado-borrador">Sin subir</span>
                        @elseif($enviada)<span class="estado estado-aceptada">Enviada</span>
                        @else<span class="estado estado-enviada">Por enviar</span>@endif
                    </div>
                    <div class="panel-body">
                        <div class="metodo-sat mb-3" role="radiogroup" aria-label="Método de pago de la factura">
                            <label :class="sat === 'PUE' && 'activo'"><input type="radio" name="metodo_sat" value="PUE" x-model="sat"><b>PUE</b><span>Una sola exhibición</span></label>
                            <label :class="sat === 'PPD' && 'activo'"><input type="radio" name="metodo_sat" value="PPD" x-model="sat"><b>PPD</b><span>En parcialidades o diferido</span></label>
                        </div>
                        <div class="secundario mb-3" style="font-size:12.5px" x-show="sat === 'PPD'" x-cloak><i class="bi bi-info-circle me-1"></i>Con PPD, cada pago que recibas lleva su complemento de pago (REP). Súbelos abajo. Guarda para conservar el método.</div>
                        @if($facturas->isEmpty())
                            <p class="secundario mt-0 mb-3" style="font-size:13.5px">Sube el PDF y el XML. El cliente los podrá descargar desde su cotización y su espacio.</p>
                        @else
                            <ul class="list-unstyled d-grid gap-1 mb-3">
                                @foreach($facturas as $f)
                                    <li class="d-flex align-items-center gap-2">
                                        <i class="bi {{ $f->icono }}" style="font-size:18px; color: var(--text-2)"></i>
                                        <a href="{{ route('admin.presupuestos.facturas.ver', [$p, $f]) }}" target="_blank" class="text-reset text-truncate flex-grow-1" style="font-size:13.5px" title="{{ $f->nombre }}">{{ $f->nombre }}</a>
                                        <span class="secundario num" style="font-size:12px">{{ $f->peso_texto }}</span>
                                        <button type="submit" form="borrar-factura-{{ $f->id }}" class="btn btn-fantasma btn-icono btn-sm" aria-label="Quitar {{ $f->nombre }}"><i class="bi bi-x-lg"></i></button>
                                    </li>
                                @endforeach
                            </ul>
                            @if($enviada)<div class="secundario mb-3" style="font-size:12.5px"><i class="bi bi-check2-circle"></i> Enviada por correo {{ $enviada->locale('es')->diffForHumans() }}</div>@endif
                        @endif
                        <div class="d-grid gap-2">
                            @if($facturas->isNotEmpty())
                                <button type="button" class="btn {{ $enviada ? 'btn-borde' : 'btn-primario' }} btn-sm" data-correo="factura"><i class="bi bi-send me-1"></i> {{ $enviada ? 'Enviar de nuevo' : 'Enviar factura' }}</button>
                            @endif
                            <label class="btn btn-borde btn-sm mb-0"><i class="bi bi-upload me-1"></i> {{ $facturas->isEmpty() ? 'Subir PDF y XML' : 'Subir otro archivo' }}
                                <input type="file" name="facturas[]" form="subir-factura" multiple accept=".pdf,.xml,.zip" class="d-none" onchange="this.files.length && this.form.requestSubmit()">
                            </label>
                        </div>
                        @error('facturas')<div class="text-danger mt-2" style="font-size:13px">{{ $message }}</div>@enderror
                        @error('facturas.*')<div class="text-danger mt-2" style="font-size:13px">{{ $message }}</div>@enderror
                    </div>
                </section>

                {{-- Complementos de pago (PPD) --}}
                <section class="panel" id="complementos" x-show="sat === 'PPD' || {{ $lotes->isNotEmpty() ? 'true' : 'false' }}" @if($p->metodo_sat_efectivo !== 'PPD' && $lotes->isEmpty()) x-cloak @endif
                         x-data="{ subirPara(id) { this.$refs.compPago.value = id || ''; this.$refs.compFecha.value = ''; this.$refs.compArchivo.click(); } }">
                    <div class="panel-head"><h2>Complementos de pago</h2>
                        @if($pagosPr->isNotEmpty())<span class="ayuda">{{ $lotePorPago->count() }} de {{ $pagosPr->where('pagado', true)->count() ?: $pagosPr->count() }} {{ $pagosPr->where('pagado', true)->count() ? 'pagos recibidos' : 'pagos' }}</span>
                        @else<span class="ayuda">{{ $lotes->count() ?: '' }}</span>@endif
                    </div>
                    @if($pagosPr->isNotEmpty())
                        <ul class="comp-lista">
                            @foreach($pagosPr as $pg)
                                @php $c = $lotePorPago->get($pg->id); @endphp
                                <li>
                                    <span class="comp-ic {{ $c ? ($c['enviado'] ? 'verde' : 'azul') : ($pg->pagado ? 'ambar' : '') }}"><i class="bi {{ $c ? ($c['enviado'] ? 'bi-check2' : 'bi-receipt-cutoff') : ($pg->pagado ? 'bi-exclamation' : 'bi-hourglass') }}"></i></span>
                                    <div class="t">
                                        <b>{{ $pg->concepto }} <span class="secundario fw-normal num">· {{ $pg->monto_texto }}</span></b>
                                        @if($c)
                                            <span>@foreach($c['archivos'] as $f)<a href="{{ route('admin.presupuestos.facturas.ver', [$p, $f]) }}" target="_blank" class="text-reset me-2"><i class="bi {{ $f->icono }}"></i> {{ $f->nombre }}</a><button type="submit" form="borrar-factura-{{ $f->id }}" class="comp-x" aria-label="Quitar {{ $f->nombre }}"><i class="bi bi-x"></i></button>@endforeach
                                                · {{ $c['enviado'] ? 'Enviado ' . $c['enviado']->locale('es')->diffForHumans() : 'Por enviar' }}</span>
                                        @elseif($pg->pagado)
                                            <span class="text-warning-emphasis">Pagado el {{ $pg->pagado_el->locale('es')->isoFormat('D MMM') }} · falta su complemento</span>
                                        @else
                                            <span>Aún no se paga · el complemento se emite al recibirlo</span>
                                        @endif
                                    </div>
                                    @if($c)
                                        <button type="button" class="btn {{ $c['enviado'] ? 'btn-borde' : 'btn-primario' }} btn-sm" data-correo="{{ 'complemento@' . ($c['archivos']->first()->lote ?: 'f' . $c['archivos']->first()->id) }}"><i class="bi bi-send me-1"></i> {{ $c['enviado'] ? 'Reenviar' : 'Enviar' }}</button>
                                    @elseif($pg->pagado)
                                        <button type="button" class="btn btn-acento btn-sm" @click="subirPara({{ $pg->id }})"><i class="bi bi-upload me-1"></i> Subir</button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if($sueltos->isNotEmpty())
                        <ul class="comp-lista">
                            @foreach($sueltos as $lote => $c)
                                <li>
                                    <span class="comp-ic {{ $c['enviado'] ? 'verde' : 'azul' }}"><i class="bi {{ $c['enviado'] ? 'bi-check2' : 'bi-receipt-cutoff' }}"></i></span>
                                    <div class="t">
                                        <b>{{ $c['titulo'] }}</b>
                                        <span>@foreach($c['archivos'] as $f)<a href="{{ route('admin.presupuestos.facturas.ver', [$p, $f]) }}" target="_blank" class="text-reset me-2"><i class="bi {{ $f->icono }}"></i> {{ $f->nombre }}</a><button type="submit" form="borrar-factura-{{ $f->id }}" class="comp-x" aria-label="Quitar {{ $f->nombre }}"><i class="bi bi-x"></i></button>@endforeach
                                            · {{ $c['enviado'] ? 'Enviado ' . $c['enviado']->locale('es')->diffForHumans() : 'Por enviar' }}</span>
                                    </div>
                                    <button type="button" class="btn {{ $c['enviado'] ? 'btn-borde' : 'btn-primario' }} btn-sm" data-correo="{{ 'complemento@' . $lote }}"><i class="bi bi-send me-1"></i> {{ $c['enviado'] ? 'Reenviar' : 'Enviar' }}</button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="panel-body comp-subir">
                        @if($pagosPr->isEmpty() && $lotes->isEmpty())
                            <p class="secundario mt-0 mb-3" style="font-size:13.5px">Cuando el cliente te pague, sube aquí el complemento (PDF y XML) y envíaselo. Si creas el proyecto, cada complemento se liga a su pago.</p>
                        @endif
                        <div class="row g-2 align-items-end">
                            <div class="col-sm-5">
                                <label class="form-label" for="comp-pago" style="font-size:12.5px">Pago que ampara</label>
                                <select id="comp-pago" name="pago_id" form="subir-complemento" class="form-select form-select-sm" x-ref="compPago">
                                    <option value="">{{ $pagosPr->isNotEmpty() ? 'Ninguno en particular' : ($p->proyecto ? 'El proyecto no tiene pagos' : 'Sin proyecto todavía') }}</option>
                                    @foreach($pagosPr as $pg)<option value="{{ $pg->id }}" @selected($pagadosSinComp->first()?->id === $pg->id)>{{ $pg->concepto }} · {{ $pg->monto_texto }}{{ $pg->pagado ? '' : ' (pendiente)' }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label" for="comp-fecha" style="font-size:12.5px">Fecha del pago</label>
                                <input type="date" id="comp-fecha" name="fecha_pago" form="subir-complemento" class="form-control form-control-sm num" x-ref="compFecha">
                            </div>
                            <div class="col-sm-4">
                                <label class="btn btn-borde btn-sm w-100 mb-0"><i class="bi bi-upload me-1"></i> Subir PDF y XML
                                    <input type="file" name="complemento[]" form="subir-complemento" multiple accept=".pdf,.xml,.zip" class="d-none" x-ref="compArchivo" onchange="this.files.length && this.form.requestSubmit()">
                                </label>
                            </div>
                        </div>
                        @error('complemento')<div class="text-danger mt-2" style="font-size:13px">{{ $message }}</div>@enderror
                        @error('complemento.*')<div class="text-danger mt-2" style="font-size:13px">{{ $message }}</div>@enderror
                    </div>
                </section>
                </div>
            @endunless
            @if(! $nuevo && $p->cliente)
                @php $cl = $p->cliente; $csf = $cl->constancias()->first(); @endphp
                <section class="panel">
                    <div class="panel-head"><h2>Datos fiscales</h2>
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
        </div>
        @endunless
            @unless($nuevo)
                <div class="d-flex flex-wrap gap-2 mt-4">
                    <button type="submit" form="duplicar" class="btn btn-fantasma"><i class="bi bi-copy me-1"></i> Duplicar cotización</button>
                    <button type="submit" form="eliminar" class="btn btn-fantasma text-danger"><i class="bi bi-trash me-1"></i> Eliminar</button>
                </div>
            @endunless
        </div>

        {{-- ================= Lateral: siempre a la vista ================= --}}
        <aside class="lateral">
            <section class="panel panel-body">
                <div class="secundario">Total</div>
                <div class="total-lateral num" x-text="dinero(total)"></div>
                <div class="secundario num" x-show="modoIva !== 'sin_iva'">
                    <span x-text="dinero(subtotal)"></span> + IVA <span x-text="dinero(iva)"></span>
                </div>
                <div class="utilidad num" x-show="usaCosteo && costoTotal > 0" x-cloak>
                    <div><span class="secundario">Costo total</span> <span x-text="dinero(costoTotal)"></span></div>
                    <div x-show="costoTerceros > 0 && costoOperativo > 0"><span class="secundario">· Terceros</span> <span x-text="dinero(costoTerceros)"></span></div>
                    <div x-show="costoTerceros > 0 && costoOperativo > 0"><span class="secundario">· Operativo</span> <span x-text="dinero(costoOperativo)"></span></div>
                    <div><span class="secundario">Tu utilidad</span> <b :style="utilidadTotal < 0 ? 'color: var(--red)' : 'color: var(--green-ink)'" x-text="dinero(utilidadTotal)"></b></div>
                    <div><span class="secundario" title="Qué parte de lo vendido (con costeo) es utilidad">Margen</span> <b x-text="margen === null ? '—' : margen + '%'"></b></div>
                    <div class="secundario" style="font-size: 12px" x-show="conceptos.some(c => ! conCosteo(c))">Solo cuenta los conceptos con costeo.</div>
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

                </div>
            </section>
        </aside>
    </div>
</form>

@unless($nuevo)
    <form id="convertir" method="get" action="{{ route('admin.proyectos.create', $p) }}"></form>
    <form id="subir-factura" method="post" action="{{ route('admin.presupuestos.facturas.subir', $p) }}" enctype="multipart/form-data">@csrf</form>
    <form id="subir-complemento" method="post" action="{{ route('admin.presupuestos.complementos.subir', $p) }}" enctype="multipart/form-data">@csrf</form>
    @foreach($p->archivosFiscales as $f)
        <form id="borrar-factura-{{ $f->id }}" method="post" action="{{ route('admin.presupuestos.facturas.borrar', [$p, $f]) }}" onsubmit="return confirm({{ Js::from('¿Quitar ' . $f->nombre . '? El cliente ya no podrá descargarlo.') }})">@csrf @method('delete')</form>
    @endforeach
    <form id="duplicar" method="post" action="{{ route('admin.presupuestos.duplicar', $p) }}">@csrf</form>
    <form id="generar-codigo" method="post" action="{{ route('admin.presupuestos.codigo', $p) }}">@csrf</form>
    <form id="eliminar" method="post" action="{{ route('admin.presupuestos.destroy', $p) }}" onsubmit="return confirm({{ Js::from($p->proyecto ? '¿Eliminar ' . $p->folio . '? También se elimina su proyecto «' . $p->proyecto->nombre . '» con sus pagos y entregas, y deja de contar en tus ingresos. El enlace del cliente dejará de funcionar.' : '¿Eliminar ' . $p->folio . '? El enlace del cliente dejará de funcionar.') }})">@csrf @method('delete')</form>
    @include('admin.correos._modal', ['ctxTipo' => 'presupuesto', 'ctxId' => $p->id])
@endunless
@endsection

@push('scripts')
<script>
function editor(init) {
    return {
        ...init,
        ahora: Date.now(),
        init() {
            setInterval(() => this.ahora = Date.now(), 30000);
            if (location.hash === '#factura' && this.$root.querySelector('[data-seccion="factura"]')) this.irA('factura');
        },
        // Secciones: se recuerda en la dirección (?ver=) para volver a la misma al guardar
        irA(s) {
            this.seccion = s;
            try { const u = new URL(location.href); u.searchParams.set('ver', s); u.hash = ''; history.replaceState(history.state, '', u.href); } catch (e) {}
            const nav = this.$root.querySelector('.subnav');
            if (nav && nav.getBoundingClientRect().top < 0) nav.scrollIntoView({ block: 'start' });
        },
        // Un campo obligatorio vacío en otra sección: se abre esa sección y se señala el campo
        alInvalido(e) {
            const s = e.target.closest('[data-seccion]')?.dataset.seccion;
            if (!s || s === this.seccion) return;
            this.irA(s);
            this.$nextTick(() => { e.target.focus(); e.target.reportValidity(); });
        },
        importe(c) { return Math.round((+c.cantidad || 0) * (+c.precio || 0) * 100) / 100; },
        sugerirObservaciones() {
            // Al cambiar a un tipo con texto base, solo se llena si está vacío o tenía otro texto base
            const base = this.obsBase[this.tipo];
            const actual = this.observaciones.trim();
            const eraBase = Object.values(this.obsBase).includes(actual);
            if (base && (actual === '' || eraBase)) this.observaciones = base;
            else if (! base && eraBase) this.observaciones = '';   // no dejar el texto de otro tipo sin tocar
        },
        clon(c) { return JSON.parse(JSON.stringify(c)); },
        nuevoConcepto() {
            const c = this.clon(this.vacio);
            if (this.tiposCosteo.includes(this.tipo)) this.activarCosteo(c);
            return c;
        },
        activarCosteo(c) {
            c.costear = true;
            if (c.utilidad === '' || c.utilidad === null) { c.utilidad = this.utilidadBase; c.utilidad_modo = 'pct'; }
            // Si ya tenía precio, se respeta: la utilidad sale de ese precio en cuanto captures los costos
        },
        // Costos o utilidad cambian → precio sugerido. El precio sigue siendo editable.
        recalcularPrecio(c) { if (this.conCosteo(c)) c.precio = this.precioCosteo(c); },
        // Escribes el precio → la utilidad se ajusta (en % sobre el costo o en $, según elegiste)
        recalcularUtilidad(c) {
            if (! this.conCosteo(c)) return;
            const costo = this.costo(c), ganancia = this.importe(c) - costo;
            c.utilidad = c.utilidad_modo === 'monto' ? Math.round(ganancia * 100) / 100 : (costo > 0 ? Math.round(ganancia / costo * 10000) / 100 : '');
        },
        quitarCosteo(c) { c.costos.forEach(k => k.monto = ''); c.costear = false; },
        get usaCosteo() { return this.conceptos.some(c => this.conCosteo(c)); },
        conCosteo(c) { return c.costear && c.costos.some(k => k.monto !== '' && k.monto !== null); },
        costoClase(c, clase) {
            const n = +c.cantidad || 0;
            return Math.round(c.costos.filter(k => ! clase || k.clase === clase).reduce((s, k) => s + (+k.monto || 0) * (k.modo === 'unidad' ? n : 1), 0) * 100) / 100;
        },
        costo(c) { return this.costoClase(c, null); },
        // Misma fórmula que PresupuestoConcepto::precioDesdeCosteo
        precioCosteo(c) {
            const cant = +c.cantidad || 0, costo = this.costo(c);
            const total = c.utilidad_modo === 'monto' ? costo + (+c.utilidad || 0) : costo * (1 + (+c.utilidad || 0) / 100);
            return cant > 0 ? Math.round(total / cant * 100) / 100 : 0;
        },
        get costoTotal() { return this.conceptos.filter(c => this.conCosteo(c)).reduce((s, c) => s + this.costo(c), 0); },
        get costoTerceros() { return this.conceptos.filter(c => this.conCosteo(c)).reduce((s, c) => s + this.costoClase(c, 'tercero'), 0); },
        get costoOperativo() { return this.conceptos.filter(c => this.conCosteo(c)).reduce((s, c) => s + this.costoClase(c, 'operativo'), 0); },
        get utilidadTotal() { return this.conceptos.filter(c => this.conCosteo(c)).reduce((s, c) => s + this.importe(c) - this.costo(c), 0); },
        // Margen sobre lo vendido en los conceptos con costeo
        get margen() {
            const venta = this.conceptos.filter(c => this.conCosteo(c)).reduce((s, c) => s + this.importe(c), 0);
            return venta > 0 ? Math.round(this.utilidadTotal / venta * 1000) / 10 : null;
        },
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
