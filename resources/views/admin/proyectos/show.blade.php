@extends('admin.layout')
@section('titulo', $p->nombre)

@php
    /** @var \App\Models\Proyecto $p */
    $quien = $p->cliente?->empresa ?: $p->cliente?->nombre;
    $linea = $p->lineaDelTiempo();
    $bloqueo = session('bloqueo');
    $wa = $p->cliente?->whatsapp;
    // Un solo enlace para el cliente: el de su cotización (desde ahí entra a su proyecto)
    $enlaceCliente = $p->presupuesto?->url_publica ?? $p->url_publica;
    $msgWa = "Hola {$p->cliente?->nombre}, aquí puedes ver tu cotización y el avance de tu proyecto: {$enlaceCliente}";
    $hoy = now(config('vandu.zona_horaria'))->toDateString();
    $dbx = \App\Support\Dropbox\Dropbox::conectado();
    $metodoCliente = $p->cliente?->metodo_pago;
@endphp

@push('head')
<style>
    .ficha-p { display: grid; grid-template-columns: minmax(0, 1fr) 330px; gap: 24px; align-items: start; }
    .lateral { position: sticky; top: 24px; display: grid; gap: 16px; }
    .tipo-pill { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 500; padding: 3px 10px; border-radius: 99px; background: #EEF0F3; color: var(--text-2); }

    .resumen-p { display: grid; grid-template-columns: 1.4fr 1fr 1fr; }
    .resumen-p > div { padding: 18px 20px; }
    .resumen-p > div + div { border-left: 1px solid var(--line); }
    .resumen-p .k { font-size: 13px; color: var(--muted); }
    .resumen-p .v { font-size: 22px; font-weight: 600; letter-spacing: -.01em; margin-top: 2px; }
    .prog { height: 8px; border-radius: 99px; background: #E9EBEE; overflow: hidden; margin-top: 10px; }
    .prog span { display: block; height: 100%; border-radius: 99px; background: var(--ink); }

    /* Línea del tiempo */
    .tl { list-style: none; margin: 0; padding: 8px 0; }
    .tl > li { position: relative; display: grid; grid-template-columns: 56px minmax(0, 1fr); }
    .tl > li::before { content: ''; position: absolute; left: 27px; top: 0; bottom: 0; width: 2px; background: var(--line); }
    .tl > li:first-child::before { top: 26px; }
    .tl > li:last-child::before { bottom: calc(100% - 26px); }
    .tl > li.hecho::before { background: var(--ink); }
    .nodo { position: relative; z-index: 1; margin: 14px auto 0; width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center;
            background: var(--surface); border: 2px solid var(--line-strong); color: var(--muted); font-size: 12px; }
    .hecho .nodo { background: var(--ink); border-color: var(--ink); color: var(--green); }
    .curso .nodo { border-color: var(--ink); color: var(--ink); box-shadow: 0 0 0 4px rgba(19,22,29,.08); }
    .curso .nodo::after { content: ''; width: 10px; height: 10px; border-radius: 50%; background: var(--ink); }
    .pago .nodo { border-radius: 8px; }
    .pago.pendiente .nodo { border-color: var(--amber); color: var(--amber); background: var(--amber-soft); }
    .tl-cuerpo { padding: 12px 20px 18px 0; border-bottom: 1px solid var(--line); min-width: 0; }
    .tl > li:last-child .tl-cuerpo { border-bottom: 0; }
    .tl-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .tl-titulo { font-weight: 600; font-size: 15.5px; }
    .tl-meta { font-size: 13.5px; color: var(--muted); display: flex; gap: 14px; flex-wrap: wrap; margin-top: 2px; }
    .tl-desc { color: var(--text-2); font-size: 14px; margin: 6px 0 0; }
    .tl-estados { display: inline-flex; background: #E9EBEE; border-radius: 9px; padding: 2px; gap: 2px; }
    .tl-estados button { border: 0; background: transparent; border-radius: 7px; padding: 4px 10px; font-size: 13px; color: var(--text-2); }
    .tl-estados button:hover { color: var(--text); }
    .tl-estados button.activo { background: var(--surface); color: var(--text); font-weight: 500; box-shadow: 0 1px 2px rgba(16,24,40,.08); }
    .tl-estados .activo.completada { color: var(--green-ink); }
    .bloqueo { margin-top: 12px; padding: 12px 14px; border-radius: 10px; background: var(--amber-soft); color: #6B3B00; font-size: 14px; display: flex; gap: 10px; align-items: flex-start; flex-wrap: wrap; }

    .docs { list-style: none; padding: 0; margin: 12px 0 0; display: grid; gap: 6px; }
    .docs li { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: 1px solid var(--line); border-radius: 9px; background: var(--sunken); }
    .docs li i.ext { font-size: 18px; color: var(--muted); }
    .docs .n { min-width: 0; flex: 1; }
    .docs .n a { color: var(--text); text-decoration: none; font-weight: 500; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .docs .n a:hover { text-decoration: underline; }
    .docs .oculto { opacity: .6; }

    /* Subida */
    .subir { margin-top: 10px; }
    .subir-zona { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border: 1px dashed var(--line-strong); border-radius: 8px; cursor: pointer; color: var(--muted); font-size: 13.5px; }
    .subir-zona b { font-weight: 500; }
    .subir-zona:hover, .arrastrando .subir-zona { border-color: var(--ink); background: var(--sunken); color: var(--text); }
    .subir-zona i { font-size: 18px; }
    .subir-grande .subir-zona { display: flex; border-width: 1.5px; color: var(--text-2); flex-direction: column; justify-content: center; text-align: center; padding: 28px 16px; }
    .subir-grande .subir-zona i { font-size: 28px; }
    .subir-progreso { padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; }
    .subir-progreso .barra { height: 6px; background: #E9EBEE; border-radius: 99px; overflow: hidden; }
    .subir-progreso .barra span { display: block; height: 100%; background: var(--ink); transition: width .15s; }

    /* Galería */
    .galeria { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
    .g-item { position: relative; aspect-ratio: 1; border-radius: 10px; overflow: hidden; background: var(--sunken); border: 1px solid var(--line); }
    .g-item img, .g-item video { width: 100%; height: 100%; object-fit: cover; display: block; }
    .g-item .archivo { height: 100%; display: grid; place-items: center; text-align: center; padding: 10px; font-size: 13px; color: var(--muted); }
    .g-item .archivo i { font-size: 28px; display: block; }
    .g-item .play { position: absolute; inset: 0; display: grid; place-items: center; pointer-events: none; }
    .g-item .play i { width: 40px; height: 40px; border-radius: 50%; background: rgba(19,22,29,.7); color: #fff; display: grid; place-items: center; font-size: 18px; }
    .g-acc { position: absolute; top: 6px; right: 6px; display: flex; gap: 4px; opacity: 0; transition: opacity .12s; }
    .g-item:hover .g-acc, .g-item:focus-within .g-acc { opacity: 1; }
    .g-acc button, .g-acc a { width: 30px; height: 30px; border-radius: 8px; border: 0; background: rgba(255,255,255,.95); color: var(--text); display: grid; place-items: center; box-shadow: 0 1px 3px rgba(0,0,0,.2); }
    .g-item.oculto img, .g-item.oculto video { opacity: .35; }
    .g-item .marca-dbx { position: absolute; left: 6px; top: 6px; width: 22px; height: 22px; border-radius: 6px; background: rgba(255,255,255,.95); color: #0061FE; display: grid; place-items: center; font-size: 12px; }
    .dbx-barra { display: flex; flex-wrap: wrap; gap: 8px 12px; align-items: center; padding: 10px 14px; margin-bottom: 12px; border: 1px solid var(--line); border-radius: 10px; background: var(--sunken); font-size: 13.5px; }
    .dbx-barra .ruta { color: var(--muted); min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1 1 220px; }
    .dbx-barra .ruta i { color: #0061FE; }
    .dbx-velo { position: fixed; inset: 0; z-index: 1080; background: rgba(15,18,25,.55); display: flex; align-items: flex-start; justify-content: center; padding: 40px 16px; overflow-y: auto; }
    .dbx-ventana { background: var(--surface); border-radius: 16px; width: min(760px, 100%); box-shadow: 0 24px 60px rgba(0,0,0,.25); }
    .dbx-cab { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--line); }
    .dbx-cab h2 { font-size: 17px; font-weight: 600; margin: 0; }
    .dbx-migas { display: flex; flex-wrap: wrap; gap: 4px; padding: 10px 20px; border-bottom: 1px solid var(--line); font-size: 13.5px; }
    .dbx-migas button { border: 0; background: none; padding: 2px 4px; color: var(--muted); border-radius: 6px; }
    .dbx-migas button:hover { background: var(--sunken); color: var(--text); }
    .dbx-lista { max-height: 52vh; overflow-y: auto; }
    .dbx-fila { display: flex; align-items: center; gap: 12px; padding: 9px 20px; border-bottom: 1px solid var(--line); font-size: 14px; cursor: pointer; }
    .dbx-fila:hover { background: var(--sunken); }
    .dbx-fila i.tipo { font-size: 18px; color: var(--muted); width: 20px; text-align: center; }
    .dbx-fila.carpeta i.tipo { color: #0061FE; }
    .dbx-fila .n { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .dbx-pie { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; padding: 14px 20px; }
    .g-item .marca-oculto { position: absolute; left: 6px; bottom: 6px; font-size: 12px; background: rgba(19,22,29,.8); color: #fff; border-radius: 6px; padding: 2px 8px; }

    @media (max-width: 1199.98px) { .ficha-p { grid-template-columns: 1fr; } .lateral { position: static; } }
    @media (max-width: 767.98px) { .resumen-p { grid-template-columns: 1fr; } .resumen-p > div + div { border-left: 0; border-top: 1px solid var(--line); } .g-acc { opacity: 1; } }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.proyectos.index') }}">Proyectos</a> <i class="bi bi-chevron-right small"></i> <span class="text-truncate">{{ $p->nombre }}</span></div>
<div class="page-head">
    <div class="persona">
        @include('admin._avatar', ['nombre' => $quien])
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h1>{{ $p->nombre }}</h1>
                <span class="tipo-pill"><i class="bi {{ $p->metodologia['icono'] ?? 'bi-kanban' }}"></i> {{ $p->tipo_nombre }}</span>
                @if($p->estado !== 'activo')<span class="estado {{ $p->estado === 'terminado' ? 'estado-aceptada' : 'estado-borrador' }}">{{ \App\Models\Proyecto::ESTADOS[$p->estado] }}</span>@endif
            </div>
            <p class="sub">
                <a href="{{ route('admin.clientes.show', $p->cliente) }}" class="text-reset">{{ $quien }}</a>
                @if($p->presupuesto) · desde <a href="{{ route('admin.presupuestos.edit', $p->presupuesto) }}" class="text-reset num">{{ $p->presupuesto->folio }}</a>@endif
            </p>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.proyectos.fechas', $p) }}" class="btn btn-borde"><i class="bi bi-calendar-week me-1"></i> Editar fechas</a>
        <a href="{{ $p->url_publica }}?vista_previa=1" target="_blank" class="btn btn-borde"><i class="bi bi-eye me-1"></i> Ver como cliente</a>
        <button type="button" class="btn btn-primario" data-copiar="{{ $p->url_publica }}"><i class="bi bi-link-45deg me-1"></i> Copiar enlace</button>
    </div>
</div>

<div class="ficha-p">
    <div class="d-grid gap-4">
        <div class="panel resumen-p">
            <div>
                <div class="k">Avance</div>
                <div class="v num">{{ $p->progreso }}%</div>
                <div class="prog" role="progressbar" aria-valuenow="{{ $p->progreso }}" aria-valuemin="0" aria-valuemax="100" aria-label="Avance del proyecto"><span style="width: {{ $p->progreso }}%"></span></div>
            </div>
            <div><div class="k">Cobrado</div><div class="v num">${{ number_format($p->pagado, 0) }}</div><div class="secundario num">de ${{ number_format($p->monto_total, 0) }}</div></div>
            <div><div class="k">Siguiente paso</div><div class="fw-semibold mt-1">{{ $p->siguiente_paso ?? 'Terminado' }}</div></div>
        </div>

        {{-- ================= Línea del tiempo ================= --}}
        <section class="panel">
            <div class="panel-head"><h2>Línea del tiempo</h2><a href="{{ route('admin.proyectos.fechas', $p) }}" class="btn btn-fantasma btn-sm"><i class="bi bi-calendar-week me-1"></i> Editar todas las fechas</a></div>
            <ol class="tl">
                @foreach($linea as $t)
                    @if($t['tipo'] === 'pago')
                        @php $pg = $t['item']; @endphp
                        <li class="pago {{ $pg->pagado ? 'hecho' : 'pendiente' }}" id="pago-{{ $pg->id }}">
                            <span class="nodo"><i class="bi {{ $pg->pagado ? 'bi-check-lg' : 'bi-cash-coin' }}"></i></span>
                            <div class="tl-cuerpo" x-data="{ editar: false }">
                                <div class="tl-top">
                                    <div>
                                        <div class="tl-titulo">{{ $pg->concepto }} <span class="secundario fw-normal num">· {{ rtrim(rtrim(number_format($pg->porcentaje, 2), '0'), '.') }}%</span></div>
                                        <div class="tl-meta">
                                            <span class="num fw-medium text-body">{{ $pg->monto_texto }}</span>
                                            @if($pg->pagado)
                                                <button type="button" class="vig vig-ok border-0 bg-transparent p-0" @click="editar = true" title="Cambiar fecha de pago"><i class="bi bi-check-circle"></i> Pagado el {{ $pg->pagado_el->locale('es')->isoFormat('D [de] MMMM') }} <i class="bi bi-pencil small text-secondary"></i></button>
                                            @elseif($pg->vence_el)
                                                <span class="vig {{ $pg->vencido ? 'vig-vencida' : 'vig-pronto' }}"><i class="bi {{ $pg->vencido ? 'bi-exclamation-circle' : 'bi-calendar-event' }}"></i> {{ $pg->vencido ? 'Venció' : 'Vence' }} el {{ $pg->vence_el->locale('es')->isoFormat('D [de] MMMM') }}@if($p->a_credito && $p->dias_credito) · crédito {{ $p->dias_credito }} días @endif</span>
                                            @elseif($pg->antes_de)
                                                <span class="vig vig-pronto"><i class="bi bi-lock"></i> Requerido antes de {{ $p->etapas->firstWhere('clave', $pg->antes_de)?->nombre }}</span>
                                            @endif
                                            @if($pg->metodo_texto)<span><i class="bi {{ ['transferencia' => 'bi-bank', 'efectivo' => 'bi-cash', 'credito' => 'bi-hourglass-split', 'tarjeta_credito' => 'bi-credit-card'][$pg->metodo] ?? 'bi-wallet2' }} me-1"></i>{{ $pg->metodo_texto }}</span>@endif
                                            @if($pg->referencia)<span>Ref. {{ $pg->referencia }}</span>@endif
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center">
                                        @unless($pg->pagado)
                                            <form method="post" action="{{ route('admin.proyectos.pago', [$p, $pg]) }}" class="d-flex gap-2 align-items-center">
                                                @csrf @method('patch')
                                                <input type="hidden" name="accion" value="pagar">
                                                <input type="date" name="pagado_el" value="{{ $hoy }}" class="form-control form-control-sm num" style="width: 150px" aria-label="Fecha de pago">
                                                <select name="metodo" class="form-select form-select-sm" style="width: 170px" aria-label="Método de pago">
                                                    <option value="">Método…</option>
                                                    @foreach(config('vandu.metodos_pago') as $mk => $ml)<option value="{{ $mk }}" @selected(($pg->metodo ?? $metodoCliente) === $mk)>{{ $ml }}</option>@endforeach
                                                </select>
                                                <button class="btn btn-sm btn-acento text-nowrap"><i class="bi bi-check-lg"></i> Registrar pago</button>
                                            </form>
                                        @endunless
                                        <div class="dropdown">
                                            <button class="btn btn-fantasma btn-icono" data-bs-toggle="dropdown" aria-label="Opciones de {{ $pg->concepto }}"><i class="bi bi-three-dots"></i></button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><button type="button" class="dropdown-item" @click="editar = !editar"><i class="bi bi-pencil"></i> Editar fecha, monto, método o referencia</button></li>
                                                @unless($pg->pagado)
                                                    <li><button type="button" class="dropdown-item" data-correo="recordatorio_pago@{{ $pg->id }}"><i class="bi bi-envelope"></i> Enviar recordatorio por correo</button></li>
                                                @endunless
                                                @if($pg->pagado)
                                                    <li><form method="post" action="{{ route('admin.proyectos.pago', [$p, $pg]) }}">@csrf @method('patch')
                                                        <input type="hidden" name="accion" value="deshacer"><button class="dropdown-item"><i class="bi bi-arrow-counterclockwise"></i> Marcar como pendiente</button></form></li>
                                                @endif
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <form method="post" action="{{ route('admin.proyectos.pago', [$p, $pg]) }}" class="row g-2 mt-2" x-show="editar" x-cloak>
                                    @csrf @method('patch')
                                    <input type="hidden" name="editar_fecha" value="1">
                                    @if($pg->vence_el || $p->a_credito)
                                        <div class="col-sm-3"><label class="form-label">Vence el</label>
                                            <input type="date" name="vence_el" value="{{ $pg->vence_el?->toDateString() }}" class="form-control form-control-sm num"></div>
                                    @endif
                                    <div class="col-sm-3"><label class="form-label">Pagado el</label>
                                        <input type="date" name="pagado_el" value="{{ $pg->pagado_el?->toDateString() }}" max="{{ $hoy }}" class="form-control form-control-sm num">
                                        <div class="secundario" style="font-size:12px">Vacío = pendiente</div></div>
                                    <div class="col-sm-2"><label class="form-label">Monto</label>
                                        <div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="number" step="0.01" min="0" name="monto" value="{{ $pg->monto }}" class="form-control num"></div></div>
                                    <div class="col-sm-2"><label class="form-label">Método</label>
                                        <select name="metodo" class="form-select form-select-sm">
                                            <option value="">Sin especificar</option>
                                            @foreach(config('vandu.metodos_pago') as $mk => $ml)<option value="{{ $mk }}" @selected(($pg->metodo ?? $metodoCliente) === $mk)>{{ $ml }}</option>@endforeach
                                        </select></div>
                                    <div class="col-sm-3"><label class="form-label">Referencia o nota</label><input name="referencia" value="{{ $pg->referencia }}" class="form-control form-control-sm" placeholder="Transferencia, folio…"></div>
                                    <div class="col-sm-2 d-flex align-items-start" style="padding-top:26px"><button class="btn btn-sm btn-primario w-100">Guardar</button></div>
                                </form>
                            </div>
                        </li>
                    @else
                        @php
                            $e = $t['item'];
                            $clase = ['completada' => 'hecho', 'en_curso' => 'curso', 'pendiente' => ''][$e->estado];
                            $bloqueada = $p->pagosQueBloquean($e)->isNotEmpty();
                        @endphp
                        <li class="{{ $clase }}" id="etapa-{{ $e->id }}">
                            <span class="nodo">@if($e->estado === 'completada')<i class="bi bi-check-lg"></i>@endif</span>
                            <div class="tl-cuerpo" x-data="{ editar: {{ $errors->any() && old('etapa') == $e->id ? 'true' : 'false' }} }">
                                <div class="tl-top">
                                    <div class="min-w-0">
                                        <div class="tl-titulo">{{ $e->nombre }}</div>
                                        <div class="tl-meta">
                                            @if($e->fechas_texto)
                                                <span><i class="bi {{ $e->es_fecha ? 'bi-calendar-event' : 'bi-calendar-range' }} me-1"></i>{{ $e->fechas_texto }}</span>
                                            @elseif($e->es_fecha)
                                                <span class="vig vig-pronto"><i class="bi bi-calendar-plus"></i> Falta agendar</span>
                                            @endif
                                            @if($e->completada_at)<span>Completada el {{ $e->completada_at->timezone(config('vandu.zona_horaria'))->locale('es')->isoFormat('D MMM') }}</span>@endif
                                            @if($bloqueada && $e->estado === 'pendiente')<span><i class="bi bi-lock me-1"></i>Esperando pago</span>@endif
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center">
                                        <form method="post" action="{{ route('admin.proyectos.etapa', [$p, $e]) }}" class="tl-estados" aria-label="Estado de {{ $e->nombre }}">
                                            @csrf @method('patch')
                                            @foreach(\App\Models\ProyectoEtapa::ESTADOS as $k => $label)
                                                <button name="estado" value="{{ $k }}" class="{{ $e->estado === $k ? 'activo ' . $k : '' }}" @if($e->estado === $k) aria-pressed="true" @endif>{{ $label }}</button>
                                            @endforeach
                                        </form>
                                        <button type="button" class="btn btn-fantasma btn-icono" @click="editar = !editar" :aria-expanded="editar" aria-label="Editar {{ $e->nombre }}"><i class="bi bi-pencil"></i></button>
                                    </div>
                                </div>
                                @if($e->descripcion)<p class="tl-desc">{{ $e->descripcion }}</p>@endif

                                @if($bloqueo && $bloqueo['etapa_id'] === $e->id)
                                    <div class="bloqueo" role="alert">
                                        <i class="bi bi-lock-fill mt-1"></i>
                                        <div class="flex-grow-1"><strong>{{ $bloqueo['mensaje'] }}</strong><br>Registra el pago arriba, o avanza de todos modos si ya lo acordaste con el cliente.</div>
                                        <form method="post" action="{{ route('admin.proyectos.etapa', [$p, $e]) }}">@csrf @method('patch')
                                            <input type="hidden" name="estado" value="{{ $bloqueo['estado'] }}"><input type="hidden" name="forzar" value="1">
                                            <button class="btn btn-sm btn-borde">Avanzar de todos modos</button>
                                        </form>
                                    </div>
                                @endif

                                <form method="post" action="{{ route('admin.proyectos.etapa', [$p, $e]) }}" class="row g-2 mt-2" x-show="editar" x-cloak>
                                    @csrf @method('patch')
                                    <input type="hidden" name="fechas" value="1"><input type="hidden" name="etapa" value="{{ $e->id }}">
                                    <div class="col-md-6"><label class="form-label">Nombre</label><input name="nombre" value="{{ $e->nombre }}" class="form-control form-control-sm" required></div>
                                    @if($e->es_fecha)
                                        <div class="col-md-6"><label class="form-label">Fecha</label><input type="date" name="fecha_inicio" value="{{ $e->fecha_inicio?->toDateString() }}" class="form-control form-control-sm num"></div>
                                    @else
                                        <div class="col-md-3"><label class="form-label">Inicio</label><input type="date" name="fecha_inicio" value="{{ $e->fecha_inicio?->toDateString() }}" class="form-control form-control-sm num"></div>
                                        <div class="col-md-3"><label class="form-label">Fin</label><input type="date" name="fecha_fin" value="{{ $e->fecha_fin?->toDateString() }}" class="form-control form-control-sm num"></div>
                                    @endif
                                    @if($e->estado === 'completada')
                                        <div class="col-md-6"><label class="form-label">Completada el</label><input type="date" name="completada_el" value="{{ $e->completada_at?->timezone(config('vandu.zona_horaria'))->toDateString() }}" max="{{ $hoy }}" class="form-control form-control-sm num"></div>
                                    @endif
                                    <div class="col-12"><label class="form-label">Descripción para el cliente</label><textarea name="descripcion" rows="2" class="form-control form-control-sm">{{ $e->descripcion }}</textarea></div>
                                    <div class="col-12 d-flex gap-2 justify-content-end">
                                        <button type="button" class="btn btn-sm btn-fantasma" @click="editar = false">Cancelar</button>
                                        <button class="btn btn-sm btn-primario">Guardar etapa</button>
                                    </div>
                                </form>

                                @if($e->archivos->isNotEmpty())
                                    <ul class="docs">
                                        @foreach($e->archivos as $a)
                                            <li class="{{ $a->visible ? '' : 'oculto' }}">
                                                <i class="bi {{ $a->icono }} ext"></i>
                                                <div class="n"><a href="{{ route('admin.proyectos.archivo.ver', [$p, $a]) }}" target="_blank">{{ $a->nombre }}</a>
                                                    <span class="secundario num">{{ $a->peso_texto }}{{ $a->visible ? '' : ' · Oculto para el cliente' }}</span></div>
                                                <form method="post" action="{{ route('admin.proyectos.archivo', [$p, $a]) }}">@csrf @method('patch')
                                                    <input type="hidden" name="visible" value="{{ $a->visible ? 0 : 1 }}">
                                                    <button class="btn btn-fantasma btn-icono" title="{{ $a->visible ? 'Ocultar al cliente' : 'Mostrar al cliente' }}" aria-label="{{ $a->visible ? 'Ocultar al cliente' : 'Mostrar al cliente' }}"><i class="bi {{ $a->visible ? 'bi-eye' : 'bi-eye-slash' }}"></i></button>
                                                </form>
                                                <form method="post" action="{{ route('admin.proyectos.archivo.borrar', [$p, $a]) }}" onsubmit="return confirm('¿Eliminar {{ addslashes($a->nombre) }}?')">@csrf @method('delete')
                                                    <button class="btn btn-fantasma btn-icono text-danger" title="Eliminar" aria-label="Eliminar {{ $a->nombre }}"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                @include('admin.proyectos._subir', ['grupo' => 'documento', 'etapaId' => $e->id, 'texto' => 'Agregar descargable a esta etapa'])
                            </div>
                        </li>
                    @endif
                @endforeach
            </ol>
        </section>

        {{-- ================= Galería ================= --}}
            <section class="panel" id="galeria">
                <div class="panel-head">
                    <div><h2>Galería de entregables</h2><span class="ayuda num">{{ $galeria->count() }} {{ $galeria->count() === 1 ? 'archivo' : 'archivos' }} · {{ $galeria->where('visible', true)->count() }} visibles para el cliente</span></div>
                    @if($galeria->where('visible', true)->isNotEmpty())
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="{{ $p->url_entrega }}?vista_previa=1" target="_blank" class="btn btn-borde btn-sm"><i class="bi bi-box-arrow-up-right me-1"></i> Ver página de entrega</a>
                            <button type="button" class="btn btn-primario btn-sm" data-correo="entrega_digital"><i class="bi bi-send me-1"></i> Enviar entrega por correo</button>
                        </div>
                    @endif
                </div>
                <div class="panel-body">
                    @if($dbx)
                        @php $carpetaGal = \App\Support\ArchivosProyecto::carpetaGaleria($p); @endphp
                        <div class="dbx-barra">
                            <span class="ruta" title="{{ $carpetaGal }}"><i class="bi bi-dropbox me-1"></i> {{ $carpetaGal }}</span>
                            @unless(config('vandu.dropbox.simulado'))
                                <a href="https://www.dropbox.com/home{{ str_replace('%2F', '/', rawurlencode(\App\Support\ArchivosProyecto::carpetaProyecto($p))) }}" target="_blank" rel="noopener" class="btn btn-fantasma btn-sm"><i class="bi bi-box-arrow-up-right me-1"></i> Abrir en Dropbox</a>
                            @endunless
                            <button type="button" class="btn btn-borde btn-sm" onclick="window.dispatchEvent(new CustomEvent('explorar-dropbox'))"><i class="bi bi-folder2-open me-1"></i> Importar de otra carpeta</button>
                            <form method="post" action="{{ route('admin.proyectos.dropbox.sincronizar', $p) }}" class="d-inline">@csrf
                                <button class="btn btn-borde btn-sm" title="Trae lo que agregaste directo en la carpeta Galería o No publicado"><i class="bi bi-arrow-repeat me-1"></i> Sincronizar</button>
                            </form>
                        </div>
                    @endif
                    @include('admin.proyectos._subir', ['grupo' => 'galeria', 'texto' => 'Subir fotos o videos', 'accept' => 'image/*,video/*,.zip,.pdf', 'grande' => true])
                    @if($galeria->isNotEmpty())
                        <div class="galeria mt-3">
                            @foreach($galeria as $a)
                                <div class="g-item {{ $a->visible ? '' : 'oculto' }}">
                                    @if($a->es_imagen)
                                        <img src="{{ route('admin.proyectos.archivo.ver', [$p, $a]) }}?v=miniatura" alt="{{ $a->nombre }}" loading="lazy">
                                    @elseif($a->es_video && $a->miniatura)
                                        <img src="{{ route('admin.proyectos.archivo.ver', [$p, $a]) }}?v=miniatura" alt="{{ $a->nombre }}" loading="lazy">
                                        <span class="play"><i class="bi bi-play-fill"></i></span>
                                    @elseif($a->es_video)
                                        <video src="{{ route('admin.proyectos.archivo.ver', [$p, $a]) }}#t=0.5" preload="metadata" muted playsinline></video>
                                        <span class="play"><i class="bi bi-play-fill"></i></span>
                                    @else
                                        <div class="archivo"><div><i class="bi {{ $a->icono }}"></i>{{ \Illuminate\Support\Str::limit($a->nombre, 28) }}</div></div>
                                    @endif
                                    @unless($a->visible)<span class="marca-oculto">Oculto</span>@endunless
                                    @if($a->en_dropbox)<span class="marca-dbx" title="Guardado en Dropbox"><i class="bi bi-dropbox"></i></span>@endif
                                    <div class="g-acc">
                                        <a href="{{ route('admin.proyectos.archivo.ver', [$p, $a]) }}" target="_blank" title="Abrir original" aria-label="Abrir {{ $a->nombre }}"><i class="bi bi-arrows-angle-expand"></i></a>
                                        <form method="post" action="{{ route('admin.proyectos.archivo', [$p, $a]) }}">@csrf @method('patch')
                                            <input type="hidden" name="visible" value="{{ $a->visible ? 0 : 1 }}">
                                            <button title="{{ $a->visible ? 'Ocultar al cliente' : 'Mostrar al cliente' }}" aria-label="{{ $a->visible ? 'Ocultar al cliente' : 'Mostrar al cliente' }}"><i class="bi {{ $a->visible ? 'bi-eye' : 'bi-eye-slash' }}"></i></button>
                                        </form>
                                        <form method="post" action="{{ route('admin.proyectos.archivo.borrar', [$p, $a]) }}" onsubmit="return confirm('¿Eliminar este archivo de la galería?')">@csrf @method('delete')
                                            <button class="text-danger" title="Eliminar" aria-label="Eliminar {{ $a->nombre }}"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>
    </div>

    {{-- ================= Lateral ================= --}}
    <aside class="lateral">
        <section class="panel">
            <div class="panel-head"><h2>Enlace del cliente</h2><span class="ayuda d-inline-flex align-items-center gap-1"><i class="bi bi-eye"></i> {{ $p->vistas }}</span></div>
            <div class="panel-body">
                <div class="d-flex gap-2">
                    <input class="form-control num" style="font-size:13px" value="{{ $p->url_publica }}" readonly aria-label="Enlace del cliente" onclick="this.select()">
                    <button type="button" class="btn btn-primario btn-icono flex-none" style="width:40px;height:40px" data-copiar="{{ $p->url_publica }}" title="Copiar" aria-label="Copiar enlace"><i class="bi bi-copy"></i></button>
                </div>
                @if($wa)
                    <a class="btn btn-borde w-100 mt-2" target="_blank" href="https://wa.me/{{ $wa }}?text={{ rawurlencode($msgWa) }}"><i class="bi bi-whatsapp me-1"></i> Enviar por WhatsApp</a>
                @endif
                <button type="button" class="btn btn-borde w-100 mt-2" data-correo="inicio_proyecto"><i class="bi bi-envelope me-1"></i> Enviar por correo</button>
                <p class="secundario mt-3 mb-0">{{ $p->vistas ? "Abierto {$p->vistas} " . ($p->vistas === 1 ? 'vez' : 'veces') . ', la última ' . $p->ultima_vista_at->locale('es')->diffForHumans() . '.' : 'El cliente aún no lo abre.' }}</p>
            </div>
        </section>

        @include('admin.correos._historial', ['correos' => \App\Models\Correo::where('proyecto_id', $p->id)->latest()->take(8)->get(), 'plantilla' => 'inicio_proyecto'])

        <section class="panel">
            <div class="panel-head"><h2>Datos del proyecto</h2></div>
            <form method="post" action="{{ route('admin.proyectos.update', $p) }}" class="panel-body d-grid gap-3">
                @csrf @method('put')
                <div><label class="form-label" for="nombre">Nombre</label><input name="nombre" id="nombre" value="{{ old('nombre', $p->nombre) }}" class="form-control" required></div>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label" for="estado">Estado</label>
                        <select name="estado" id="estado" class="form-select">@foreach(\App\Models\Proyecto::ESTADOS as $k => $l)<option value="{{ $k }}" @selected($p->estado === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div class="col-6"><label class="form-label" for="monto_total">Monto total</label>
                        <input type="number" step="0.01" min="0" name="monto_total" id="monto_total" value="{{ old('monto_total', $p->monto_total) }}" class="form-control num"></div>
                </div>
                <div><label class="form-label" for="mensaje_cliente">Mensaje para el cliente</label>
                    <textarea name="mensaje_cliente" id="mensaje_cliente" rows="3" class="form-control" placeholder="Aparece arriba en su enlace, p. ej. “Esta semana te compartimos el diseño”.">{{ old('mensaje_cliente', $p->mensaje_cliente) }}</textarea></div>
                <div><label class="form-label" for="notas_internas">Notas internas</label>
                    <textarea name="notas_internas" id="notas_internas" rows="2" class="form-control" placeholder="Solo las ves tú">{{ old('notas_internas', $p->notas_internas) }}</textarea></div>
                <button class="btn btn-primario">Guardar cambios</button>
            </form>
        </section>

        <form method="post" action="{{ route('admin.proyectos.destroy', $p) }}" onsubmit="return confirm('¿Eliminar este proyecto con sus archivos? La cotización no se borra.')">
            @csrf @method('delete')
            <button class="btn btn-fantasma text-danger w-100"><i class="bi bi-trash me-1"></i> Eliminar proyecto</button>
        </form>
    </aside>
</div>
@include('admin.correos._modal', ['ctxTipo' => 'proyecto', 'ctxId' => $p->id])

@if($dbx)
{{-- Explorador de Dropbox para importar a la galería --}}
<div x-data="exploradorDropbox({{ Js::from(['explorar' => route('admin.dropbox.explorar'), 'importar' => route('admin.proyectos.dropbox.importar', $p), 'inicio' => \App\Support\Dropbox\Dropbox::raiz(), 'token' => csrf_token()]) }})"
     @explorar-dropbox.window="abrir()" @keydown.escape.window="abierto && !importando && (abierto = false)">
    <div class="dbx-velo" x-show="abierto" x-cloak x-transition.opacity @click.self="!importando && (abierto = false)">
        <div class="dbx-ventana" role="dialog" aria-modal="true" aria-labelledby="dbx-titulo">
            <div class="dbx-cab">
                <div><h2 id="dbx-titulo"><i class="bi bi-dropbox me-1" style="color:#0061FE"></i> Importar desde Dropbox</h2>
                    <span class="secundario" style="font-size:13px">Los archivos elegidos se mueven a la carpeta Galería de este proyecto.</span></div>
                <button type="button" class="btn btn-fantasma btn-icono" @click="abierto = false" :disabled="importando" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="dbx-migas">
                <template x-for="(m, i) in migas" :key="m.ruta">
                    <span class="d-inline-flex align-items-center"><i class="bi bi-chevron-right small text-secondary me-1" x-show="i > 0"></i><button type="button" @click="ir(m.ruta)" x-text="m.nombre"></button></span>
                </template>
            </div>
            <div class="dbx-lista">
                <div class="p-4 text-center secundario" x-show="cargando"><span class="spinner-border spinner-border-sm me-2"></span> Abriendo carpeta…</div>
                <div class="p-4 text-danger" x-show="error" x-text="error" x-cloak></div>
                <template x-if="!cargando && !error">
                    <div>
                        <template x-for="c in carpetas" :key="c.ruta">
                            <div class="dbx-fila carpeta" @click="ir(c.ruta)"><i class="bi bi-folder-fill tipo"></i><span class="n" x-text="c.nombre"></span><i class="bi bi-chevron-right text-secondary"></i></div>
                        </template>
                        <template x-for="a in archivos" :key="a.id">
                            <label class="dbx-fila mb-0">
                                <input type="checkbox" class="form-check-input mt-0" :value="a.id" x-model="elegidos">
                                <i class="bi tipo" :class="a.tipo === 'foto' ? 'bi-image' : (a.tipo === 'video' ? 'bi-film' : 'bi-file-earmark')"></i>
                                <span class="n" x-text="a.nombre"></span><span class="secundario num" style="font-size:12.5px" x-text="a.peso"></span>
                            </label>
                        </template>
                        <div class="p-4 text-center secundario" x-show="!carpetas.length && !archivos.length">Esta carpeta está vacía.</div>
                    </div>
                </template>
            </div>
            <div class="dbx-pie">
                <button type="button" class="btn btn-fantasma btn-sm" x-show="archivos.length" @click="elegidos = elegidos.length === archivos.length ? [] : archivos.map(a => a.id)" x-text="elegidos.length === archivos.length ? 'Quitar selección' : 'Elegir todos'"></button>
                <span class="secundario me-auto" style="font-size:13px" x-show="importando" x-text="'Importando ' + hechos + ' de ' + total + '…'"></span>
                <span class="text-danger me-auto" style="font-size:13px" x-show="errorImportar" x-text="errorImportar" x-cloak></span>
                <button type="button" class="btn btn-borde" @click="abierto = false" :disabled="importando">Cancelar</button>
                <button type="button" class="btn btn-primario" :disabled="!elegidos.length || importando" @click="importar()">
                    <i class="bi bi-download me-1"></i> <span x-text="elegidos.length ? 'Importar ' + elegidos.length : 'Importar'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
/* ---------- Dropbox: subida directa desde el navegador ---------- */
const VANDU_DBX = {{ Js::from([
    'activo'    => $dbx,
    'token'     => route('admin.dropbox.token'),
    'destino'   => route('admin.proyectos.dropbox.destino', $p),
    'registrar' => route('admin.proyectos.dropbox.registrar', $p),
    'csrf'      => csrf_token(),
]) }};
const DBX_CHUNK = 8 * 1024 * 1024;
// Dropbox pide el encabezado Dropbox-API-Arg en ASCII
const dbxArg = (o) => JSON.stringify(o).replace(/[\u007f-\uffff]/g, (c) => '\\u' + ('000' + c.charCodeAt(0).toString(16)).slice(-4));

function dbxPost(api, token, endpoint, arg, cuerpo, alAvanzar) {
    return new Promise((ok, mal) => {
        const x = new XMLHttpRequest();
        x.open('POST', api + endpoint);
        x.setRequestHeader('Authorization', 'Bearer ' + token);
        x.setRequestHeader('Dropbox-API-Arg', dbxArg(arg));
        x.setRequestHeader('Content-Type', 'application/octet-stream');
        if (alAvanzar) x.upload.onprogress = (e) => alAvanzar(e.loaded);
        x.onload = () => {
            if (x.status >= 200 && x.status < 300) { try { ok(JSON.parse(x.responseText || 'null')); } catch { ok(null); } return; }
            let m = x.responseText; try { m = JSON.parse(x.responseText).error_summary || m; } catch {}
            mal(new Error(x.status === 507 || /insufficient_space/.test(m) ? 'Tu Dropbox no tiene espacio suficiente.' : 'Dropbox respondió: ' + String(m).slice(0, 160)));
        };
        x.onerror = () => mal(new Error('Se perdió la conexión con Dropbox.'));
        x.send(cuerpo);
    });
}

async function conReintentos(fn, veces = 3) {
    for (let i = 1; ; i++) {
        try { return await fn(); } catch (e) { if (i >= veces) throw e; await new Promise((r) => setTimeout(r, 1200 * i)); }
    }
}

/** Sube un archivo a Dropbox (en partes de 8 MB si es grande). alAvanzar recibe los bytes de este archivo ya enviados. */
async function subirADropbox(archivo, ruta, cred, alAvanzar) {
    const commit = { path: ruta, mode: 'add', autorename: true, mute: true };
    if (archivo.size <= DBX_CHUNK) {
        return conReintentos(() => dbxPost(cred.api, cred.token, 'files/upload', commit, archivo, alAvanzar));
    }
    let offset = 0;
    const primera = archivo.slice(0, DBX_CHUNK);
    const inicio = await conReintentos(() => dbxPost(cred.api, cred.token, 'files/upload_session/start', { close: false }, primera, (n) => alAvanzar(n)));
    offset = primera.size;
    while (archivo.size - offset > DBX_CHUNK) {
        const parte = archivo.slice(offset, offset + DBX_CHUNK), base = offset;
        await conReintentos(() => dbxPost(cred.api, cred.token, 'files/upload_session/append_v2', { cursor: { session_id: inicio.session_id, offset: base }, close: false }, parte, (n) => alAvanzar(base + n)));
        offset += parte.size;
    }
    const resto = archivo.slice(offset), base = offset;
    return conReintentos(() => dbxPost(cred.api, cred.token, 'files/upload_session/finish', { cursor: { session_id: inicio.session_id, offset: base }, commit }, resto, (n) => alAvanzar(base + n)));
}

/** Toma un cuadro del video (en tu computadora, antes de subirlo) para usarlo como portada */
function portadaDeVideo(archivo) {
    return new Promise((ok) => {
        const v = document.createElement('video'), url = URL.createObjectURL(archivo);
        const fin = (b) => { URL.revokeObjectURL(url); ok(b); };
        const limite = setTimeout(() => fin(null), 10000);
        v.muted = true; v.playsInline = true; v.preload = 'metadata'; v.src = url;
        v.onloadedmetadata = () => { v.currentTime = Math.min(1.5, (v.duration || 3) / 3); };
        v.onseeked = () => {
            const esc = Math.min(1, 1600 / (v.videoWidth || 1600)), c = document.createElement('canvas');
            c.width = Math.round(v.videoWidth * esc); c.height = Math.round(v.videoHeight * esc);
            c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
            c.toBlob((b) => { clearTimeout(limite); fin(b); }, 'image/jpeg', 0.84);
        };
        v.onerror = () => { clearTimeout(limite); fin(null); };
    });
}

function subidor(url, grupo, etapaId) {
    return {
        subiendo: false, arrastrando: false, pct: 0, cuantos: 0, error: '', detalle: '',
        async enviarDropbox(lista) {
            const archivos = [...lista];
            this.cuantos = archivos.length; this.pct = 0; this.error = ''; this.subiendo = true;
            const total = archivos.reduce((s, f) => s + f.size, 0) || 1;
            let listos = 0;
            try {
                const cred = await (await fetch(VANDU_DBX.token, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })).json();
                const q = new URLSearchParams({ grupo, ...(etapaId ? { etapa_id: etapaId } : {}) });
                const r = await fetch(VANDU_DBX.destino + '?' + q, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!r.ok) throw new Error('No se pudo preparar la carpeta del proyecto en Dropbox.');
                const { carpeta } = await r.json();
                for (const [i, f] of archivos.entries()) {
                    this.detalle = (archivos.length > 1 ? (i + 1) + ' de ' + archivos.length + ' · ' : '') + f.name;
                    const meta = await subirADropbox(f, carpeta + '/' + f.name, cred, (n) => { this.pct = Math.min(99, Math.round((listos + n) / total * 100)); });
                    listos += f.size;
                    const fd = new FormData();
                    fd.append('_token', VANDU_DBX.csrf); fd.append('grupo', grupo); fd.append('dropbox_id', meta.id);
                    if (etapaId) fd.append('etapa_id', etapaId);
                    if (f.type.startsWith('video/')) { const portada = await portadaDeVideo(f); if (portada) fd.append('poster', portada, 'portada.jpg'); }
                    const reg = await fetch(VANDU_DBX.registrar, { method: 'POST', body: fd, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    if (!reg.ok) { let m = ''; try { m = (await reg.json()).message; } catch {} throw new Error('Se subió a Dropbox, pero no se pudo registrar en el panel' + (m ? ': ' + m : '.')); }
                }
                this.pct = 100; this.subiendo = false;
                window.vanduRefrescar ? window.vanduRefrescar() : location.reload();
            } catch (e) {
                this.subiendo = false; this.error = e.message || 'No se pudo subir a Dropbox.';
                if (listos) window.vanduRefrescar && window.vanduRefrescar();
            }
        },
        enviar(lista) {
            if (!lista || !lista.length) return;
            if (VANDU_DBX.activo) return this.enviarDropbox(lista);
            const fd = new FormData();
            fd.append('_token', '{{ csrf_token() }}');
            fd.append('grupo', grupo);
            if (etapaId) fd.append('etapa_id', etapaId);
            [...lista].forEach((f) => fd.append('archivos[]', f));
            this.cuantos = lista.length; this.pct = 0; this.error = ''; this.subiendo = true;

            const x = new XMLHttpRequest();
            x.open('POST', url);
            x.setRequestHeader('Accept', 'application/json');
            x.upload.onprogress = (e) => { if (e.lengthComputable) this.pct = Math.round(e.loaded / e.total * 100); };
            x.onload = () => {
                if (x.status >= 200 && x.status < 300) { this.subiendo = false; window.vanduRefrescar ? window.vanduRefrescar() : location.reload(); return; }
                this.subiendo = false;
                try { const r = JSON.parse(x.responseText); this.error = Object.values(r.errors || {}).flat()[0] || r.message; }
                catch { this.error = x.status === 413 ? 'Los archivos pesan más de lo que permite el servidor.' : 'No se pudieron subir los archivos.'; }
            };
            x.onerror = () => { this.subiendo = false; this.error = 'Se perdió la conexión al subir.'; };
            x.send(fd);
        },
    };
}

function exploradorDropbox(cfg) {
    return {
        abierto: false, cargando: false, error: '', ruta: cfg.inicio, migas: [], carpetas: [], archivos: [], elegidos: [],
        importando: false, hechos: 0, total: 0, errorImportar: '',
        abrir() { this.abierto = true; this.errorImportar = ''; this.ir(this.ruta); },
        async ir(ruta) {
            this.cargando = true; this.error = ''; this.elegidos = [];
            try {
                const r = await fetch(cfg.explorar + '?' + new URLSearchParams({ ruta }), { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const d = await r.json();
                if (!r.ok) throw new Error(d.error || 'No se pudo abrir la carpeta.');
                Object.assign(this, { ruta: d.ruta, migas: d.migas, carpetas: d.carpetas, archivos: d.archivos });
            } catch (e) { this.error = e.message; } finally { this.cargando = false; }
        },
        async importar() {
            const ids = [...this.elegidos];
            this.importando = true; this.hechos = 0; this.total = ids.length; this.errorImportar = '';
            for (let i = 0; i < ids.length; i += 5) {
                const fd = new FormData(); fd.append('_token', cfg.token);
                ids.slice(i, i + 5).forEach((id) => fd.append('ids[]', id));
                try {
                    const r = await fetch(cfg.importar, { method: 'POST', body: fd, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    const d = await r.json();
                    this.hechos += d.importados || 0;
                    if (d.errores && d.errores.length) this.errorImportar = d.errores[0];
                } catch (e) { this.errorImportar = 'Se perdió la conexión al importar.'; break; }
            }
            this.importando = false;
            if (!this.errorImportar) this.abierto = false;
            window.vanduRefrescar ? window.vanduRefrescar() : location.reload();
        },
    };
}
</script>
@endpush
