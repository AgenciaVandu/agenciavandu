@extends('admin.layout')
@section('titulo', $cliente->empresa ?: $cliente->nombre)

@php
    $secciones = \App\Http\Controllers\Admin\ClienteController::SECCIONES;
    $quien = $cliente->empresa ?: $cliente->nombre;
    $ps = $cliente->presupuestos;
    $aceptadas = $ps->where('estado', 'aceptada');
    $aceptado = $aceptadas->sum(fn ($p) => $p->subtotal);
    $pagos = $cliente->proyectos->flatMap->pagos;
    $porCobrar = $pagos->reject->pagado->sum('monto');
    $vencido = $pagos->filter->vencido->sum('monto');
    $activos = $cliente->proyectos->where('estado', '!=', 'terminado');
    $conFactura = $ps->filter(fn ($p) => $p->facturas->isNotEmpty());
    $facturaPorEnviar = $conFactura->filter(fn ($p) => ! $p->facturas->max('enviada_at'));
    $sinFactura = $aceptadas->filter(fn ($p) => $p->facturas->isEmpty());
    $porResponder = $ps->filter(fn ($p) => in_array($p->estado, ['enviada', 'negociacion'], true) && $p->vigente);
    $portal = $cliente->portal_url;
    $correos = \App\Models\Correo::where('cliente_id', $cliente->id)->latest()->take(30)->get();
    $mensajes = $cliente->mensajes()->take(20)->get();
    $conteo = ['cotizaciones' => $ps->count(), 'proyectos' => $cliente->proyectos->count(), 'facturacion' => $facturaPorEnviar->count() ?: null, 'correos' => $correos->count() ?: null];
    $url = fn ($s) => route('admin.clientes.show', $s === 'resumen' ? $cliente : [$cliente, 'ver' => $s]);
    $dinero = fn ($n) => '$' . number_format($n, 0);

    // Lo que hay que atender con este cliente, en orden de urgencia
    $pendientes = collect();
    if ($vencido > 0) $pendientes->push(['bi-exclamation-octagon', 'rojo', 'Tiene ' . $dinero($vencido) . ' de pagos vencidos', 'Mándale un recordatorio desde su proyecto.', route('admin.clientes.show', [$cliente, 'ver' => 'proyectos']), 'Ver pagos']);
    foreach ($facturaPorEnviar as $p) $pendientes->push(['bi-send', 'verde', "La factura de {$p->folio} está lista para enviar", $p->facturas_ordenadas->pluck('nombre')->join(' · '), route('admin.presupuestos.edit', [$p, 'correo' => 'factura']), 'Enviar factura']);
    // Facturas PPD: cada pago recibido lleva su complemento
    foreach ($ps as $p) {
        foreach ($p->complementos_lotes as $lote => $c) if (! $c['enviado']) $pendientes->push(['bi-receipt-cutoff', 'verde', "El complemento de pago de {$p->folio} está listo para enviar", $c['titulo'], route('admin.presupuestos.edit', [$p, 'correo' => "complemento@$lote"]), 'Enviar']);
        if ($p->metodo_sat_efectivo === 'PPD' && $p->facturas->isNotEmpty() && $p->proyecto) {
            $conComp = $p->complementos->pluck('pago_id')->filter()->all();
            foreach ($p->proyecto->pagos->filter(fn ($x) => $x->pagado && ! in_array($x->id, $conComp)) as $x)
                $pendientes->push(['bi-receipt-cutoff', 'ambar', "Falta el complemento del {$x->concepto} de {$p->folio}", 'Pagado el ' . $x->pagado_el->locale('es')->isoFormat('D [de] MMMM') . ' · ' . $x->monto_texto, route('admin.presupuestos.edit', [$p, 'ver' => 'factura']) . '#complementos', 'Subir']);
        }
    }
    foreach ($sinFactura->take(3) as $p) $pendientes->push(['bi-receipt', 'ambar', "{$p->folio} está aceptada y no tiene factura", \Illuminate\Support\Str::limit($p->conceptos->first()?->resumen ?: $p->titulo, 70), route('admin.presupuestos.edit', [$p, 'ver' => 'factura']), 'Subir factura']);
    foreach ($porResponder->take(3) as $p) $pendientes->push(['bi-hourglass-split', 'azul', "{$p->folio} espera respuesta", 'Vence el ' . $p->vigencia_local->locale('es')->isoFormat('D [de] MMMM') . ' · ' . $p->monto($p->modo_iva === 'desglosado' ? $p->total : $p->subtotal), route('admin.presupuestos.edit', [$p, 'correo' => 'por_vencer']), 'Dar seguimiento']);
    if ($aceptadas->isNotEmpty() && ! $cliente->fiscales_completos) $pendientes->push(['bi-person-vcard', 'ambar', 'Faltan sus datos fiscales', 'Los necesitas para facturar.', route('admin.clientes.edit', $cliente) . '#rfc', 'Capturar']);
