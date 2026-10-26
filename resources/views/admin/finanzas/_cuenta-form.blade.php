@php
    $nueva = ! $c;
    $hoyF = now(config('vandu.zona_horaria'))->toDateString();
@endphp
<form method="post" action="{{ $nueva ? route('admin.finanzas.cuentas.store') : route('admin.finanzas.cuentas.update', $c) }}" class="panel caja-form"
      x-data="{ tipo: '{{ $c?->tipo ?? 'banco' }}' }">
    @csrf
    @unless($nueva) @method('PUT') @endunless
    <h3>{{ $nueva ? 'Nueva cuenta' : 'Editar ' . $c->nombre }}</h3>
    <div class="rejilla-f">
        <div><label class="form-label small">Nombre</label><input name="nombre" value="{{ $c?->nombre }}" maxlength="80" class="form-control" required placeholder="Banco BBVA"></div>
        <div><label class="form-label small">Tipo</label>
            <select name="tipo" class="form-select" x-model="tipo">@foreach(\App\Models\CuentaDinero::TIPOS as $k => $t)<option value="{{ $k }}" @selected(($c?->tipo ?? 'banco') === $k)>{{ $t['texto'] }}</option>@endforeach</select></div>
        <div x-show="tipo === 'banco'"><label class="form-label small">Banco (opcional)</label><input name="banco" value="{{ $c?->banco }}" maxlength="60" class="form-control" placeholder="BBVA, Banorte…"></div>
        <div><label class="form-label small">Saldo inicial</label><input type="number" step="0.01" name="saldo_inicial" value="{{ $c ? number_format($c->saldo_inicial, 2, '.', '') : '' }}" class="form-control" placeholder="0.00"></div>
        <div><label class="form-label small">A la fecha</label><input type="date" name="saldo_desde" value="{{ $c?->saldo_desde?->toDateString() ?? now(config('vandu.zona_horaria'))->startOfMonth()->toDateString() }}" max="{{ $hoyF }}" class="form-control" required></div>
    </div>
    <div class="mt-3">
        <div class="form-label small mb-1">Lo que se cobra o paga con estas formas cae aquí solo</div>
        <div class="formas-chk">
            @foreach(\App\Models\CuentaDinero::FORMAS as $k => $t)
                <label class="form-check m-0"><input type="checkbox" name="formas[]" value="{{ $k }}" class="form-check-input" @checked(in_array($k, (array) ($c?->formas ?? []), true))> <span class="form-check-label">{{ $t }}</span></label>
            @endforeach
        </div>
        <div class="secundario small mt-1">Cada forma va a una sola cuenta: si la marcas aquí, se quita de la otra.@if($nueva) Si no marcas ninguna, se usan las de su tipo.@endif</div>
    </div>
    @unless($nueva)
        <input type="hidden" name="activa" value="0">
        <label class="form-check small mt-3"><input type="checkbox" name="activa" value="1" class="form-check-input" @checked($c->activa)> <span class="form-check-label">Cuenta activa (si la apagas, deja de sumar y de recibir movimientos)</span></label>
    @endunless
    <div class="mt-3 d-flex gap-2"><button class="btn btn-primario">{{ $nueva ? 'Crear cuenta' : 'Guardar' }}</button><button type="button" class="btn btn-fantasma" @click="{{ $nueva ? 'forma = null' : 'edit = null' }}">Cancelar</button></div>
</form>
