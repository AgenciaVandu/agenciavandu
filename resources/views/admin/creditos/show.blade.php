@extends('admin.layout')
@section('titulo', 'Venta · ' . ($c->cliente?->nombre ?? $c->unidad?->nombre))

@php
    $esCredito = $c->tipo === 'credito';
    $t = $c->termometro;
    $atrasadas = $c->atrasadas();
    $proxima = $c->proxima;
    $ultimoPago = $c->pagos->first();
    $dinero = fn ($n) => '$' . number_format((float) $n, 2);
    $fecha = fn ($d) => $d?->locale('es')->isoFormat('D MMM YYYY');
    $wa = $c->cliente?->whatsapp;
    $msg = $esCredito && $proxima ? 'Hola ' . \Illuminate\Support\Str::before($c->cliente?->nombre ?? '', ' ') . ', te recordamos tu mensualidad ' . $proxima->numero . ' de ' . $c->plazo
        . ' por ' . $dinero($proxima->monto) . ' con vencimiento el ' . $fecha($proxima->vence_el) . ' (' . $c->unidad?->nombre . ').'
        . ($atrasadas->count() ? ' Tienes ' . $atrasadas->count() . ' ' . ($atrasadas->count() === 1 ? 'letra vencida' : 'letras vencidas') . ' por ' . $dinero($c->vencido) . '.' : '') : null;
@endphp