@endphp

@push('head')
<style>
    .cabecera { display: flex; align-items: center; gap: 16px; min-width: 0; }
    .cabecera .avatar { width: 56px; height: 56px; font-size: 19px; border-radius: 14px; flex: none; }
    .cabecera h1 { overflow-wrap: anywhere; }

    /* Acciones rápidas: lo que más se usa, siempre a la mano */
    .rapidas { display: flex; flex-wrap: wrap; gap: 8px; margin: -6px 0 22px; }
    .rapidas .btn { display: inline-flex; align-items: center; gap: 7px; }
    .rapidas .dropdown-menu { min-width: 280px; max-height: 360px; overflow-y: auto; }
    .rapidas .dropdown-item { display: block; white-space: normal; padding: 8px 14px; }
    .rapidas .dropdown-item small { display: block; color: var(--muted); }

    .subnav { display: flex; gap: 4px; border-bottom: 1px solid var(--line); margin: 0 0 22px; overflow-x: auto; scrollbar-width: none; }
    .subnav::-webkit-scrollbar { display: none; }
    .subnav a { display: inline-flex; align-items: center; gap: 7px; padding: 10px 14px; color: var(--muted); text-decoration: none; font-weight: 500; font-size: 14.5px;
                border-bottom: 2px solid transparent; margin-bottom: -1px; white-space: nowrap; }
    .subnav a:hover { color: var(--text); }
    .subnav a.activo { color: var(--text); border-bottom-color: var(--ink); }
    .subnav a.activo i { color: var(--green-ink); }
    .subnav .n { font-size: 12px; background: var(--sunken); color: var(--text-2); border-radius: 99px; padding: 0 7px; line-height: 19px; }
    .subnav .n.alerta { background: var(--green-soft); color: var(--green-ink); }

    .ficha { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 20px; align-items: start; }
    .datos dt { font-size: 13px; color: var(--muted); font-weight: 400; }
    .datos dd { margin: 2px 0 16px; overflow-wrap: anywhere; }
    .datos dd:last-child { margin-bottom: 0; }
    .kpis { display: grid; grid-template-columns: repeat(4, 1fr); }
    .kpis > div { padding: 16px 20px; }
    .kpis > div + div { border-left: 1px solid var(--line); }
    .kpis .v { font-size: 22px; font-weight: 600; letter-spacing: -.01em; }
    .kpis .k { font-size: 13px; color: var(--muted); }
    .kpis .rojo { color: var(--red); }

    .pend { list-style: none; margin: 0; padding: 0; }
    .pend li { display: flex; align-items: center; gap: 12px; padding: 12px 20px; border-top: 1px solid var(--line); }
    .pend li:first-child { border-top: 0; }
    .pend .ico { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; flex: none; font-size: 16px; }
    .pend .ico.rojo { background: var(--red-soft, #FDECEC); color: var(--red); }
    .pend .ico.verde { background: var(--green-soft); color: var(--green-ink); }
    .pend .ico.ambar { background: var(--amber-soft); color: var(--amber); }
    .pend .ico.azul { background: #EAF1FE; color: #2456C8; }
    .pend .t { flex: 1; min-width: 0; }
    .pend .t b { display: block; font-weight: 500; }
    .pend .t span { display: block; font-size: 13px; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .al-dia { display: flex; gap: 10px; align-items: center; padding: 16px 20px; color: var(--green-ink); }

    .contacto-nuevo { border-color: #BDF2D6; background: linear-gradient(0deg, var(--green-soft), var(--green-soft)); }
    .contacto-nuevo .ico { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; background: var(--surface); color: var(--green-ink); font-size: 19px; }
    .msj-sitio { list-style: none; margin: 0; padding: 0; }
    .msj-sitio li { padding: 10px 0; border-top: 1px solid var(--line); font-size: 14px; }
    .msj-sitio li:first-child { border-top: 0; padding-top: 0; }
    .fac-fila { display: flex; align-items: center; gap: 12px; padding: 12px 20px; border-top: 1px solid var(--line); flex-wrap: wrap; }
    .fac-fila .t { flex: 1 1 200px; min-width: 0; }
    .fac-fila .archivos { font-size: 12.5px; color: var(--muted); }

    @media (max-width: 991.98px) { .ficha { grid-template-columns: minmax(0, 1fr); } .kpis { grid-template-columns: repeat(2, 1fr); } .kpis > div:nth-child(3) { border-left: 0; } .kpis > div:nth-child(n+3) { border-top: 1px solid var(--line); } }
    @media (max-width: 767.98px) {
        .subnav { flex-wrap: wrap; border-bottom: 0; gap: 6px; }
        .subnav a { border: 1px solid var(--line); border-radius: 99px; padding: 6px 12px; font-size: 13.5px; margin: 0; }
        .subnav a.activo { background: var(--ink); color: #fff; border-color: var(--ink); } .subnav a.activo i { color: var(--green); }
        .rapidas .btn { flex: 1 1 calc(50% - 8px); justify-content: center; }
        .rapidas .dropdown { flex: 1 1 calc(50% - 8px); } .rapidas .dropdown .btn { width: 100%; }
        .pend li { flex-wrap: wrap; } .pend .t { flex-basis: calc(100% - 50px); } .pend .btn { margin-left: 46px; }
    }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.clientes.index') }}">Clientes</a> <i class="bi bi-chevron-right small"></i> <span>{{ $quien }}</span></div>
<div class="page-head">
    <div class="cabecera">
        @include('admin._avatar', ['nombre' => $quien])
        <div style="min-width:0">
            <h1>{{ $quien }}</h1>
            <p class="sub">{{ $cliente->empresa ? $cliente->nombre : 'Cliente' }} · desde {{ $cliente->created_at->locale('es')->isoFormat('MMMM YYYY') }}@if($cliente->origen === 'sitio') · llegó por el sitio @endif</p>
        </div>
    </div>
    <a href="{{ route('admin.clientes.edit', $cliente) }}" class="btn btn-borde"><i class="bi bi-pencil me-1"></i> Editar</a>
</div>

{{-- Acciones rápidas --}}
<div class="rapidas">
    <a href="{{ route('admin.presupuestos.create', ['cliente' => $cliente->id]) }}" class="btn btn-primario"><i class="bi bi-plus-lg"></i> Nueva cotización</a>
    <button type="button" class="btn btn-borde" data-correo=""><i class="bi bi-envelope"></i> Enviar correo</button>
    @if($conFactura->isNotEmpty() || $sinFactura->isNotEmpty())
        <div class="dropdown">
            <button type="button" class="btn btn-borde dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-receipt"></i> Factura
                @if($facturaPorEnviar->isNotEmpty())<span class="badge rounded-pill" style="background: var(--green); color: var(--ink)">{{ $facturaPorEnviar->count() }}</span>@endif</button>
            <ul class="dropdown-menu">
                @foreach($conFactura as $p)
                    @php $env = $p->facturas->max('enviada_at'); @endphp
                    <li><a class="dropdown-item" href="{{ route('admin.presupuestos.edit', [$p, 'correo' => 'factura']) }}"><i class="bi bi-send me-1"></i> {{ $env ? 'Reenviar' : 'Enviar' }} factura de {{ $p->folio }}
                        <small>{{ $env ? 'Enviada ' . $env->locale('es')->diffForHumans() : 'Lista para enviar' }} · {{ \Illuminate\Support\Str::limit($p->conceptos->first()?->resumen ?: $p->titulo, 40) }}</small></a></li>
                @endforeach
                @if($conFactura->isNotEmpty() && $sinFactura->isNotEmpty())<li><hr class="dropdown-divider"></li>@endif
                @foreach($sinFactura as $p)
                    <li><a class="dropdown-item" href="{{ route('admin.presupuestos.edit', [$p, 'ver' => 'factura']) }}"><i class="bi bi-upload me-1"></i> Subir factura de {{ $p->folio }}
                        <small>{{ \Illuminate\Support\Str::limit($p->conceptos->first()?->resumen ?: $p->titulo, 50) }}</small></a></li>
                @endforeach
            </ul>
        </div>
    @endif
    @if($cliente->whatsapp)
        <a class="btn btn-borde" target="_blank" rel="noopener" href="https://wa.me/{{ $cliente->whatsapp }}?text={{ rawurlencode('Hola ' . \App\Support\Correos::primerNombre((string) $cliente->nombre) . ', ') }}"><i class="bi bi-whatsapp"></i> WhatsApp</a>
    @endif
    <button type="button" class="btn btn-borde" data-copiar="{{ $portal }}"><i class="bi bi-link-45deg"></i> Copiar su espacio</button>
</div>

@if($cliente->nuevo)
    <section class="panel contacto-nuevo mb-4">
        <div class="panel-body d-flex flex-wrap gap-3 align-items-center">
            <div class="ico"><i class="bi bi-inbox-fill"></i></div>
            <div class="flex-grow-1" style="min-width: 220px">
                <b>Contacto nuevo desde agenciavandu.com</b>
                <div class="secundario">{{ $cliente->interes ? 'Le interesa: ' . $cliente->interes . ' · ' : '' }}{{ ($cliente->contacto_at ?? $cliente->created_at)->copy()->setTimezone(config('vandu.zona_horaria'))->locale('es')->isoFormat('D [de] MMMM, h:mm a') }}</div>
            </div>
            <form method="post" action="{{ route('admin.clientes.atendido', $cliente) }}">@csrf @method('patch')
                <button class="btn btn-borde"><i class="bi bi-check2-circle me-1"></i> Marcar como atendido</button>
            </form>
        </div>
    </section>
@endif

<nav class="subnav" aria-label="Secciones del cliente">
    @foreach($secciones as $k => [$texto, $icono])
        <a href="{{ $url($k) }}" class="{{ $ver === $k ? 'activo' : '' }}" @if($ver === $k) aria-current="page" @endif>
            <i class="bi {{ $icono }}"></i> {{ $texto }}
            @if(! empty($conteo[$k]))<span class="n {{ $k === 'facturacion' ? 'alerta' : '' }}">{{ $conteo[$k] }}</span>@endif
        </a>
    @endforeach
</nav>

{{-- ============ RESUMEN ============ --}}
@if($ver === 'resumen')
<div class="ficha">
    <div class="d-grid gap-4">
        <div class="panel kpis">
            <div><div class="k">Aceptado, antes de IVA</div><div class="v num">{{ $dinero($aceptado) }}</div></div>
            <div><div class="k">Por cobrar</div><div class="v num {{ $vencido > 0 ? 'rojo' : '' }}">{{ $dinero($porCobrar) }}</div></div>
            <div><div class="k">Proyectos activos</div><div class="v num">{{ $activos->count() }}</div></div>
            <div><div class="k">Cotizaciones</div><div class="v num">{{ $ps->count() }}</div></div>
        </div>

        <section class="panel">
            <div class="panel-head"><h2>Por atender</h2>@if($pendientes->isNotEmpty())<span class="ayuda">{{ $pendientes->count() }}</span>@endif</div>
            @if($pendientes->isEmpty())
                <div class="al-dia"><i class="bi bi-check2-circle fs-5"></i> Todo al día con {{ $quien }}.</div>
            @else
                <ul class="pend">
                    @foreach($pendientes as [$ico, $color, $titulo, $detalle, $link, $boton])
                        <li>
                            <span class="ico {{ $color }}"><i class="bi {{ $ico }}"></i></span>
                            <span class="t"><b>{{ $titulo }}</b>@if($detalle)<span>{{ $detalle }}</span>@endif</span>
                            <a href="{{ $link }}" class="btn btn-borde btn-sm">{{ $boton }}</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Lo más reciente</h2><a href="{{ $url('cotizaciones') }}" class="btn btn-fantasma btn-sm">Ver todas</a></div>
            @if($ps->isEmpty() && $activos->isEmpty())
                <div class="vacio">
                    <div class="ico"><i class="bi bi-file-earmark-plus"></i></div>
                    <h3>Sin cotizaciones todavía</h3>
                    <p>Crea la primera para {{ $cliente->nombre }}.</p>
                    <a href="{{ route('admin.presupuestos.create', ['cliente' => $cliente->id]) }}" class="btn btn-primario">Crear cotización</a>
                </div>
            @else
                <ul class="list-unstyled m-0">
                    @foreach($activos as $pr)
                        <li class="border-top" style="border-top-color: transparent !important">
                            <a href="{{ route('admin.proyectos.show', $pr) }}" class="d-flex align-items-center gap-3 px-3 py-2 text-reset text-decoration-none">
                                <i class="bi {{ $pr->metodologia['icono'] ?? 'bi-kanban' }} fs-5 text-secondary"></i>
                                <span class="flex-grow-1" style="min-width:0"><span class="principal d-block text-truncate">{{ $pr->nombre }}</span><span class="secundario" style="font-size:13px">Proyecto · {{ $pr->siguiente_paso ?? 'Terminado' }}</span></span>
                                <span class="secundario num">{{ $pr->progreso }}%</span>
                            </a>
                        </li>
                    @endforeach
                    @foreach($ps->take(4) as $p)
                        <li class="border-top">
                            <a href="{{ route('admin.presupuestos.edit', $p) }}" class="d-flex align-items-center gap-3 px-3 py-2 text-reset text-decoration-none">
                                <i class="bi bi-file-earmark-text fs-5 text-secondary"></i>
                                <span class="flex-grow-1" style="min-width:0"><span class="principal d-block text-truncate">{{ \Illuminate\Support\Str::limit($p->conceptos->first()?->resumen ?: $p->titulo, 60) }}</span><span class="secundario num" style="font-size:13px">{{ $p->folio }} · {{ $p->monto($p->modo_iva === 'desglosado' ? $p->total : $p->subtotal) }}</span></span>
                                <span class="estado estado-{{ $p->estado }}">{{ \App\Models\Presupuesto::ESTADOS[$p->estado] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <div class="d-grid gap-4">
        <section class="panel">
            <div class="panel-head"><h2>Contacto</h2><a href="{{ route('admin.clientes.edit', $cliente) }}" class="btn btn-fantasma btn-sm">Editar</a></div>
            <dl class="panel-body datos mb-0">
                <dt>Correo</dt>
                <dd>@if($cliente->email)<a href="mailto:{{ $cliente->email }}">{{ $cliente->email }}</a>@else<span class="text-secondary">—</span>@endif</dd>
                <dt>Teléfono</dt>
                <dd class="num">@if($cliente->telefono)<a href="tel:{{ preg_replace('/\D/', '', $cliente->telefono) }}" class="text-reset">{{ $cliente->telefono }}</a>@else<span class="text-secondary">—</span>@endif</dd>
                @if($cliente->interes)<dt>Le interesa</dt><dd>{{ $cliente->interes }}</dd>@endif
            </dl>
        </section>
        <section class="panel">
            <div class="panel-head"><h2>Espacio del cliente</h2><span class="ayuda">@if($cliente->portal_visto_at)Lo abrió {{ $cliente->portal_visto_at->locale('es')->diffForHumans() }}@else Aún no lo abre @endif</span></div>
            <div class="panel-body d-grid gap-2">
                <p class="secundario m-0" style="font-size:13.5px">Un solo enlace con todas sus cotizaciones, proyectos, entregas y facturas.</p>
                <div class="d-flex gap-2">
                    <input class="form-control" style="font-size:13px" value="{{ $portal }}" readonly onclick="this.select()" aria-label="Enlace del espacio del cliente">
                    <button type="button" class="btn btn-primario text-nowrap" data-copiar="{{ $portal }}" aria-label="Copiar enlace"><i class="bi bi-copy"></i></button>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @if($cliente->whatsapp)<a class="btn btn-borde btn-sm" target="_blank" rel="noopener" href="https://wa.me/{{ $cliente->whatsapp }}?text={{ rawurlencode('Hola ' . \App\Support\Correos::primerNombre((string) $cliente->nombre) . ', aquí puedes ver todos tus proyectos y cotizaciones con nosotros: ' . $portal) }}"><i class="bi bi-whatsapp me-1"></i> Mandar por WhatsApp</a>@endif
                    <a class="btn btn-borde btn-sm" target="_blank" rel="noopener" href="{{ $portal }}?vista_previa=1"><i class="bi bi-eye me-1"></i> Ver como cliente</a>
                </div>
            </div>
        </section>
        @if($cliente->notas)
            <section class="panel">
                <div class="panel-head"><h2>Notas</h2></div>
                <div class="panel-body" style="white-space: pre-line">{{ $cliente->notas }}</div>
            </section>
        @endif
    </div>
</div>
@endif

{{-- ============ COTIZACIONES ============ --}}
@if($ver === 'cotizaciones')
<section class="panel">
    <div class="panel-head"><h2>Cotizaciones</h2>
        <span class="ayuda">{{ $porResponder->count() }} esperando respuesta · {{ $aceptadas->count() }} aceptadas</span></div>
    @if($ps->isEmpty())
        <div class="vacio">
            <div class="ico"><i class="bi bi-file-earmark-plus"></i></div>
            <h3>Sin cotizaciones todavía</h3>
            <p>Crea la primera para {{ $cliente->nombre }}.</p>
            <a href="{{ route('admin.presupuestos.create', ['cliente' => $cliente->id]) }}" class="btn btn-primario">Crear cotización</a>
        </div>
    @else
        <div class="table-responsive">
            <table class="tabla">
                <thead><tr><th>Folio</th><th>Concepto</th><th class="text-end">Importe</th><th>Vigencia</th><th>Estado</th><th>Factura</th><th><span class="visually-hidden">Acciones</span></th></tr></thead>
                <tbody>
                @foreach($ps as $p)
                    @php $env = $p->facturas->max('enviada_at'); @endphp
                    <tr>
                        <td class="text-nowrap"><a href="{{ route('admin.presupuestos.edit', $p) }}" class="fila-link"><span class="principal num">{{ $p->folio }}</span></a>
                            <div class="secundario">{{ $p->fecha->locale('es')->isoFormat('D MMM YYYY') }}</div></td>
                        <td class="secundario" style="min-width: 180px">{{ \Illuminate\Support\Str::limit($p->conceptos->first()?->resumen, 60) }}</td>
                        <td class="text-end num fw-medium text-nowrap">{{ $p->monto($p->modo_iva === 'desglosado' ? $p->total : $p->subtotal) }}</td>
                        <td>@include('admin.presupuestos._vigencia', ['p' => $p])</td>
                        <td><span class="estado estado-{{ $p->estado }}">{{ \App\Models\Presupuesto::ESTADOS[$p->estado] }}</span></td>
                        <td class="text-nowrap" style="font-size:13px">
                            @if($p->facturas->isNotEmpty())
                                <span class="{{ $env ? 'text-success' : '' }}"><i class="bi {{ $env ? 'bi-check2-circle' : 'bi-receipt' }}"></i> {{ $env ? 'Enviada' : 'Por enviar' }}</span>
                            @elseif($p->estado === 'aceptada')
                                <a href="{{ route('admin.presupuestos.edit', [$p, 'ver' => 'factura']) }}" class="secundario">Subir</a>
                            @else<span class="secundario">—</span>@endif
                        </td>
                        <td class="text-end text-nowrap">
                            <div class="dropdown d-inline-block">
                                <button type="button" class="btn btn-fantasma btn-icono" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Acciones de {{ $p->folio }}"><i class="bi bi-three-dots"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="{{ route('admin.presupuestos.edit', [$p, 'correo' => 'cotizacion']) }}"><i class="bi bi-envelope me-2"></i>Enviar por correo</a></li>
                                    @if($p->facturas->isNotEmpty())<li><a class="dropdown-item" href="{{ route('admin.presupuestos.edit', [$p, 'correo' => 'factura']) }}"><i class="bi bi-receipt me-2"></i>{{ $env ? 'Reenviar factura' : 'Enviar factura' }}</a></li>
                                    @elseif($p->estado === 'aceptada')<li><a class="dropdown-item" href="{{ route('admin.presupuestos.edit', [$p, 'ver' => 'factura']) }}"><i class="bi bi-upload me-2"></i>Subir factura</a></li>@endif
                                    @if($cliente->whatsapp)<li><a class="dropdown-item" href="{{ route('admin.presupuestos.whatsapp', $p) }}" target="_blank" rel="noopener"><i class="bi bi-whatsapp me-2"></i>Enviar por WhatsApp</a></li>@endif
                                    <li><button type="button" class="dropdown-item" data-copiar="{{ $p->url_publica }}"><i class="bi bi-link-45deg me-2"></i>Copiar enlace</button></li>
                                    <li><a class="dropdown-item" href="{{ route('admin.presupuestos.pdf', $p) }}" target="_blank"><i class="bi bi-file-earmark-pdf me-2"></i>Ver PDF</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endif

{{-- ============ PROYECTOS ============ --}}
@if($ver === 'proyectos')
<section class="panel">
    <div class="panel-head"><h2>Proyectos</h2>@if($porCobrar > 0)<span class="ayuda">Por cobrar {{ $dinero($porCobrar) }}@if($vencido > 0) · <span class="text-danger">{{ $dinero($vencido) }} vencido</span>@endif</span>@endif</div>
    @if($cliente->proyectos->isEmpty())
        <div class="vacio">
            <div class="ico"><i class="bi bi-kanban"></i></div>
            <h3>Sin proyectos</h3>
            <p>Cuando acepte una cotización, conviértela en proyecto desde la cotización.</p>
        </div>
    @else
        <ul class="list-unstyled m-0">
            @foreach($cliente->proyectos->sortBy(fn ($p) => [$p->estado === 'terminado' ? 1 : 0, -$p->id]) as $pr)
                @php $pend = $pr->pagos->reject->pagado; $venc = $pr->pagos->filter->vencido; @endphp
                <li class="{{ $loop->first ? '' : 'border-top' }}">
                    <a href="{{ route('admin.proyectos.show', $pr) }}" class="d-block px-3 py-3 text-decoration-none text-reset">
                        <div class="d-flex justify-content-between gap-2"><span class="principal text-truncate">{{ $pr->nombre }}</span><span class="secundario num">{{ $pr->progreso }}%</span></div>
                        <div class="secundario"><i class="bi {{ $pr->metodologia['icono'] ?? 'bi-kanban' }} me-1"></i>{{ $pr->tipo_nombre }} · {{ $pr->siguiente_paso ?? 'Terminado' }}</div>
                        @if($pend->isNotEmpty())
                            <div style="font-size:13px" class="mt-1 {{ $venc->isNotEmpty() ? 'text-danger' : 'secundario' }}"><i class="bi bi-cash-coin me-1"></i>{{ $pend->count() }} {{ $pend->count() === 1 ? 'pago pendiente' : 'pagos pendientes' }} · {{ $dinero($pend->sum('monto')) }}@if($venc->isNotEmpty()) · {{ $venc->count() }} vencido @endif</div>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
@endif

{{-- ============ FACTURACIÓN ============ --}}
@if($ver === 'facturacion')
<div class="ficha">
    <div class="d-grid gap-4">
        <section class="panel">
            <div class="panel-head"><h2>Facturas</h2><span class="ayuda">Se suben y envían desde cada cotización</span></div>
            @if($conFactura->isEmpty() && $sinFactura->isEmpty())
                <div class="panel-body secundario">Aún no hay cotizaciones aceptadas que facturar.</div>
            @else
                @foreach($conFactura as $p)
                    @php $env = $p->facturas->max('enviada_at'); @endphp
                    <div class="fac-fila" @if($loop->first) style="border-top:0" @endif>
                        <i class="bi bi-receipt fs-5 {{ $env ? 'text-success' : 'text-secondary' }}"></i>
                        <div class="t">
                            <a href="{{ route('admin.presupuestos.edit', [$p, 'ver' => 'factura']) }}" class="principal text-reset text-decoration-none num">{{ $p->folio }}</a>
                            <span class="secundario"> · {{ \Illuminate\Support\Str::limit($p->conceptos->first()?->resumen ?: $p->titulo, 50) }}</span>
                            <div class="archivos">
                                @foreach($p->facturas_ordenadas as $f)<a href="{{ route('admin.presupuestos.facturas.ver', [$p, $f]) }}" target="_blank" class="text-reset me-2"><i class="bi {{ $f->icono }}"></i> {{ $f->nombre }}</a>@endforeach
                                · {{ $env ? 'Enviada ' . $env->locale('es')->diffForHumans() : 'Por enviar' }}
                            </div>
                        </div>
                        <a href="{{ route('admin.presupuestos.edit', [$p, 'correo' => 'factura']) }}" class="btn {{ $env ? 'btn-borde' : 'btn-primario' }} btn-sm"><i class="bi bi-send me-1"></i> {{ $env ? 'Reenviar' : 'Enviar factura' }}</a>
                    </div>
                @endforeach
                @foreach($sinFactura as $p)
                    <div class="fac-fila" @if($loop->first && $conFactura->isEmpty()) style="border-top:0" @endif>
                        <i class="bi bi-receipt-cutoff fs-5 text-secondary"></i>
                        <div class="t">
                            <a href="{{ route('admin.presupuestos.edit', [$p, 'ver' => 'factura']) }}" class="principal text-reset text-decoration-none num">{{ $p->folio }}</a>
                            <span class="secundario"> · {{ \Illuminate\Support\Str::limit($p->conceptos->first()?->resumen ?: $p->titulo, 50) }}</span>
                            <div class="archivos">Aceptada · sin factura</div>
                        </div>
                        <a href="{{ route('admin.presupuestos.edit', [$p, 'ver' => 'factura']) }}" class="btn btn-borde btn-sm"><i class="bi bi-upload me-1"></i> Subir factura</a>
                    </div>
                @endforeach
            @endif
        </section>

        <section class="panel" id="constancias">
            <div class="panel-head"><h2>Constancia de Situación Fiscal</h2><span class="ayuda">{{ $cliente->constancias->count() ?: '' }}</span></div>
            @if($cliente->constancias->isNotEmpty())
                <ul class="list-unstyled m-0 constancias">
                    @foreach($cliente->constancias as $c)
                        <li class="d-flex align-items-center gap-2 px-3 py-2 {{ $loop->first ? '' : 'border-top' }}">
                            <i class="bi {{ $c->es_pdf ? 'bi-file-earmark-pdf' : 'bi-file-earmark-image' }} fs-5 {{ $loop->first ? 'text-danger' : 'text-secondary' }}"></i>
                            <a href="{{ route('admin.clientes.constancias.show', [$cliente, $c]) }}" target="_blank" class="flex-grow-1 text-reset text-decoration-none" style="min-width:0">
                                <div class="text-truncate fw-medium" style="font-size:14px">{{ $c->nombre }}</div>
                                <div class="secundario num" style="font-size:12.5px">
                                    @if($loop->first)<span class="text-success">Vigente</span> · @endif
                                    {{ $c->emitida_el ? 'Emitida ' . $c->emitida_el->locale('es')->isoFormat('D MMM YYYY') : 'Subida ' . $c->created_at->timezone(config('vandu.zona_horaria'))->locale('es')->isoFormat('D MMM YYYY') }} · {{ $c->peso_texto }}
                                </div>
                            </a>
                            <a href="{{ route('admin.clientes.constancias.show', [$cliente, $c]) }}?descargar=1" class="btn btn-fantasma btn-icono" title="Descargar" aria-label="Descargar {{ $c->nombre }}" data-recargar><i class="bi bi-download"></i></a>
                            <form method="post" action="{{ route('admin.clientes.constancias.destroy', [$cliente, $c]) }}" onsubmit="return confirm('¿Eliminar {{ $c->nombre }} del expediente?')">@csrf @method('delete')
                                <button class="btn btn-fantasma btn-icono" title="Eliminar" aria-label="Eliminar {{ $c->nombre }}"><i class="bi bi-trash"></i></button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
            <form method="post" enctype="multipart/form-data" action="{{ route('admin.clientes.constancias.store', $cliente) }}" class="px-3 pb-3 pt-2 subir-constancia">
                @csrf
                <label class="btn btn-borde btn-sm w-100 mb-0">
                    <i class="bi bi-upload me-1"></i> {{ $cliente->constancias->isEmpty() ? 'Subir constancia' : 'Subir una más reciente' }}
                    <input type="file" name="constancia" accept="application/pdf,image/jpeg,image/png" class="visually-hidden" onchange="this.form.requestSubmit()">
                </label>
                @error('constancia')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </form>
        </section>
    </div>

    <div class="d-grid gap-4">
        <section class="panel" id="facturacion">
            <div class="panel-head">
                <h2>Datos fiscales</h2>
                @if($cliente->fiscales_completos)<span class="estado estado-aceptada">Completos</span>
                @elseif($cliente->rfc || $cliente->razon_social)<span class="estado estado-enviada">Incompletos</span>@endif
            </div>
            @if($cliente->rfc || $cliente->razon_social || $cliente->metodo_pago)
                <dl class="panel-body datos mb-0">
                    <dt>RFC</dt><dd class="num">{{ $cliente->rfc ?: '—' }}</dd>
                    <dt>Razón social</dt><dd>{{ $cliente->razon_social ?: '—' }}</dd>
                    <dt>Régimen fiscal</dt><dd>{{ $cliente->regimen_texto ?: '—' }}</dd>
                    <dt>C.P. fiscal</dt><dd class="num">{{ $cliente->cp_fiscal ?: '—' }}</dd>
                    <dt>Uso de CFDI</dt><dd>{{ $cliente->uso_cfdi_texto ?: '—' }}</dd>
                    <dt>Método de pago</dt><dd>{{ $cliente->metodo_pago_texto ?: '—' }}@if($cliente->metodo_pago === 'credito' && $cliente->dias_credito) a {{ $cliente->dias_credito }} días @endif</dd>
                    <dt>Correo para la factura</dt><dd>{{ $cliente->email_factura ?: ($cliente->email ? $cliente->email . ' (contacto)' : '—') }}</dd>
                </dl>
                <div class="px-3 pb-3 d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-borde btn-sm" data-copiar="{{ $cliente->fiscales_texto }}"><i class="bi bi-clipboard me-1"></i> Copiar datos</button>
                    <a href="{{ route('admin.clientes.edit', $cliente) }}#rfc" class="btn btn-fantasma btn-sm"><i class="bi bi-pencil me-1"></i> Editar</a>
                </div>
            @else
                <div class="panel-body secundario">Sin datos de facturación. <a href="{{ route('admin.clientes.edit', $cliente) }}#rfc">Capturarlos</a></div>
            @endif
        </section>
    </div>
</div>
@endif

{{-- ============ CORREOS Y MENSAJES ============ --}}
@if($ver === 'correos')
<div class="ficha">
    <div class="d-grid gap-4">
        @include('admin.correos._historial', ['correos' => $correos, 'plantilla' => ''])
    </div>
    <div class="d-grid gap-4">
        <section class="panel">
            <div class="panel-head"><h2>Mensajes desde el sitio</h2>@if($mensajes->isNotEmpty())<span class="ayuda">{{ $mensajes->count() }}</span>@endif</div>
            <div class="panel-body">
                @if($mensajes->isEmpty())
                    <span class="secundario" style="font-size:14px">No ha escrito desde el formulario del sitio.</span>
                @else
                    <ul class="msj-sitio">
                        @foreach($mensajes as $m)
                            <li><b class="fw-medium">{{ $m->servicio ?: 'Sin servicio' }}</b>
                                <div class="secundario">{{ $m->created_at->copy()->setTimezone(config('vandu.zona_horaria'))->locale('es')->isoFormat('D MMM YYYY, h:mm a') }} · {{ $m->datos['phone'] ?? '' }}</div></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    </div>
</div>
@endif

@include('admin.correos._modal', ['ctxTipo' => 'cliente', 'ctxId' => $cliente->id])
@endsection
