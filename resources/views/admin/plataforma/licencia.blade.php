@extends('admin.layout')
@section('titulo', 'Licencia · ' . $cuenta->nombre)

@php
    use App\Models\Licencia;
    use App\Models\LicenciaPago;
    $hoy = now(config('vandu.zona_horaria'))->toDateString();
    $f = fn ($d) => $d ? $d->locale('es')->isoFormat('D MMM YYYY') : '—';
    $d2 = fn ($n) => '$' . number_format((float) $n, 2);
    $extrasL = collect($l?->extras ?? [])->keyBy('clave');
    $usuMax = $l?->usuariosMax();
    $sucMax = $l?->sucursalesMax();
    $cfg = [
        'planes' => $planes->mapWithKeys(fn ($p) => [$p->id => ['mensual' => $p->precio_mensual, 'anual' => $p->precio('anual')]]),
        'tipo' => old('tipo', $l?->tipo ?? 'suscripcion'), 'plan' => (string) old('plan_id', $l?->plan_id ?? $planes->first()?->id), 'periodo' => old('periodo', $l?->periodo ?? 'mensual'),
        'extras' => $extras->mapWithKeys(fn ($x) => [$x->id => ['cantidad' => (int) old("extras.{$x->id}.cantidad", $extrasL[$x->clave]['cantidad'] ?? 0), 'precio' => (float) old("extras.{$x->id}.precio", $extrasL[$x->clave]['precio'] ?? $x->precio_mensual)]]),
    ];
    $pendienteImpl = $l && $l->implementacion > 0 && ! $l->implementacion_pagada_el;
@endphp

