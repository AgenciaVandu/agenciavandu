@extends('admin.layout')
@section('titulo', 'Timbres')

@php
    $d2 = fn ($n) => '$' . number_format($n, 2);
    $activos = $paquetes->where('activo', true)->values();
    $iniciales = old('cuenta_id') ? ['cuenta' => (int) old('cuenta_id'), 'paquete' => (int) old('paquete_id'), 'como' => old('como', 'cobrado')] : null;
@endphp

@push('head')
<style>
    .tb-kpis { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 20px; }
    .tb-kpi { background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius); padding: 14px 16px; }
    .tb-kpi .lbl { font-size: 13px; color: var(--muted); }
    .tb-kpi .val { font-size: 24px; font-weight: 600; margin-top: 2px; }
    .tb-kpi .val small { font-size: 13px; font-weight: 400; color: var(--muted); }

    .tb-paqs { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; padding: 16px 18px 18px; }
    .tb-paq { border: 1px solid var(--line); border-radius: var(--radius); padding: 16px; background: var(--surface); display: flex; flex-direction: column; gap: 4px; position: relative; }
    .tb-paq.oculto { background: var(--sunken); }
    .tb-paq.oculto .tb-num, .tb-paq.oculto .tb-precio { color: var(--muted); }
    .tb-paq .tb-nombre { font-weight: 600; padding-right: 64px; }
    .tb-paq .tb-num { font-size: 30px; font-weight: 700; line-height: 1.1; letter-spacing: -.5px; }
    .tb-paq .tb-num span { font-size: 14px; font-weight: 500; color: var(--muted); letter-spacing: 0; }
    .tb-paq .tb-precio { font-size: 17px; font-weight: 600; }
    .tb-paq .tb-acc { position: absolute; top: 10px; right: 10px; display: flex; gap: 2px; }
    .tb-paq .tb-acc button { border: 0; background: transparent; color: var(--muted); width: 30px; height: 30px; border-radius: 8px; }
    .tb-paq .tb-acc button:hover { background: var(--sunken); color: var(--text); }
    .tb-paq form { display: grid; gap: 8px; }
    .tb-nuevo { border-style: dashed; justify-content: center; }
    .tb-nuevo > button { border: 0; background: transparent; color: var(--marca); font-weight: 600; padding: 22px 0; }
    .tb-dos { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }

    .tb-saldo { display: inline-flex; align-items: baseline; gap: 4px; font-weight: 600; font-size: 16px; }
    .tb-bajo { color: var(--red); }
    .tb-cero { display: inline-block; font-size: 12px; font-weight: 500; padding: 1px 8px; border-radius: 999px; background: var(--red-soft); color: var(--red); margin-left: 6px; }
    .tb-poco { display: inline-block; font-size: 12px; font-weight: 500; padding: 1px 8px; border-radius: 999px; background: var(--amber-soft); color: var(--amber); margin-left: 6px; }

    .tb-fondo { position: fixed; inset: 0; background: rgba(15, 18, 34, .45); z-index: 1050; display: flex; align-items: flex-start; justify-content: center; padding: 8vh 16px 16px; overflow-y: auto; }
    .tb-modal { background: var(--surface); border-radius: 16px; width: 100%; max-width: 520px; box-shadow: 0 20px 60px rgba(15, 18, 34, .25); }
    .tb-modal header { padding: 18px 20px 6px; }
    .tb-modal header h2 { font-size: 18px; font-weight: 600; margin: 0; }
    .tb-modal .cuerpo { padding: 10px 20px 20px; display: grid; gap: 16px; }
    .tb-opc { display: grid; gap: 8px; }
    .tb-opc label { display: flex; align-items: center; gap: 12px; border: 1px solid var(--line); border-radius: 10px; padding: 12px 14px; cursor: pointer; margin: 0; }
    .tb-opc label:has(input:checked) { border-color: var(--marca); background: var(--marca-soft); }
    .tb-opc input { margin: 0; }
    .tb-opc .der { margin-left: auto; text-align: right; font-weight: 600; }
    .tb-opc .der small { display: block; font-weight: 400; font-size: 12px; color: var(--muted); }
    .tb-seg { display: grid; grid-template-columns: 1fr 1fr; background: var(--sunken); border-radius: 10px; padding: 3px; }
    .tb-seg button { border: 0; background: transparent; border-radius: 8px; padding: 8px; font-weight: 500; color: var(--muted); }
    .tb-seg button.on { background: var(--surface); color: var(--text); box-shadow: 0 1px 3px rgba(15, 18, 34, .12); }
    .tb-modal footer { display: flex; gap: 8px; justify-content: flex-end; padding: 14px 20px; border-top: 1px solid var(--line); }

    @media (max-width: 767.98px) {
        .tb-kpis { grid-template-columns: minmax(0, 1fr); }
        .tb-tabla .col-plan, .tb-tabla .col-usados { display: none; }
    }
