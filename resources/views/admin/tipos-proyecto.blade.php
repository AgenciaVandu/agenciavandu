@extends('admin.layout')
@section('titulo', 'Tipos de proyecto')

@php
    $deFabrica = collect($tipos)->filter(fn ($t, $k) => array_key_exists($k, \App\Support\TiposProyecto::fabrica()));
    $propios = collect($tipos)->reject(fn ($t, $k) => array_key_exists($k, \App\Support\TiposProyecto::fabrica()));
    $inicial = [
        'nombre' => $tipo['nombre'],
        'icono'  => $tipo['icono'] ?? 'bi-kanban',
        'etapas' => collect($tipo['etapas'])->map(fn ($e) => [
            'ref' => $e['clave'], 'clave' => $e['clave'], 'nombre' => $e['nombre'], 'dias' => (int) ($e['dias'] ?? 1),
            'descripcion' => $e['descripcion'] ?? '', 'fecha' => ! empty($e['fecha']),
        ])->values(),
        'pagos'  => collect($tipo['pagos'])->map(fn ($p) => [
            'concepto' => $p['concepto'], 'porcentaje' => (float) $p['porcentaje'], 'antes_de' => $p['antes_de'] ?? '',
        ])->values(),
        'galeria' => ! empty($tipo['galeria']),
        'costeo'  => ! empty($tipo['costeo']),
    ];
@endphp