@push('head')
<style>
    .lic-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.15fr); gap: 20px; align-items: start; }
    .lic-estado { padding: 20px; display: grid; gap: 12px; }
    .lic-estado .grande { font-size: 15.5px; }
    .lic-linea { display: grid; gap: 0; border-left: 2px solid var(--line); margin-left: 6px; padding-left: 16px; }
    .lic-linea div { position: relative; padding: 5px 0; font-size: 14px; }
    .lic-linea div::before { content: ''; position: absolute; left: -22px; top: 11px; width: 10px; height: 10px; border-radius: 50%; background: var(--line-strong); }
    .lic-linea div.hoy::before { background: var(--ink); }
    .lic-uso { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
    .lic-uso div { background: var(--sunken); border-radius: 10px; padding: 10px 12px; }
    .lic-uso b { display: block; font-size: 17px; } .lic-uso span { font-size: 12px; color: var(--muted); }
    .lic-uso .lleno b { color: var(--red); }
    .lic-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .lic-form .ancho { grid-column: 1 / -1; }
    .lic-tipos { display: grid; gap: 8px; }
    .lic-tipos label { display: flex; gap: 10px; border: 1.5px solid var(--line-strong); border-radius: 12px; padding: 10px 12px; cursor: pointer; }
    .lic-tipos label:has(input:checked) { border-color: var(--ink); background: var(--sunken); }
    .lic-extras td { padding-top: 6px; padding-bottom: 6px; }
    .lic-sugerido { font-size: 13px; color: var(--muted); }
    @media (max-width: 991.98px) { .lic-grid { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 575.98px) { .lic-form { grid-template-columns: minmax(0, 1fr); } .lic-uso { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); } }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>

        <h1>{{ $cuenta->nombre }}</h1>
        <p class="sub">Licencia, pagos y límites. <span class="estado estado-{{ $e->clase }}">{{ $e->texto }}</span></p>
    </div>
    <a href="{{ route('admin.plataforma.planes') }}" class="btn btn-borde"><i class="bi bi-tags me-1"></i> Planes y precios</a>
</div>

<div class="lic-grid">
<div class="d-grid gap-3">
    {{-- Estado --}}
    <section class="panel lic-estado">
        @if(! $l)
            <div class="grande"><b>Sin licencia.</b> Este negocio no tiene límites ni vencimiento. Asígnale un plan a la derecha.</div>
        @else
            <div class="grande">
                <b>{{ $l->plan?->nombre ?? Licencia::TIPOS[$l->tipo]['texto'] }}</b> · {{ Licencia::TIPOS[$l->tipo]['texto'] }}
                @if($l->tipo !== 'cortesia') · <span class="num">{{ $d2($l->precio) }}</span> {{ $l->periodo === 'anual' ? 'al año' : 'al mes' }}@endif
                @if($l->tipo === 'perpetua' && $l->mantenimiento) · mantenimiento <span class="num">{{ $d2($l->mantenimiento) }}</span> al año @endif
            </div>
            @if($frase = \App\Support\Licencias\Estado::frase($e))<div class="aviso-suave m-0">{{ $frase }}</div>@endif
            @if($l->tipo === 'suscripcion' && $e->vence)
                <div class="lic-linea">
                    <div class="{{ in_array($e->clave, ['corriente', 'por_vencer'], true) ? 'hoy' : '' }}">Pagado hasta el <b>{{ $f($e->vence) }}</b>{{ $e->dias !== null && $e->dias >= 0 ? ' · faltan ' . $e->dias . ' días' : '' }}</div>
                    <div class="{{ $e->clave === 'gracia' ? 'hoy' : '' }}">Gracia ({{ $l->dias_gracia }} días): todo funciona y ve el aviso</div>
                    <div class="{{ $e->clave === 'lectura' ? 'hoy' : '' }}">Solo lectura desde el <b>{{ $f($e->lecturaDesde) }}</b></div>
                    <div class="{{ $e->clave === 'suspendida' ? 'hoy' : '' }}">Suspensión el <b>{{ $f($e->suspendeEl) }}</b></div>
                </div>
            @elseif($l->tipo === 'perpetua')
                <div class="small secundario">Mantenimiento pagado hasta: <b>{{ $f($l->vence_el) }}</b>. Al vencer solo se avisa; nunca se bloquea.</div>
            @endif
            <div class="small secundario">Implementación: {{ $l->implementacion > 0 ? $d2($l->implementacion) . ($l->implementacion_pagada_el ? ' · pagada el ' . $f($l->implementacion_pagada_el) : ' · pendiente') : 'sin costo' }} · cliente desde el {{ $f($l->inicio) }}</div>
        @endif
        <div class="lic-uso">
            <div class="{{ $usuMax !== null && $uso['usuarios'] >= $usuMax ? 'lleno' : '' }}"><b class="num">{{ $uso['usuarios'] }}{{ $usuMax !== null ? ' / ' . $usuMax : '' }}</b><span>Usuarios activos</span></div>
            <div class="{{ $sucMax !== null && $uso['sucursales'] > $sucMax ? 'lleno' : '' }}"><b class="num">{{ $uso['sucursales'] }}{{ $sucMax !== null ? ' / ' . $sucMax : '' }}</b><span>Sucursales</span></div>
            <div><b class="num">{{ $uso['ia'] }}</b><span>Lecturas IA este mes</span></div>
        </div>
    </section>

    {{-- Comprobantes del negocio --}}
    @php $porRevisar = $l?->comprobantes->where('estado', 'pendiente') ?? collect(); @endphp
    @foreach($porRevisar as $cp)
        <section class="panel" style="border-color: #F5DDB0" x-data="{ concepto: '{{ $l->tipo === 'perpetua' ? 'mantenimiento' : 'periodo' }}', rechazar: false }">
            <div class="panel-head"><div><h2>Comprobante por revisar</h2><span class="ayuda">{{ $cp->usuario?->name }} · enviado {{ $cp->created_at->locale('es')->diffForHumans() }}</span></div>
                <a href="{{ route('admin.plataforma.licencia.comprobante', [$cuenta, $cp]) }}" target="_blank" class="btn btn-borde btn-sm" data-recargar><i class="bi bi-eye me-1"></i> Ver comprobante</a></div>
            <div class="panel-body pb-0">
                <div><b class="num">{{ $d2($cp->monto) }}</b> pagados el {{ $f($cp->fecha) }}{{ $cp->referencia ? ' · ref. ' . $cp->referencia : '' }}</div>
                @if($cp->nota)<div class="secundario small mt-1">“{{ $cp->nota }}”</div>@endif
            </div>
            <form method="post" action="{{ route('admin.plataforma.licencia.confirmar', [$cuenta, $cp]) }}" class="panel-body lic-form" x-show="! rechazar">
                @csrf
                <div><label class="form-label small">Registrar como</label>
                    <select name="concepto" class="form-select" x-model="concepto">@foreach(LicenciaPago::CONCEPTOS as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></div>
                <div x-show="['periodo', 'mantenimiento'].includes(concepto)"><label class="form-label small">Periodos que cubre</label>
                    <input type="number" name="periodos" min="1" max="36" value="{{ $l->precio > 0 ? max(1, (int) round($cp->monto / $l->precio)) : 1 }}" class="form-control num"></div>
                <div><label class="form-label small">Monto</label><input type="number" step="0.01" min="0" name="monto" value="{{ $cp->monto }}" class="form-control num" required></div>
                <div><label class="form-label small">Forma</label><select name="forma" class="form-select">@foreach(LicenciaPago::FORMAS as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></div>
                <div class="ancho d-flex gap-2 flex-wrap"><button class="btn btn-primario"><i class="bi bi-check2 me-1"></i> Confirmar pago</button>
                    <button type="button" class="btn btn-fantasma" @click="rechazar = true">No es válido</button></div>
            </form>
            <form method="post" action="{{ route('admin.plataforma.licencia.rechazar', [$cuenta, $cp]) }}" class="panel-body" x-show="rechazar" x-cloak>
                @csrf
                <label class="form-label small">¿Por qué no se acepta? (se le manda al negocio)</label>
                <input name="motivo" maxlength="300" class="form-control" required placeholder="No vemos el depósito en la cuenta; revisa la CLABE">
                <div class="d-flex gap-2 mt-2"><button class="btn btn-borde" style="color: var(--red)">Rechazar comprobante</button><button type="button" class="btn btn-fantasma" @click="rechazar = false">Cancelar</button></div>
            </form>
        </section>
    @endforeach

    {{-- Registrar pago --}}
    @if($l && $l->tipo !== 'cortesia')
        <section class="panel" x-data="{ concepto: '{{ $l->tipo === 'perpetua' ? 'mantenimiento' : ($pendienteImpl ? 'implementacion' : 'periodo') }}', periodos: 1,
            montos: { periodo: {{ $l->precio }}, implementacion: {{ $l->implementacion }}, mantenimiento: {{ (float) $l->mantenimiento }}, otro: 0 },
            get monto() { return ['periodo', 'mantenimiento'].includes(this.concepto) ? this.montos[this.concepto] * this.periodos : this.montos[this.concepto] } }">
            <div class="panel-head"><div><h2>Registrar pago</h2><span class="ayuda">La mensualidad, anualidad o mantenimiento recorre el vencimiento</span></div></div>
            <form method="post" action="{{ route('admin.plataforma.licencia.pagar', $cuenta) }}" class="panel-body lic-form">
                @csrf
                <div><label class="form-label small">Concepto</label>
                    <select name="concepto" class="form-select" x-model="concepto">@foreach(LicenciaPago::CONCEPTOS as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></div>
                <div x-show="['periodo', 'mantenimiento'].includes(concepto)"><label class="form-label small" x-text="concepto === 'mantenimiento' || '{{ $l->periodo }}' === 'anual' ? 'Años que paga' : 'Meses que paga'"></label>
                    <input type="number" name="periodos" min="1" max="36" x-model.number="periodos" class="form-control num"></div>
                <div><label class="form-label small">Monto</label><input type="number" step="0.01" min="0" name="monto" :value="monto.toFixed(2)" class="form-control num" required></div>
                <div><label class="form-label small">Fecha</label><input type="date" name="fecha" value="{{ $hoy }}" class="form-control" required></div>
                <div><label class="form-label small">Forma</label>
                    <select name="forma" class="form-select">@foreach(LicenciaPago::FORMAS as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></div>
                <div><label class="form-label small">Referencia (opcional)</label><input name="referencia" maxlength="120" class="form-control" placeholder="Folio SPEI"></div>
                <div class="ancho"><button class="btn btn-primario"><i class="bi bi-cash-coin me-1"></i> Registrar pago</button></div>
            </form>
        </section>
    @endif

    {{-- Historial --}}
    @if($l)
        <section class="panel">
            <div class="panel-head"><h2>Pagos</h2><span class="ayuda">{{ $d2($l->pagos->sum('monto')) }} en total</span></div>
            @if($l->pagos->isEmpty())
                <div class="panel-body secundario">Todavía no hay pagos registrados.</div>
            @else
                <div class="table-responsive">
                    <table class="tabla">
                        <tbody>
                        @foreach($l->pagos as $p)
                            <tr>
                                <td class="secundario text-nowrap">{{ $f($p->fecha) }}</td>
                                <td><span class="principal">{{ LicenciaPago::CONCEPTOS[$p->concepto] ?? $p->concepto }}</span>
                                    <div class="secundario">{{ LicenciaPago::FORMAS[$p->forma] ?? '' }}{{ $p->referencia ? ' · ' . $p->referencia : '' }}{{ $p->cubre_hasta ? ' · cubre hasta el ' . $f($p->cubre_hasta) : '' }}</div></td>
                                <td class="text-end num">{{ $d2($p->monto) }}</td>
                                <td class="text-end text-nowrap"><a href="{{ route('admin.plataforma.licencia.recibo', [$cuenta, $p]) }}" target="_blank" class="btn btn-fantasma btn-sm" data-recargar aria-label="Recibo"><i class="bi bi-file-earmark-pdf"></i></a><form class="d-inline" method="post" action="{{ route('admin.plataforma.licencia.pagos.destroy', [$cuenta, $p]) }}" onsubmit="return confirm('¿Borrar este pago? Si recorrió el vencimiento, se regresa.')">@csrf @method('DELETE')
                                    <button class="btn btn-fantasma btn-sm" aria-label="Borrar pago"><i class="bi bi-trash3"></i></button></form></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
</div>

{{-- Acuerdo --}}
<section class="panel" x-data="licenciaForm({{ Js::from($cfg) }})">
    <div class="panel-head"><div><h2>{{ $l ? 'Acuerdo' : 'Asignar licencia' }}</h2><span class="ayuda">El plan define usuarios, sucursales y funciones; el precio se puede negociar</span></div></div>
    <form method="post" action="{{ route('admin.plataforma.licencia.update', $cuenta) }}" class="panel-body lic-form">
        @csrf @method('PUT')
        <div class="ancho lic-tipos">
            @foreach(Licencia::TIPOS as $k => $t)
                <label><input type="radio" name="tipo" value="{{ $k }}" x-model="tipo" class="form-check-input mt-1"><span><b>{{ $t['texto'] }}</b><span class="secundario d-block small">{{ $t['ayuda'] }}</span></span></label>
            @endforeach
        </div>
        <div><label class="form-label small">Plan</label>
            <select name="plan_id" class="form-select" x-model="plan">
                <option value="">Sin plan (sin límites)</option>
                @foreach($planes as $p)<option value="{{ $p->id }}">{{ $p->nombre }} · ${{ number_format($p->precio_mensual) }}/mes{{ $p->activo ? '' : ' (inactivo)' }}</option>@endforeach
            </select></div>
        <div x-show="tipo === 'suscripcion'"><label class="form-label small">Se cobra</label>
            <select name="periodo" class="form-select" x-model="periodo">@foreach(Licencia::PERIODOS as $k => $t)<option value="{{ $k }}">{{ $t }}</option>@endforeach</select></div>

        <div class="ancho" x-show="tipo !== 'cortesia'">
            <div class="form-label small mb-1">Extras</div>
            <table class="tabla lic-extras">
                <tbody>
                @foreach($extras as $x)
                    <tr>
                        <td><span class="principal">{{ $x->nombre }}</span>@if($x->descripcion)<div class="secundario small">{{ $x->descripcion }}</div>@endif</td>
                        <td style="width: 90px"><input type="number" min="0" max="99" name="extras[{{ $x->id }}][cantidad]" x-model.number="extras[{{ $x->id }}].cantidad" class="form-control form-control-sm num" aria-label="Cantidad de {{ $x->nombre }}"></td>
                        <td style="width: 130px"><div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="number" step="0.01" min="0" name="extras[{{ $x->id }}][precio]" x-model.number="extras[{{ $x->id }}].precio" class="form-control num" aria-label="Precio mensual de {{ $x->nombre }}"></div></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div x-show="tipo === 'suscripcion'"><label class="form-label small">Precio acordado <span x-text="periodo === 'anual' ? 'al año' : 'al mes'"></span></label>
            <input type="number" step="0.01" min="0" name="precio" value="{{ old('precio', $l && $l->tipo === 'suscripcion' ? $l->precio : '') }}" :placeholder="sugerido.toFixed(2)" class="form-control num">
            <div class="lic-sugerido">Vacío = plan + extras: <span class="num" x-text="'$' + sugerido.toLocaleString('es-MX', { minimumFractionDigits: 2 })"></span></div></div>
        <div x-show="tipo !== 'cortesia'"><label class="form-label small">Implementación (pago único)</label>
            <input type="number" step="0.01" min="0" name="implementacion" value="{{ old('implementacion', $l?->implementacion ?: '') }}" placeholder="3500" class="form-control num"></div>
        <div x-show="tipo === 'perpetua'"><label class="form-label small">Mantenimiento anual</label>
            <input type="number" step="0.01" min="0" name="mantenimiento" value="{{ old('mantenimiento', $l?->mantenimiento) }}" class="form-control num"></div>

        <div><label class="form-label small">Cliente desde</label><input type="date" name="inicio" value="{{ old('inicio', $l?->inicio?->toDateString() ?? $hoy) }}" class="form-control" required></div>
        <div x-show="tipo !== 'cortesia'"><label class="form-label small" x-text="tipo === 'perpetua' ? 'Mantenimiento pagado hasta' : 'Pagado hasta'"></label>
            <input type="date" name="vence_el" value="{{ old('vence_el', $l?->vence_el?->toDateString()) }}" class="form-control">
            <div class="lic-sugerido">Se recorre solo al registrar cada pago.@if(! $l) Vacío: vence el día de inicio hasta que registres el primer pago.@endif</div></div>
        <div x-show="tipo === 'suscripcion'"><label class="form-label small">Días de gracia</label><input type="number" min="0" max="60" name="dias_gracia" value="{{ old('dias_gracia', $l?->dias_gracia ?? 5) }}" class="form-control num" required></div>
        <div x-show="tipo === 'suscripcion'"><label class="form-label small">Días en solo lectura antes de suspender</label><input type="number" min="0" max="90" name="dias_lectura" value="{{ old('dias_lectura', $l?->dias_lectura ?? 10) }}" class="form-control num" required></div>
        <div class="ancho"><label class="form-label small">Notas del acuerdo</label><textarea name="notas" rows="2" maxlength="2000" class="form-control" placeholder="Fundador: precio congelado 12 meses…">{{ old('notas', $l?->notas) }}</textarea></div>
        <div class="ancho"><button class="btn btn-primario">{{ $l ? 'Guardar acuerdo' : 'Asignar licencia' }}</button>
            <span class="secundario small ms-2">Al guardar se aplican sus sucursales y funciones (Gastos, IA) según el plan y los extras.</span></div>
    </form>
</section>
</div>
@endsection

@push('scripts')
<script>
window.licenciaForm = function (cfg) {
    return {
        tipo: cfg.tipo, plan: cfg.plan, periodo: cfg.periodo, extras: cfg.extras,
        get sugerido() {
            const p = cfg.planes[this.plan];
            const base = p ? (this.periodo === 'anual' ? p.anual : p.mensual) : 0;
            const meses = this.periodo === 'anual' ? 10 : 1;
            return base + Object.values(this.extras).reduce((s, x) => s + (x.cantidad || 0) * (x.precio || 0) * meses, 0);
        },
    };
};
</script>
@endpush