</style>
@endpush

@section('contenido')
<div x-data="timbres(@js($iniciales))" @keydown.escape.window="cerrar()">
<div class="page-head">
    <div>
        <h1>Timbres</h1>
        <p class="sub">Crea tus paquetes una vez y asígnalos a cada negocio en dos clics. Cada factura, complemento o nota de crédito gasta un timbre.</p>
    </div>
    <a href="{{ route('admin.plataforma.planes') }}" class="btn btn-borde"><i class="bi bi-tags me-1"></i> Planes y precios</a>
</div>

<div class="tb-kpis">
    <div class="tb-kpi"><div class="lbl">Vendidos este mes</div><div class="val num">{{ number_format($vendidos) }} <small>· {{ $d2($ingresos) }}</small></div></div>
    <div class="tb-kpi"><div class="lbl">Regalados este mes</div><div class="val num">{{ number_format($regalados) }}</div></div>
    <div class="tb-kpi"><div class="lbl">Negocios con {{ \App\Support\Facturas\Timbres::AVISO }} o menos</div><div class="val num {{ $bajos ? 'tb-bajo' : '' }}">{{ $bajos }}</div></div>
</div>

{{-- Paquetes --}}
<section class="panel mb-4" id="paquetes">
    <div class="panel-head"><div><h2>Paquetes</h2><span class="ayuda">Lo que vendes. Los ocultos no se ofrecen a los negocios, pero conservan su historial.</span></div></div>
    <div class="tb-paqs">
        @foreach($paquetes as $pq)
            <div class="tb-paq {{ $pq->activo ? '' : 'oculto' }}" x-data="{ editar: {{ $errors->any() && old('_paquete') == $pq->id ? 'true' : 'false' }} }">
                <template x-if="!editar"><div class="d-grid gap-1">
                    <div class="tb-nombre">{{ $pq->nombre }} @unless($pq->activo)<span class="tb-poco">Oculto</span>@endunless</div>
                    <div class="tb-num num">{{ number_format($pq->timbres) }} <span>timbres</span></div>
                    <div class="tb-precio num">{{ $d2($pq->precio) }}</div>
                    <div class="secundario small num">{{ $d2($pq->por_timbre) }} por timbre</div>
                </div></template>
                <div class="tb-acc" x-show="!editar">
                    <button type="button" @click="editar = true" title="Editar" aria-label="Editar {{ $pq->nombre }}"><i class="bi bi-pencil"></i></button>
                    <form method="post" action="{{ route('admin.plataforma.timbres.paquetes.destroy', $pq) }}" onsubmit="return confirm('¿Eliminar el paquete {{ addslashes($pq->nombre) }}?')">
                        @csrf @method('DELETE')<button title="Eliminar" aria-label="Eliminar {{ $pq->nombre }}"><i class="bi bi-trash3"></i></button>
                    </form>
                </div>
                <form method="post" action="{{ route('admin.plataforma.timbres.paquetes.update', $pq) }}" x-show="editar" x-cloak>
                    @csrf @method('PUT')
                    <input type="hidden" name="_paquete" value="{{ $pq->id }}">
                    <input name="nombre" value="{{ $pq->nombre }}" class="form-control form-control-sm fw-semibold" maxlength="80" required aria-label="Nombre">
                    <div class="tb-dos">
                        <div><label class="form-label small mb-1">Timbres</label><input type="number" name="timbres" min="1" max="1000000" value="{{ $pq->timbres }}" class="form-control form-control-sm num" required></div>
                        <div><label class="form-label small mb-1">Precio</label><input type="number" name="precio" step="0.01" min="0" value="{{ $pq->precio + 0 }}" class="form-control form-control-sm num" required></div>
                    </div>
                    <label class="d-flex align-items-center gap-2 small m-0"><input type="hidden" name="activo" value="0"><input type="checkbox" name="activo" value="1" class="form-check-input m-0" @checked($pq->activo)> Se ofrece a los negocios</label>
                    <div class="d-flex gap-2"><button class="btn btn-primario btn-sm">Guardar</button><button type="button" class="btn btn-borde btn-sm" @click="editar = false">Cancelar</button></div>
                </form>
            </div>
        @endforeach

        <div class="tb-paq tb-nuevo" x-data="{ abierto: {{ $errors->nuevo->any() || $paquetes->isEmpty() ? 'true' : 'false' }} }">
            <button type="button" x-show="!abierto" @click="abierto = true; $nextTick(() => $refs.n.focus())"><i class="bi bi-plus-lg me-1"></i> Nuevo paquete</button>
            <form method="post" action="{{ route('admin.plataforma.timbres.paquetes.store') }}" x-show="abierto" x-cloak>
                @csrf
                <input name="nombre" x-ref="n" value="{{ old('nombre') }}" class="form-control form-control-sm fw-semibold" maxlength="80" required placeholder="Ej. Paquete 100" aria-label="Nombre del paquete">
                <div class="tb-dos">
                    <div><label class="form-label small mb-1">Timbres</label><input type="number" name="timbres" min="1" max="1000000" value="{{ old('timbres') }}" class="form-control form-control-sm num" required placeholder="100"></div>
                    <div><label class="form-label small mb-1">Precio</label><input type="number" name="precio" step="0.01" min="0" value="{{ old('precio') }}" class="form-control form-control-sm num" required placeholder="450"></div>
                </div>
                @foreach($errors->nuevo->all() as $m)<div class="text-danger small">{{ $m }}</div>@endforeach
                <div class="d-flex gap-2"><button class="btn btn-primario btn-sm"><i class="bi bi-plus-lg me-1"></i> Crear</button>@if($paquetes->isNotEmpty())<button type="button" class="btn btn-borde btn-sm" @click="abierto = false">Cancelar</button>@endif</div>
            </form>
        </div>
    </div>
