@extends('admin.layout')
@section('titulo', $t->titulo)

@php
    $e = \App\Models\Tarea::ESTADOS[$t->estado];
    $puedeSubir = $t->carpeta && $dropbox && ($t->estado !== 'terminada' || $gestiona);
    $hayError = isset($archivos['error']);
    $lista = $hayError ? [] : $archivos;
    $estadoBtn = fn ($estado, $texto, $icono, $clase = 'btn-borde', $nota = false) => compact('estado', 'texto', 'icono', 'clase', 'nota');
    // Quien gestiona revisa lo entregado; si la tarea es suya, puede aprobarla directo sin pasar por revisión
    $revisa = $gestiona && ($t->estado === 'revision' || ($mia && in_array($t->estado, ['pendiente', 'en_curso'], true)));
    $acciones = [];
    if ($t->estado === 'pendiente' && ($mia || $gestiona)) $acciones[] = $estadoBtn('en_curso', 'Empezar', 'bi-play-fill', $revisa ? 'btn-borde' : 'btn-primario');
    if ($t->estado === 'en_curso' && $mia && ! $gestiona) $acciones[] = $estadoBtn('revision', 'Entregar para revisión', 'bi-send', 'btn-primario', true);
    if ($t->estado === 'revision' && $mia && ! $gestiona) $acciones[] = $estadoBtn('en_curso', 'Seguir trabajando', 'bi-arrow-counterclockwise');
    if ($t->estado === 'terminada' && $gestiona) $acciones[] = $estadoBtn('en_curso', 'Reabrir', 'bi-arrow-counterclockwise');
    if ($gestiona && ! $revisa && in_array($t->estado, ['pendiente', 'en_curso'], true)) $acciones[] = $estadoBtn('terminada', 'Cerrar sin entrega', 'bi-x-circle', 'btn-fantasma');
    $pendientesRev = collect($lista);
@endphp