@push('head')
<style>
    .tp-grid { display: grid; grid-template-columns: 260px minmax(0, 1fr); gap: 20px; align-items: start; }
    .tp-lista { padding: 8px; }
    .tp-lista .grupo { font-size: 12px; color: var(--muted); font-weight: 500; padding: 10px 10px 6px; }
    .tp-lista a { display: flex; align-items: center; gap: 10px; padding: 9px 10px; border-radius: 9px; color: var(--text); text-decoration: none; font-size: 14.5px; }
    .tp-lista a:hover { background: var(--sunken); }
    .tp-lista a.activo { background: var(--ink); color: #fff; }
    .tp-lista a.activo .etq { background: rgba(255,255,255,.16); color: #fff; }
    .tp-lista a i { width: 18px; text-align: center; opacity: .75; }
    .tp-lista a .n { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .etq { font-size: 11px; padding: 1px 7px; border-radius: 99px; background: var(--sunken); color: var(--text-2); white-space: nowrap; }
    .etq-ed { background: #FFF4D6; color: #8A5A00; }
    .tp-cuerpo { padding: 20px 22px; display: grid; gap: 22px; }
    .tp-sec h3 { font-size: 15px; font-weight: 600; margin: 0 0 4px; }
    .tp-sec .ayuda-sec { font-size: 13px; color: var(--muted); margin: 0 0 12px; }
    .iconos { display: flex; gap: 6px; flex-wrap: wrap; }
    .iconos button { width: 36px; height: 36px; border: 1px solid var(--line-strong); border-radius: 9px; display: grid; place-items: center; font-size: 16px; background: var(--surface); color: var(--text); }
    .iconos button.sel { background: var(--ink); color: #fff; border-color: var(--ink); }
    .tp-etapa { display: grid; grid-template-columns: 30px minmax(0, 1fr) auto; gap: 10px; align-items: start; padding: 12px; border: 1px solid var(--line); border-radius: 12px; background: var(--surface); }
    .tp-etapa + .tp-etapa { margin-top: 8px; }
    .tp-etapa .num { width: 28px; height: 28px; border-radius: 50%; background: var(--sunken); display: grid; place-items: center; font-size: 13px; font-weight: 600; margin-top: 4px; }
    .tp-etapa .campos { display: grid; grid-template-columns: minmax(0, 1fr) 130px; gap: 8px; }
    .tp-etapa .campos .desc { grid-column: 1 / -1; }
    .tp-etapa .acc { display: flex; flex-direction: column; gap: 2px; }
    .tp-etapa .acc button { border: 0; background: none; width: 30px; height: 28px; border-radius: 7px; color: var(--text-2); }
    .tp-etapa .acc button:hover:not(:disabled) { background: var(--sunken); color: var(--text); }
    .tp-etapa .acc button:disabled { opacity: .3; }
    .tp-pago { display: grid; grid-template-columns: minmax(0, 1fr) 120px minmax(0, 1fr) 34px; gap: 8px; align-items: center; }
    .tp-pago + .tp-pago { margin-top: 8px; }
    .tp-pago .quitar { border: 0; background: none; color: var(--text-2); height: 34px; border-radius: 7px; }
    .tp-pago .quitar:hover { background: var(--sunken); color: #B42318; }
    .tp-suma { font-size: 13.5px; margin-top: 10px; }
    .tp-suma.mal { color: #B42318; }
    .tp-suma.bien { color: #157F3C; }
    .tp-total { font-size: 13px; color: var(--muted); }
    .tp-pie { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 14px 22px; border-top: 1px solid var(--line); }
    .tp-cab-pago { display: grid; grid-template-columns: minmax(0, 1fr) 120px minmax(0, 1fr) 34px; gap: 8px; font-size: 12.5px; color: var(--muted); margin-bottom: 6px; }
    @media (max-width: 991.98px) {
        .tp-grid { grid-template-columns: minmax(0, 1fr); }
        .tp-lista { display: flex; gap: 6px; overflow-x: auto; padding: 8px; scrollbar-width: none; }
        .tp-lista .grupo { display: none; }
        .tp-lista a { flex-shrink: 0; border: 1px solid var(--line); }
        .tp-lista a .n { overflow: visible; }
        .tp-cuerpo { padding: 16px; }
        .tp-pie { padding: 12px 16px; }
    }
    @media (max-width: 575.98px) {
        .tp-etapa { grid-template-columns: minmax(0, 1fr) auto; }
        .tp-etapa .num { display: none; }
        .tp-etapa .campos { grid-template-columns: minmax(0, 1fr); }
        .tp-etapa .campos .input-group { max-width: 160px; }
        .tp-cab-pago { display: none; }
        .tp-pago { grid-template-columns: minmax(0, 1fr) 110px 34px; padding: 10px; border: 1px solid var(--line); border-radius: 10px; }
        .tp-pago .antes { grid-column: 1 / 3; grid-row: 2; }
        .tp-pago .quitar { grid-row: 1; grid-column: 3; }
    }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <h1>Tipos de proyecto</h1>
        <p class="sub">Las etapas y pagos con los que arranca cada proyecto. En cada proyecto todavía puedes ajustarlas para ese cliente.</p>
    </div>
    <form method="post" action="{{ route('admin.tipos.store') }}">@csrf
        <button class="btn btn-primario"><i class="bi bi-plus-lg me-1"></i> Nuevo tipo</button>
    </form>
</div>

<div class="tp-grid">
    <nav class="panel tp-lista" aria-label="Tipos de proyecto">
        <div class="grupo">De fábrica</div>
        @foreach($deFabrica as $k => $t)
            <a href="{{ route('admin.tipos', ['t' => $k]) }}" class="{{ $k === $clave ? 'activo' : '' }}" @if($k === $clave) aria-current="page" @endif>
                <i class="bi {{ $t['icono'] ?? 'bi-kanban' }}"></i><span class="n">{{ $t['nombre'] }}</span>
                @if(! empty($t['editado']))<span class="etq etq-ed">Editado</span>@endif
            </a>
        @endforeach
        <div class="grupo">Tuyos</div>
        @forelse($propios as $k => $t)
            <a href="{{ route('admin.tipos', ['t' => $k]) }}" class="{{ $k === $clave ? 'activo' : '' }}" @if($k === $clave) aria-current="page" @endif>
                <i class="bi {{ $t['icono'] ?? 'bi-kanban' }}"></i><span class="n">{{ $t['nombre'] }}</span>
            </a>
        @empty
            <p class="secundario px-2 mb-2" style="font-size:13px">Crea los tuyos: branding, eventos, consultoría…</p>
        @endforelse
    </nav>

    <form method="post" action="{{ route('admin.tipos.update', $clave) }}" class="panel" x-data="editorTipo({{ Js::from($inicial) }})">
        @csrf @method('put')
        <input type="hidden" name="datos" :value="JSON.stringify(datos())">

        <div class="panel-head">
            <h2 class="d-flex align-items-center gap-2"><i class="bi text-secondary" :class="icono"></i> <span x-text="nombre || 'Sin nombre'"></span></h2>
            <span class="ayuda">
                {{ $fabrica ? 'De fábrica' : 'Tipo tuyo' }}
                @if($usos) · {{ $usos }} {{ $usos === 1 ? 'proyecto' : 'proyectos' }}@endif
                · <span x-text="totalDias() + ' días aprox.'"></span>
            </span>
        </div>

        <div class="tp-cuerpo">
            @if(! empty($tipo['redes']))
                <div class="aviso mb-0 py-2" style="font-size:13.5px"><i class="bi bi-grid-3x3-gap"></i>
                    <span>Los proyectos de este tipo se ligan al calendario de contenido del cliente en <a href="{{ route('admin.redes') }}">Redes sociales</a>.</span>
                </div>
            @endif

            <div class="tp-sec">
                <label class="form-label" for="tp-nombre">Nombre</label>
                <input id="tp-nombre" class="form-control" x-model="nombre" maxlength="80" required style="max-width: 420px">
            </div>

            <div class="tp-sec">
                <span class="form-label d-block">Ícono</span>
                <div class="iconos">
                    @foreach($iconos as $ic)
                        <button type="button" title="{{ $ic }}" :class="icono === '{{ $ic }}' && 'sel'" @click="icono = '{{ $ic }}'" aria-label="Ícono {{ $ic }}"><i class="bi {{ $ic }}"></i></button>
                    @endforeach
                </div>
            </div>

            <div class="tp-sec">
                <h3>Etapas</h3>
                <p class="ayuda-sec">En orden. Los días sirven para proponer las fechas de cada proyecto; “día específico” es para algo que se agenda en una fecha, como una grabación o una entrega.</p>
                <template x-for="(e, i) in etapas" :key="e.ref">
                    <div class="tp-etapa">
                        <span class="num" x-text="i + 1"></span>
                        <div class="campos">
                            <input class="form-control" x-model="e.nombre" placeholder="Nombre de la etapa" maxlength="120" :aria-label="'Nombre de la etapa ' + (i + 1)">
                            <div class="input-group">
                                <input type="number" class="form-control" x-model.number="e.dias" min="1" max="365" :aria-label="'Días de la etapa ' + (i + 1)">
                                <span class="input-group-text" x-text="e.dias == 1 ? 'día' : 'días'"></span>
                            </div>
                            <input class="form-control desc" x-model="e.descripcion" placeholder="Qué pasa en esta etapa (lo ve tu cliente)" maxlength="300" :aria-label="'Descripción de la etapa ' + (i + 1)">
                            <label class="form-check m-0 desc" style="font-size:13.5px"><input type="checkbox" class="form-check-input" x-model="e.fecha"> <span class="form-check-label">Día específico</span></label>
                        </div>
                        <div class="acc">
                            <button type="button" @click="mover(i, -1)" :disabled="i === 0" title="Subir" aria-label="Subir etapa"><i class="bi bi-arrow-up"></i></button>
                            <button type="button" @click="mover(i, 1)" :disabled="i === etapas.length - 1" title="Bajar" aria-label="Bajar etapa"><i class="bi bi-arrow-down"></i></button>
                            <button type="button" @click="quitarEtapa(i)" :disabled="etapas.length === 1" title="Quitar" aria-label="Quitar etapa"><i class="bi bi-trash"></i></button>
                        </div>
                    </div>
                </template>
                <button type="button" class="btn btn-borde btn-sm mt-2" @click="agregarEtapa()"><i class="bi bi-plus-lg me-1"></i> Agregar etapa</button>
            </div>

            <div class="tp-sec">
                <h3>Pagos</h3>
                <p class="ayuda-sec">Cómo se divide el total de la cotización. Si un pago va “antes de” una etapa, esa etapa no arranca hasta recibirlo.</p>
                <div class="tp-cab-pago"><span>Concepto</span><span>%</span><span>Se pide antes de</span><span></span></div>
                <template x-for="(p, i) in pagos" :key="i">
                    <div class="tp-pago">
                        <input class="form-control" x-model="p.concepto" placeholder="Anticipo, saldo, mensualidad…" maxlength="80" :aria-label="'Concepto del pago ' + (i + 1)">
                        <div class="input-group">
                            <input type="number" class="form-control" x-model.number="p.porcentaje" min="0" max="100" step="0.01" :aria-label="'Porcentaje del pago ' + (i + 1)">
                            <span class="input-group-text">%</span>
                        </div>
                        <select class="form-select antes" x-model="p.antes_de" :aria-label="'Antes de qué etapa se pide el pago ' + (i + 1)">
                            <option value="">Sin condición</option>
                            <template x-for="e in etapas" :key="e.ref"><option :value="e.ref" x-text="e.nombre || 'Etapa sin nombre'" :selected="p.antes_de === e.ref"></option></template>
                        </select>
                        <button type="button" class="quitar" @click="pagos.splice(i, 1)" title="Quitar pago" aria-label="Quitar pago"><i class="bi bi-x-lg"></i></button>
                    </div>
                </template>
                <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                    <button type="button" class="btn btn-borde btn-sm" @click="pagos.push({ concepto: '', porcentaje: Math.max(0, 100 - suma()), antes_de: '' })"><i class="bi bi-plus-lg me-1"></i> Agregar pago</button>
                    <span class="tp-suma" :class="Math.abs(suma() - 100) < 0.01 ? 'bien' : 'mal'">
                        <i class="bi" :class="Math.abs(suma() - 100) < 0.01 ? 'bi-check-circle' : 'bi-exclamation-circle'"></i>
                        <span x-text="Math.abs(suma() - 100) < 0.01 ? 'Suman 100%' : 'Suman ' + suma() + '%: deben sumar 100%'"></span>
                    </span>
                </div>
            </div>

            @unless($fabrica)
                <div class="tp-sec">
                    <h3>Opciones</h3>
                    <label class="form-check"><input type="checkbox" class="form-check-input" x-model="galeria"> <span class="form-check-label">Entrega con galería de fotos y videos para el cliente</span></label>
                    <label class="form-check"><input type="checkbox" class="form-check-input" x-model="costeo"> <span class="form-check-label">Costear cada concepto de la cotización (proveedor + gasolina + utilidad)</span></label>
                </div>
            @endunless

            <p class="secundario m-0" style="font-size:13px"><i class="bi bi-info-circle"></i> Los cambios se usan en los proyectos nuevos; los que ya existen conservan sus etapas.</p>
        </div>

        <div class="tp-pie">
            <button class="btn btn-primario px-4"><i class="bi bi-check2 me-1"></i> Guardar tipo</button>
            @if($fabrica && ! empty($tipo['editado']))
                <button type="submit" form="restaurar-tipo" class="btn btn-fantasma" onclick="return confirm('¿Volver a las etapas y pagos originales?')"><i class="bi bi-arrow-counterclockwise me-1"></i> Restaurar original</button>
            @endif
            @unless($fabrica)
                @if($usos)
                    <span class="secundario ms-auto" style="font-size:13px">No se puede eliminar mientras haya proyectos de este tipo.</span>
                @else
                    <button type="submit" form="eliminar-tipo" class="btn btn-fantasma text-danger ms-auto" onclick="return confirm('¿Eliminar el tipo “{{ $tipo['nombre'] }}”?')"><i class="bi bi-trash me-1"></i> Eliminar</button>
                @endif
            @endunless
        </div>
    </form>
</div>

@if($fabrica && ! empty($tipo['editado']))
    <form id="restaurar-tipo" method="post" action="{{ route('admin.tipos.restaurar', $clave) }}" class="d-none">@csrf</form>
@endif
@unless($fabrica)
    <form id="eliminar-tipo" method="post" action="{{ route('admin.tipos.destroy', $clave) }}" class="d-none">@csrf @method('delete')</form>
@endunless
@endsection

@push('scripts')
<script>
window.editorTipo = (cfg) => ({
    ...cfg, _n: 0,
    agregarEtapa() { this.etapas.push({ ref: 'nueva-' + (++this._n), clave: '', nombre: '', dias: 3, descripcion: '', fecha: false }); },
    quitarEtapa(i) {
        const ref = this.etapas[i].ref;
        this.etapas.splice(i, 1);
        this.pagos.forEach((p) => { if (p.antes_de === ref) p.antes_de = ''; });
    },
    mover(i, d) { const j = i + d; if (j < 0 || j >= this.etapas.length) return; const [e] = this.etapas.splice(i, 1); this.etapas.splice(j, 0, e); },
    suma() { return Math.round(this.pagos.reduce((s, p) => s + (parseFloat(p.porcentaje) || 0), 0) * 100) / 100; },
    totalDias() { return this.etapas.reduce((s, e) => s + (parseInt(e.dias) || 0), 0); },
    datos() {
        return { nombre: this.nombre, icono: this.icono, etapas: this.etapas, pagos: this.pagos, galeria: this.galeria, costeo: this.costeo };
    },
});
</script>
@endpush