</section>

{{-- Negocios --}}
<section class="panel mb-4" id="negocios">
    <div class="panel-head">
        <div><h2>Negocios</h2><span class="ayuda">Los que facturan. Disponibles = lo que le queda del plan este mes + lo comprado o regalado.</span></div>
        @if($negocios->count() > 8)<input type="search" x-model="buscar" class="form-control form-control-sm" style="max-width: 220px" placeholder="Buscar negocio" aria-label="Buscar negocio">@endif
    </div>
    @if($negocios->isEmpty())
        <div class="panel-body secundario">Aún no hay negocios con facturación. Cuando un negocio tenga el módulo de facturación aparecerá aquí.</div>
    @else
    <div class="table-responsive">
        <table class="tabla tb-tabla">
            <thead><tr><th>Negocio</th><th class="col-plan">Su plan incluye</th><th class="col-usados text-end">Usados este mes</th><th class="text-end">Disponibles</th><th></th></tr></thead>
            <tbody>
            @foreach($negocios as $n)
                <tr x-show="coincide(@js(mb_strtolower($n->cuenta->nombre)))">
                    <td><a href="{{ route('admin.plataforma.licencia', $n->cuenta) }}#timbres" class="fila-link fw-semibold">{{ $n->cuenta->nombre }}</a>
                        @unless($n->cuenta->activa)<span class="tb-poco">Inactivo</span>@endunless</td>
                    <td class="col-plan secundario">{{ $n->plan ? $n->plan . ' al mes' : '—' }}</td>
                    <td class="col-usados text-end num">{{ number_format($n->usados) }}</td>
                    <td class="text-end">
                        <span class="tb-saldo num {{ $n->disponibles <= \App\Support\Facturas\Timbres::AVISO ? 'tb-bajo' : '' }}">{{ number_format($n->disponibles) }}</span>
                        @if($n->disponibles === 0)<span class="tb-cero">Sin timbres</span>@elseif($n->disponibles <= \App\Support\Facturas\Timbres::AVISO)<span class="tb-poco">Pocos</span>@endif
                    </td>
                    <td class="text-end"><button type="button" class="btn btn-primario btn-sm" @click="abrir({{ $n->cuenta->id }}, @js($n->cuenta->nombre))" @disabled($activos->isEmpty())><i class="bi bi-plus-lg me-1"></i> Asignar</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($activos->isEmpty())<div class="panel-body pt-2 secundario small">Crea un paquete arriba para poder asignarlo.</div>@endif
    @endif
</section>

