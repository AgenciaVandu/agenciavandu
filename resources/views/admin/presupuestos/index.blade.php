@extends('admin.layout')
@section('titulo', 'Cotizaciones')

@php
    $vistas = \App\Http\Controllers\Admin\PresupuestoController::VISTAS;
    $dinero = fn ($n) => '$' . number_format($n, 0);
    $url = fn (array $extra = []) => route('admin.presupuestos.index', array_filter(array_merge(['filtro' => $filtro === 'todas' ? null : $filtro, 'q' => $q ?: null], $extra), fn ($v) => $v !== null && $v !== ''));
    $hayAlguna = $conteos['todas'] > 0;
@endphp

@push('head')
<style>
    .cq-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 22px; }
    .cq-kpi { display: flex; gap: 14px; align-items: center; background: var(--surface); border: 1px solid var(--line); border-radius: 16px; padding: 14px 16px;
              text-decoration: none; color: var(--text); transition: border-color .15s, box-shadow .15s; }
    .cq-kpi:hover { border-color: var(--line-strong); box-shadow: 0 6px 18px rgba(17, 24, 39, .06); color: var(--text); }
    .cq-kpi.activo { border-color: var(--ink); box-shadow: inset 0 0 0 1px var(--ink); }
    .cq-kpi .ic { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; font-size: 18px; flex: none; background: var(--sunken); color: var(--text-2); }
    .cq-kpi .v { font-size: 22px; font-weight: 600; letter-spacing: -.01em; line-height: 1.15; }
    .cq-kpi .k { font-size: 13px; color: var(--muted); }
    .cq-kpi.azul .ic { background: #EAF1FE; color: #2456C8; }
    .cq-kpi.ambar .ic { background: var(--amber-soft); color: var(--amber); }
    .cq-kpi.verde .ic { background: var(--green-soft); color: var(--green-ink); }

    .cq-barra { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
    .cq-pills { display: inline-flex; flex-wrap: wrap; gap: 6px; }
    .cq-pills a { display: inline-flex; align-items: center; gap: 6px; padding: 7px 13px; border-radius: 99px; border: 1px solid var(--line); background: var(--surface);
                  font-size: 13.5px; color: var(--text-2); text-decoration: none; white-space: nowrap; }
    .cq-pills a:hover { border-color: var(--line-strong); color: var(--text); }
    .cq-pills a.activo { background: var(--ink); border-color: var(--ink); color: #fff; }
    .cq-pills .n { font-size: 12px; opacity: .65; }
    .cq-buscar { position: relative; min-width: 260px; }
    .cq-buscar i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: var(--muted); }
    .cq-buscar input { padding-left: 36px; border-radius: 99px; }

    /* Lista */
    .cq-lista { background: var(--surface); border: 1px solid var(--line); border-radius: 16px; overflow: visible; }
    .cq-fila { display: grid; grid-template-columns: minmax(0, 2.2fr) minmax(0, 1.1fr) 130px minmax(0, 1.3fr) 112px; gap: 16px; align-items: center; padding: 14px 18px; border-top: 1px solid var(--line); position: relative; }
    .cq-fila:first-child { border-top: 0; }
    .cq-fila:hover { background: #FAFBFC; }
    .cq-fila.cabeza { padding-top: 10px; padding-bottom: 10px; font-size: 12.5px; color: var(--muted); background: var(--sunken); border-radius: 16px 16px 0 0; }
    .cq-fila.cabeza:hover { background: var(--sunken); }
    .cq-quien { display: flex; align-items: center; gap: 12px; min-width: 0; text-decoration: none; color: var(--text); }
    .cq-quien::after { content: ""; position: absolute; inset: 0; }   /* toda la fila abre la cotización */
    .cq-quien .t { min-width: 0; }
    .cq-quien .t > b { display: block; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cq-quien .t > span { display: block; font-size: 13px; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cq-folio { font-size: 13.5px; } .cq-folio .s { font-size: 12.5px; color: var(--muted); }
    .cq-monto { text-align: right; font-weight: 600; }
    .cq-monto .s { font-size: 12px; color: var(--muted); font-weight: 400; }
    .cq-sit { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; font-size: 13px; }
    .cq-sit .vistas { color: var(--muted); display: inline-flex; gap: 4px; align-items: center; }
    .cq-tag { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 99px; font-size: 12px; font-weight: 500; position: relative; z-index: 1; text-decoration: none; }
    .cq-tag.ambar { background: var(--amber-soft); color: var(--amber); }
    .cq-tag.verde { background: var(--green-soft); color: var(--green-ink); }
    .cq-acc { display: flex; justify-content: flex-end; gap: 2px; position: relative; z-index: 2; }
    .cq-acc:has(.dropdown-menu.show) { z-index: 20; }   /* el menú abierto queda encima de la fila de abajo */

    .cq-vacio { text-align: center; padding: 48px 20px; }
    .cq-vacio .ico { width: 56px; height: 56px; border-radius: 16px; display: grid; place-items: center; margin: 0 auto 14px; background: var(--sunken); font-size: 24px; color: var(--text-2); }
    .cq-vacio h3 { font-size: 17px; margin-bottom: 6px; }
    .cq-vacio p { color: var(--muted); margin-bottom: 16px; }

    @media (max-width: 1199.98px) { .cq-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 991.98px) {
        .cq-fila.cabeza { display: none; }
        .cq-fila { grid-template-columns: minmax(0, 1fr) auto; gap: 8px 12px; padding: 14px 16px; }
        .cq-fila:nth-child(2) { border-top: 0; }
        .cq-folio { display: none; }
        .cq-monto { grid-column: 2; grid-row: 1; }
        .cq-sit { grid-column: 1; padding-left: 52px; }
        .cq-acc { grid-column: 2; }
        .cq-buscar { min-width: 0; width: 100%; }
    }
    @media (max-width: 575.98px) {
        .cq-kpi { padding: 12px; gap: 10px; } .cq-kpi .ic { width: 34px; height: 34px; font-size: 15px; } .cq-kpi .v { font-size: 18px; }
        .cq-pills { flex-wrap: nowrap; overflow-x: auto; max-width: 100%; scrollbar-width: none; } .cq-pills::-webkit-scrollbar { display: none; }
        .cq-barra > * { width: 100%; }
    }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <h1>Cotizaciones</h1>
        <p class="sub">Lo que has cotizado, lo que espera respuesta y lo que ya cerraste.</p>
    </div>
    <a href="{{ route('admin.presupuestos.create') }}" class="btn btn-primario"><i class="bi bi-plus-lg me-1"></i> Nueva cotización</a>
</div>

@if($hayAlguna)
    <div class="cq-kpis">
        <a href="{{ $url(['filtro' => 'vigentes']) }}" class="cq-kpi azul {{ $filtro === 'vigentes' ? 'activo' : '' }}">
            <span class="ic"><i class="bi bi-hourglass-split"></i></span>
            <div><div class="v num">{{ $resumen['porResponder']['n'] }}</div><div class="k">Por responder · {{ $dinero($resumen['porResponder']['monto']) }}</div></div>
        </a>
        <a href="{{ $url(['filtro' => 'por_vencer']) }}" class="cq-kpi {{ $resumen['porVencer'] ? 'ambar' : '' }} {{ $filtro === 'por_vencer' ? 'activo' : '' }}">
            <span class="ic"><i class="bi bi-alarm"></i></span>
            <div><div class="v num">{{ $resumen['porVencer'] }}</div><div class="k">Vencen en 3 días</div></div>
        </a>
        <a href="{{ $url(['filtro' => 'aceptada']) }}" class="cq-kpi verde {{ $filtro === 'aceptada' ? 'activo' : '' }}">
            <span class="ic"><i class="bi bi-trophy"></i></span>
            <div><div class="v num">{{ $dinero($resumen['aceptadasMes']['monto']) }}</div><div class="k">Aceptado este mes · {{ $resumen['aceptadasMes']['n'] }}</div></div>
        </a>
        <a href="{{ $url(['filtro' => 'borrador']) }}" class="cq-kpi {{ $filtro === 'borrador' ? 'activo' : '' }}">
            <span class="ic"><i class="bi bi-pencil-square"></i></span>
            <div><div class="v num">{{ $resumen['borradores'] }}</div><div class="k">{{ $resumen['borradores'] === 1 ? 'Borrador sin enviar' : 'Borradores sin enviar' }}</div></div>
        </a>
    </div>
@endif

<div class="cq-barra">
    <nav class="cq-pills" aria-label="Filtrar cotizaciones">
        @foreach($vistas as $k => $label)
            <a href="{{ $url(['filtro' => $k === 'todas' ? null : $k]) }}" class="{{ $filtro === $k ? 'activo' : '' }}" @if($filtro === $k) aria-current="true" @endif>{{ $label }} <span class="n num">{{ $conteos[$k] }}</span></a>
        @endforeach
    </nav>
    <form class="cq-buscar" method="get" role="search">
        @if($filtro !== 'todas')<input type="hidden" name="filtro" value="{{ $filtro }}">@endif
        <i class="bi bi-search"></i>
        <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Buscar por folio, cliente o concepto" aria-label="Buscar cotizaciones">
    </form>
</div>

<div class="cq-lista">
    @if($presupuestos->isEmpty())
        <div class="cq-vacio">
            @if($q)
                <div class="ico"><i class="bi bi-search"></i></div>
                <h3>Sin resultados para “{{ $q }}”</h3>
                <p>Prueba con otro folio, el nombre de la empresa o lo que cotizaste.</p>
                <a href="{{ $url(['q' => null]) }}" class="btn btn-borde">Limpiar búsqueda</a>
            @elseif(! $hayAlguna)
                <div class="ico"><i class="bi bi-file-earmark-plus"></i></div>
                <h3>Aún no hay cotizaciones</h3>
                <p>Crea la primera y compártela con tu cliente por WhatsApp o correo.</p>
                <a href="{{ route('admin.presupuestos.create') }}" class="btn btn-primario">Crear cotización</a>
            @else
                <div class="ico"><i class="bi bi-check2-circle"></i></div>
                <h3>Nada en «{{ $vistas[$filtro] }}»</h3>
                <p>No tienes cotizaciones en esta vista ahora mismo.</p>
                <a href="{{ $url(['filtro' => null]) }}" class="btn btn-primario">Ver todas ({{ $conteos['todas'] }})</a>
            @endif
        </div>
    @else
        <div class="cq-fila cabeza" aria-hidden="true"><span>Cliente y concepto</span><span>Folio</span><span class="text-end">Importe</span><span>Situación</span><span></span></div>
        @foreach($presupuestos as $p)
            @php
                $quien = $p->cliente_empresa ?: $p->cliente_nombre;
                $concepto = \Illuminate\Support\Str::limit($p->conceptos->first()?->resumen ?: $p->titulo, 60);
                $wa = $p->contacto?->whatsapp;
                $facEnv = $p->facturas->max('enviada_at');
            @endphp
            <div class="cq-fila">
                <a href="{{ route('admin.presupuestos.edit', $p) }}" class="cq-quien">
                    @include('admin._avatar', ['nombre' => $quien])
                    <span class="t">
                        <b>{{ $quien }}@if($p->para_prospecto) <span class="d-inline secundario fw-normal" style="font-size:12px"><i class="bi bi-funnel"></i> Prospecto</span>@endif</b>
                        <span>{{ $concepto }}</span>
                    </span>
                </a>
                <div class="cq-folio"><div class="num fw-medium">{{ $p->folio }}</div><div class="s">{{ $p->fecha->locale('es')->isoFormat('D MMM YYYY') }}</div></div>
                <div class="cq-monto num">{{ $dinero($p->modo_iva === 'desglosado' ? $p->total : $p->subtotal) }}<div class="s">{{ $p->modo_iva === 'mas_iva' ? '+ IVA' : ($p->modo_iva === 'desglosado' ? 'con IVA' : 'sin IVA') }}</div></div>
                <div class="cq-sit">
                    <span class="estado estado-{{ $p->estado }}">{{ \App\Models\Presupuesto::ESTADOS[$p->estado] ?? $p->estado }}</span>
                    @if(! in_array($p->estado, ['aceptada', 'rechazada', 'cancelada'], true))@include('admin.presupuestos._vigencia', ['p' => $p])@endif
                    @if($p->estado === 'aceptada')
                        @if(! $p->proyecto)<a href="{{ route('admin.proyectos.create', $p) }}" class="cq-tag ambar"><i class="bi bi-kanban"></i> Crear proyecto</a>@endif
                        @if($p->facturas->isEmpty())<a href="{{ route('admin.presupuestos.edit', [$p, 'ver' => 'factura']) }}" class="cq-tag ambar"><i class="bi bi-receipt"></i> Sin {{ $p->es_nota ? 'nota' : 'factura' }}</a>
                        @elseif(! $facEnv)<a href="{{ route('admin.presupuestos.edit', [$p, 'correo' => $p->comprobante_corto]) }}" class="cq-tag ambar"><i class="bi bi-send"></i> Enviar {{ $p->es_nota ? 'nota' : 'factura' }}</a>
                        @else<span class="cq-tag verde"><i class="bi bi-receipt"></i> {{ $p->es_nota ? 'Nota enviada' : 'Facturada' }}</span>@endif
                    @endif
                    @if($p->vistas && $p->estado !== 'aceptada')<span class="vistas" title="{{ $p->ultima_vista_at ? 'Última vez ' . $p->ultima_vista_at->locale('es')->diffForHumans() : '' }}"><i class="bi bi-eye"></i> {{ $p->vistas }}</span>@endif
                </div>
                <div class="cq-acc">
                    @if($wa && ! in_array($p->estado, ['aceptada', 'rechazada', 'cancelada'], true))
                        <a class="btn btn-fantasma btn-icono" target="_blank" rel="noopener" href="{{ route('admin.presupuestos.whatsapp', $p) }}" title="Enviar por WhatsApp" aria-label="Enviar {{ $p->folio }} por WhatsApp"><i class="bi bi-whatsapp"></i></a>
                    @endif
                    <button type="button" class="btn btn-fantasma btn-icono" data-copiar="{{ $p->url_publica }}" title="Copiar enlace del cliente" aria-label="Copiar enlace de {{ $p->folio }}"><i class="bi bi-link-45deg"></i></button>
                    <div class="dropdown">
                        <button class="btn btn-fantasma btn-icono" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Más acciones para {{ $p->folio }}"><i class="bi bi-three-dots"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('admin.presupuestos.edit', $p) }}"><i class="bi bi-pencil"></i> Abrir</a></li>
                            <li><a class="dropdown-item" href="{{ route('admin.presupuestos.edit', [$p, 'correo' => 'cotizacion']) }}"><i class="bi bi-envelope"></i> Enviar por correo</a></li>
                            @if($p->proyecto)
                                <li><a class="dropdown-item" href="{{ route('admin.proyectos.show', $p->proyecto) }}"><i class="bi bi-kanban"></i> Ver proyecto</a></li>
                            @elseif($p->estado === 'aceptada')
                                <li><a class="dropdown-item fw-medium" href="{{ route('admin.proyectos.create', $p) }}"><i class="bi bi-kanban"></i> Convertir en proyecto</a></li>
                            @endif
                            <li><a class="dropdown-item" target="_blank" href="{{ $p->url_publica }}?vista_previa=1"><i class="bi bi-eye"></i> Ver como cliente</a></li>
                            <li><a class="dropdown-item" target="_blank" href="{{ route('admin.presupuestos.pdf', $p) }}"><i class="bi bi-file-earmark-pdf"></i> Ver PDF</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="post" action="{{ route('admin.presupuestos.rapido', $p) }}">@csrf @method('patch')
                                    <input type="hidden" name="extender_dias" value="{{ config('vandu.vigencia_dias') }}">
                                    <button class="dropdown-item"><i class="bi bi-calendar-plus"></i> Vigencia: {{ config('vandu.vigencia_dias') }} días desde hoy</button>
                                </form>
                            </li>
                            @foreach(\App\Models\Presupuesto::ESTADOS as $k => $label)
                                @continue($k === $p->estado)
                                <li>
                                    <form method="post" action="{{ route('admin.presupuestos.rapido', $p) }}">@csrf @method('patch')
                                        <input type="hidden" name="estado" value="{{ $k }}">
                                        <button class="dropdown-item"><i class="bi bi-circle-fill" style="font-size:7px"></i> Marcar como {{ mb_strtolower($label) }}</button>
                                    </form>
                                </li>
                            @endforeach
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="post" action="{{ route('admin.presupuestos.duplicar', $p) }}">@csrf
                                    <button class="dropdown-item"><i class="bi bi-copy"></i> Duplicar</button>
                                </form>
                            </li>
                            <li>
                                <form method="post" action="{{ route('admin.presupuestos.destroy', $p) }}" onsubmit="return confirm({{ Js::from($p->proyecto ? '¿Eliminar ' . $p->folio . '? También se elimina su proyecto «' . $p->proyecto->nombre . '» con sus pagos y entregas, y deja de contar en tus ingresos. El enlace del cliente dejará de funcionar.' : '¿Eliminar ' . $p->folio . '? El enlace del cliente dejará de funcionar.') }})">@csrf @method('delete')
                                    <button class="dropdown-item text-danger"><i class="bi bi-trash text-danger"></i> Eliminar</button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>
@if($presupuestos->hasPages())
    <div class="mt-3">{{ $presupuestos->links('pagination::bootstrap-5') }}</div>
@endif
@endsection
