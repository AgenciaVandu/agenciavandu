@extends('admin.layout')
@section('titulo', 'Factura ' . $v->folio)

@php
    $d = fn ($n) => '$' . number_format((float) $n, 2);
    $tz = config('vandu.zona_horaria');
    $arch = $doc->archivos();
    $ing = $arch->first();
    $fis = $doc->fiscales();
    $ppd = $v->metodo_pago === 'PPD';
    $timbrada = (bool) $doc->facturapiId();
    $motivos = \App\Support\Facturas\Timbrado::MOTIVOS;
    $compPorPago = $complementos->filter(fn ($g) => ! $g->first()->cancelada_at)->keyBy(fn ($g) => $g->first()->venta_pago_id);
    $hoy = now($tz)->toDateString();
@endphp

@push('head')
<style>
    .fv { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 20px; align-items: start; }
    @media (max-width: 1099.98px) { .fv { grid-template-columns: minmax(0, 1fr); } }
    .fv-b { font-size: 12px; font-weight: 600; padding: 2px 9px; border-radius: 99px; background: var(--sunken); color: var(--muted); white-space: nowrap; }
    .fv-b.ok { background: var(--green-soft); color: var(--green-ink); } .fv-b.pend { background: var(--amber-soft); color: var(--amber); } .fv-b.mal { background: var(--red-soft); color: var(--red); }
    .fv-arch { display: inline-flex; gap: 6px; align-items: center; font-size: 13px; padding: 4px 10px; border: 1px solid var(--line); border-radius: 8px; color: inherit; text-decoration: none; background: var(--surface); }
    .fv-fila { display: flex; flex-wrap: wrap; gap: 8px 14px; align-items: center; padding: 12px 20px; }
    .fv-fila + .fv-fila { border-top: 1px solid #F0F1F4; }
    .fv-fila .t { font-weight: 600; } .fv-fila .s { font-size: 13px; color: var(--muted); }
    .fv-uuid { font-family: ui-monospace, monospace; font-size: 12.5px; color: var(--muted); word-break: break-all; }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.facturas') }}">Facturas</a> <i class="bi bi-chevron-right small"></i> <span>{{ $ing?->serie ? $ing->serie . '-' . $ing->folio : $v->folio }}</span></div>
<div class="page-head">
    <div>
        <h1>{{ $fis['razon_social'] ?: $v->quien }}</h1>
        <p class="sub">{{ $ing?->serie ? 'Factura ' . $ing->serie . '-' . $ing->folio . ' · ' : '' }}venta {{ $v->folio }} · {{ $v->fecha->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}{{ $fis['rfc'] ? ' · RFC ' . mb_strtoupper($fis['rfc']) : '' }}</p>
        <div class="d-flex flex-wrap gap-2 mt-1">
            @if($v->metodo_pago)<span class="fv-b">{{ $v->metodo_pago === 'PPD' ? 'PPD · pago en parcialidades' : 'PUE · pagada en una exhibición' }}</span>@endif
            @if($v->saldo > 0.009)<span class="fv-b pend">Por cobrar {{ $d($v->saldo) }}</span>@else<span class="fv-b ok">Pagada</span>@endif
            @if($doc->enviada())<span class="fv-b ok">Enviada {{ $doc->enviada()->locale('es')->isoFormat('D MMM') }}</span>@endif
            @if($doc->cancelacion() === 'pendiente')<span class="fv-b mal">Cancelación esperando al cliente</span>@endif
        </div>
    </div>
    <a href="{{ route('admin.facturas.crear', ['cliente' => $v->cliente_id]) }}" class="btn btn-borde"><i class="bi bi-plus-lg me-1"></i> Otra factura a este cliente</a>
</div>

<div class="fv">
    <div class="d-grid gap-4">
        {{-- La factura --}}
        <section class="panel" x-data="{ cancelar: false }">
            <div class="panel-head"><h2><i class="bi bi-receipt me-1"></i> Factura</h2>@if($ing?->uuid)<span class="fv-uuid">{{ $ing->uuid }}</span>@endif</div>
            @if($arch->isEmpty())
                <div class="panel-body">
                    <p class="secundario mb-2">Esta venta todavía no tiene factura.</p>
                    <a href="{{ route('admin.facturas', ['ver' => 'por_facturar']) }}" class="btn btn-primario btn-sm">Facturarla desde la bandeja</a>
                </div>
            @else
                <div class="panel-body d-flex flex-wrap gap-2 align-items-center">
                    @foreach($arch as $f)<a href="{{ route('admin.facturas.venta.archivo', [$v, $f->id]) }}" target="_blank" class="fv-arch"><i class="bi {{ $f->icono }}"></i> {{ strtoupper($f->ext) }}</a>@endforeach
                    @if($doc->correo())
                        <form method="post" action="{{ route('admin.facturas.enviar', ['venta', $v->id]) }}">@csrf<button class="btn btn-borde btn-sm"><i class="bi bi-envelope me-1"></i> {{ $doc->enviada() ? 'Reenviar' : 'Enviar' }} a {{ $doc->correo() }}</button></form>
                    @endif
                    @if($doc->whatsapp())<a href="{{ route('admin.facturas.whatsapp', ['venta', $v->id]) }}" target="_blank" rel="noopener" class="btn btn-borde btn-sm"><i class="bi bi-whatsapp me-1"></i> WhatsApp</a>@endif
                    @if($timbrada && $doc->cancelacion() !== 'pendiente')<button type="button" class="btn btn-fantasma btn-sm text-danger ms-auto" @click="cancelar = ! cancelar">Cancelar factura</button>@endif
                    @if($doc->cancelacion() === 'pendiente')<form method="post" action="{{ route('admin.facturas.revisar', ['venta', $v->id]) }}" class="ms-auto">@csrf<button class="btn btn-fantasma btn-sm">Revisar cancelación</button></form>@endif
                </div>
                <form method="post" action="{{ route('admin.facturas.cancelar', ['venta', $v->id]) }}" class="panel-body border-top d-flex flex-wrap gap-2 align-items-end" x-show="cancelar" x-cloak
                      onsubmit="return confirm('Se cancelará la factura ante el SAT. ¿Continuar?')">
                    @csrf
                    <div style="flex: 1 1 240px"><label class="form-label small">Motivo</label><select name="motivo" class="form-select form-select-sm">@foreach($motivos as $k => $t)<option value="{{ $k }}">{{ $k }} · {{ $t }}</option>@endforeach</select></div>
                    <button class="btn btn-sm" style="background: var(--red); color: #fff">Cancelar ante el SAT</button>
                    @if($complementos->contains(fn ($g) => ! $g->first()->cancelada_at))<div class="small secundario w-100">Antes cancela sus complementos de pago.</div>@endif
                </form>
            @endif
        </section>

        {{-- Conceptos --}}
        <section class="panel">
            <div class="panel-head"><h2>Conceptos</h2></div>
            <div class="table-responsive"><table class="tabla">
                <thead><tr><th>Descripción</th><th>Clave · unidad</th><th class="text-center">Cantidad</th><th class="text-end">Precio</th><th class="text-end">Importe</th></tr></thead>
                <tbody>
                @foreach($v->conceptos as $c)
                    <tr><td>{{ $c->descripcion }}</td><td class="secundario num">{{ $c->clave_sat ?: $c->producto?->clave_sat ?: '—' }} · {{ $c->unidad_sat ?: $c->producto?->unidad_sat ?: '—' }}</td>
                        <td class="text-center num">{{ rtrim(rtrim(number_format($c->cantidad, 4), '0'), '.') }}</td><td class="text-end num">{{ $d($c->precio) }}</td><td class="text-end num fw-medium">{{ $d($c->importe) }}</td></tr>
                @endforeach
                <tr class="tot"><td colspan="4" class="text-end secundario">Subtotal</td><td class="text-end num">{{ $d($v->subtotal) }}</td></tr>
                @if($v->descuento > 0)<tr><td colspan="4" class="text-end secundario">Descuento</td><td class="text-end num">−{{ $d($v->descuento) }}</td></tr>@endif
                @if($v->con_iva)<tr><td colspan="4" class="text-end secundario">IVA</td><td class="text-end num">{{ $d($v->iva) }}</td></tr>@endif
                @if($v->envio_costo > 0)<tr><td colspan="4" class="text-end secundario">Envío</td><td class="text-end num">{{ $d($v->envio_costo) }}</td></tr>@endif
                <tr><td colspan="4" class="text-end fw-semibold">Total</td><td class="text-end num fw-semibold">{{ $d($v->total) }}</td></tr>
                </tbody>
            </table></div>
        </section>

        {{-- Notas de crédito --}}
        @if($timbrada || $notas->isNotEmpty())
        <section class="panel" x-data="{ nueva: {{ $errors->hasAny(['motivo']) ? 'true' : 'false' }} }">
            <div class="panel-head"><div><h2>Notas de crédito</h2><span class="ayuda">Descuentos, bonificaciones o devoluciones después de facturar</span></div>
                @if($timbrada && $timbrar)<button type="button" class="btn btn-borde btn-sm" @click="nueva = ! nueva"><i class="bi bi-plus-lg me-1"></i> Nota de crédito</button>@endif</div>
            @foreach($notas as $g)
                @php $n = $g->first(); @endphp
                <div class="fv-fila" x-data="{ cancelar: false }">
                    <div><div class="t">{{ $n->serie }}-{{ $n->folio }} · {{ $d($n->monto) }}</div><div class="s">{{ $n->motivo }} · {{ $n->created_at->setTimezone($tz)->locale('es')->isoFormat('D MMM YYYY') }}</div></div>
                    @if($n->cancelada_at)<span class="fv-b mal">Cancelada</span>@elseif($n->cancelacion === 'pendiente')<span class="fv-b pend">Cancelación pendiente</span>@endif
                    <div class="d-flex flex-wrap gap-2 ms-auto">
                        @foreach($g as $f)<a href="{{ route('admin.facturas.venta.archivo', [$v, $f->id]) }}" target="_blank" class="fv-arch"><i class="bi {{ $f->icono }}"></i> {{ strtoupper($f->ext) }}</a>@endforeach
                        @unless($n->cancelada_at)
                            <form method="post" action="{{ route('admin.facturas.venta.enviar-cfdi', [$v, $n->id]) }}">@csrf<button class="btn btn-fantasma btn-sm" @disabled(! $doc->correo())><i class="bi bi-envelope"></i> {{ $n->enviada_at ? 'Reenviar' : 'Enviar' }}</button></form>
                            <button type="button" class="btn btn-fantasma btn-sm text-danger" @click="cancelar = ! cancelar">Cancelar</button>
                        @endunless
                    </div>
                    <form method="post" action="{{ route('admin.facturas.venta.cancelar-cfdi', [$v, $n->id]) }}" class="w-100 d-flex gap-2 align-items-end" x-show="cancelar" x-cloak onsubmit="return confirm('¿Cancelar esta nota de crédito ante el SAT?')">
                        @csrf<select name="motivo" class="form-select form-select-sm" style="max-width: 320px" aria-label="Motivo">@foreach($motivos as $k => $t)<option value="{{ $k }}">{{ $k }} · {{ $t }}</option>@endforeach</select>
                        <button class="btn btn-sm" style="background: var(--red); color: #fff">Cancelar ante el SAT</button></form>
                </div>
            @endforeach
            @if($notas->isEmpty())<div class="panel-body secundario small" x-show="! nueva">Sin notas de crédito.</div>@endif
            @if($timbrada && $timbrar)
                <form method="post" action="{{ route('admin.facturas.venta.nota', $v) }}" class="panel-body border-top row g-2 align-items-end" x-show="nueva" x-cloak>
                    @csrf
                    <div class="col-md-5"><label class="form-label small">Motivo</label><input name="motivo" maxlength="160" class="form-control" value="{{ old('motivo') }}" placeholder="Descuento por pronto pago" required></div>
                    <div class="col-md-3"><label class="form-label small">Monto con IVA</label><div class="input-group"><span class="input-group-text">$</span><input type="number" step="0.01" min="0.01" name="monto" class="form-control num" required value="{{ old('monto') }}"></div></div>
                    <div class="col-md-4"><label class="form-label small">Forma</label><select name="forma_pago" class="form-select">@foreach(\App\Support\Facturas\Timbrado::FORMAS_SAT as $k => $t)<option value="{{ $k }}" @selected($k === \App\Support\Facturas\Timbrado::formaDe($v->pagos->last()?->forma))>{{ $k }} · {{ $t }}</option>@endforeach</select></div>
                    <div class="col-12"><button class="btn btn-primario btn-sm"><i class="bi bi-receipt me-1"></i> Timbrar nota de crédito</button></div>
                </form>
            @endif
        </section>
        @endif

        @if($canceladas->isNotEmpty())
            <section class="panel">
                <div class="panel-head"><h2>Facturas canceladas</h2></div>
                @foreach($canceladas as $g)
                    @php $n = $g->first(); @endphp
                    <div class="fv-fila"><div><div class="t">{{ $n->serie ? $n->serie . '-' . $n->folio : $n->nombre }}</div><div class="s">Cancelada el {{ $n->cancelada_at?->setTimezone($tz)->locale('es')->isoFormat('D MMM YYYY') }}</div></div>
                        <div class="d-flex gap-2 ms-auto">@foreach($g as $f)<a href="{{ route('admin.facturas.venta.archivo', [$v, $f->id]) }}" target="_blank" class="fv-arch"><i class="bi {{ $f->icono }}"></i> {{ strtoupper($f->ext) }}</a>@endforeach</div></div>
                @endforeach
            </section>
        @endif
    </div>

    {{-- Pagos y complementos --}}
    <aside class="panel">
        <div class="panel-head"><div><h2>Pagos</h2>@if($ppd)<span class="ayuda">Cada pago de una factura PPD lleva su complemento</span>@endif</div></div>
        <div class="panel-body d-grid gap-1 pb-2">
            <div class="d-flex justify-content-between"><span class="secundario">Total</span><b class="num">{{ $d($v->total) }}</b></div>
            <div class="d-flex justify-content-between"><span class="secundario">Pagado</span><span class="num">{{ $d($v->pagado) }}</span></div>
            <div class="d-flex justify-content-between" style="font-size: 17px"><span>Resta</span><b class="num">{{ $d($v->saldo) }}</b></div>
        </div>
        @foreach($v->pagos as $pg)
            @php $cg = $compPorPago->get($pg->id); $cp = $cg?->first(); @endphp
            <div class="fv-fila" x-data="{ cancelar: false }">
                <div><div class="t num">{{ $d($pg->monto) }}</div><div class="s">{{ $pg->fecha->locale('es')->isoFormat('D MMM YYYY') }} · {{ \App\Models\Venta::FORMAS[$pg->forma] ?? $pg->forma }}</div></div>
                @if($ppd)
                    <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
                        @if($cp)
                            <span class="fv-b ok">{{ $cp->serie }}-{{ $cp->folio }}</span>
                            @foreach($cg as $f)<a href="{{ route('admin.facturas.venta.archivo', [$v, $f->id]) }}" target="_blank" class="fv-arch"><i class="bi {{ $f->icono }}"></i> {{ strtoupper($f->ext) }}</a>@endforeach
                            <form method="post" action="{{ route('admin.facturas.venta.enviar-cfdi', [$v, $cp->id]) }}">@csrf<button class="btn btn-fantasma btn-sm" @disabled(! $doc->correo()) aria-label="Enviar complemento"><i class="bi bi-envelope"></i></button></form>
                            <button type="button" class="btn btn-fantasma btn-sm text-danger" @click="cancelar = ! cancelar" aria-label="Cancelar complemento"><i class="bi bi-x-circle"></i></button>
                        @elseif($timbrada && $timbrar)
                            <form method="post" action="{{ route('admin.facturas.venta.complemento', [$v, $pg]) }}">@csrf<button class="btn btn-borde btn-sm">Timbrar complemento</button></form>
                        @else
                            <span class="fv-b pend">Sin complemento</span>
                        @endif
                    </div>
                    @if($cp)
                        <form method="post" action="{{ route('admin.facturas.venta.cancelar-cfdi', [$v, $cp->id]) }}" class="w-100 d-flex gap-2" x-show="cancelar" x-cloak onsubmit="return confirm('¿Cancelar este complemento ante el SAT?')">
                            @csrf<select name="motivo" class="form-select form-select-sm" aria-label="Motivo">@foreach($motivos as $k => $t)<option value="{{ $k }}">{{ $k }} · {{ $t }}</option>@endforeach</select>
                            <button class="btn btn-sm" style="background: var(--red); color: #fff">Cancelar</button></form>
                    @endif
                @endif
            </div>
        @endforeach
        @if($v->saldo > 0.009 && $v->estado !== 'cancelada')
            <form method="post" action="{{ route('admin.facturas.venta.pago', $v) }}" class="panel-body border-top d-grid gap-2">
                @csrf
                <div class="row g-2">
                    <div class="col-6"><div class="input-group"><span class="input-group-text">$</span><input name="monto" type="number" step="0.01" min="0.01" max="{{ $v->saldo }}" value="{{ $v->saldo }}" class="form-control num @error('monto') is-invalid @enderror" required aria-label="Monto"></div></div>
                    <div class="col-6"><select name="forma" class="form-select" aria-label="Forma de pago">@foreach(\App\Models\Venta::FORMAS as $k => $t)<option value="{{ $k }}" @selected($k === 'transferencia')>{{ $t }}</option>@endforeach</select></div>
                    <div class="col-12"><input type="date" name="fecha" value="{{ $hoy }}" max="{{ $hoy }}" class="form-control" aria-label="Fecha del pago"></div>
                </div>
                @error('monto')<div class="text-danger small">{{ $message }}</div>@enderror
                @if($ppd && $timbrada && $timbrar)<label class="form-check small m-0"><input type="checkbox" name="complemento" value="1" class="form-check-input" checked> <span class="form-check-label">Timbrar su complemento de pago</span></label>@endif
                <button class="btn btn-primario"><i class="bi bi-cash-coin me-1"></i> Registrar pago</button>
            </form>
        @endif
    </aside>
</div>
@endsection
