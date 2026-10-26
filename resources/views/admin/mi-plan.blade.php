@extends('admin.layout')
@section('titulo', 'Mi plan')

@php
    use App\Models\Licencia;
    $f = fn ($d) => $d ? $d->locale('es')->isoFormat('D [de] MMMM [de] YYYY') : '—';
    $fc = fn ($d) => $d ? $d->locale('es')->isoFormat('D MMM YYYY') : '—';
    $d2 = fn ($n) => '$' . number_format((float) $n, 2);
    $usuMax = $l->usuariosMax();
    $sucMax = $l->sucursalesMax();
    $funciones = collect($l->funciones())->map(fn ($m) => \App\Support\Giros::MODULOS[$m]['texto'] ?? $m);
    $extras = $l->extrasDetalle();
    $pendientes = $l->comprobantes->where('estado', 'pendiente');
    $ultimoRechazo = $l->comprobantes->firstWhere('estado', 'rechazado');
    $cobra = $l->tipo === 'suscripcion' ? $l->precio : ($l->tipo === 'perpetua' ? (float) $l->mantenimiento : 0);
    $pct = fn ($a, $b) => $b ? min(100, round($a / $b * 100)) : 0;
@endphp

@push('head')
<style>
    .mp-grid { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); gap: 20px; align-items: start; }
    .mp-plan { padding: 22px; display: grid; gap: 14px; }
    .mp-plan .nombre { font-size: 24px; font-weight: 600; letter-spacing: -.02em; }
    .mp-plan .precio { font-size: 15px; color: var(--text-2); }
    .mp-incluye { display: flex; flex-wrap: wrap; gap: 6px; }
    .mp-incluye span { background: var(--sunken); border-radius: 99px; padding: 4px 11px; font-size: 13px; }
    .mp-uso { display: grid; gap: 14px; }
    .mp-uso .fila { display: grid; gap: 6px; }
    .mp-uso .k { display: flex; justify-content: space-between; font-size: 14px; }
    .mp-uso .barra { height: 8px; border-radius: 4px; background: #F1F2F4; overflow: hidden; }
    .mp-uso .barra span { display: block; height: 100%; background: var(--ink); border-radius: 4px; }
    .mp-uso .lleno .barra span { background: var(--red); }
    .mp-proximo { padding: 20px; display: grid; gap: 8px; }
    .mp-proximo .fecha { font-size: 20px; font-weight: 600; }
    .mp-pago { background: var(--sunken); border-radius: 12px; padding: 12px 14px; display: grid; gap: 3px; font-size: 14px; }
    .mp-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .mp-form .ancho { grid-column: 1 / -1; }
    @media (max-width: 991.98px) { .mp-grid { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 575.98px) { .mp-form { grid-template-columns: minmax(0, 1fr); } }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <h1>Mi plan</h1>
        <p class="sub">Lo que tienes contratado, cuánto usas y tus pagos. <span class="estado estado-{{ $e->clase }}">{{ $e->texto }}</span></p>
    </div>
</div>

<div class="mp-grid">
<div class="d-grid gap-3">
    <section class="panel mp-plan">
        <div>
            <div class="nombre">{{ $l->plan?->nombre ?? Licencia::TIPOS[$l->tipo]['texto'] }}</div>
            <div class="precio">
                @if($l->tipo === 'suscripcion'){{ $d2($l->precio) }} {{ $l->periodo === 'anual' ? 'al año' : 'al mes' }}
                @elseif($l->tipo === 'perpetua')Licencia de por vida{{ $l->mantenimiento ? ' · mantenimiento ' . $d2($l->mantenimiento) . ' al año' : '' }}
                @else Cortesía @endif
                · cliente desde el {{ $f($l->inicio) }}
            </div>
        </div>
        @if($l->plan?->descripcion)<div class="secundario">{{ $l->plan->descripcion }}</div>@endif
        <div class="mp-incluye">
            <span><i class="bi bi-people me-1"></i>{{ $usuMax === null ? 'Usuarios sin límite' : $usuMax . ' ' . ($usuMax === 1 ? 'usuario' : 'usuarios') }}</span>
            <span><i class="bi bi-geo-alt me-1"></i>{{ $sucMax }} {{ $sucMax === 1 ? 'sucursal' : 'sucursales' }}</span>
            @foreach($funciones as $fn)<span><i class="bi bi-stars me-1"></i>{{ $fn }}</span>@endforeach
            @foreach($extras as $x)<span><i class="bi bi-plus-circle me-1"></i>{{ $x['cantidad'] > 1 ? $x['cantidad'] . ' × ' : '' }}{{ $x['plan']->nombre }}</span>@endforeach
        </div>
        <div class="mp-uso">
            <div class="fila {{ $usuMax !== null && $uso['usuarios'] >= $usuMax ? 'lleno' : '' }}">
                <div class="k"><span>Usuarios activos</span><b class="num">{{ $uso['usuarios'] }}{{ $usuMax !== null ? ' de ' . $usuMax : '' }}</b></div>
                @if($usuMax !== null)<div class="barra"><span style="width: {{ $pct($uso['usuarios'], $usuMax) }}%"></span></div>@endif
            </div>
            <div class="fila">
                <div class="k"><span>Sucursales</span><b class="num">{{ $uso['sucursales'] }} de {{ $sucMax }}</b></div>
                <div class="barra"><span style="width: {{ $pct($uso['sucursales'], $sucMax) }}%"></span></div>
            </div>
            @if($c->tiene('ia'))
                <div class="fila {{ $uso['iaTope'] && $uso['ia'] >= $uso['iaTope'] ? 'lleno' : '' }}">
                    <div class="k"><span>Lecturas con IA este mes</span><b class="num">{{ $uso['ia'] }} de {{ $uso['iaTope'] }}</b></div>
                    <div class="barra"><span style="width: {{ $pct($uso['ia'], $uso['iaTope']) }}%"></span></div>
                </div>
            @endif
        </div>
        @if(! empty($pago['whatsapp']))
            <div><a href="https://wa.me/{{ preg_replace('/\D/', '', $pago['whatsapp']) }}?text={{ rawurlencode('Hola, quiero ampliar el plan de ' . $c->nombre . '.') }}" target="_blank" rel="noopener" class="btn btn-borde btn-sm"><i class="bi bi-whatsapp me-1"></i> ¿Necesitas más? Escríbenos</a></div>
        @endif
    </section>

    <section class="panel">
        <div class="panel-head"><div><h2>Pagos y recibos</h2><span class="ayuda">{{ $l->pagos->count() }} {{ $l->pagos->count() === 1 ? 'pago' : 'pagos' }}</span></div></div>
        @if($l->pagos->isEmpty() && $l->comprobantes->isEmpty())
            <div class="panel-body secundario">Todavía no hay pagos registrados.</div>
        @else
            <div class="table-responsive">
                <table class="tabla">
                    <tbody>
                    @foreach($pendientes as $cp)
                        <tr>
                            <td class="secundario text-nowrap">{{ $fc($cp->fecha) }}</td>
                            <td><span class="principal">Comprobante enviado</span><div class="secundario">Lo estamos revisando{{ $cp->referencia ? ' · ref. ' . $cp->referencia : '' }}</div></td>
                            <td class="text-end num">{{ $d2($cp->monto) }}</td>
                            <td class="text-end"><span class="estado estado-negociacion">Por confirmar</span></td>
                        </tr>
                    @endforeach
                    @foreach($l->pagos as $p)
                        <tr>
                            <td class="secundario text-nowrap">{{ $fc($p->fecha) }}</td>
                            <td><span class="principal">{{ $p->concepto_texto }}</span>@if($p->cubre_hasta)<div class="secundario">Cubre hasta el {{ $fc($p->cubre_hasta) }}</div>@endif</td>
                            <td class="text-end num">{{ $d2($p->monto) }}</td>
                            <td class="text-end"><a href="{{ route('admin.mi-plan.recibo', $p) }}" target="_blank" class="btn btn-borde btn-sm" data-recargar><i class="bi bi-file-earmark-pdf me-1"></i> Recibo</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>

<div class="d-grid gap-3">
    @if($l->tipo !== 'cortesia')
        <section class="panel mp-proximo">
            <div class="secundario small">{{ $l->tipo === 'perpetua' ? 'Mantenimiento pagado hasta' : 'Tu plan está pagado hasta' }}</div>
            <div class="fecha">{{ $l->vence_el ? $f($l->vence_el) : 'Sin fecha' }}</div>
            @if($frase = \App\Support\Licencias\Estado::frase($e))<div class="aviso-suave m-0">{{ $frase }}</div>@endif
            @if($cobra > 0)<div>Próximo pago: <b class="num">{{ $d2($cobra) }}</b> {{ $l->tipo === 'perpetua' ? 'de mantenimiento anual' : ($l->periodo === 'anual' ? 'por el año' : 'por el mes') }}</div>@endif
            @if(! empty($pago['clabe']) || ! empty($pago['banco']))
                <div class="mp-pago">
                    @if(! empty($pago['banco']))<div>Banco: <b>{{ $pago['banco'] }}</b></div>@endif
                    @if(! empty($pago['titular']))<div>A nombre de: <b>{{ $pago['titular'] }}</b></div>@endif
                    @if(! empty($pago['clabe']))<div>CLABE: <b class="num">{{ $pago['clabe'] }}</b> <button type="button" class="btn btn-fantasma btn-sm py-0" data-copiar="{{ $pago['clabe'] }}" aria-label="Copiar CLABE"><i class="bi bi-clipboard"></i></button></div>@endif
                    @if(! empty($pago['nota']))<div class="secundario small">{{ $pago['nota'] }}</div>@endif
                </div>
            @endif
        </section>

        <section class="panel">
            <div class="panel-head"><div><h2>Ya pagué</h2><span class="ayuda">Sube tu comprobante y lo confirmamos</span></div></div>
            @if($ultimoRechazo && $pendientes->isEmpty() && $ultimoRechazo->updated_at->gt(now()->subDays(15)))
                <div class="panel-body pb-0"><div class="aviso-suave m-0"><i class="bi bi-exclamation-circle"></i><div>Tu último comprobante no se pudo confirmar: {{ $ultimoRechazo->motivo }}</div></div></div>
            @endif
            <form method="post" action="{{ route('admin.mi-plan.comprobante') }}" enctype="multipart/form-data" class="panel-body mp-form">
                @csrf
                <div><label class="form-label small">Monto</label><input type="number" step="0.01" min="1" name="monto" value="{{ old('monto', $cobra ?: '') }}" class="form-control num" required></div>
                <div><label class="form-label small">Fecha del pago</label><input type="date" name="fecha" value="{{ old('fecha', now(config('vandu.zona_horaria'))->toDateString()) }}" class="form-control" required></div>
                <div class="ancho"><label class="form-label small">Comprobante (foto o PDF)</label><input type="file" name="archivo" accept="image/*,application/pdf" class="form-control" required></div>
                <div><label class="form-label small">Referencia (opcional)</label><input name="referencia" value="{{ old('referencia') }}" maxlength="120" class="form-control" placeholder="Folio SPEI"></div>
                <div><label class="form-label small">Nota (opcional)</label><input name="nota" value="{{ old('nota') }}" maxlength="300" class="form-control" placeholder="Pago de noviembre"></div>
                <div class="ancho"><button class="btn btn-primario"><i class="bi bi-send me-1"></i> Enviar comprobante</button></div>
            </form>
        </section>
    @endif
</div>
</div>
@endsection
