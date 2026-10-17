@extends('admin.layout')
@section('titulo', 'Vender ' . $u->nombre)

@php
    $estado = [
        'tipo' => old('tipo', 'credito'), 'precio' => (float) old('precio', $u->precio), 'enganche' => (float) old('enganche', round($u->precio * $a['enganche_pct'] / 100, 2)),
        'tasa' => (float) old('tasa', $a['tasa']), 'plazo' => (int) old('plazo', $a['plazo']), 'fecha' => old('fecha', now(config('vandu.zona_horaria'))->toDateString()),
        'primer' => old('primer_vencimiento', ''), 'clienteId' => (string) old('cliente_id', ''), 'verTabla' => false,
    ];
@endphp

@push('head')
<style>
    .venta { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 24px; align-items: start; }
    .venta .lateral { position: sticky; top: 24px; }
    .tipos-v { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
    .tipos-v label { margin: 0; }
    .tipos-v input { position: absolute; opacity: 0; pointer-events: none; }
    .tipos-v span { display: flex; flex-direction: column; gap: 2px; padding: 12px 14px; border: 1.5px solid var(--line); border-radius: 12px; cursor: pointer; background: var(--surface); font-size: 14px; }
    .tipos-v span small { color: var(--muted); font-size: 12.5px; }
    .tipos-v input:checked + span { border-color: var(--ink); box-shadow: inset 0 0 0 1px var(--ink); }
    .tipos-v input:focus-visible + span { outline: 2px solid var(--blue); outline-offset: 2px; }
    .resumen-v .fila { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14.5px; }
    .resumen-v .grande { font-size: 28px; font-weight: 650; }
    .tabla-am { max-height: 360px; overflow: auto; }
    .tabla-am table { width: 100%; font-size: 13px; }
    .tabla-am th, .tabla-am td { padding: 5px 8px; text-align: right; }
    .tabla-am th:first-child, .tabla-am td:first-child { text-align: left; }
    .tabla-am thead th { position: sticky; top: 0; background: var(--sunken); }
    @media (max-width: 1199.98px) { .venta { grid-template-columns: minmax(0, 1fr); } .venta .lateral { position: static; } }
    @media (max-width: 575.98px) { .tipos-v { grid-template-columns: minmax(0, 1fr); } }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.unidades.index') }}">Unidades</a> <i class="bi bi-chevron-right small"></i> <a href="{{ route('admin.unidades.show', $u) }}">{{ $u->nombre }}</a> <i class="bi bi-chevron-right small"></i> <span>Vender</span></div>
<div class="page-head"><div><h1>Vender {{ $u->marca }} {{ $u->modelo }} {{ $u->anio }}</h1><p class="sub">{{ collect([$u->placas, $u->serie])->filter()->join(' · ') }}</p></div></div>

<form method="post" action="{{ route('admin.creditos.store', $u) }}" class="venta" x-data="venta({{ Js::from($estado) }})">
    @csrf
    <div class="d-grid gap-4">
        <section class="panel panel-body">
            <h2 class="fs-6 fw-semibold mb-3">Cliente</h2>
            <select name="cliente_id" class="form-select mb-2" x-model="clienteId" aria-label="Cliente">
                <option value="">Cliente nuevo…</option>
                @foreach($clientes as $cl)<option value="{{ $cl->id }}">{{ $cl->nombre }}{{ $cl->empresa ? ' · ' . $cl->empresa : '' }}{{ $cl->telefono ? ' · ' . $cl->telefono : '' }}</option>@endforeach
            </select>
            <div class="row g-2" x-show="!clienteId">
                <div class="col-md-5"><input name="cliente_nombre" class="form-control" placeholder="Nombre completo" value="{{ old('cliente_nombre') }}" maxlength="160" :required="!clienteId"></div>
                <div class="col-md-3"><input name="cliente_telefono" class="form-control num" placeholder="WhatsApp" value="{{ old('cliente_telefono') }}" maxlength="30" inputmode="tel"></div>
                <div class="col-md-4"><input name="cliente_email" type="email" class="form-control" placeholder="Correo (opcional)" value="{{ old('cliente_email') }}" maxlength="160"></div>
            </div>
        </section>

        <section class="panel panel-body">
            <h2 class="fs-6 fw-semibold mb-3">Forma de pago</h2>
            <div class="tipos-v" role="radiogroup" aria-label="Forma de pago">
                <label><input type="radio" name="tipo" value="credito" x-model="tipo"><span><b>Crédito del lote</b><small>Enganche y mensualidades que cobra el lote</small></span></label>
                <label><input type="radio" name="tipo" value="contado" x-model="tipo"><span><b>De contado</b><small>Un solo pago</small></span></label>
                <label><input type="radio" name="tipo" value="financiera" x-model="tipo"><span><b>Banco o financiera</b><small>La financiera le paga al lote</small></span></label>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-md-4"><label class="form-label" for="precio">Precio de venta</label>
                    <div class="input-group"><span class="input-group-text">$</span><input id="precio" name="precio" type="number" step="0.01" min="1" class="form-control num" x-model.number="precio" required></div></div>
                <div class="col-md-4"><label class="form-label" for="fecha">Fecha de venta</label><input id="fecha" name="fecha" type="date" class="form-control num" x-model="fecha" required></div>
                <div class="col-md-4" x-show="tipo === 'financiera'"><label class="form-label" for="fin">Financiera</label><input id="fin" name="financiera" class="form-control" maxlength="120" value="{{ old('financiera') }}" placeholder="Banco, Credito Real…"></div>
            </div>
            <div class="row g-3 mt-1" x-show="tipo === 'credito'">
                <div class="col-md-4"><label class="form-label" for="eng">Enganche</label>
                    <div class="input-group"><span class="input-group-text">$</span><input id="eng" name="enganche" type="number" step="0.01" min="0" class="form-control num" x-model.number="enganche"></div>
                    <div class="form-text" x-text="precio ? Math.round(enganche / precio * 100) + '% del precio' : ''"></div></div>
                <div class="col-md-4"><label class="form-label" for="tasa">Interés mensual</label>
                    <div class="input-group"><input id="tasa" name="tasa" type="number" step="0.01" min="0" max="20" class="form-control num" x-model.number="tasa"><span class="input-group-text">%</span></div></div>
                <div class="col-md-4"><label class="form-label" for="plazo">Plazo</label>
                    <div class="input-group"><input id="plazo" name="plazo" type="number" min="1" max="120" class="form-control num" x-model.number="plazo"><span class="input-group-text">meses</span></div></div>
                <div class="col-md-4"><label class="form-label" for="primer">Primera mensualidad</label><input id="primer" name="primer_vencimiento" type="date" class="form-control num" x-model="primer" :min="fecha">
                    <div class="form-text">Si la dejas vacía, un mes después de la venta.</div></div>
            </div>
            <details class="mt-3" x-show="tipo === 'credito'">
                <summary class="small" style="cursor:pointer">Moratorio y descuento por adelanto para este cliente</summary>
                <div class="row g-3 mt-1">
                    <div class="col-md-4"><label class="form-label small" for="mm">Moratorio</label>
                        <select id="mm" name="moratorio_modo" class="form-select"><option value="porcentaje" @selected($a['moratorio_modo'] === 'porcentaje')>% de la letra</option><option value="dia" @selected($a['moratorio_modo'] === 'dia')>Monto por día</option></select></div>
                    <div class="col-md-3"><label class="form-label small" for="mv">Valor</label><input id="mv" name="moratorio_valor" type="number" step="0.01" min="0" class="form-control num" value="{{ $a['moratorio_valor'] }}"></div>
                    <div class="col-md-2"><label class="form-label small" for="dg">Gracia</label><input id="dg" name="dias_gracia" type="number" min="0" max="60" class="form-control num" value="{{ $a['dias_gracia'] }}"></div>
                    <div class="col-md-3"><label class="form-label small" for="da">Desc. adelanto</label><input id="da" name="descuento_adelanto" type="number" step="0.01" min="0" class="form-control num" value="{{ $a['descuento_adelanto'] }}"></div>
                </div>
            </details>
        </section>

        <section class="panel panel-body">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="vend">Vendedor</label>
                    <select id="vend" name="vendedor_id" class="form-select">@foreach($vendedores as $v)<option value="{{ $v->id }}" @selected(($u->vendedor_id ?? auth()->id()) === $v->id)>{{ $v->name }}</option>@endforeach</select></div>
                @if($sucursales->isNotEmpty())
                    <div class="col-md-6"><label class="form-label" for="suc">Sucursal</label>
                        <select id="suc" name="sucursal_id" class="form-select"><option value="">—</option>@foreach($sucursales as $s)<option value="{{ $s->id }}" @selected($u->sucursal_id === $s->id)>{{ $s->nombre }}</option>@endforeach</select></div>
                @endif
                <div class="col-12"><label class="form-label" for="notas">Notas <span class="secundario fw-normal">(opcional)</span></label><textarea id="notas" name="notas" rows="2" class="form-control" maxlength="2000">{{ old('notas') }}</textarea></div>
            </div>
        </section>

        <section class="panel" x-show="tipo === 'credito' && tabla.length" x-cloak>
            <div class="panel-head"><h2>Tabla de pagos</h2><button type="button" class="btn btn-borde btn-sm" @click="verTabla = !verTabla" x-text="verTabla ? 'Ocultar' : 'Ver las ' + tabla.length + ' letras'"></button></div>
            <div class="tabla-am" x-show="verTabla">
                <table>
                    <thead><tr><th>Letra</th><th>Vence</th><th>Capital</th><th>Interés</th><th>Mensualidad</th><th>Saldo</th></tr></thead>
                    <tbody><template x-for="f in tabla" :key="f.numero"><tr>
                        <td x-text="f.numero"></td><td class="num" x-text="f.vence"></td><td class="num" x-text="dinero(f.capital)"></td><td class="num" x-text="dinero(f.interes)"></td><td class="num" x-text="dinero(f.monto)"></td><td class="num" x-text="dinero(f.saldo)"></td>
                    </tr></template></tbody>
                </table>
            </div>
        </section>
    </div>

    <aside class="lateral">
        <section class="panel panel-body resumen-v">
            <template x-if="tipo === 'credito'">
                <div>
                    <div class="secundario small">Mensualidad fija</div>
                    <div class="grande num" x-text="dinero(mensualidad)"></div>
                    <div class="secundario small mb-3" x-text="plazo + ' meses al ' + tasa + '% mensual sobre saldo'"></div>
                    <div class="fila"><span>Enganche</span><b class="num" x-text="dinero(enganche)"></b></div>
                    <div class="fila"><span>A financiar</span><b class="num" x-text="dinero(financiado)"></b></div>
                    <div class="fila"><span>Intereses totales</span><span class="num" x-text="dinero(intereses)"></span></div>
                    <div class="fila" style="border-top:1px solid var(--line); margin-top:6px; padding-top:10px"><span>Total que pagará</span><b class="num" x-text="dinero(enganche + financiado + intereses)"></b></div>
                </div>
            </template>
            <template x-if="tipo !== 'credito'">
                <div><div class="secundario small">Total</div><div class="grande num" x-text="dinero(precio)"></div></div>
            </template>
            <button class="btn btn-primario w-100 mt-3"><i class="bi bi-bag-check me-1"></i> Registrar venta</button>
            <p class="secundario small mt-2 mb-0 text-center">La unidad queda como vendida.</p>
        </section>
    </aside>
</form>
@endsection

@push('scripts')
<script>
function venta(d) {
    return {
        ...d,
        dinero(n) { return '$' + (+n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        get financiado() { return Math.max(0, Math.round(((+this.precio || 0) - (+this.enganche || 0)) * 100) / 100); },
        get mensualidad() {
            const p = this.financiado, i = (+this.tasa || 0) / 100, n = +this.plazo || 0;
            if (n < 1 || p <= 0) return 0;
            return i <= 0 ? Math.round(p / n * 100) / 100 : Math.round(p * i / (1 - Math.pow(1 + i, -n)) * 100) / 100;
        },
        get tabla() {
            const n = +this.plazo || 0, i = (+this.tasa || 0) / 100, pago = this.mensualidad;
            if (n < 1 || this.financiado <= 0) return [];
            const inicio = this.primer ? new Date(this.primer + 'T12:00:00') : (() => { const f = new Date(this.fecha + 'T12:00:00'); f.setMonth(f.getMonth() + 1); return f; })();
            let saldo = this.financiado; const filas = [];
            for (let k = 1; k <= n; k++) {
                const interes = Math.round(saldo * i * 100) / 100;
                const capital = k === n ? saldo : Math.round((pago - interes) * 100) / 100;
                saldo = Math.round((saldo - capital) * 100) / 100;
                const v = new Date(inicio); v.setMonth(inicio.getMonth() + k - 1);
                if (v.getDate() !== inicio.getDate()) v.setDate(0);
                filas.push({ numero: k, vence: v.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' }), capital, interes, monto: Math.round((capital + interes) * 100) / 100, saldo: Math.max(0, saldo) });
            }
            return filas;
        },
        get intereses() { return Math.round(this.tabla.reduce((s, f) => s + f.interes, 0) * 100) / 100; },
    };
}
</script>
@endpush
