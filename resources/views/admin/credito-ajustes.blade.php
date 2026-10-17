@extends('admin.layout')
@section('titulo', 'Crédito y cobranza')

@php $t = old('termometro', $a['termometro']); @endphp

@push('head')
<style>
    .ca { max-width: 820px; }
    .ca .panel + .panel { margin-top: 20px; }
    .nivel { display: grid; grid-template-columns: 44px minmax(0, 1fr) 150px 36px; gap: 10px; align-items: center; }
    .nivel + .nivel { margin-top: 8px; }
    .nivel input[type=color] { width: 44px; height: 38px; padding: 2px; border-radius: 8px; border: 1px solid var(--line); }
    .ejemplo td, .ejemplo th { padding: 6px 10px; font-size: 13.5px; }
</style>
@endpush

@section('contenido')
<div class="ca" x-data="{ niveles: {{ Js::from(array_values($t)) }}, modo: {{ Js::from(old('moratorio_modo', $a['moratorio_modo'])) }} }">
    <div class="page-head">
        <div>
            <h1>Crédito y cobranza</h1>
            <p class="sub">Las condiciones con las que vendes a crédito. Al hacer una venta se proponen estas y se pueden cambiar para ese cliente.</p>
        </div>
    </div>

    <form method="post" action="{{ route('admin.credito-ajustes.guardar') }}">
        @csrf
        <section class="panel panel-body">
            <h2 class="fs-6 fw-semibold mb-3">Condiciones del crédito</h2>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label" for="tasa">Interés mensual sobre saldo</label>
                    <div class="input-group"><input id="tasa" name="tasa" type="number" step="0.01" min="0" max="20" class="form-control num" value="{{ old('tasa', $a['tasa']) }}" required><span class="input-group-text">%</span></div></div>
                <div class="col-md-4"><label class="form-label" for="plazo">Plazo más común</label>
                    <div class="input-group"><input id="plazo" name="plazo" type="number" min="1" max="120" class="form-control num" value="{{ old('plazo', $a['plazo']) }}" required><span class="input-group-text">meses</span></div></div>
                <div class="col-md-4"><label class="form-label" for="eng">Enganche mínimo</label>
                    <div class="input-group"><input id="eng" name="enganche_pct" type="number" step="0.5" min="0" max="100" class="form-control num" value="{{ old('enganche_pct', $a['enganche_pct']) }}" required><span class="input-group-text">%</span></div></div>
            </div>
            <p class="secundario small mt-2 mb-0">La mensualidad es fija. Con estos datos se calcula el "desde $X al mes" que aparece en la página de cada auto.</p>
        </section>

        <section class="panel panel-body">
            <h2 class="fs-6 fw-semibold mb-3">Pago tardío y pago adelantado</h2>
            <div class="row g-3">
                <div class="col-md-5">
                    <span class="form-label d-block">Moratorio</span>
                    <div class="d-flex gap-3">
                        <label class="d-flex gap-2 align-items-center m-0"><input type="radio" name="moratorio_modo" value="porcentaje" x-model="modo"> % de la letra atrasada</label>
                        <label class="d-flex gap-2 align-items-center m-0"><input type="radio" name="moratorio_modo" value="dia" x-model="modo"> Monto por día</label>
                    </div>
                </div>
                <div class="col-md-4"><label class="form-label" for="mv" x-text="modo === 'dia' ? 'Monto por cada día de atraso' : 'Porcentaje de la letra'">Valor</label>
                    <div class="input-group"><span class="input-group-text" x-show="modo === 'dia'">$</span><input id="mv" name="moratorio_valor" type="number" step="0.01" min="0" class="form-control num" value="{{ old('moratorio_valor', $a['moratorio_valor']) }}" required><span class="input-group-text" x-show="modo !== 'dia'">%</span></div></div>
                <div class="col-md-3"><label class="form-label" for="dg">Días de gracia</label>
                    <input id="dg" name="dias_gracia" type="number" min="0" max="60" class="form-control num" value="{{ old('dias_gracia', $a['dias_gracia']) }}" required></div>
                <div class="col-md-5"><label class="form-label" for="da">Descuento por pago adelantado</label>
                    <div class="input-group"><span class="input-group-text">$</span><input id="da" name="descuento_adelanto" type="number" step="0.01" min="0" class="form-control num" value="{{ old('descuento_adelanto', $a['descuento_adelanto']) }}" required></div></div>
            </div>
            <p class="secundario small mt-2 mb-0">Adelantar: el día que vence una letra (o antes) el cliente puede pagar también la última pendiente; esa se cobra sin sus intereses y con este descuento.</p>
        </section>

        <section class="panel panel-body">
            <h2 class="fs-6 fw-semibold mb-1">Termómetro de clientes</h2>
            <p class="secundario small mb-3">Según cuántas letras vencidas tiene cada cliente. El último nivel es para todo lo que pase del anterior.</p>
            <template x-for="(n, i) in niveles" :key="i">
                <div class="nivel">
                    <input type="color" :name="`termometro[${i}][color]`" x-model="n.color" :aria-label="'Color del nivel ' + (i + 1)">
                    <input class="form-control" :name="`termometro[${i}][nombre]`" x-model="n.nombre" required maxlength="30" :aria-label="'Nombre del nivel ' + (i + 1)">
                    <div class="input-group" x-show="i < niveles.length - 1">
                        <span class="input-group-text">hasta</span>
                        <input type="number" min="0" max="120" class="form-control num" :name="`termometro[${i}][hasta]`" x-model.number="n.hasta" :aria-label="'Letras vencidas máximas del nivel ' + (i + 1)">
                    </div>
                    <span class="secundario small" x-show="i === niveles.length - 1" x-text="'más de ' + (niveles[niveles.length - 2]?.hasta ?? 0)"></span>
                    <button type="button" class="btn btn-fantasma btn-icono text-danger" @click="niveles.splice(i, 1)" :disabled="niveles.length <= 2" :aria-label="'Quitar nivel ' + (i + 1)"><i class="bi bi-x-lg"></i></button>
                </div>
            </template>
            <button type="button" class="btn btn-borde btn-sm mt-3" @click="niveles.splice(niveles.length - 1, 0, { nombre: '', hasta: (niveles[niveles.length - 2]?.hasta ?? 0) + 1, color: '#6b7280' })" :disabled="niveles.length >= 6"><i class="bi bi-plus-lg"></i> Agregar nivel</button>
        </section>

        <div class="d-flex justify-content-end mt-4"><button class="btn btn-primario px-4">Guardar condiciones</button></div>
    </form>
</div>
@endsection