{{-- Últimos movimientos --}}
@if($movimientos->isNotEmpty())
<section class="panel">
    <div class="panel-head"><div><h2>Últimas asignaciones</h2><span class="ayuda">Compras, regalos y ajustes</span></div></div>
    <div class="table-responsive">
        <table class="tabla">
            <thead><tr><th>Fecha</th><th>Negocio</th><th>Movimiento</th><th class="text-end">Timbres</th><th class="text-end">Cobrado</th></tr></thead>
            <tbody>
            @foreach($movimientos as $mv)
                <tr>
                    <td class="secundario num" style="white-space: nowrap">{{ $mv->created_at->timezone(config('vandu.zona_horaria'))->format('d/m/Y') }}</td>
                    <td>{{ $nombres[$mv->cuenta_id] ?? '—' }}</td>
                    <td>{{ \App\Models\TimbreMovimiento::TIPOS[$mv->tipo] ?? $mv->tipo }}{{ $mv->paquete ? ' · ' . $mv->paquete->nombre : '' }}@if($mv->nota)<div class="secundario small">{{ $mv->nota }}</div>@endif</td>
                    <td class="text-end num">{{ $mv->cantidad > 0 ? '+' : '' }}{{ number_format($mv->cantidad) }}</td>
                    <td class="text-end num">{{ $mv->monto ? $d2($mv->monto) : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endif

{{-- Asignar --}}
<div class="tb-fondo" x-show="abierto" x-cloak x-transition.opacity @click.self="cerrar()">
    <form class="tb-modal" method="post" action="{{ route('admin.plataforma.timbres.asignar') }}" role="dialog" aria-modal="true" aria-labelledby="tb-titulo">
        @csrf
        <input type="hidden" name="cuenta_id" :value="cuenta">
        <input type="hidden" name="como" :value="como">
        <header><h2 id="tb-titulo">Asignar timbres a <span x-text="nombre"></span></h2></header>
        <div class="cuerpo">
            <div class="tb-opc">
                @foreach($activos as $pq)
                    <label><input type="radio" name="paquete_id" value="{{ $pq->id }}" class="form-check-input" x-model.number="paquete" data-precio="{{ $pq->precio + 0 }}">
                        <span><b>{{ $pq->nombre }}</b><span class="d-block secundario small num">{{ number_format($pq->timbres) }} timbres</span></span>
                        <span class="der num">{{ $d2($pq->precio) }}<small>{{ $d2($pq->por_timbre) }} c/u</small></span></label>
                @endforeach
            </div>
            <div class="tb-seg" role="group" aria-label="Cómo se entrega">
                <button type="button" :class="{ on: como === 'cobrado' }" @click="como = 'cobrado'"><i class="bi bi-cash-coin me-1"></i> Me lo pagó</button>
                <button type="button" :class="{ on: como === 'regalo' }" @click="como = 'regalo'"><i class="bi bi-gift me-1"></i> Regalo</button>
            </div>
            <div class="tb-dos" x-show="como === 'cobrado'">
                <div><label class="form-label small mb-1">Forma de pago</label>
                    <select name="forma" class="form-select form-select-sm">@foreach(\App\Models\LicenciaPago::FORMAS as $k => $t)<option value="{{ $k }}" @selected(old('forma') === $k)>{{ $t }}</option>@endforeach</select></div>
                <div><label class="form-label small mb-1">Monto cobrado</label><input type="number" step="0.01" min="0" name="monto" class="form-control form-control-sm num" :placeholder="precio()" value="{{ old('monto') }}"></div>
            </div>
            <p class="secundario small m-0" x-show="como === 'cobrado'">Si el negocio tiene licencia, el pago queda registrado con su recibo. Deja el monto vacío para usar el precio del paquete.</p>
            <p class="secundario small m-0" x-show="como === 'regalo'" x-cloak>Se cargan sin cobro. Queda anotado como regalo.</p>
            <div><label class="form-label small mb-1">Nota <span class="secundario">(opcional)</span></label><input name="nota" maxlength="200" class="form-control form-control-sm" placeholder="Folio SPEI, promoción…" value="{{ old('nota') }}"></div>
            @if($errors->any() && ! $errors->nuevo->any() && old('cuenta_id'))
                @foreach($errors->all() as $m)<div class="text-danger small">{{ $m }}</div>@endforeach
            @endif
        </div>
        <footer>
            <button type="button" class="btn btn-borde" @click="cerrar()">Cancelar</button>
            <button class="btn btn-primario" :disabled="!paquete"><i class="bi bi-check2 me-1"></i> <span x-text="como === 'regalo' ? 'Regalar' : 'Asignar'"></span></button>
        </footer>
    </form>
</div>
</div>

<script>
    function timbres(ini) {
        const nombres = @js($negocios->mapWithKeys(fn ($n) => [$n->cuenta->id => $n->cuenta->nombre]));
        return {
            abierto: !!ini, cuenta: ini?.cuenta ?? null, nombre: ini ? (nombres[ini.cuenta] ?? '') : '',
            paquete: ini?.paquete ?? {{ $activos->count() === 1 ? $activos->first()->id : 'null' }}, como: ini?.como ?? 'cobrado', buscar: '',
            abrir(id, nombre) { this.cuenta = id; this.nombre = nombre; this.abierto = true; },
            cerrar() { this.abierto = false; },
            coincide(n) { return !this.buscar || n.includes(this.buscar.toLowerCase().trim()); },
            precio() {
                const r = this.$root.querySelector(`input[name=paquete_id][value="${this.paquete}"]`);
                return r ? r.dataset.precio : '';
            },
        };
    }
</script>
@endsection
