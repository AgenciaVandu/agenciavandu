@extends('admin.layout')
@php $nuevo = ! $giro; @endphp
@section('titulo', $nuevo ? 'Nuevo tipo de empresa' : $giro->nombre)

@push('head')
<style>
    .seccion { display: grid; grid-template-columns: 260px minmax(0, 1fr); gap: 32px; padding: 28px 0; border-top: 1px solid var(--line); }
    .seccion:first-of-type { border-top: 0; padding-top: 4px; }
    .seccion h2 { font-size: 15.5px; font-weight: 600; margin: 0 0 4px; }
    .seccion > div > p { color: var(--muted); font-size: 14px; margin: 0; }
    .iconos { display: flex; flex-wrap: wrap; gap: 6px; }
    .iconos label { margin: 0; }
    .iconos input { position: absolute; opacity: 0; pointer-events: none; }
    .iconos span { width: 40px; height: 40px; display: grid; place-items: center; border: 1px solid var(--line); border-radius: 10px; font-size: 18px; cursor: pointer; background: var(--surface); }
    .iconos input:checked + span { border-color: var(--ink); box-shadow: inset 0 0 0 1px var(--ink); background: var(--sunken); }
    .iconos input:focus-visible + span { outline: 2px solid var(--blue); outline-offset: 2px; }
    .opcion { display: flex; gap: 12px; align-items: flex-start; padding: 12px 14px; border: 1.5px solid var(--line); border-radius: 12px; cursor: pointer; margin: 0; }
    .opcion:has(input:checked) { border-color: var(--ink); background: var(--sunken); }
    .opcion input { margin-top: 3px; flex: none; }
    .opcion small { display: block; color: var(--muted); font-size: 13px; }
    .opcion i.bi { font-size: 18px; }
    .rol { border: 1px solid var(--line); border-radius: 12px; padding: 14px; background: var(--surface); }
    .rol + .rol { margin-top: 12px; }
    .permisos { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 6px 14px; margin-top: 10px; }
    .permisos label { display: flex; gap: 8px; align-items: center; font-size: 13.5px; margin: 0; }
    .barra-acciones { position: sticky; bottom: 0; z-index: 5; margin: 8px -36px -64px; padding: 14px 36px; background: rgba(244,245,247,.92);
                      backdrop-filter: blur(6px); border-top: 1px solid var(--line); display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
    @media (max-width: 991.98px) { .seccion { grid-template-columns: minmax(0, 1fr); gap: 14px; } .barra-acciones { margin: 8px -16px -48px; padding: 12px 16px; } }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.plataforma') }}">Plataforma</a> <i class="bi bi-chevron-right small"></i> <span>{{ $nuevo ? 'Nuevo tipo de empresa' : $giro->nombre }}</span></div>
<div class="page-head">
    <div>
        <h1>{{ $nuevo ? 'Nuevo tipo de empresa' : $giro->nombre }}</h1>
        <p class="sub">Con esto arranca cada negocio de este tipo. Después cada uno ajusta sus procesos, roles y equipo desde su panel.</p>
    </div>
</div>

<form method="post" action="{{ $nuevo ? route('admin.plataforma.giros.store') : route('admin.plataforma.giros.update', $giro) }}"
      x-data="giroForm({{ Js::from(['roles' => old('roles', $datos['roles']), 'modulos' => old('modulos', $datos['modulos']), 'modulosSeccion' => $modulosSeccion]) }})">
    @csrf
    @unless($nuevo) @method('put') @endunless

    <div class="seccion">
        <div><h2>Tipo de empresa</h2><p>Así aparece al dar de alta un negocio.</p></div>
        <div class="panel panel-body d-grid gap-3">
            <div>
                <label class="form-label" for="nombre">Nombre</label>
                <input name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $datos['nombre']) }}" required maxlength="80" placeholder="Distribuidora, Taller, Clínica…" @if($nuevo) autofocus @endif>
            </div>
            <div>
                <label class="form-label" for="descripcion">Descripción <span class="text-secondary fw-normal">(opcional)</span></label>
                <input name="descripcion" id="descripcion" class="form-control" value="{{ old('descripcion', $datos['descripcion']) }}" maxlength="255" placeholder="Venden productos y dan servicio de instalación">
            </div>
            <div>
                <span class="form-label d-block">Ícono</span>
                <div class="iconos" role="radiogroup" aria-label="Ícono">
                    @foreach($iconos as $ic)
                        <label><input type="radio" name="icono" value="{{ $ic }}" @checked(old('icono', $datos['icono']) === $ic)><span><i class="bi {{ $ic }}"></i></span></label>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="seccion">
        <div><h2>Módulos</h2><p>Lo que se agrega al panel además de clientes, cotizaciones, proyectos, tareas y finanzas.</p></div>
        <div class="panel panel-body d-grid gap-2">
            @foreach($modulos as $k => $m)
                <label class="opcion">
                    <input type="checkbox" class="form-check-input" name="modulos[]" value="{{ $k }}" x-model="modulos">
                    <i class="bi {{ $m['icono'] }}"></i>
                    <span><b class="fw-semibold">{{ $m['texto'] }}</b><small>{{ $m['ayuda'] }}</small></span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="seccion">
        <div><h2>Tipos de proyecto</h2><p>Los procesos con etapas y pagos que se arman al aceptar una cotización.</p></div>
        <div class="panel panel-body d-grid gap-2">
            @unless($nuevo)
                <label class="opcion"><input type="radio" class="form-check-input" name="origen" value="conservar" @checked(old('origen', 'conservar') === 'conservar')>
                    <span><b class="fw-semibold">Dejar los que tiene</b><small>{{ count($giro->proyectos ?? []) }} {{ count($giro->proyectos ?? []) === 1 ? 'tipo' : 'tipos' }} de proyecto.</small></span></label>
            @endunless
            <label class="opcion"><input type="radio" class="form-check-input" name="origen" value="blanco" @checked(old('origen', $datos['origen']) === 'blanco')>
                <span><b class="fw-semibold">En blanco</b><small>Un solo "Proyecto general" de dos etapas. Cada negocio le cambia el nombre y arma sus propios procesos en “Tipos de proyecto”.</small></span></label>
            @php $copiando = ! in_array(old('origen', $datos['origen']), ['blanco', 'conservar'], true); @endphp
            <label class="opcion">
                <input type="radio" class="form-check-input" name="origen" value="copiar" @checked($copiando)>
                <span class="flex-grow-1"><b class="fw-semibold">Copiar los de otro tipo de empresa</b>
                    <select class="form-select form-select-sm mt-2" style="max-width: 360px" name="origen_copia" onchange="this.closest('label').querySelector('input').checked = true" aria-label="Tipo de empresa a copiar">
                        @foreach($origenes as $k => $texto)
                            @continue($giro && $k === $giro->clave)
                            <option value="{{ $k }}" @selected(old('origen_copia') === $k)>{{ $texto }}</option>
                        @endforeach
                    </select>
                </span>
            </label>
        </div>
    </div>

    <div class="seccion">
        <div><h2>Roles de arranque</h2><p>Además del Super admin (el dueño). Cada negocio puede cambiarlos, crear más e invitar a su equipo desde “Usuarios y roles”.</p></div>
        <div>
            <template x-for="(r, i) in roles" :key="i">
                <div class="rol">
                    <div class="d-flex gap-2 align-items-start">
                        <div class="flex-grow-1 d-grid gap-2">
                            <input class="form-control fw-semibold" :name="`roles[${i}][nombre]`" x-model="r.nombre" placeholder="Nombre del rol (Vendedor, Gerente…)" required maxlength="60" :aria-label="'Nombre del rol ' + (i + 1)">
                            <input class="form-control form-control-sm" :name="`roles[${i}][descripcion]`" x-model="r.descripcion" placeholder="Qué hace (opcional)" maxlength="200" :aria-label="'Descripción del rol ' + (i + 1)">
                        </div>
                        <button type="button" class="btn btn-fantasma btn-icono text-danger" @click="roles.splice(i, 1)" :aria-label="'Quitar rol ' + (r.nombre || (i + 1))"><i class="bi bi-trash"></i></button>
                    </div>
                    <div class="permisos">
                        @foreach($secciones as $s => $info)
                            <label x-show="disponible('{{ $s }}')"><input type="checkbox" class="form-check-input" :name="`roles[${i}][permisos][]`" value="{{ $s }}" x-model="r.permisos"> <i class="bi {{ $info['icono'] }}"></i> {{ $info['texto'] }}</label>
                        @endforeach
                    </div>
                </div>
            </template>
            <p class="secundario small" x-show="!roles.length" x-cloak>Sin roles extra: el dueño los crea a su medida.</p>
            <button type="button" class="btn btn-borde btn-sm mt-3" @click="roles.push({ nombre: '', descripcion: '', permisos: ['cotizaciones'] })"><i class="bi bi-plus-lg"></i> Agregar rol</button>
            <p class="secundario small mt-2 mb-0">Todos ven sus propias tareas, avisos y su cuenta. Usuarios y permisos solo los maneja el Super admin.</p>
        </div>
    </div>

    <div class="barra-acciones">
        @if(! $nuevo)
            <button type="submit" form="eliminar-giro" class="btn btn-fantasma text-danger me-auto" @disabled($cuentas > 0) title="{{ $cuentas ? 'Lo usan ' . $cuentas . ' negocios' : '' }}"><i class="bi bi-trash me-1"></i> Eliminar</button>
        @endif
        <a href="{{ route('admin.plataforma') }}" class="btn btn-borde">Cancelar</a>
        <button class="btn btn-primario">{{ $nuevo ? 'Crear tipo de empresa' : 'Guardar cambios' }}</button>
    </div>
</form>
@unless($nuevo)
    <form id="eliminar-giro" method="post" action="{{ route('admin.plataforma.giros.destroy', $giro) }}" onsubmit="return confirm('¿Eliminar este tipo de empresa?')">@csrf @method('delete')</form>
@endunless
@endsection

@push('scripts')
<script>
function giroForm(d) {
    return {
        roles: (d.roles || []).map((r) => ({ nombre: r.nombre || '', descripcion: r.descripcion || '', permisos: r.permisos || [] })),
        modulos: d.modulos || [],
        disponible(s) { const m = d.modulosSeccion[s]; return !m || this.modulos.includes(m); },
    };
}
</script>
@endpush
