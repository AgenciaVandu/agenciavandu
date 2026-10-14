@extends('admin.layout')
@section('titulo', 'Clientes')

@php
    $vistas = \App\Http\Controllers\Admin\ClienteController::VISTAS;
    $ordenes = \App\Http\Controllers\Admin\ClienteController::ORDENES;
    $dinero = fn ($n) => '$' . number_format((float) $n, 0);
    $url = fn (array $extra = []) => route('admin.clientes.index', array_filter(array_merge(['ver' => $ver === 'todos' ? null : $ver, 'orden' => $orden === 'reciente' ? null : $orden, 'q' => $q ?: null], $extra), fn ($v) => $v !== null && $v !== ''));
    $zona = config('vandu.zona_horaria');
@endphp

@push('head')
<style>
    .cl-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 22px; }
    .cl-kpi { display: flex; gap: 14px; align-items: center; background: var(--surface); border: 1px solid var(--line); border-radius: 16px; padding: 14px 16px; text-decoration: none; color: var(--text); transition: border-color .15s, box-shadow .15s; }
    a.cl-kpi:hover { border-color: var(--line-strong); box-shadow: 0 6px 18px rgba(17, 24, 39, .06); color: var(--text); }
    .cl-kpi.activo { border-color: var(--ink); box-shadow: inset 0 0 0 1px var(--ink); }
    .cl-kpi .ic { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; font-size: 18px; flex: none; background: var(--sunken); color: var(--text-2); }
    .cl-kpi .v { font-size: 22px; font-weight: 600; letter-spacing: -.01em; line-height: 1.15; }
    .cl-kpi .k { font-size: 13px; color: var(--muted); }
    .cl-kpi.verde .ic { background: var(--green-soft); color: var(--green-ink); }
    .cl-kpi.azul .ic { background: #EAF1FE; color: #2456C8; }
    .cl-kpi.rojo .ic { background: #FDECEC; color: var(--red); } .cl-kpi.rojo .v { color: var(--red); }

    .cl-barra { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
    .cl-pills { display: inline-flex; flex-wrap: wrap; gap: 6px; }
    .cl-pills a { display: inline-flex; align-items: center; gap: 6px; padding: 7px 13px; border-radius: 99px; border: 1px solid var(--line); background: var(--surface); font-size: 13.5px; color: var(--text-2); text-decoration: none; white-space: nowrap; }
    .cl-pills a:hover { border-color: var(--line-strong); color: var(--text); }
    .cl-pills a.activo { background: var(--ink); border-color: var(--ink); color: #fff; }
    .cl-pills a.verde:not(.activo) { border-color: #BDF2D6; background: var(--green-soft); color: var(--green-ink); }
    .cl-pills .n { font-size: 12px; opacity: .65; }
    .cl-herr { display: flex; gap: 8px; flex-wrap: wrap; }
    .cl-buscar { position: relative; min-width: 260px; }
    .cl-buscar i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--muted); }
    .cl-buscar input { padding-left: 36px; border-radius: 99px; }
    .cl-orden { border-radius: 99px !important; width: auto; font-size: 13.5px; padding-left: 14px; padding-right: 2.3rem !important; background-position: right .8rem center; }

    .cl-lista { background: var(--surface); border: 1px solid var(--line); border-radius: 16px; }
    .cl-fila { position: relative; display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1.4fr) 120px 120px minmax(0, 1.5fr) 180px; gap: 16px; align-items: center; padding: 14px 18px; border-top: 1px solid var(--line); }
    .cl-fila:first-child { border-top: 0; }
    .cl-fila:hover { background: #FAFBFC; }
    .cl-fila.cabeza { padding-top: 10px; padding-bottom: 10px; font-size: 12.5px; color: var(--muted); background: var(--sunken); border-radius: 16px 16px 0 0; }
    .cl-fila.cabeza:hover { background: var(--sunken); }
    .cl-fila.es-nuevo { box-shadow: inset 3px 0 0 var(--green); }
    .cl-quien { display: flex; align-items: center; gap: 12px; min-width: 0; text-decoration: none; color: var(--text); }
    .cl-quien::after { content: ""; position: absolute; inset: 0; }   /* toda la fila abre al cliente */
    .cl-quien .t { min-width: 0; }
    .cl-quien .t > b { display: block; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cl-quien .t > span { display: block; font-size: 13px; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .etq-nuevo { display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px; font-weight: 600; padding: 1px 8px; border-radius: 99px; background: var(--green-soft); color: var(--green-ink); vertical-align: 2px; margin-left: 6px; }
    .cl-contacto { font-size: 13px; min-width: 0; }
    .cl-contacto div { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cl-contacto .s { color: var(--muted); }
    .cl-num { text-align: right; font-weight: 600; font-size: 14.5px; }
    .cl-num .s { display: block; font-size: 12px; color: var(--muted); font-weight: 400; }
    .cl-num.rojo { color: var(--red); }
    .cl-num.cero { color: var(--faint, #B8BCC4); font-weight: 400; }
    .cl-pend { display: flex; flex-wrap: wrap; gap: 5px; position: relative; z-index: 1; }
    .cl-tag { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 99px; font-size: 12px; font-weight: 500; text-decoration: none; white-space: nowrap; }
    .cl-tag.ambar { background: var(--amber-soft); color: var(--amber); }
    .cl-tag.azul { background: #EAF1FE; color: #2456C8; }
    .cl-tag.verde { background: var(--green-soft); color: var(--green-ink); }
    .cl-tag.gris { background: var(--sunken); color: var(--text-2); }
    .cl-tag:hover { filter: brightness(.96); }
    .cl-acc .btn { white-space: nowrap; }
    .cl-acc { display: flex; justify-content: flex-end; gap: 2px; position: relative; z-index: 2; }
    .cl-acc:has(.dropdown-menu.show) { z-index: 20; }   /* el menú abierto queda encima de la fila de abajo */

    .cl-vacio { text-align: center; padding: 48px 20px; }
    .cl-vacio .ico { width: 56px; height: 56px; border-radius: 16px; display: grid; place-items: center; margin: 0 auto 14px; background: var(--sunken); font-size: 24px; color: var(--text-2); }
    .cl-vacio h3 { font-size: 17px; margin-bottom: 6px; } .cl-vacio p { color: var(--muted); margin-bottom: 16px; }

    @media (max-width: 1199.98px) {
        .cl-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .cl-fila { grid-template-columns: minmax(0, 2fr) 110px 110px minmax(0, 1.4fr) 170px; }
        .cl-contacto { display: none; }
    }
    @media (max-width: 991.98px) {
        .cl-fila.cabeza { display: none; }
        .cl-fila { grid-template-columns: minmax(0, 1fr) auto; gap: 8px 12px; padding: 14px 16px; }
        .cl-fila:nth-child(2) { border-top: 0; }
        .cl-num.valor { grid-column: 2; grid-row: 1; }
        .cl-num.cobrar { display: none; }
        .cl-pend { grid-column: 1; padding-left: 52px; }
        .cl-acc { grid-column: 2; }
        .cl-buscar { min-width: 0; flex: 1; }
    }
    @media (max-width: 575.98px) {
        .cl-kpi { padding: 12px; gap: 10px; } .cl-kpi .ic { width: 34px; height: 34px; font-size: 15px; } .cl-kpi .v { font-size: 18px; }
        .cl-pills { flex-wrap: nowrap; overflow-x: auto; max-width: 100%; scrollbar-width: none; } .cl-pills::-webkit-scrollbar { display: none; }
        .cl-barra > * { width: 100%; }
    }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <h1>Clientes</h1>
        <p class="sub">Quién te compra, cuánto vale cada uno y qué tienes pendiente con ellos.</p>
    </div>
    <a href="{{ route('admin.clientes.create') }}" class="btn btn-primario"><i class="bi bi-person-plus me-1"></i> Nuevo cliente</a>
</div>

@if($resumen['total'])
    <div class="cl-kpis">
        <div class="cl-kpi"><span class="ic"><i class="bi bi-people"></i></span>
            <div><div class="v num">{{ $resumen['total'] }}</div><div class="k">Clientes{{ $resumen['nuevosMes'] ? ' · ' . $resumen['nuevosMes'] . ' este mes' : '' }}</div></div></div>
        <a href="{{ $url(['ver' => 'activos']) }}" class="cl-kpi verde {{ $ver === 'activos' ? 'activo' : '' }}"><span class="ic"><i class="bi bi-kanban"></i></span>
            <div><div class="v num">{{ $resumen['activos'] }}</div><div class="k">Con proyecto en marcha</div></div></a>
        <a href="{{ $url(['ver' => 'por_cobrar', 'orden' => 'cobrar']) }}" class="cl-kpi {{ $resumen['vencido'] > 0 ? 'rojo' : 'azul' }} {{ $ver === 'por_cobrar' ? 'activo' : '' }}"><span class="ic"><i class="bi bi-cash-coin"></i></span>
            <div><div class="v num">{{ $dinero($resumen['porCobrar']) }}</div><div class="k">Por cobrar{{ $resumen['vencido'] > 0 ? ' · ' . $dinero($resumen['vencido']) . ' vencido' : '' }}</div></div></a>
        <a href="{{ $url(['orden' => 'valor', 'ver' => null]) }}" class="cl-kpi {{ $orden === 'valor' ? 'activo' : '' }}"><span class="ic"><i class="bi bi-graph-up-arrow"></i></span>
            <div><div class="v num">{{ $dinero($resumen['anio']) }}</div><div class="k">Aceptado en {{ now($zona)->year }}</div></div></a>
    </div>
@endif

<div class="cl-barra">
    <nav class="cl-pills" aria-label="Filtrar clientes">
        @foreach($vistas as $k => $label)
            @continue($k === 'nuevos' && ! $conteos['nuevos'] && $ver !== 'nuevos')
            <a href="{{ $url(['ver' => $k === 'todos' ? null : $k]) }}" class="{{ $ver === $k ? 'activo' : '' }} {{ $k === 'nuevos' ? 'verde' : '' }}" @if($ver === $k) aria-current="true" @endif>{{ $label }} <span class="n num">{{ $conteos[$k] }}</span></a>
        @endforeach
    </nav>
    <div class="cl-herr">
        <select class="form-select form-select-sm cl-orden" aria-label="Ordenar" onchange="location.href = this.value">
            @foreach($ordenes as $k => $label)<option value="{{ $url(['orden' => $k === 'reciente' ? null : $k]) }}" @selected($orden === $k)>{{ $label }}</option>@endforeach
        </select>
        <form class="cl-buscar" method="get" role="search">
            @if($ver !== 'todos')<input type="hidden" name="ver" value="{{ $ver }}">@endif
            @if($orden !== 'reciente')<input type="hidden" name="orden" value="{{ $orden }}">@endif
            <i class="bi bi-search"></i>
            <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Nombre, empresa, correo, teléfono o RFC" aria-label="Buscar clientes">
        </form>
    </div>
</div>

<div class="cl-lista">
    @if($clientes->isEmpty())
        <div class="cl-vacio">
            @if($q)
                <div class="ico"><i class="bi bi-search"></i></div>
                <h3>Ningún cliente coincide con “{{ $q }}”</h3>
                <p>Revisa la ortografía o busca por correo, teléfono o RFC.</p>
                <a href="{{ $url(['q' => null]) }}" class="btn btn-borde">Limpiar búsqueda</a>
            @elseif(! $resumen['total'])
                <div class="ico"><i class="bi bi-people"></i></div>
                <h3>Todavía no tienes clientes</h3>
                <p>Agrega el primero para empezar a cotizar. Los que lleguen por el sitio entran a Ventas como prospectos.</p>
                <a href="{{ route('admin.clientes.create') }}" class="btn btn-primario">Agregar cliente</a>
            @else
                <div class="ico"><i class="bi bi-check2-circle"></i></div>
                <h3>Nadie en «{{ $vistas[$ver] }}»</h3>
                <p>No hay clientes en esta vista ahora mismo.</p>
                <a href="{{ $url(['ver' => null]) }}" class="btn btn-primario">Ver todos ({{ $resumen['total'] }})</a>
            @endif
        </div>
    @else
        <div class="cl-fila cabeza" aria-hidden="true"><span>Cliente</span><span>Contacto</span><span class="text-end">Aceptado</span><span class="text-end">Por cobrar</span><span>Pendiente</span><span></span></div>
        @foreach($clientes as $c)
            @php
                $quien = $c->empresa ?: $c->nombre;
                $ps = $c->presupuestos;
                $esperan = $ps->filter(fn ($p) => $p->estado === 'negociacion' || ($p->estado === 'enviada' && $p->vigente_hasta > now()));
                $facPorEnviar = $ps->filter(fn ($p) => $p->facturas->isNotEmpty() && ! $p->facturas->max('enviada_at'));
                $sinFactura = $ps->filter(fn ($p) => $p->estado === 'aceptada' && $p->facturas->isEmpty());
                $ultima = \Illuminate\Support\Carbon::parse($c->ultima_actividad);
                $porCobrar = (float) $c->por_cobrar;
            @endphp
            <div class="cl-fila {{ $c->nuevo ? 'es-nuevo' : '' }}">
                <a href="{{ route('admin.clientes.show', $c) }}" class="cl-quien">
                    @include('admin._avatar', ['nombre' => $quien])
                    <span class="t">
                        <b>{{ $quien }}@if($c->nuevo)<span class="etq-nuevo">Nuevo</span>@endif</b>
                        <span>{{ $c->empresa ? $c->nombre . ' · ' : '' }}{{ $c->presupuestos_count }} {{ $c->presupuestos_count === 1 ? 'cotización' : 'cotizaciones' }} · {{ $ultima->locale('es')->diffForHumans() }}</span>
                    </span>
                </a>
                <div class="cl-contacto">
                    @if($c->email)<div>{{ $c->email }}</div>@endif
                    @if($c->telefono)<div class="s num">{{ $c->telefono }}</div>@endif
                    @if(! $c->email && ! $c->telefono)<div class="s">Sin datos de contacto</div>@endif
                </div>
                <div class="cl-num valor num {{ $c->valor_aceptado > 0 ? '' : 'cero' }}">{{ $c->valor_aceptado > 0 ? $dinero($c->valor_aceptado) : '—' }}<span class="s">{{ $c->valor_aceptado > 0 ? '+ IVA' : '' }}</span></div>
                <div class="cl-num cobrar num {{ $porCobrar > 0 ? '' : 'cero' }}">{{ $porCobrar > 0 ? $dinero($porCobrar) : '—' }}</div>
                <div class="cl-pend">
                    @if($c->activos_count)<a href="{{ route('admin.clientes.show', [$c, 'ver' => 'proyectos']) }}" class="cl-tag verde"><i class="bi bi-kanban"></i> {{ $c->activos_count }} en marcha</a>@endif
                    @if($esperan->isNotEmpty())<a href="{{ route('admin.clientes.show', [$c, 'ver' => 'cotizaciones']) }}" class="cl-tag azul"><i class="bi bi-hourglass-split"></i> {{ $esperan->count() }} por responder</a>@endif
                    @foreach($facPorEnviar->take(1) as $p)<a href="{{ route('admin.presupuestos.edit', [$p, 'correo' => 'factura']) }}" class="cl-tag ambar"><i class="bi bi-send"></i> Enviar factura</a>@endforeach
                    @if($sinFactura->isNotEmpty())<a href="{{ route('admin.clientes.show', [$c, 'ver' => 'facturacion']) }}" class="cl-tag ambar"><i class="bi bi-receipt"></i> {{ $sinFactura->count() }} sin factura</a>@endif
                    @if($c->nuevo)<span class="cl-tag gris">{{ $c->interes ?: 'Llegó por el sitio' }}</span>@endif
                </div>
                <div class="cl-acc">
                    @if($c->whatsapp)<a class="btn btn-fantasma btn-icono" target="_blank" rel="noopener" href="https://wa.me/{{ $c->whatsapp }}?text={{ rawurlencode('Hola ' . \App\Support\Correos::primerNombre((string) $c->nombre) . ', ') }}" title="WhatsApp" aria-label="WhatsApp a {{ $quien }}"><i class="bi bi-whatsapp"></i></a>@endif
                    <a href="{{ route('admin.presupuestos.create', ['cliente' => $c->id]) }}" class="btn btn-borde btn-sm" title="Nueva cotización"><i class="bi bi-plus-lg"></i> Cotizar</a>
                    <div class="dropdown">
                        <button class="btn btn-fantasma btn-icono" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Más acciones para {{ $quien }}"><i class="bi bi-three-dots"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('admin.clientes.show', $c) }}"><i class="bi bi-person"></i> Abrir ficha</a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.clientes.show', [$c, 'correo' => '']) }}"><i class="bi bi-envelope"></i> Enviar correo</a></li>
                            <li><button type="button" class="dropdown-item" data-copiar="{{ $c->portal_url }}"><i class="bi bi-link-45deg"></i> Copiar enlace de su espacio</button></li>
                            <li><a class="dropdown-item" href="{{ route('admin.clientes.edit', $c) }}"><i class="bi bi-pencil"></i> Editar datos</a></li>
                            @if($c->nuevo)
                                <li><hr class="dropdown-divider"></li>
                                <li><form method="post" action="{{ route('admin.clientes.atendido', $c) }}">@csrf @method('patch')
                                    <button class="dropdown-item"><i class="bi bi-check2-circle"></i> Marcar como atendido</button></form></li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>
@if($clientes->hasPages())
    <div class="mt-3">{{ $clientes->links('pagination::bootstrap-5') }}</div>
@endif
@endsection