@push('head')
<style>
    .cr { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 20px; align-items: start; }
    .cr .lateral { position: sticky; top: 24px; display: grid; gap: 16px; }
    .mini-c { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .mini-c > div { padding: 16px 20px; } .mini-c > div + div { border-left: 1px solid var(--line); }
    .mini-c .k { font-size: 13px; color: var(--muted); } .mini-c .v { font-size: 20px; font-weight: 600; }
    .termo { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; padding: 4px 12px; border-radius: 99px; color: #fff; }
    .letras td, .letras th { padding: 9px 12px; font-size: 14px; }
    .letras td.r, .letras th.r { text-align: right; }
    .letras tr.vencida td { background: #FDF0EF; }
    .letras tr.pagada td { color: var(--muted); }
    .letras .tag { font-size: 12px; padding: 2px 8px; border-radius: 99px; white-space: nowrap; }
    .tag.ok { background: var(--green-soft); color: var(--green-ink); } .tag.mal { background: var(--red-soft); color: var(--red); } .tag.ad { background: var(--blue-soft); color: var(--blue); }
    .cobro .fila { display: flex; justify-content: space-between; font-size: 14px; padding: 4px 0; }
    .cobro .total { font-size: 26px; font-weight: 650; }
    .unidad-mini { display: flex; gap: 12px; align-items: center; text-decoration: none; color: inherit; }
    .unidad-mini img, .unidad-mini .ph { width: 72px; height: 54px; border-radius: 8px; object-fit: cover; background: var(--sunken); display: grid; place-items: center; color: var(--faint); }
    @media (max-width: 1199.98px) { .cr { grid-template-columns: minmax(0, 1fr); } .cr .lateral { position: static; } }
    @media (max-width: 767.98px) { .mini-c { grid-template-columns: repeat(2, minmax(0, 1fr)); } .mini-c > div:nth-child(3) { border-left: 0; } .mini-c > div:nth-child(n+3) { border-top: 1px solid var(--line); } }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.creditos.index') }}">Cobranza</a> <i class="bi bi-chevron-right small"></i> <span>{{ $c->cliente?->nombre }}</span></div>
<div class="page-head">
    <div>
        <h1>{{ $c->cliente?->nombre ?? 'Venta' }}</h1>
        <p class="sub mb-0">
            @if($esCredito && $c->estado === 'activo')<span class="termo" style="background: {{ $t['color'] }}">{{ $t['nombre'] }}</span>@endif
            {{ \App\Models\Credito::TIPOS[$c->tipo] }}{{ $c->financiera ? ' · ' . $c->financiera : '' }} · {{ $fecha($c->fecha) }}
            @if($c->estado === 'liquidado' && $esCredito) · <span class="estado estado-pagada">Liquidado</span>@endif
            @if($c->estado === 'cancelado') · <span class="estado estado-cancelada">Cancelada</span>@endif
        </p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if($wa && $msg)<a href="https://wa.me/{{ $wa }}?text={{ rawurlencode($msg) }}" target="_blank" rel="noopener" class="btn btn-borde"><i class="bi bi-whatsapp me-1"></i> Recordatorio</a>@endif
        @if($c->cliente)<a href="{{ route('admin.clientes.show', $c->cliente) }}" class="btn btn-borde"><i class="bi bi-person me-1"></i> Cliente</a>@endif
    </div>
</div>

<div class="cr">
    <div class="d-grid gap-4">
        @if($esCredito)
            <div class="panel mini-c">
                <div><div class="k">Financiado</div><div class="v num">{{ $dinero($c->financiado) }}</div></div>
                <div><div class="k">Cobrado</div><div class="v num">{{ $dinero($c->cobrado) }}</div></div>
                <div><div class="k">Por cobrar</div><div class="v num">{{ $dinero($c->por_cobrar) }}</div></div>
                <div><div class="k">Vencido</div><div class="v num" style="{{ $c->vencido > 0 ? 'color: var(--red)' : '' }}">{{ $dinero($c->vencido) }}</div></div>
            </div>

            <section class="panel">
                <div class="panel-head"><h2>Letras</h2><span class="ayuda">{{ $c->plazo }} de {{ $dinero($c->mensualidad) }} · {{ rtrim(rtrim(number_format($c->tasa, 3), '0'), '.') }}% mensual</span></div>
                <div class="table-responsive">
                    <table class="tabla letras">
                        <thead><tr><th>Letra</th><th>Vence</th><th class="r">Capital</th><th class="r">Interés</th><th class="r">Mensualidad</th><th>Estado</th></tr></thead>
                        <tbody>
                            @foreach($c->letras as $l)
                                @php $vencida = ! $l->pagada && $l->vence_el->copy()->addDays($c->dias_gracia)->lt(\Illuminate\Support\Carbon::parse($hoy)); @endphp
                                <tr class="{{ $l->pagada ? 'pagada' : ($vencida ? 'vencida' : '') }}">
                                    <td class="num">{{ $l->numero }}</td>
                                    <td class="num">{{ $fecha($l->vence_el) }}</td>
                                    <td class="r num">{{ $dinero($l->capital) }}</td>
                                    <td class="r num">{{ $dinero($l->interes) }}</td>
                                    <td class="r num">{{ $dinero($l->monto) }}</td>
                                    <td>
                                        @if($l->pagada)
                                            <span class="tag {{ $l->adelantada ? 'ad' : 'ok' }}">{{ $l->adelantada ? 'Adelantada' : 'Pagada' }} {{ $fecha($l->pagada_el) }}</span>
                                            @if($l->moratorio > 0)<span class="secundario small d-block">+ {{ $dinero($l->moratorio) }} moratorio</span>@endif
                                            @if($l->adelantada && $l->descuento > 0)<span class="secundario small d-block">− {{ $dinero($l->descuento) }} de descuento</span>@endif
                                        @elseif($vencida)
                                            <span class="tag mal">Vencida</span>
                                        @else
                                            <span class="secundario small">Pendiente</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="panel">
            <div class="panel-head"><h2>Pagos recibidos</h2><span class="ayuda">{{ $c->pagos->count() }}</span></div>
            @if($c->pagos->isEmpty())
                <p class="secundario px-4 py-3 mb-0">{{ $esCredito ? 'Todavía no hay pagos de mensualidades.' : 'Venta ' . mb_strtolower(\App\Models\Credito::TIPOS[$c->tipo]) . ' por ' . $dinero($c->precio) . '.' }}</p>
            @else
                <ul class="list-unstyled mb-0">
                    @foreach($c->pagos as $p)
                        <li class="d-flex align-items-center gap-3 px-4 py-3" style="border-top: 1px solid var(--line)">
                            <div class="flex-grow-1">
                                <b class="num">{{ $dinero($p->monto) }}</b> <span class="secundario small">· {{ $fecha($p->fecha) }}{{ $p->metodo ? ' · ' . $p->metodo : '' }}{{ $p->usuario ? ' · ' . $p->usuario->primer_nombre : '' }}</span>
                                <div class="secundario small">Letras {{ $p->letras->pluck('numero')->join(', ') }}{{ $p->nota ? ' · ' . $p->nota : '' }}</div>
                            </div>
                            @if($ultimoPago && $p->id === $ultimoPago->id)
                                <form method="post" action="{{ route('admin.creditos.pago.cancelar', [$c, $p]) }}" onsubmit="return confirm('¿Deshacer este pago? Sus letras vuelven a quedar pendientes.')">@csrf @method('delete')
                                    <button class="btn btn-fantasma btn-sm text-danger">Deshacer</button></form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <div class="lateral">
        @if($esCredito && $c->estado === 'activo')
            <section class="panel panel-body cobro" x-data="cobro({{ Js::from(['url' => route('admin.creditos.cotizar', $c), 'fecha' => $hoy]) }})" x-init="calcular()">
                <h2 class="fs-6 fw-semibold mb-3">Registrar pago</h2>
                <form method="post" action="{{ route('admin.creditos.pagar', $c) }}" class="d-grid gap-3">
                    @csrf
                    <div class="row g-2">
                        <div class="col-7"><label class="form-label small" for="pf">Fecha</label><input id="pf" type="date" name="fecha" class="form-control num" x-model="fecha" @change="calcular()" :max="hoy" required></div>
                        <div class="col-5"><label class="form-label small" for="pc">Letras</label><input id="pc" type="number" name="cuantas" min="1" max="{{ $c->letras->whereNull('pagada_el')->count() }}" class="form-control num" x-model.number="cuantas" @input.debounce.300ms="calcular()" required></div>
                    </div>
                    <label class="d-flex gap-2 align-items-start m-0" :class="{ 'opacity-50': !q.puede_adelantar }">
                        <input type="hidden" name="adelantar" value="0">
                        <input type="checkbox" class="form-check-input mt-1" name="adelantar" value="1" x-model="adelantar" @change="calcular()" :disabled="!q.puede_adelantar">
                        <span class="small">Adelantar la última letra<span class="d-block secundario" x-text="q.puede_adelantar ? 'Se cobra sin intereses y con el descuento por adelanto.' : q.motivo"></span></span>
                    </label>
                    <div>
                        <template x-for="l in q.letras" :key="l.id">
                            <div>
                                <div class="fila"><span x-text="'Letra ' + l.numero"></span><span class="num" x-text="dinero(l.monto)"></span></div>
                                <div class="fila secundario" x-show="l.moratorio > 0"><span>+ moratorio</span><span class="num" x-text="dinero(l.moratorio)"></span></div>
                            </div>
                        </template>
                        <template x-if="q.adelanto">
                            <div>
                                <div class="fila"><span x-text="'Letra ' + q.adelanto.numero + ' adelantada (capital)'"></span><span class="num" x-text="dinero(q.adelanto.capital)"></span></div>
                                <div class="fila secundario"><span>Intereses que se perdonan</span><span class="num" x-text="dinero(q.adelanto.interes_descontado)"></span></div>
                                <div class="fila secundario" x-show="q.adelanto.descuento > 0"><span>− descuento por adelanto</span><span class="num" x-text="dinero(q.adelanto.descuento)"></span></div>
                            </div>
                        </template>
                        <div class="fila" style="border-top: 1px solid var(--line); margin-top: 6px; padding-top: 8px"><span>Total a cobrar</span><span class="total num" x-text="dinero(q.total)"></span></div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6"><select name="metodo" class="form-select" aria-label="Forma de pago"><option>Efectivo</option><option>Transferencia</option><option>Depósito</option><option>Tarjeta</option></select></div>
                        <div class="col-6"><input name="nota" class="form-control" maxlength="255" placeholder="Nota (opcional)" aria-label="Nota"></div>
                    </div>
                    <button class="btn btn-primario" :disabled="!q.letras.length">Registrar pago</button>
                </form>
            </section>
        @endif

        <section class="panel panel-body">
            <a href="{{ $c->unidad ? route('admin.unidades.show', $c->unidad) : '#' }}" class="unidad-mini">
                @if($c->unidad?->portada)<img src="{{ $c->unidad->portada->miniatura_url }}" alt="">@else<span class="ph"><i class="bi bi-car-front"></i></span>@endif
                <span><b class="d-block">{{ $c->unidad?->nombre }}</b><span class="secundario small">{{ $c->unidad?->placas }}</span></span>
            </a>
            <div class="mt-3 small">
                <div class="d-flex justify-content-between"><span class="secundario">Precio</span><span class="num">{{ $dinero($c->precio) }}</span></div>
                @if($esCredito)<div class="d-flex justify-content-between"><span class="secundario">Enganche</span><span class="num">{{ $dinero($c->enganche) }}</span></div>
                <div class="d-flex justify-content-between"><span class="secundario">Moratorio</span><span>{{ $c->moratorio_modo === 'dia' ? $dinero($c->moratorio_valor) . ' por día' : rtrim(rtrim(number_format($c->moratorio_valor, 2), '0'), '.') . '% de la letra' }}{{ $c->dias_gracia ? ', ' . $c->dias_gracia . ' días de gracia' : '' }}</span></div>
                <div class="d-flex justify-content-between"><span class="secundario">Desc. por adelanto</span><span class="num">{{ $dinero($c->descuento_adelanto) }}</span></div>@endif
                @if($c->vendedor)<div class="d-flex justify-content-between"><span class="secundario">Vendedor</span><span>{{ $c->vendedor->name }}</span></div>@endif
                @if($c->sucursal)<div class="d-flex justify-content-between"><span class="secundario">Sucursal</span><span>{{ $c->sucursal->nombre }}</span></div>@endif
            </div>
            @if($c->notas)<p class="small mt-2 mb-0" style="white-space: pre-line">{{ $c->notas }}</p>@endif
            @if($c->estado !== 'cancelado' && $c->pagos->isEmpty())
                <form method="post" action="{{ route('admin.creditos.cancelar', $c) }}" class="mt-3" onsubmit="return confirm('¿Cancelar la venta? La unidad vuelve a quedar disponible.')">@csrf
                    <button class="btn btn-link text-danger p-0 small">Cancelar venta</button></form>
            @endif
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
function cobro(d) {
    return {
        url: d.url, fecha: d.fecha, hoy: d.fecha, cuantas: 1, adelantar: false,
        q: { letras: [], adelanto: null, total: 0, puede_adelantar: false, motivo: '' },
        dinero(n) { return '$' + (+n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        async calcular() {
            try {
                const u = new URL(this.url, location.href);
                u.searchParams.set('fecha', this.fecha); u.searchParams.set('cuantas', this.cuantas || 1); u.searchParams.set('adelantar', this.adelantar ? 1 : 0);
                const r = await fetch(u, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (r.ok) { this.q = await r.json(); if (!this.q.puede_adelantar) this.adelantar = false; }
            } catch (e) {}
        },
    };
}
</script>
@endpush
