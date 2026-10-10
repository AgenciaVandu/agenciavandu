@extends('admin.layout')
@section('titulo', 'Usuarios')

@push('head')
<style>
    .us-nuevo { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .us-nuevo .ancho { grid-column: 1 / -1; }
    .puesto { font-size: 13px; color: var(--text-2); }
    .etq-rol { display: inline-flex; align-items: center; gap: 5px; font-size: 12.5px; padding: 2px 10px; border-radius: 99px; background: var(--sunken); border: 1px solid var(--line); color: var(--text-2); white-space: nowrap; }
    .etq-rol.super { background: var(--ink); color: #fff; border-color: var(--ink); }
    .etq-pend { font-size: 11.5px; font-weight: 600; padding: 1px 8px; border-radius: 99px; background: var(--amber-soft); color: var(--amber); margin-left: 6px; vertical-align: 2px; }
    .etq-off { font-size: 11.5px; font-weight: 600; padding: 1px 8px; border-radius: 99px; background: #EEF0F3; color: #4E5463; margin-left: 6px; vertical-align: 2px; }
    tr.apagado { opacity: .6; }
    .roles { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 16px; align-items: start; }
    .rol-card .panel-body { display: grid; gap: 12px; }
    .permisos { display: grid; gap: 2px; }
    .permisos label { display: flex; gap: 10px; align-items: flex-start; padding: 8px 10px; border-radius: 9px; cursor: pointer; }
    .permisos label:hover { background: var(--sunken); }
    .permisos label i { width: 18px; text-align: center; color: var(--muted); margin-top: 2px; }
    .permisos label b { font-weight: 500; display: block; font-size: 14.5px; }
    .permisos label span { font-size: 12.5px; color: var(--muted); line-height: 1.35; display: block; }
    .permisos input { margin-top: 4px; }
    .permisos .fija { cursor: default; }
    .permisos .fija:hover { background: none; }
    @media (max-width: 575.98px) { .us-nuevo { grid-template-columns: minmax(0, 1fr); } .roles { grid-template-columns: minmax(0, 1fr); } }
</style>
@endpush

@section('contenido')
<div x-data="{ nuevo: {{ $errors->any() && old('email') !== null ? 'true' : 'false' }} }">
<div class="page-head">
    <div>
        <h1>Usuarios</h1>
        <p class="sub">Las personas de tu equipo, su puesto y qué secciones del panel pueden ver.</p>
    </div>
    @if($tab === 'personas')
        <button type="button" class="btn btn-primario" @click="nuevo = true; $nextTick(() => $refs.nombre.focus())" x-show="!nuevo"><i class="bi bi-person-plus me-1"></i> Agregar persona</button>
    @else
        <form method="post" action="{{ route('admin.roles.store') }}">@csrf
            <button class="btn btn-primario"><i class="bi bi-plus-lg me-1"></i> Nuevo rol</button>
        </form>
    @endif
</div>

<nav class="segmento mb-3" aria-label="Ver">
    <a href="{{ route('admin.usuarios') }}" class="{{ $tab === 'personas' ? 'activo' : '' }}"><i class="bi bi-people"></i> Personas <span class="n num">{{ $usuarios->where('activo', true)->count() }}</span></a>
    <a href="{{ route('admin.usuarios', ['tab' => 'roles']) }}" class="{{ $tab === 'roles' ? 'activo' : '' }}"><i class="bi bi-shield-check"></i> Roles y permisos</a>
</nav>

@if($tab === 'personas')
    <section class="panel mb-4" x-show="nuevo" x-cloak>
        <div class="panel-head"><h2>Agregar persona</h2><span class="ayuda">Le llega un enlace para crear su contraseña</span></div>
        <form method="post" action="{{ route('admin.usuarios.store') }}" class="panel-body">
            @csrf
            <div class="us-nuevo">
                <div><label class="form-label" for="u-nombre">Nombre</label><input id="u-nombre" name="name" x-ref="nombre" class="form-control" value="{{ old('name') }}" required maxlength="120" autocomplete="off"></div>
                <div><label class="form-label" for="u-correo">Correo</label><input id="u-correo" type="email" name="email" class="form-control" value="{{ old('email') }}" required maxlength="190" autocomplete="off"></div>
                <div>
                    <label class="form-label" for="u-puesto">Puesto</label>
                    <input id="u-puesto" name="puesto" class="form-control" value="{{ old('puesto') }}" maxlength="80" placeholder="Fotógrafo, diseñadora, project manager…" list="puestos">
                    <datalist id="puestos">@foreach($usuarios->pluck('puesto')->filter()->unique() as $p)<option value="{{ $p }}">@endforeach</datalist>
                    <div class="form-text">Lo escribes como quieras; es lo que verá el equipo.</div>
                </div>
                <div><label class="form-label" for="u-tel">WhatsApp <span class="secundario fw-normal">(opcional)</span></label><input id="u-tel" name="telefono" class="form-control num" value="{{ old('telefono') }}" maxlength="30" placeholder="999 123 4567"></div>
                <div class="ancho">
                    <label class="form-label" for="u-rol">Rol</label>
                    <select id="u-rol" name="rol_id" class="form-select" required>
                        @foreach($roles as $r)<option value="{{ $r->id }}" @selected((int) old('rol_id', $roles->firstWhere('nombre', 'Fotógrafo')?->id) === $r->id)>{{ $r->nombre }} — {{ $r->resumen }}</option>@endforeach
                    </select>
                    <div class="form-text">El rol decide qué secciones ve. Puedes ajustar los roles en <a href="{{ route('admin.usuarios', ['tab' => 'roles']) }}">Roles y permisos</a>.</div>
                </div>
                <label class="form-check ancho m-0"><input type="hidden" name="enviar_correo" value="0"><input type="checkbox" class="form-check-input" name="enviar_correo" value="1" checked> <span class="form-check-label">Enviarle la invitación por correo (también podrás mandarla por WhatsApp)</span></label>
            </div>
            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-primario px-4"><i class="bi bi-send me-1"></i> Invitar</button>
                <button type="button" class="btn btn-borde" @click="nuevo = false">Cancelar</button>
            </div>
        </form>
    </section>

    <div class="panel">
        <div class="table-responsive">
            <table class="tabla tabla-m">
                <thead><tr><th>Persona</th><th>Puesto</th><th>Rol</th><th class="text-center">Tareas abiertas</th><th>Último acceso</th><th></th></tr></thead>
                <tbody>
                    @foreach($usuarios as $u)
                        <tr class="{{ $u->activo ? '' : 'apagado' }}">
                            <td class="m-titulo" style="min-width: 240px">
                                <a href="{{ route('admin.usuarios.edit', $u) }}" class="fila-link persona">
                                    @include('admin._avatar', ['nombre' => $u->name])
                                    <div class="min-w-0">
                                        <div class="principal text-truncate">{{ $u->name }}@if($u->id === auth()->id()) <span class="secundario fw-normal">(tú)</span>@endif
                                            @if(! $u->activo)<span class="etq-off">Desactivado</span>@elseif($u->pendiente)<span class="etq-pend">Invitación pendiente</span>@endif</div>
                                        <div class="secundario text-truncate">{{ $u->email }}</div>
                                    </div>
                                </a>
                            </td>
                            <td data-k="Puesto" class="puesto">{{ $u->puesto ?: '—' }}</td>
                            <td data-k="Rol"><span class="etq-rol {{ $u->rol?->todo ? 'super' : '' }}">@if($u->rol?->todo)<i class="bi bi-star-fill" style="font-size:10px"></i>@endif {{ $u->rol?->nombre ?? 'Sin rol' }}</span></td>
                            <td data-k="Tareas abiertas" class="text-center num">
                                @if($u->abiertas_count)<a href="{{ route('admin.tareas.index', ['ver' => $u->id]) }}">{{ $u->abiertas_count }}</a>@else<span class="secundario">0</span>@endif
                            </td>
                            <td data-k="Último acceso" class="secundario text-nowrap">{{ $u->ultimo_acceso_at ? $u->ultimo_acceso_at->locale('es')->diffForHumans() : 'Nunca' }}</td>
                            <td class="text-end m-sin-k"><a href="{{ route('admin.usuarios.edit', $u) }}" class="btn btn-fantasma btn-icono" aria-label="Editar a {{ $u->name }}"><i class="bi bi-chevron-right"></i></a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@else
    <p class="secundario" style="font-size:13.5px; max-width: 760px">Todos ven <b>Mis tareas</b>, sus notificaciones y su cuenta. Marca qué más puede ver cada rol. Los cambios aplican de inmediato a quien tenga ese rol.</p>
    <div class="roles">
        @foreach($roles as $r)
            <form method="post" action="{{ route('admin.roles.update', $r) }}" class="panel rol-card" id="rol-{{ $r->id }}">
                @csrf @method('put')
                <div class="panel-head">
                    <h2 class="d-flex align-items-center gap-2">@if($r->todo)<i class="bi bi-star-fill" style="font-size:13px"></i>@endif {{ $r->nombre }}</h2>
                    <span class="ayuda">{{ $r->usuarios_count }} {{ $r->usuarios_count === 1 ? 'persona' : 'personas' }}</span>
                </div>
                <div class="panel-body">
                    <div><label class="form-label" for="r-n-{{ $r->id }}">Nombre del rol</label><input id="r-n-{{ $r->id }}" name="nombre" class="form-control" value="{{ $r->nombre }}" maxlength="60" required></div>
                    <div><label class="form-label" for="r-d-{{ $r->id }}">Descripción</label><input id="r-d-{{ $r->id }}" name="descripcion" class="form-control" value="{{ $r->descripcion }}" maxlength="200" placeholder="Para qué es este rol"></div>
                    <div>
                        <span class="form-label d-block">Puede ver</span>
                        <div class="permisos">
                            <label class="fija"><input type="checkbox" class="form-check-input" checked disabled><i class="bi bi-check2-square"></i><div><b>Mis tareas</b><span>Sus tareas, subir su trabajo y comentar. Lo tiene todo el equipo.</span></div></label>
                            @foreach($secciones as $k => $s)
                                <label class="{{ $r->todo ? 'fija' : '' }}">
                                    <input type="checkbox" class="form-check-input" name="permisos[]" value="{{ $k }}" @checked($r->puede($k)) @disabled($r->todo)>
                                    <i class="bi {{ $s['icono'] }}"></i><div><b>{{ $s['texto'] }}</b><span>{{ $s['ayuda'] }}</span></div>
                                </label>
                            @endforeach
                        </div>
                        @if($r->todo)<p class="secundario m-0 mt-2" style="font-size:13px"><i class="bi bi-info-circle"></i> El super admin siempre puede todo; por eso sus casillas no se cambian.</p>@endif
                    </div>
                </div>
                <div class="d-flex gap-2 align-items-center px-3 pb-3">
                    <button class="btn btn-primario btn-sm px-3"><i class="bi bi-check2 me-1"></i> Guardar</button>
                    @unless($r->todo)
                        <button type="submit" form="borrar-rol-{{ $r->id }}" class="btn btn-fantasma btn-sm text-danger ms-auto" @if($r->usuarios_count) disabled title="Cámbiales el rol a quienes lo tienen para poder eliminarlo" @endif onclick="return confirm('¿Eliminar el rol “{{ $r->nombre }}”?')"><i class="bi bi-trash me-1"></i> Eliminar</button>
                    @endunless
                </div>
            </form>
            @unless($r->todo)
                <form id="borrar-rol-{{ $r->id }}" method="post" action="{{ route('admin.roles.destroy', $r) }}" class="d-none">@csrf @method('delete')</form>
            @endunless
        @endforeach
    </div>
@endif
</div>
@endsection
