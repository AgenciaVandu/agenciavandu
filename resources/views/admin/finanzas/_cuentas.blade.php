@php
    use App\Models\CuentaDinero as CD;
    $dn = $dinero;
    $d2 = fn ($n) => ($n < 0 ? '−' : '') . '$' . number_format(abs($n ?? 0), 2);
    $hoyF = now(config('vandu.zona_horaria'))->toDateString();
    $inicioMes = now(config('vandu.zona_horaria'))->startOfMonth()->toDateString();
    $nombres = $dn['todas']->pluck('nombre', 'id');
    $claseTxt = ['cobro' => 'Cobro', 'venta' => 'Venta', 'gasto' => 'Gasto', 'compra' => 'Proveedor', 'licencia' => 'Licencia', 'mov' => 'Movimiento'];
@endphp

@push('head')
<style>
    .cd-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 14px; margin-bottom: 14px; }
    .cd { padding: 16px 18px; display: grid; gap: 6px; text-decoration: none; color: inherit; }
    .cd.sel { outline: 2px solid var(--ink); }
    .cd .k { display: flex; align-items: center; gap: 8px; font-size: 14px; color: var(--text-2); font-weight: 500; }
    .cd .k i { font-size: 17px; color: var(--muted); }
    .cd .v { font-size: 26px; font-weight: 600; letter-spacing: -.02em; }
    .cd .v.neg { color: var(--red); }
    .cd .s { font-size: 12.5px; color: var(--muted); }
    .cd .conc { font-size: 12.5px; display: inline-flex; gap: 5px; align-items: center; }
    .cd .conc.ok { color: var(--green-ink); } .cd .conc.mal { color: var(--amber); }
    .cd .acciones { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 4px; }
    .cd-total { background: var(--ink); color: #fff; border-color: var(--ink); }
    .cd-total .k, .cd-total .s, .cd-total .k i { color: #A9AEBA; }
    .caja-form { padding: 18px 20px; margin-bottom: 14px; }
    .caja-form h3 { font-size: 15.5px; font-weight: 600; margin: 0 0 12px; }
    .caja-form .rejilla-f { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; align-items: end; }
    .formas-chk { display: flex; flex-wrap: wrap; gap: 6px 14px; font-size: 14px; }
    .monto-mas { color: var(--green-ink); } .monto-menos { color: var(--text); }
    .mini-sel { font-size: 13px; padding: 3px 26px 3px 8px; min-width: 120px; }
    .vacio-cd { padding: 26px 24px; display: grid; gap: 10px; justify-items: start; }
    .vacio-cd ul { margin: 0; padding-left: 18px; color: var(--text-2); font-size: 14.5px; }
</style>
@endpush

<div x-data="{ forma: null, conc: null, edit: null }">

@if($dn['todas']->isEmpty())
    <section class="panel">
        <div class="vacio-cd">
            <h2 class="fs-5 m-0">¿Dónde está tu dinero?</h2>
            <p class="m-0 secundario">Da de alta tu caja, tu banco y tu terminal. Desde ese momento:</p>
            <ul>
                <li>Cada cobro, venta, gasto y pago a proveedor cae solo en su cuenta según la forma de pago.</li>
                <li>Ves cuánto debería haber en cada una, y lo comparas con tu estado de cuenta o con lo que cuentas en caja.</li>
                <li>Registras cuando depositas la caja al banco o cuando retiras dinero para ti.</li>
            </ul>
            <form method="post" action="{{ route('admin.finanzas.cuentas.basicas') }}" class="d-flex flex-wrap gap-2 align-items-end mt-2">
                @csrf
                <div><label class="form-label small mb-1" for="cd-desde">Desde cuándo llevar la cuenta</label>
                    <input type="date" id="cd-desde" name="saldo_desde" value="{{ $inicioMes }}" max="{{ $hoyF }}" class="form-control"></div>
                <button class="btn btn-primario"><i class="bi bi-magic me-1"></i> Crear Caja, Banco y Terminal</button>
                <button type="button" class="btn btn-borde" @click="forma = 'nueva'">Prefiero crearlas una por una</button>
            </form>
        </div>
    </section>
@else
    <div class="cd-grid">
        <a href="{{ $q(['cuenta' => null]) }}" class="panel cd cd-total {{ ! $dn['sel'] ? 'sel' : '' }}">
            <span class="k"><i class="bi bi-safe2"></i> Dinero disponible</span>
            <span class="v num">{{ $d2($dn['total']) }}</span>
            <span class="s">Suma de {{ $dn['saldos']->count() }} {{ $dn['saldos']->count() === 1 ? 'cuenta' : 'cuentas' }} · hoy</span>
            @if($dn['sinCuenta']->isNotEmpty())<span class="s" style="color: #FFC78A">No incluye {{ $dn['sinCuenta']->count() }} {{ $dn['sinCuenta']->count() === 1 ? 'movimiento' : 'movimientos' }} sin cuenta</span>@endif
        </a>
        @foreach($dn['saldos'] as $id => $x)
            @php $c = $x['cuenta']; $u = $c->conciliaciones->first(); @endphp
            <div class="panel cd {{ $dn['sel'] === $id ? 'sel' : '' }}">
                <a href="{{ $q(['cuenta' => $dn['sel'] === $id ? null : $id]) }}" class="text-reset text-decoration-none d-grid gap-1">
                    <span class="k"><i class="bi {{ $c->icono }}"></i> {{ $c->nombre }}@if($c->banco) <span class="secundario fw-normal">· {{ $c->banco }}</span>@endif</span>
                    <span class="v num {{ $x['saldo'] < 0 ? 'neg' : '' }}">{{ $d2($x['saldo']) }}</span>
                    <span class="s">{{ collect((array) $c->formas)->map(fn ($f) => CD::FORMAS[$f] ?? $f)->join(', ') ?: 'Sin forma de pago asignada' }}</span>
                </a>
                @if($u)
                    <span class="conc {{ abs($u->diferencia) < 0.01 || $u->ajustada ? 'ok' : 'mal' }}">
                        <i class="bi {{ abs($u->diferencia) < 0.01 || $u->ajustada ? 'bi-check-circle' : 'bi-exclamation-circle' }}"></i>
                        Conciliada el {{ $f($u->fecha->toDateString()) }}{{ abs($u->diferencia) < 0.01 ? ' · cuadró' : ($u->ajustada ? ' · con ajuste' : ' · diferencia ' . $d2($u->diferencia)) }}
                    </span>
                @else
                    <span class="conc secundario"><i class="bi bi-circle"></i> Sin conciliar</span>
                @endif
                <div class="acciones">
                    <button type="button" class="btn btn-borde btn-sm" @click="conc = conc === {{ $id }} ? null : {{ $id }}; forma = null; edit = null"><i class="bi bi-check2-square me-1"></i> Conciliar</button>
                    <button type="button" class="btn btn-fantasma btn-sm" @click="edit = edit === {{ $id }} ? null : {{ $id }}; forma = null; conc = null">Editar</button>
                </div>
            </div>
        @endforeach
    </div>

    @php $apagadas = $dn['todas']->reject->activa; @endphp
    @if($apagadas->isNotEmpty())
        <p class="secundario small mb-3">Apagadas: @foreach($apagadas as $c)<button type="button" class="btn btn-link btn-sm p-0 align-baseline" @click="edit = {{ $c->id }}; forma = null; conc = null">{{ $c->nombre }}</button>{{ $loop->last ? '' : ', ' }}@endforeach</p>
    @endif

    <div class="d-flex flex-wrap gap-2 mb-3">
        <button type="button" class="btn btn-borde" @click="forma = forma === 'traspaso' ? null : 'traspaso'; conc = null; edit = null"><i class="bi bi-arrow-left-right me-1"></i> Traspaso entre cuentas</button>
        <button type="button" class="btn btn-borde" @click="forma = forma === 'retiro' ? null : 'retiro'; conc = null; edit = null"><i class="bi bi-box-arrow-up-right me-1"></i> Retiro del dueño</button>
        <button type="button" class="btn btn-borde" @click="forma = forma === 'aportacion' ? null : 'aportacion'; conc = null; edit = null"><i class="bi bi-box-arrow-in-down-right me-1"></i> Aportación</button>
        <button type="button" class="btn btn-fantasma" @click="forma = forma === 'nueva' ? null : 'nueva'; conc = null; edit = null"><i class="bi bi-plus-lg me-1"></i> Nueva cuenta</button>
    </div>

    {{-- Conciliar --}}
    @foreach($dn['saldos'] as $id => $x)
        <div x-show="conc === {{ $id }}" x-cloak>
            <form method="post" action="{{ route('admin.finanzas.cuentas.conciliar', $id) }}" class="panel caja-form">
                @csrf
                <h3>Conciliar {{ $x['cuenta']->nombre }}</h3>
                <p class="secundario small mb-3">{{ $x['cuenta']->tipo === 'efectivo' ? 'Cuenta el dinero de la caja' : 'Abre tu banca en línea o el estado de cuenta' }} y escribe el saldo real a esa fecha. El panel te dice si cuadra.</p>
                <div class="rejilla-f">
                    <div><label class="form-label small">Fecha</label><input type="date" name="fecha" value="{{ $hoyF }}" max="{{ $hoyF }}" min="{{ $x['cuenta']->saldo_desde->toDateString() }}" class="form-control" required></div>
                    <div><label class="form-label small">Saldo real</label><input type="number" step="0.01" name="saldo_real" class="form-control" required placeholder="{{ number_format($x['saldo'], 2, '.', '') }}"></div>
                    <div><label class="form-label small">Nota (opcional)</label><input name="nota" maxlength="200" class="form-control" placeholder="Estado de cuenta octubre"></div>
                </div>
                <label class="form-check small mt-3"><input type="checkbox" name="ajustar" value="1" class="form-check-input"> <span class="form-check-label">Si no cuadra, registrar la diferencia como ajuste (para que el panel quede igual que el banco)</span></label>
                <div class="mt-3 d-flex gap-2"><button class="btn btn-primario">Comparar</button><button type="button" class="btn btn-fantasma" @click="conc = null">Cancelar</button></div>
            </form>
        </div>
    @endforeach

    {{-- Traspaso, retiro, aportación --}}
    <div x-show="['traspaso', 'retiro', 'aportacion'].includes(forma)" x-cloak>
        <form method="post" action="{{ route('admin.finanzas.movimientos.store') }}" class="panel caja-form">
            @csrf
            <input type="hidden" name="tipo" :value="forma">
            <h3 x-text="{ traspaso: 'Traspaso entre cuentas', retiro: 'Retiro del dueño', aportacion: 'Aportación del dueño' }[forma]"></h3>
            <p class="secundario small mb-3" x-text="{ traspaso: 'Por ejemplo, cuando depositas el efectivo de la caja al banco o el banco te abona lo de la terminal.', retiro: 'Dinero que sacas del negocio para ti. No es un gasto del negocio.', aportacion: 'Dinero que metes al negocio de tu bolsa.' }[forma]"></p>
            <div class="rejilla-f">
                <div x-show="forma !== 'aportacion'"><label class="form-label small">Sale de</label>
                    <select name="de_id" class="form-select" :disabled="forma === 'aportacion'">@foreach($dn['saldos'] as $id => $x)<option value="{{ $id }}">{{ $x['cuenta']->nombre }}</option>@endforeach</select></div>
                <div x-show="forma !== 'retiro'"><label class="form-label small">Entra a</label>
                    <select name="a_id" class="form-select" :disabled="forma === 'retiro'">@foreach($dn['saldos'] as $id => $x)<option value="{{ $id }}" @selected($loop->index === 1)>{{ $x['cuenta']->nombre }}</option>@endforeach</select></div>
                <div><label class="form-label small">Monto</label><input type="number" step="0.01" min="0.01" name="monto" class="form-control" required></div>
                <div><label class="form-label small">Fecha</label><input type="date" name="fecha" value="{{ $hoyF }}" max="{{ $hoyF }}" class="form-control" required></div>
                <div><label class="form-label small">Nota (opcional)</label><input name="nota" maxlength="200" class="form-control"></div>
            </div>
            <div class="mt-3 d-flex gap-2"><button class="btn btn-primario">Registrar</button><button type="button" class="btn btn-fantasma" @click="forma = null">Cancelar</button></div>
        </form>
    </div>

    {{-- Editar --}}
    @foreach($dn['todas'] as $c)
        <div x-show="edit === {{ $c->id }}" x-cloak>
            @include('admin.finanzas._cuenta-form', ['c' => $c])
        </div>
    @endforeach
@endif

{{-- Nueva --}}
<div x-show="forma === 'nueva'" x-cloak>
    @include('admin.finanzas._cuenta-form', ['c' => null])
</div>

@if($dn['sinCuenta']->isNotEmpty())
    <section class="panel" style="margin-bottom: 14px">
        <div class="panel-head"><div><h2>Sin cuenta</h2><span class="ayuda">{{ $dn['sinCuenta']->count() }} {{ $dn['sinCuenta']->count() === 1 ? 'movimiento no tiene' : 'movimientos no tienen' }} forma de pago, o su forma no está en ninguna cuenta. Elige dónde pasó.</span></div></div>
        <div class="table-responsive">
            <table class="tabla">
                <tbody>
                @foreach($dn['sinCuenta']->take(15) as $m)
                    <tr>
                        <td class="secundario text-nowrap" style="width: 120px">{{ $f($m->fecha) }}</td>
                        <td><span class="principal">@if($m->url)<a href="{{ $m->url }}" class="text-reset">{{ $m->concepto }}</a>@else{{ $m->concepto }}@endif</span><div class="secundario">{{ $claseTxt[$m->clase] ?? '' }}{{ $m->detalle ? ' · ' . $m->detalle : '' }}</div></td>
                        <td class="text-end num {{ $m->monto > 0 ? 'monto-mas' : 'monto-menos' }}">{{ $m->monto > 0 ? '+' : '' }}{{ $d2($m->monto) }}</td>
                        <td class="text-end" style="width: 170px">
                            <form method="post" action="{{ route('admin.finanzas.asignar') }}">@csrf
                                <input type="hidden" name="clase" value="{{ $m->clase }}"><input type="hidden" name="id" value="{{ $m->id }}">
                                <select name="cuenta_dinero_id" class="form-select mini-sel" onchange="this.form.submit()" aria-label="Cuenta">
                                    <option value="">Elegir cuenta…</option>
                                    @foreach($dn['saldos'] as $id => $x)<option value="{{ $id }}">{{ $x['cuenta']->nombre }}</option>@endforeach
                                </select>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

@if($dn['todas']->isNotEmpty())
    <section class="panel">
        <div class="panel-head"><div><h2>Movimientos{{ $dn['sel'] ? ' de ' . $nombres[$dn['sel']] : '' }}</h2>
            <span class="ayuda">{{ \App\Http\Controllers\Admin\FinanzasController::PERIODOS[$periodo] }} · {{ $dn['nMovs'] }} {{ $dn['nMovs'] === 1 ? 'movimiento' : 'movimientos' }}{{ $dn['nMovs'] > 80 ? ' (se muestran los 80 más recientes)' : '' }}</span></div></div>
        @if($dn['movs']->isEmpty())
            <div class="panel-body secundario">Sin movimientos en este periodo.</div>
        @else
            <div class="table-responsive">
                <table class="tabla">
                    <thead><tr><th>Fecha</th><th>Concepto</th><th>Cuenta</th><th class="text-end">Monto</th></tr></thead>
                    <tbody>
                    @foreach($dn['movs'] as $m)
                        <tr>
                            <td class="secundario text-nowrap" style="width: 120px">{{ $f($m->fecha) }}</td>
                            <td><span class="principal">@if($m->url)<a href="{{ $m->url }}" class="text-reset">{{ $m->concepto }}</a>@else{{ $m->concepto }}@endif</span><div class="secundario">{{ $claseTxt[$m->clase] ?? '' }}{{ $m->detalle ? ' · ' . $m->detalle : '' }}</div></td>
                            <td style="width: 170px">
                                @if($m->clase === 'mov')
                                    <span class="secundario">{{ $nombres[$m->cuenta] ?? '—' }}</span>
                                    <form method="post" action="{{ route('admin.finanzas.movimientos.destroy', $m->id) }}" class="d-inline" onsubmit="return confirm('¿Borrar este movimiento?')">@csrf @method('DELETE')
                                        <button class="btn btn-fantasma btn-sm py-0 px-1" title="Borrar movimiento" aria-label="Borrar movimiento"><i class="bi bi-trash3"></i></button></form>
                                @else
                                    <form method="post" action="{{ route('admin.finanzas.asignar') }}">@csrf
                                        <input type="hidden" name="clase" value="{{ $m->clase }}"><input type="hidden" name="id" value="{{ $m->id }}">
                                        <select name="cuenta_dinero_id" class="form-select mini-sel" onchange="this.form.submit()" aria-label="Cuenta">
                                            <option value="">Sin cuenta</option>
                                            @foreach($dn['saldos'] as $id => $x)<option value="{{ $id }}" @selected($m->cuenta === $id)>{{ $x['cuenta']->nombre }}</option>@endforeach
                                        </select>
                                    </form>
                                @endif
                            </td>
                            <td class="text-end num {{ $m->monto > 0 ? 'monto-mas' : 'monto-menos' }}">{{ $m->monto > 0 ? '+' : '' }}{{ $d2($m->monto) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endif
</div>
