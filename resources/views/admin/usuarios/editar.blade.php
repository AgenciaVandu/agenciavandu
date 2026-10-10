@extends('admin.layout')
@section('titulo', $u->name)

@php $enlace = session('enlace'); @endphp

@push('head')
<style>
    .us-grid { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 20px; align-items: start; }
    .us-campos { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .us-campos .ancho { grid-column: 1 / -1; }
    .enlace-listo { border: 1px solid #BFEBD4; background: var(--green-soft); border-radius: 12px; padding: 14px; display: grid; gap: 10px; }
    .enlace-listo .url { display: flex; gap: 8px; }
    .enlace-listo .url input { font-size: 13px; background: #fff; }
    .t-lista { list-style: none; margin: 0; padding: 0; }
    .t-lista li + li { border-top: 1px solid var(--line); }
    .t-lista a { display: flex; gap: 10px; align-items: center; padding: 10px 0; color: var(--text); text-decoration: none; }
    .t-lista a:hover .n { text-decoration: underline; }
    .t-lista .n { font-weight: 500; }
    .t-lista .s { font-size: 12.5px; color: var(--muted); }
    .t-lista .punto { width: 9px; height: 9px; border-radius: 50%; flex: none; }
    @media (max-width: 991.98px) { .us-grid { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 575.98px) { .us-campos { grid-template-columns: minmax(0, 1fr); } }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.usuarios') }}">Usuarios</a> <i class="bi bi-chevron-right small"></i> <span>{{ $u->name }}</span></div>
<div class="page-head">
    <div class="d-flex align-items-center gap-3">
        @include('admin._avatar', ['nombre' => $u->name])
        <div>
            <h1>{{ $u->name }}</h1>
            <p class="sub">{{ $u->puesto ?: 'Sin puesto' }} · {{ $u->rol?->nombre ?? 'Sin rol' }}
                @if(! $u->activo) · <b>Desactivado</b>@elseif($u->pendiente) · <span style="color:var(--amber)">Invitación pendiente</span>@elseif($u->ultimo_acceso_at) · entró {{ $u->ultimo_acceso_at->locale('es')->diffForHumans() }}@endif</p>
        </div>
    </div>
</div>

<div class="us-grid">
    <form method="post" action="{{ route('admin.usuarios.update', $u) }}" class="panel">
        @csrf @method('put')
        <div class="panel-head"><h2>Datos y rol</h2></div>
        <div class="panel-body us-campos">
            <div><label class="form-label" for="e-nombre">Nombre</label><input id="e-nombre" name="name" class="form-control" value="{{ old('name', $u->name) }}" required maxlength="120"></div>
            <div><label class="form-label" for="e-correo">Correo</label><input id="e-correo" type="email" name="email" class="form-control" value="{{ old('email', $u->email) }}" required maxlength="190"></div>
            <div><label class="form-label" for="e-puesto">Puesto</label><input id="e-puesto" name="puesto" class="form-control" value="{{ old('puesto', $u->puesto) }}" maxlength="80" placeholder="Fotógrafo, diseñadora, project manager…"></div>
            <div><label class="form-label" for="e-tel">WhatsApp</label><input id="e-tel" name="telefono" class="form-control num" value="{{ old('telefono', $u->telefono) }}" maxlength="30"></div>
            <div class="ancho">
                <label class="form-label" for="e-rol">Rol</label>
                <select id="e-rol" name="rol_id" class="form-select" required>
                    @foreach($roles as $r)<option value="{{ $r->id }}" @selected((int) old('rol_id', $u->rol_id) === $r->id)>{{ $r->nombre }} — {{ $r->resumen }}</option>@endforeach
                </select>
            </div>
            <label class="form-check form-switch ancho m-0">
                <input type="checkbox" class="form-check-input" role="switch" name="activo" value="1" @checked(old('activo', $u->activo)) @disabled($yo)>
                @if($yo)<input type="hidden" name="activo" value="1">@endif
                <span class="form-check-label">Puede entrar al panel @if($yo)<span class="secundario">(es tu cuenta)</span>@endif</span>
            </label>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center px-3 pb-3 px-md-4">
            <button class="btn btn-primario px-4"><i class="bi bi-check2 me-1"></i> Guardar</button>
            @unless($yo)
                <button type="submit" form="eliminar-usuario" class="btn btn-fantasma text-danger ms-auto" onclick="return confirm('¿Eliminar a {{ $u->name }}? Sus tareas quedan sin asignar. Si solo quieres quitarle el acceso, desactívalo.')"><i class="bi bi-trash me-1"></i> Eliminar</button>
            @endunless
        </div>
    </form>

    <div class="d-grid gap-4">
        <section class="panel">
            <div class="panel-head"><h2>Acceso</h2></div>
            <div class="panel-body d-grid gap-3">
                @if($enlace)
                    <div class="enlace-listo" x-data>
                        <b style="font-size:14px"><i class="bi bi-link-45deg"></i> Enlace listo · sirve 7 días</b>
                        <div class="url">
                            <input class="form-control" value="{{ $enlace['url'] }}" readonly onfocus="this.select()" aria-label="Enlace para crear contraseña">
                            <button type="button" class="btn btn-borde text-nowrap" data-copiar="{{ $enlace['url'] }}"><i class="bi bi-clipboard"></i> Copiar</button>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ $enlace['whatsapp'] }}" target="_blank" rel="noopener" class="btn btn-borde btn-sm"><i class="bi bi-whatsapp me-1"></i> Mandar por WhatsApp</a>
                            @if($enlace['correo'])<span class="secundario align-self-center" style="font-size:13px"><i class="bi bi-envelope-check"></i> Ya se lo mandamos a {{ $u->email }}</span>@endif
                        </div>
                        <span class="secundario" style="font-size:12.5px">Por seguridad este enlace solo se muestra ahora. Si lo pierdes, genera otro.</span>
                    </div>
                @endif
                @if(! $u->activo)
                    <p class="secundario m-0">La cuenta está desactivada: no puede entrar ni recibe avisos. Actívala para invitarla otra vez.</p>
                @else
                    <p class="m-0" style="font-size:14px">
                        @if($u->pendiente)
                            Todavía no crea su contraseña. @if($u->invitacion_expira?->isPast()) Su invitación ya venció. @else La invitación vence {{ $u->invitacion_expira?->locale('es')->diffForHumans() }}. @endif
                        @else
                            ¿Olvidó su contraseña? Genera un enlace para que cree una nueva.
                        @endif
                    </p>
                    <form method="post" action="{{ route('admin.usuarios.enlace', $u) }}" class="d-grid gap-2">@csrf
                        <label class="form-check m-0"><input type="hidden" name="enviar_correo" value="0"><input type="checkbox" class="form-check-input" name="enviar_correo" value="1" checked> <span class="form-check-label">Mandarlo también por correo</span></label>
                        <button class="btn btn-borde justify-self-start"><i class="bi bi-arrow-repeat me-1"></i> {{ $u->pendiente ? 'Reenviar invitación' : 'Enlace para nueva contraseña' }}</button>
                    </form>
                @endif
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Tareas abiertas</h2>
                @if($u->activo)<a href="{{ route('admin.tareas.create', ['para' => $u->id]) }}" class="btn btn-borde btn-sm"><i class="bi bi-plus-lg me-1"></i> Asignarle tarea</a>@endif
            </div>
            <div class="panel-body">
                @if($tareas->isEmpty())
                    <p class="secundario m-0">No tiene tareas pendientes.</p>
                @else
                    <ul class="t-lista">
                        @foreach($tareas as $t)
                            <li><a href="{{ route('admin.tareas.show', $t) }}">
                                <span class="punto" style="background: {{ \App\Models\Tarea::ESTADOS[$t->estado]['color'] }}"></span>
                                <div class="min-w-0 flex-grow-1"><div class="n text-truncate">{{ $t->titulo }}</div><div class="s text-truncate">{{ \App\Models\Tarea::ESTADOS[$t->estado]['texto'] }}@if($t->donde) · {{ $t->donde }}@endif</div></div>
                                @if($t->cuando)<span class="s text-nowrap" @if($t->vencida) style="color:var(--red)" @endif>{{ $t->cuando }}</span>@endif
                            </a></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    </div>
</div>

@unless($yo)
    <form id="eliminar-usuario" method="post" action="{{ route('admin.usuarios.destroy', $u) }}" class="d-none">@csrf @method('delete')</form>
@endunless
@endsection
