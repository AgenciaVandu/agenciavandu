@extends('admin.layout')
@section('titulo', $p->quien)

@php
    use App\Models\Prospecto;
    $d = fn ($n) => '$' . number_format($n ?? 0, 0);
    $abiertas = $etapas->where('tipo', 'abierta')->values();
    $ganada = $etapas->firstWhere('tipo', 'ganada');
    $perdida = $etapas->firstWhere('tipo', 'perdida');
    $tz = config('vandu.zona_horaria');
    $cuando = fn ($f) => $f->copy()->setTimezone($tz)->locale('es')->isoFormat('D MMM YYYY, h:mm a');
    $idxActual = $abiertas->search(fn ($e) => $e->id === $p->etapa_id);
    $tareasAbiertas = $p->tareas->where('estado', '!=', 'terminada');
@endphp

@push('head')
<style>
    .ficha { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 20px; align-items: start; }
    .cabecera { display: flex; align-items: center; gap: 16px; }
    .cabecera .avatar { width: 56px; height: 56px; font-size: 19px; border-radius: 14px; }
    .pasos { display: flex; gap: 4px; flex-wrap: wrap; }
    .pasos form { flex: 1 1 120px; margin: 0; }
    .pasos button { width: 100%; border: 0; padding: 10px 8px; font-size: 13px; font-weight: 500; background: #EEF0F3; color: var(--text-2); border-radius: 8px; line-height: 1.2; }
    .pasos button:hover:not(:disabled) { background: #E2E5EA; color: var(--text); }
    .pasos button.hecho { background: #D7F5E6; color: var(--green-ink); }
    .pasos button.actual { background: var(--ink); color: #fff; }
    .pasos button small { display: block; font-size: 11px; opacity: .75; font-weight: 400; }
    .cierre-btns { display: flex; gap: 8px; flex-wrap: wrap; }
    .estado-cerrado { padding: 14px 18px; border-radius: 12px; display: flex; gap: 12px; align-items: center; }
    .estado-cerrado.ganado { background: var(--green-soft); color: var(--green-ink); }
    .estado-cerrado.perdido { background: #FEF3F2; color: var(--red); }
    .datos dt { font-size: 13px; color: var(--muted); font-weight: 400; }
    .datos dd { margin: 2px 0 14px; overflow-wrap: anywhere; }
    .datos dd:last-child { margin-bottom: 0; }
    .linea { list-style: none; margin: 0; padding: 0 20px 16px; }
    .linea li { position: relative; padding: 0 0 16px 26px; }
    .linea li::before { content: ''; position: absolute; left: 7px; top: 22px; bottom: 0; width: 2px; background: var(--line); }
    .linea li:last-child::before { display: none; }
    .linea .pt { position: absolute; left: 0; top: 4px; width: 16px; height: 16px; border-radius: 50%; background: #EEF0F3; display: grid; place-items: center; font-size: 9px; color: var(--muted); }
    .linea .pt.verde { background: var(--green-soft); color: var(--green-ink); } .linea .pt.rojo { background: #FEF3F2; color: var(--red); } .linea .pt.negro { background: var(--ink); color: #fff; }
    .linea .q { font-size: 12.5px; color: var(--muted); }
    .linea .tx { white-space: pre-line; overflow-wrap: anywhere; }
    .tarea-fila { display: flex; gap: 10px; align-items: center; padding: 10px 20px; border-top: 1px solid var(--line); text-decoration: none; color: inherit; }
    .tarea-fila:hover { background: #FAFBFC; }
    .tarea-fila .w { min-width: 0; flex: 1; }
    .tarea-fila .w .s { font-size: 12.5px; color: var(--muted); }
    .tarea-fila .vencida { color: var(--red); }
    @media (max-width: 991.98px) { .ficha { grid-template-columns: minmax(0, 1fr); } }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.ventas.index') }}">Ventas</a> <i class="bi bi-chevron-right small"></i> <span>{{ $p->quien }}</span></div>
<div class="page-head">
    <div class="cabecera">
        @include('admin._avatar', ['nombre' => $p->quien])
        <div>
            <h1>{{ $p->quien }}</h1>
            <p class="sub">{{ $p->empresa ? $p->nombre . ' · ' : '' }}<i class="bi {{ Prospecto::ORIGENES[$p->origen]['icono'] ?? 'bi-dot' }}"></i> {{ Prospecto::ORIGENES[$p->origen]['texto'] ?? $p->origen }} · desde {{ $p->created_at->locale('es')->isoFormat('D MMM YYYY') }}</p>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if($p->whatsapp)<a class="btn btn-borde" target="_blank" rel="noopener" href="https://wa.me/{{ $p->whatsapp }}?text={{ rawurlencode('Hola ' . \App\Support\Correos::primerNombre((string) $p->nombre) . ', ') }}"><i class="bi bi-whatsapp me-1"></i> WhatsApp</a>@endif
        <a href="{{ route('admin.ventas.edit', $p) }}" class="btn btn-borde"><i class="bi bi-pencil me-1"></i> Editar</a>
        @if($p->presupuesto)
            <a href="{{ route('admin.presupuestos.edit', $p->presupuesto) }}" class="btn btn-primario"><i class="bi bi-file-earmark-text me-1"></i> Ver cotización {{ $p->presupuesto->folio }}</a>
        @elseif($p->etapa?->tipo !== 'perdida')
            <a href="{{ route('admin.presupuestos.create', ['prospecto' => $p->id]) }}" class="btn btn-primario"><i class="bi bi-file-earmark-plus me-1"></i> Hacer cotización</a>
        @endif
        @if(! $p->cliente && $p->etapa?->tipo !== 'perdida')
            <form method="post" action="{{ route('admin.ventas.convertir', $p) }}">@csrf
                <button class="btn btn-borde"><i class="bi bi-person-check me-1"></i> Convertir en cliente</button>
            </form>
        @endif
    </div>
</div>

<div class="ficha">
    <div class="d-grid gap-4">
        {{-- Etapa --}}
        <section class="panel">
            <div class="panel-head">
                <h2>{{ $p->etapa?->nombre }}</h2>
                <span class="ayuda num">{{ $p->valor ? $d($p->valor) . ' · ' : '' }}{{ $p->etapa?->tipo === 'abierta' ? 'hace ' . ($p->dias_en_etapa ?: 0) . ' ' . ($p->dias_en_etapa === 1 ? 'día' : 'días') . ' en esta etapa' : '' }}</span>
            </div>
            <div class="panel-body d-grid gap-3">
                @if($p->etapa?->tipo === 'ganada')
                    <div class="estado-cerrado ganado"><i class="bi bi-trophy-fill fs-4"></i><div><b>Ganado {{ $p->cerrado_at?->locale('es')->isoFormat('D [de] MMMM') }}</b><div style="font-size: 13.5px">{{ $p->valor ? 'Por ' . $d($p->valor) . ' antes de IVA' : 'Sin valor registrado' }}</div></div></div>
                @elseif($p->etapa?->tipo === 'perdida')
                    <div class="estado-cerrado perdido"><i class="bi bi-x-octagon-fill fs-4"></i><div><b>Perdido {{ $p->cerrado_at?->locale('es')->isoFormat('D [de] MMMM') }}</b><div style="font-size: 13.5px">{{ $p->motivo_perdida }}{{ $p->valor ? ' · ' . $d($p->valor) . ' que no se cerró' : '' }}</div></div></div>
                @endif
                <div class="pasos" role="group" aria-label="Etapas del embudo">
                    @foreach($abiertas as $i => $e)
                        <form method="post" action="{{ route('admin.ventas.etapa', $p) }}">@csrf @method('patch')
                            <input type="hidden" name="etapa_id" value="{{ $e->id }}">
                            <button class="{{ $e->id === $p->etapa_id ? 'actual' : ($idxActual !== false && $i < $idxActual ? 'hecho' : '') }}" @disabled($e->id === $p->etapa_id)>{{ $e->nombre }}<small>{{ $e->probabilidad }}%</small></button>
                        </form>
                    @endforeach
                </div>
                @if($p->etapa?->tipo === 'abierta')
                    <div class="cierre-btns">
                        <details>
                            <summary class="btn btn-borde btn-sm" style="color: var(--green-ink)"><i class="bi bi-trophy me-1"></i> Marcar como ganado</summary>
                            <form method="post" action="{{ route('admin.ventas.etapa', $p) }}" class="d-flex flex-wrap gap-2 align-items-end mt-2">@csrf @method('patch')
                                <input type="hidden" name="etapa_id" value="{{ $ganada->id }}">
                                <div><label class="form-label" for="valor-g">¿En cuánto se cerró?</label>
                                    <div class="input-group" style="max-width: 200px"><span class="input-group-text">$</span><input id="valor-g" type="number" step="0.01" min="0" name="valor" value="{{ $p->valor }}" class="form-control num"></div></div>
                                <button class="btn btn-primario">Ganado</button>
                            </form>
                        </details>
                        <details>
                            <summary class="btn btn-borde btn-sm" style="color: var(--red)"><i class="bi bi-x-octagon me-1"></i> Marcar como perdido</summary>
                            <form method="post" action="{{ route('admin.ventas.etapa', $p) }}" class="d-flex flex-wrap gap-2 align-items-end mt-2">@csrf @method('patch')
                                <input type="hidden" name="etapa_id" value="{{ $perdida->id }}">
                                <div><label class="form-label" for="motivo">¿Por qué?</label>
                                    <select id="motivo" name="motivo" class="form-select">@foreach(Prospecto::MOTIVOS as $m)<option>{{ $m }}</option>@endforeach</select></div>
                                <div class="flex-grow-1"><label class="form-label" for="nota-p">Nota</label><input id="nota-p" name="nota" class="form-control" maxlength="500"></div>
                                <button class="btn btn-borde">Perdido</button>
                            </form>
                        </details>
                    </div>
                @else
                    <div class="secundario" style="font-size: 13px">¿Se reactivó? Elige la etapa en la que va y vuelve al embudo.</div>
                @endif
            </div>
        </section>

        {{-- Seguimiento --}}
        <section class="panel" id="seguimiento">
            <div class="panel-head"><h2>Seguimiento</h2><span class="ayuda">{{ $tareasAbiertas->count() ? $tareasAbiertas->count() . ' ' . ($tareasAbiertas->count() === 1 ? 'pendiente' : 'pendientes') : 'Sin pendientes' }}</span></div>
            @foreach($p->tareas as $t)
                <a href="{{ route('admin.tareas.show', $t) }}" class="tarea-fila">
                    <i class="bi {{ \App\Models\Tarea::ESTADOS[$t->estado]['icono'] }}" style="color: {{ \App\Models\Tarea::ESTADOS[$t->estado]['color'] }}"></i>
                    <span class="w"><span class="d-block text-truncate {{ $t->estado === 'terminada' ? 'text-decoration-line-through secundario' : '' }}">{{ $t->titulo }}</span>
                        <span class="s">{{ $t->responsable?->primer_nombre ?? 'Sin asignar' }}</span></span>
                    @if($t->cuando && $t->estado !== 'terminada')<span class="num text-nowrap {{ $t->vencida ? 'vencida' : 'secundario' }}" style="font-size: 13px"><i class="bi bi-alarm"></i> {{ $t->cuando }}</span>@endif
                </a>
            @endforeach
            <form method="post" action="{{ route('admin.ventas.seguimiento', $p) }}" class="panel-body {{ $p->tareas->isNotEmpty() ? 'border-top' : '' }} row g-2 align-items-end mx-0">
                @csrf
                <div class="col-12 col-md-4">
                    <label class="form-label" for="seg-titulo">Qué hay que hacer</label>
                    <input id="seg-titulo" name="titulo" class="form-control" value="{{ $p->etapa?->tipo === 'abierta' ? 'Dar seguimiento a ' . \App\Support\Correos::primerNombre((string) $p->nombre) : '' }}" required maxlength="160">
                </div>
                <div class="col-6 col-md-3"><label class="form-label" for="seg-fecha">Fecha</label><input id="seg-fecha" type="date" name="fecha_limite" class="form-control num" value="{{ now($tz)->addDay()->toDateString() }}"></div>
                <div class="col-6 col-md-2"><label class="form-label" for="seg-hora">Hora</label><input id="seg-hora" type="time" step="300" name="hora" class="form-control num" value="10:00"></div>
                <div class="col-8 col-md-2"><label class="form-label" for="seg-quien">Para</label>
                    <select id="seg-quien" name="asignada_a" class="form-select">@foreach($equipo as $u)<option value="{{ $u->id }}" @selected($u->id === auth()->id())>{{ \Illuminate\Support\Str::before($u->name, ' ') }}</option>@endforeach</select></div>
                <div class="col-4 col-md-1"><button class="btn btn-primario w-100" title="Agendar"><i class="bi bi-plus-lg"></i></button></div>
            </form>
        </section>

        {{-- Historial --}}
        <section class="panel">
            <div class="panel-head"><h2>Notas e historial</h2></div>
            <form method="post" action="{{ route('admin.ventas.nota', $p) }}" class="panel-body d-grid gap-2">
                @csrf
                <label class="visually-hidden" for="nota">Nota</label>
                <textarea id="nota" name="texto" rows="2" class="form-control" placeholder="Qué platicaron, qué necesita, cuándo decide…" required maxlength="3000"></textarea>
                <div><button class="btn btn-borde btn-sm"><i class="bi bi-plus-lg me-1"></i> Agregar nota</button></div>
            </form>
            <ul class="linea">
                @foreach($p->eventos as $ev)
                    @php
                        [$ico, $cls, $titulo] = match ($ev->tipo) {
                            'alta'       => ['bi-plus', 'negro', 'Entró al embudo' . ($ev->a ? ' en ' . $ev->a->nombre : '')],
                            'etapa'      => $ev->a?->tipo === 'ganada' ? ['bi-trophy-fill', 'verde', '¡Ganado!'] : ($ev->a?->tipo === 'perdida' ? ['bi-x', 'rojo', 'Se perdió'] : ['bi-arrow-right', '', ($ev->de ? $ev->de->nombre . ' → ' : 'Pasó a ') . ($ev->a?->nombre ?? 'otra etapa')]),
                            'nota'       => ['bi-chat-left-text', '', 'Nota'],
                            'mensaje'    => ['bi-globe2', 'verde', 'Escribió desde el sitio'],
                            'convertido' => ['bi-person-check', 'verde', 'Se volvió cliente'],
                            'cotizacion' => ['bi-file-earmark-text', 'negro', 'Cotización'],
                            default      => ['bi-dot', '', ucfirst($ev->tipo)],
                        };
                    @endphp
                    <li>
                        <span class="pt {{ $cls }}"><i class="bi {{ $ico }}"></i></span>
                        <div><b style="font-size: 14px">{{ $titulo }}</b> <span class="q">· {{ $cuando($ev->created_at) }}{{ $ev->user ? ' · ' . $ev->user->primer_nombre : '' }}</span></div>
                        @if($ev->texto)<div class="tx">{{ $ev->texto }}</div>@endif
                        @if($ev->tipo === 'mensaje' && is_array($ev->datos))
                            <div class="q">{{ collect([$ev->datos['email'] ?? null, $ev->datos['phone'] ?? null])->filter()->implode(' · ') }}</div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    <div class="d-grid gap-4">
        <section class="panel">
            <div class="panel-head"><h2>Datos</h2><a href="{{ route('admin.ventas.edit', $p) }}" class="btn btn-fantasma btn-sm">Editar</a></div>
            <dl class="panel-body datos m-0">
                <dt>Valor de la venta</dt><dd class="num fw-semibold">{{ $p->valor ? $d($p->valor) : 'Sin valor' }}@if($p->valor && $p->etapa?->tipo === 'abierta') <span class="secundario fw-normal">· pronóstico {{ $d($p->ponderado) }}</span>@endif</dd>
                @if($p->interes)<dt>Le interesa</dt><dd>{{ $p->interes }}</dd>@endif
                @if($p->telefono)<dt>Teléfono</dt><dd class="num"><a href="tel:{{ preg_replace('/\D/', '', $p->telefono) }}" class="text-reset">{{ $p->telefono }}</a></dd>@endif
                @if($p->email)<dt>Correo</dt><dd><a href="mailto:{{ $p->email }}" class="text-reset">{{ $p->email }}</a></dd>@endif
                <dt>Responsable</dt><dd>{{ $p->responsable?->name ?? 'Sin asignar' }}</dd>
                @if($p->notas)<dt>Notas</dt><dd style="white-space: pre-line">{{ $p->notas }}</dd>@endif
            </dl>
        </section>
        @if($p->cliente || $cotizaciones->isNotEmpty())
            <section class="panel">
                <div class="panel-head"><h2>{{ $p->cliente ? 'Cliente' : 'Cotizaciones' }}</h2>@if($p->etapa?->tipo !== 'perdida')<a href="{{ route('admin.presupuestos.create', ['prospecto' => $p->id]) }}" class="btn btn-fantasma btn-sm"><i class="bi bi-plus-lg me-1"></i> Otra</a>@endif</div>
                <div class="panel-body d-grid gap-2">
                    @if($p->cliente)<a href="{{ route('admin.clientes.show', $p->cliente) }}" class="d-flex align-items-center gap-2 text-reset text-decoration-none">@include('admin._avatar', ['nombre' => $p->cliente->empresa ?: $p->cliente->nombre])<span class="principal">{{ $p->cliente->empresa ?: $p->cliente->nombre }}</span></a>@endif
                    @foreach($cotizaciones->take(5) as $c)
                        <a href="{{ route('admin.presupuestos.edit', $c) }}" class="d-flex justify-content-between text-reset text-decoration-none" style="font-size: 13.5px">
                            <span class="num">{{ $c->folio }} <span class="estado estado-{{ $c->estado }} ms-1">{{ \App\Models\Presupuesto::ESTADOS[$c->estado] }}</span></span><span class="num">{{ $d($c->subtotal) }}</span></a>
                    @endforeach
                </div>
            </section>
        @endif
        <form method="post" action="{{ route('admin.ventas.destroy', $p) }}" onsubmit="return confirm({{ Js::from('¿Eliminar a ' . $p->quien . ' de Ventas? Se borra su historial; su ficha de cliente (si tiene) se queda.') }})">
            @csrf @method('delete')
            <button class="btn btn-fantasma btn-sm text-danger"><i class="bi bi-trash me-1"></i> Eliminar prospecto</button>
        </form>
    </div>
</div>
@endsection