@push('head')
<style>
    .ts-grid { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); gap: 20px; align-items: start; }
    .ts-datos { display: grid; gap: 12px; font-size: 14.5px; }
    .ts-datos .f { display: flex; gap: 10px; }
    .ts-datos .f > i { color: var(--muted); width: 18px; text-align: center; margin-top: 2px; }
    .ts-datos .f .k { font-size: 12.5px; color: var(--muted); display: block; }
    .ts-desc { white-space: pre-line; font-size: 15px; line-height: 1.6; color: var(--text-2); }
    .estado-t { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 500; padding: 3px 10px; border-radius: 99px; background: var(--sunken); border: 1px solid var(--line); }
    .ts-acciones { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .zona { border: 2px dashed var(--line-strong); border-radius: 14px; padding: 26px 16px; text-align: center; background: var(--sunken); transition: border-color .12s, background .12s; cursor: pointer; }
    .zona:hover, .zona.sobre { border-color: var(--ink); background: #fff; }
    .zona i { font-size: 28px; color: var(--muted); }
    .zona b { display: block; margin-top: 6px; font-weight: 600; }
    .zona span { font-size: 13px; color: var(--muted); }
    .subidas { list-style: none; margin: 12px 0 0; padding: 0; display: grid; gap: 8px; }
    .subidas li { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 4px 10px; font-size: 13.5px; }
    .subidas .barra { grid-column: 1 / -1; height: 6px; border-radius: 99px; background: var(--sunken); overflow: hidden; }
    .subidas .barra span { display: block; height: 100%; background: var(--ink); transition: width .2s; }
    .subidas li.listo .barra span { background: var(--green-ink); }
    .subidas li.mal .barra span { background: var(--red); }
    .arch { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
    .arch .a { border: 1px solid var(--line); border-radius: 12px; overflow: hidden; background: var(--surface); display: flex; flex-direction: column; position: relative; }
    .arch .mini { aspect-ratio: 4 / 3; background: var(--sunken); display: grid; place-items: center; color: var(--muted); font-size: 30px; text-decoration: none; overflow: hidden; }
    .arch .mini img { width: 100%; height: 100%; object-fit: cover; }
    .arch .info { padding: 8px 10px; font-size: 12.5px; min-width: 0; }
    .arch .info .n { font-weight: 500; font-size: 13px; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; text-decoration: none; }
    .arch .info .s { color: var(--muted); }
    .arch .quitar { position: absolute; top: 6px; right: 6px; width: 28px; height: 28px; border-radius: 8px; border: 0; background: rgba(255,255,255,.92); color: var(--text-2); display: grid; place-items: center; box-shadow: 0 1px 4px rgba(0,0,0,.12); }
    .arch .quitar:hover { color: var(--red); }
    .revision { border-color: var(--ink); box-shadow: 0 6px 22px rgba(19,22,29,.07); }
    .dest-op { display: flex; flex-wrap: wrap; gap: 8px; }
    .dest-op label { display: inline-flex; gap: 8px; align-items: center; border: 1.5px solid var(--line-strong); border-radius: 10px; padding: 8px 12px; cursor: pointer; font-size: 14px; }
    .dest-op label.sel { border-color: var(--ink); background: var(--sunken); }
    .dest-op input { display: none; }
    .dest-op b { font-weight: 500; }
    .rev-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 8px; max-height: 420px; overflow-y: auto; padding: 2px; }
    .rev-a { position: relative; border: 2px solid var(--line); border-radius: 10px; overflow: hidden; cursor: pointer; display: flex; flex-direction: column; background: var(--surface); }
    .rev-a input { position: absolute; opacity: 0; pointer-events: none; }
    .rev-a .mini { aspect-ratio: 1; background: var(--sunken); display: grid; place-items: center; color: var(--muted); font-size: 24px; overflow: hidden; }
    .rev-a .mini img { width: 100%; height: 100%; object-fit: cover; }
    .rev-a .n { font-size: 11.5px; padding: 4px 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .rev-a .marca { position: absolute; top: 5px; right: 5px; font-size: 18px; color: var(--green-ink); background: #fff; border-radius: 50%; line-height: 1; }
    .rev-a.on { border-color: var(--green-ink); }
    .rev-a.off { opacity: .5; }
    .rev-a.off .marca { color: #C9CCD3; }
    .rev-a:has(input:focus-visible) { outline: 2px solid var(--ink); outline-offset: 2px; }
    .com { list-style: none; margin: 0; padding: 0; display: grid; gap: 14px; }
    .com li { display: flex; gap: 10px; }
    .com .avatar { width: 30px; height: 30px; font-size: 11px; flex: none; }
    .com .t { font-size: 14px; line-height: 1.5; white-space: pre-line; }
    .com .q { font-size: 12.5px; color: var(--muted); }
    .com .q b { color: var(--text); font-weight: 600; }
    @media (max-width: 991.98px) { .ts-grid { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 575.98px) { .arch { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.tareas.index') }}">{{ $gestiona ? 'Tareas' : 'Mis tareas' }}</a> <i class="bi bi-chevron-right small"></i> <span class="text-truncate">{{ $t->titulo }}</span></div>
<div class="page-head">
    <div class="min-w-0">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
            <span class="estado-t"><i class="bi {{ $e['icono'] }}" style="color: {{ $e['color'] }}"></i> {{ $e['texto'] }}</span>
            @if($t->urgente)<span class="estado estado-rechazada">Urgente</span>@endif
            @if($t->vencida)<span class="estado estado-rechazada">{{ $t->cuando }}</span>@endif
        </div>
        <h1>{{ $t->titulo }}</h1>
        @if($t->donde)<p class="sub">{{ $t->donde }}</p>@endif
    </div>
    <div class="ts-acciones" x-data="{ nota: null }">
        @foreach($acciones as $a)
            <form method="post" action="{{ route('admin.tareas.estado', $t) }}" class="d-inline-flex">@csrf @method('patch')
                <input type="hidden" name="estado" value="{{ $a['estado'] }}">
                @if($a['nota'])<input type="hidden" name="nota" :value="nota">@endif
                <button class="btn {{ $a['clase'] }}" @if($a['nota']) @click="nota = prompt({{ Js::from($a['estado'] === 'revision' ? '¿Algo que deba saber quien revisa? (opcional)' : '¿Qué hay que ajustar?') }}, ''); if (nota === null) $event.preventDefault()" @endif><i class="bi {{ $a['icono'] }} me-1"></i> {{ $a['texto'] }}</button>
            </form>
        @endforeach
        @if($gestiona)<a href="{{ route('admin.tareas.edit', $t) }}" class="btn btn-borde"><i class="bi bi-pencil me-1"></i> Editar</a>@endif
    </div>
</div>

<div class="ts-grid">
    <div class="d-grid gap-4">
        @if($revisa)
            <section class="panel revision" x-data="revisionTarea({{ Js::from(['ids' => $pendientesRev->pluck('id')->values(), 'destino' => (string) ($t->destino ?? ($t->proyecto ? ($t->proyecto->tiene_galeria ? 'galeria' : 'documento') : '')), 'etapa' => (string) ($t->etapa_id ?? ''), 'completar' => (bool) $t->completar_etapa]) }})">
                <div class="panel-head">
                    <h2 class="d-flex align-items-center gap-2"><i class="bi bi-clipboard-check"></i> {{ $t->estado === 'revision' ? 'Revisa la entrega' . ($t->ronda > 1 ? ' ' . $t->ronda : '') : '¿Ya quedó? Apruébala' }}</h2>
                    <span class="ayuda">@if($t->estado === 'revision' && $t->responsable) Entregada por {{ $t->responsable->primer_nombre }} {{ $t->entregada_at?->locale('es')->diffForHumans() }} @else Es tuya: no necesita pasar por revisión @endif</span>
                </div>
                <form method="post" action="{{ route('admin.tareas.aprobar', $t) }}" class="panel-body d-grid gap-3" id="form-aprobar">
                    @csrf
                    @if($t->proyecto)
                        <input type="hidden" name="destino" :value="destino">
                        <div>
                            <span class="form-label d-block">Incluir en <b>{{ $t->proyecto->nombre }}</b> como</span>
                            <div class="dest-op">
                                @foreach($destinos as $dk => $dd)
                                    <label :class="destino === '{{ $dk }}' && 'sel'"><input type="radio" value="{{ $dk }}" x-model="destino"><i class="bi {{ $dd['icono'] }}"></i><span><b>{{ $dd['texto'] }}</b></span></label>
                                @endforeach
                                <label :class="destino === '' && 'sel'"><input type="radio" value="" x-model="destino"><i class="bi bi-slash-circle"></i><span><b>No incluir</b></span></label>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-3 align-items-end" x-show="destino !== ''">
                            <div style="min-width: 240px">
                                <label class="form-label" for="ap-etapa">Etapa</label>
                                <select id="ap-etapa" name="etapa_id" class="form-select" x-model="etapa">
                                    <option value="">Ninguna en particular</option>
                                    @foreach($etapasProyecto as $ep)<option value="{{ $ep->id }}">{{ $ep->nombre }}{{ $ep->estado === 'completada' ? ' · completada' : '' }}</option>@endforeach
                                </select>
                            </div>
                            <label class="form-check m-0" x-show="etapa"><input type="checkbox" class="form-check-input" name="completar_etapa" value="1" x-model="completar"> <span class="form-check-label">Marcar la etapa como completada</span></label>
                            <div x-show="destino === 'galeria'" style="min-width: 260px" x-data="{ sec: 'nueva' }">
                                <label class="form-label" for="ap-sec">Sección de la galería</label>
                                <select id="ap-sec" name="seccion" class="form-select" x-model="sec">
                                    <option value="nueva">Nueva sección…</option>
                                    @foreach($seccionesProyecto as $sx)<option value="{{ $sx->id }}">{{ $sx->nombre }}</option>@endforeach
                                </select>
                                <input name="seccion_nombre" class="form-control mt-2" x-show="sec === 'nueva'" value="{{ $t->titulo }}" maxlength="120" aria-label="Nombre de la sección nueva">
                            </div>
                            <label class="form-check m-0" x-show="destino === 'galeria'"><input type="hidden" name="publicar" value="0"><input type="checkbox" class="form-check-input" name="publicar" value="1" checked> <span class="form-check-label">Publicar para el cliente</span></label>
                        </div>
                    @endif

                    @if($pendientesRev->isNotEmpty())
                        <div x-show="{{ $t->proyecto ? "destino !== ''" : 'false' }}">
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <b style="font-size:14px">Elige qué pasa al proyecto</b>
                                <span class="secundario" style="font-size:13px" x-text="sel.length + ' de ' + ids.length"></span>
                                <button type="button" class="btn btn-link btn-sm p-0 ms-auto" @click="sel = [...ids]">Todos</button>
                                <button type="button" class="btn btn-link btn-sm p-0" @click="sel = []">Ninguno</button>
                            </div>
                            <div class="rev-grid">
                                @foreach($pendientesRev as $a)
                                    <label class="rev-a" :class="sel.includes({{ Js::from($a['id']) }}) ? 'on' : 'off'">
                                        <input type="checkbox" name="ids[]" value="{{ $a['id'] }}" x-model="sel">
                                        <span class="mini">@if($a['tipo'] === 'foto')<img src="{{ route('admin.tareas.archivo', [$t, $a['id']]) }}?t=p" alt="" loading="lazy">@else<i class="bi {{ $a['tipo'] === 'video' ? 'bi-camera-video' : 'bi-file-earmark' }}"></i>@endif</span>
                                        <span class="n" title="{{ $a['nombre'] }}">{{ $a['nombre'] }}</span>
                                        <i class="bi bi-check-circle-fill marca"></i>
                                    </label>
                                @endforeach
                            </div>
                            <p class="secundario m-0 mt-2" style="font-size:12.5px">Lo que no elijas se queda en la carpeta de la tarea.</p>
                        </div>
                    @elseif($t->proyecto && $t->carpeta)
                        <p class="secundario m-0" style="font-size:13.5px" x-show="destino !== ''">No hay archivos nuevos en la carpeta. Puedes aprobarla igual.</p>
                    @endif

                    <textarea name="nota" class="form-control" rows="2" maxlength="2000" placeholder="Un comentario para {{ $mia ? 'el historial' : ($t->responsable?->primer_nombre ?? 'el equipo') }} (opcional)" aria-label="Comentario de aprobación"></textarea>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <button class="btn btn-primario">
                            <i class="bi bi-check2-circle me-1"></i>
                            <span x-text="destino !== '' && sel.length ? 'Aprobar e incluir ' + sel.length + (sel.length === 1 ? ' archivo' : ' archivos') + ' en el proyecto' : 'Aprobar y terminar'"></span>
                        </button>
                        @if($t->estado === 'revision')
                            <button type="button" class="btn btn-borde" @click="ajustes = !ajustes"><i class="bi bi-arrow-counterclockwise me-1"></i> Pedir ajustes</button>
                        @endif
                    </div>
                </form>
                @if($t->estado === 'revision')
                    <form method="post" action="{{ route('admin.tareas.estado', $t) }}" class="panel-body pt-0 d-grid gap-2" x-show="ajustes" x-cloak>
                        @csrf @method('patch')
                        <input type="hidden" name="estado" value="en_curso">
                        <label class="form-label m-0" for="ap-ajustes">¿Qué hay que ajustar?</label>
                        <textarea id="ap-ajustes" name="nota" class="form-control" rows="3" maxlength="2000" required placeholder="Ej. Faltan las fotos de la alberca y la 12 salió movida."></textarea>
                        <div><button class="btn btn-primario btn-sm"><i class="bi bi-send me-1"></i> Mandar observaciones a {{ $t->responsable?->primer_nombre ?? 'quien la tiene' }}</button>
                            <span class="secundario ms-2" style="font-size:12.5px">La tarea regresa a “en curso” y no se termina.</span></div>
                    </form>
                @endif
            </section>
        @endif

        @if($incluidos->isNotEmpty())
            <section class="panel">
                <div class="panel-head"><h2>Ya en el proyecto</h2>
                    @if($t->proyecto && auth()->user()->puede('proyectos'))<a href="{{ route('admin.proyectos.show', $t->proyecto) }}" class="btn btn-borde btn-sm"><i class="bi bi-kanban me-1"></i> Ver proyecto</a>@endif
                </div>
                <div class="panel-body">
                    <p class="secundario m-0" style="font-size:13.5px"><i class="bi bi-check-circle-fill" style="color:var(--green-ink)"></i>
                        {{ $incluidos->count() }} {{ $incluidos->count() === 1 ? 'archivo aprobado' : 'archivos aprobados' }}@if($t->aprobador) por {{ $t->aprobador->primer_nombre }}@endif
                        · {{ $incluidos->take(4)->pluck('nombre')->implode(', ') }}{{ $incluidos->count() > 4 ? '…' : '' }}</p>
                </div>
            </section>
        @endif

        @if($t->descripcion)
            <section class="panel"><div class="panel-body ts-desc">{{ $t->descripcion }}</div></section>
        @endif

        <section class="panel" x-data="subidaTarea({{ Js::from(['url' => route('admin.tareas.subir', $t), 'parte' => $parte]) }})">
            <div class="panel-head"><h2>Entregas</h2>
                <span class="ayuda">@if($t->carpeta) {{ count($lista) }} {{ count($lista) === 1 ? 'archivo' : 'archivos' }} en la carpeta @endif</span>
            </div>
            <div class="panel-body d-grid gap-3">
                @if(! $t->carpeta)
                    <p class="secundario m-0">Esta tarea no tiene carpeta de entrega. @if($gestiona)<a href="{{ route('admin.tareas.edit', $t) }}">Elige una</a> para que puedan subir su trabajo.@endif</p>
                @elseif(! $dropbox)
                    <p class="secundario m-0"><i class="bi bi-exclamation-triangle"></i> Dropbox no está conectado, por ahora no se puede subir ni ver lo entregado.</p>
                @else
                    <div class="d-flex flex-wrap align-items-center gap-2 secundario" style="font-size:13px">
                        <i class="bi bi-dropbox"></i> <span class="text-break">{{ $t->carpeta }}</span>
                        @if($gestiona)<a href="https://www.dropbox.com/home{{ str_replace('%2F', '/', rawurlencode($t->carpeta)) }}" target="_blank" rel="noopener" class="ms-1">Abrir en Dropbox</a>@endif
                    </div>
                    @if($puedeSubir)
                        <label class="zona" :class="sobre && 'sobre'" @dragover.prevent="sobre = true" @dragleave="sobre = false" @drop.prevent="sobre = false; agregar($event.dataTransfer.files)">
                            <input type="file" multiple hidden @change="agregar($event.target.files); $event.target.value = ''">
                            <i class="bi bi-cloud-arrow-up"></i>
                            <b>Arrastra aquí tus archivos o toca para elegirlos</b>
                            <span>Fotos, videos, diseños… sin límite de tamaño. Se guardan directo en la carpeta del cliente.</span>
                        </label>
                        <ul class="subidas" x-show="subidas.length" x-cloak>
                            <template x-for="s in subidas" :key="s.id">
                                <li :class="{ listo: s.listo, mal: s.error }">
                                    <span class="text-truncate" x-text="s.nombre"></span>
                                    <span class="secundario num" x-text="s.error ? s.error : (s.listo ? 'Listo' : Math.round(s.avance * 100) + '%')"></span>
                                    <div class="barra"><span :style="'width:' + Math.max(2, s.avance * 100) + '%'"></span></div>
                                </li>
                            </template>
                        </ul>
                        <div class="aviso aviso-error mb-0 py-2" x-show="aviso" x-cloak x-text="aviso" style="font-size:13.5px"></div>
                    @endif

                    @if($hayError)
                        <div class="aviso aviso-error mb-0"><i class="bi bi-exclamation-circle"></i> {{ $archivos['error'] }}</div>
                    @elseif(! $lista)
                        <p class="secundario m-0" style="font-size:13.5px">Todavía no hay nada en la carpeta.</p>
                    @else
                        <div class="arch">
                            @foreach($lista as $a)
                                <div class="a">
                                    <a href="{{ route('admin.tareas.archivo', [$t, $a['id']]) }}" target="_blank" rel="noopener" class="mini" title="Abrir {{ $a['nombre'] }}">
                                        @if($a['tipo'] === 'foto')<img src="{{ route('admin.tareas.archivo', [$t, $a['id']]) }}?t=p" alt="" loading="lazy" x-on:error="$el.replaceWith(Object.assign(document.createElement('i'), { className: 'bi bi-image' }))">
                                        @else<i class="bi {{ $a['tipo'] === 'video' ? 'bi-camera-video' : 'bi-file-earmark' }}"></i>@endif
                                    </a>
                                    <div class="info">
                                        <a href="{{ route('admin.tareas.archivo', [$t, $a['id']]) }}" target="_blank" rel="noopener" class="n" title="{{ $a['nombre'] }}">{{ $a['nombre'] }}</a>
                                        <span class="s">@if(($a['ronda'] ?? 1) > 1)<b style="color:var(--amber)">Vuelta {{ $a['ronda'] }}</b> · @endif{{ $a['peso'] }}@if($a['quien']) · {{ $a['quien'] }}@endif @if($a['cuando']) · {{ $a['cuando']->locale('es')->diffForHumans(null, true) }}@endif</span>
                                    </div>
                                    @if($gestiona || ($a['mio'] && $a['mio'] === auth()->id()))
                                        <form method="post" action="{{ route('admin.tareas.archivo.quitar', [$t, $a['id']]) }}" onsubmit="return confirm('¿Quitar {{ addslashes($a['nombre']) }}? Se manda a la papelera de Dropbox.')">@csrf @method('delete')
                                            <button class="quitar" title="Quitar" aria-label="Quitar {{ $a['nombre'] }}"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Comentarios</h2></div>
            <div class="panel-body d-grid gap-3">
                @if($t->comentarios->isEmpty())
                    <p class="secundario m-0" style="font-size:13.5px">Usa los comentarios para dudas, ajustes o avisos sobre esta tarea.</p>
                @else
                    <ul class="com">
                        @foreach($t->comentarios as $c)
                            <li>@include('admin._avatar', ['nombre' => $c->user?->name ?? '?'])
                                <div class="min-w-0"><div class="q"><b>{{ $c->user?->name ?? 'Alguien' }}</b> · {{ $c->created_at->locale('es')->diffForHumans() }}</div><div class="t">{{ $c->texto }}</div></div></li>
                        @endforeach
                    </ul>
                @endif
                <form method="post" action="{{ route('admin.tareas.comentar', $t) }}" class="d-grid gap-2">@csrf
                    <textarea name="texto" class="form-control" rows="2" maxlength="2000" placeholder="Escribe un comentario…" required aria-label="Comentario"></textarea>
                    <div><button class="btn btn-borde btn-sm"><i class="bi bi-chat-left-text me-1"></i> Comentar</button></div>
                </form>
            </div>
        </section>
    </div>

    <section class="panel">
        <div class="panel-body ts-datos">
            <div class="f"><i class="bi bi-person"></i><div><span class="k">Para</span>@if($t->responsable){{ $t->responsable->name }}@if($t->responsable->puesto) <span class="secundario">· {{ $t->responsable->puesto }}</span>@endif @else <span class="secundario">Sin asignar</span>@endif</div></div>
            <div class="f"><i class="bi bi-calendar3"></i><div><span class="k">Fecha límite</span>@if($t->fecha_limite){{ ucfirst($t->fecha_limite->locale('es')->isoFormat('dddd D [de] MMMM')) }} <span class="secundario">· {{ $t->cuando }}</span>@else<span class="secundario">Sin fecha</span>@endif</div></div>
            @if($t->cliente || $t->proyecto)
                <div class="f"><i class="bi bi-building"></i><div><span class="k">Cliente / proyecto</span>
                    @if($t->proyecto && auth()->user()->puede('proyectos'))<a href="{{ route('admin.proyectos.show', $t->proyecto) }}">{{ $t->donde }}</a>@else{{ $t->donde }}@endif</div></div>
            @endif
            <div class="f"><i class="bi bi-clock-history"></i><div><span class="k">Historial</span>
                Creada {{ $t->created_at->locale('es')->diffForHumans() }}@if($t->autor) por {{ $t->autor->primer_nombre }}@endif
                @if($t->entregada_at)<br>Entregada {{ $t->entregada_at->locale('es')->diffForHumans() }}@endif
                @if($t->terminada_at)<br>Terminada {{ $t->terminada_at->locale('es')->diffForHumans() }}@endif
            </div></div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
window.revisionTarea = (cfg) => ({ ...cfg, sel: [...cfg.ids], ajustes: false });

// Sube cada archivo en partes al panel; el panel las pasa a Dropbox
window.subidaTarea = (cfg) => ({
    ...cfg, subidas: [], sobre: false, aviso: '', _n: 0, corriendo: false,
    agregar(lista) {
        for (const f of lista) this.subidas.push({ id: ++this._n, archivo: f, nombre: f.name, avance: 0, listo: false, error: '' });
        this.correr();
    },
    async correr() {
        if (this.corriendo) return;
        this.corriendo = true;
        let alguno = false;
        for (const s of this.subidas) {
            if (s.listo || s.error) continue;
            try { await this.subir(s); s.listo = true; s.avance = 1; alguno = true; }
            catch (e) { s.error = e.message || 'No se pudo subir'; }
        }
        this.corriendo = false;
        if (alguno && this.subidas.every((s) => s.listo || s.error)) {
            window.onbeforeunload = null;
            setTimeout(() => window.vanduRefrescar ? window.vanduRefrescar() : location.reload(), 600);
        }
    },
    async enviar(datos) {
        const f = new FormData();
        f.append('_token', this.$root.closest('main').querySelector('input[name=_token]').value);
        for (const [k, v] of Object.entries(datos)) f.append(k, v);
        for (let intento = 0; intento < 3; intento++) {
            try {
                const r = await fetch(this.url, { method: 'POST', body: f, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const j = await r.json().catch(() => ({}));
                if (r.ok) return j;
                if (r.status === 413) throw Object.assign(new Error('El servidor rechazó la parte por tamaño.'), { final: true });
                if (r.status < 500) throw Object.assign(new Error(j.message || 'Error ' + r.status), { final: true });
                if (intento === 2) throw new Error(j.message || 'Error ' + r.status);
            } catch (e) {
                if (e.final || intento === 2) throw e;
            }
            await new Promise((ok) => setTimeout(ok, 1200 * (intento + 1)));
        }
    },
    async subir(s) {
        window.onbeforeunload = () => 'Hay archivos subiéndose.';
        const f = s.archivo, P = this.parte, total = f.size;
        if (total <= P) {
            await this.enviar({ accion: 'iniciar', nombre: f.name, parte: f });
            return;
        }
        let offset = 0;
        const { sesion } = await this.enviar({ accion: 'iniciar', parte: f.slice(0, P) });
        offset = P; s.avance = offset / total;
        while (total - offset > P) {
            await this.enviar({ accion: 'agregar', sesion, offset, parte: f.slice(offset, offset + P) });
            offset += P; s.avance = offset / total;
        }
        await this.enviar({ accion: 'terminar', sesion, offset, nombre: f.name, parte: f.slice(offset) });
    },
});
</script>
@endpush
