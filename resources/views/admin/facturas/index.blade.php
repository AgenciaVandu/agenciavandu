@extends('admin.layout')
@section('titulo', 'Facturas')

@php
    $d = fn ($n) => '$' . number_format((float) $n, 2);
    $vistas = \App\Http\Controllers\Admin\FacturasController::VISTAS;
    $formas = \App\Support\Facturas\Timbrado::FORMAS_SAT;
    $usos = config('vandu.sat.usos_cfdi', []);
@endphp

@push('head')
<style>
    .fx > * + * { border-top: 1px solid #F0F1F4; }
    .fx-fila { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 14px; align-items: start; padding: 14px 20px; }
    .fx-fila .t { font-weight: 600; color: inherit; text-decoration: none; } .fx-fila .t:hover { text-decoration: underline; }
    .fx-fila .s { font-size: 13px; color: var(--muted); margin-top: 2px; display: flex; flex-wrap: wrap; gap: 4px 10px; align-items: center; }
    .fx-fila .m { font-weight: 700; white-space: nowrap; text-align: right; }
    .fx-acc { grid-column: 2 / -1; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .fx-acc form { display: contents; }
    .fx-b { font-size: 12px; font-weight: 600; padding: 2px 9px; border-radius: 99px; background: var(--sunken); color: var(--muted); white-space: nowrap; }
    .fx-b.pide { background: var(--marca-soft); color: var(--marca-2); } .fx-b.ok { background: var(--green-soft); color: var(--green-ink); } .fx-b.mal { background: var(--amber-soft); color: var(--amber); }
    .fx-arch { display: inline-flex; gap: 6px; align-items: center; font-size: 13px; padding: 4px 10px; border: 1px solid var(--line); border-radius: 8px; color: inherit; text-decoration: none; background: var(--surface); }
    .fx-arch:hover { background: #F8F9FB; }
    .fx-aviso { grid-column: 2 / -1; background: var(--amber-soft); color: #7A4A00; border-radius: 10px; padding: 8px 12px; font-size: 13px; }
    .fx-timbrar { grid-column: 2 / -1; display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; align-items: end; background: #F8F9FB; border: 1px solid var(--line); border-radius: 12px; padding: 12px; }
    .fx-barra { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; padding: 12px 20px; background: #F8F9FB; border-bottom: 1px solid var(--line); }
    .fx-subir { position: relative; overflow: hidden; }
    .fx-subir input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    @media (max-width: 575.98px) { .fx-fila { grid-template-columns: auto minmax(0, 1fr); } .fx-fila .m { grid-column: 2; text-align: left; } }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div><h1>Facturas</h1><p class="sub">Sube o genera la factura de cada venta y mándala al cliente por correo o WhatsApp sin buscarla.</p></div>
    @if(auth()->user()->puede('configuracion'))
        <a href="{{ route('admin.facturacion') }}" class="btn btn-borde"><i class="bi bi-gear me-1"></i> {{ $timbrar ? 'Facturación' : 'Generar facturas aquí' }}</a>
    @endif
</div>

<div class="mb-3">
    <nav class="segmento" aria-label="Facturas">
        @foreach($vistas as $k => $t)
            <a href="{{ route('admin.facturas', ['ver' => $k]) }}" class="{{ $ver === $k ? 'activo' : '' }}" @if($ver === $k) aria-current="page" @endif>{{ $t }}@isset($n[$k])<span class="n">{{ $n[$k] }}</span>@endisset</a>
        @endforeach
    </nav>
</div>

<div class="panel fx" x-data="{ marcadas: [] }">
    @if($ver === 'por_enviar' && $docs->isNotEmpty())
        <form method="post" action="{{ route('admin.facturas.enviar-varias') }}" id="varias" class="fx-barra">
            @csrf
            <label class="d-flex align-items-center gap-2 small mb-0">
                <input type="checkbox" class="form-check-input mt-0" @change="marcadas = $event.target.checked ? {{ Js::from($docs->filter(fn ($x) => $x->correo())->map->clave()->values()) }} : []">
                Elegir todas las que tienen correo
            </label>
            <button class="btn btn-primario btn-sm" :disabled="! marcadas.length" onclick="return confirm('Se enviará cada factura a su cliente por correo. ¿Continuar?')">
                <i class="bi bi-send me-1"></i> Enviar <span x-text="marcadas.length || ''"></span> por correo
            </button>
        </form>
    @endif

    @forelse($docs as $doc)
        @php
            $archivos = $doc->archivos();
            $correo = $doc->correo();
            $dif = $doc->diferencias();
            $fis = $doc->fiscales();
        @endphp
        <div class="fx-fila" x-data="{ generar: false, cancelar: false }">
            <div>
                @if($ver === 'por_enviar')
                    <input type="checkbox" class="form-check-input" name="docs[]" value="{{ $doc->clave() }}" form="varias" x-model="marcadas" @disabled(! $correo) aria-label="Elegir {{ $doc->folio() }}">
                @else
                    <i class="bi {{ $doc->tipo() === 'venta' ? 'bi-bag' : 'bi-file-earmark-text' }} secundario"></i>
                @endif
            </div>
            <div>
                <a href="{{ $doc->url() }}" class="t">{{ $doc->quien() }}</a>
                <div class="s">
                    <span>{{ $doc->folio() }}</span>
                    @if($doc->fecha())<span>{{ $doc->fecha()->locale('es')->isoFormat('D MMM YYYY') }}</span>@endif
                    @if($fis['rfc'])<span>RFC {{ mb_strtoupper($fis['rfc']) }}</span>@endif
                    @if($doc->solicitada() && $ver === 'por_facturar')<span class="fx-b pide"><i class="bi bi-hand-index"></i> La pidió el cliente</span>@endif
                    @if($ver === 'por_facturar' && ! $doc->fiscalesCompletos())<span class="fx-b mal">Faltan datos fiscales</span>@endif
                    @if($ver === 'enviadas' && $doc->enviada())<span class="fx-b ok"><i class="bi bi-check2"></i> Enviada el {{ $doc->enviada()->locale('es')->isoFormat('D MMM') }}</span>@endif
                    @if($ver !== 'por_facturar' && ! $correo)<span class="fx-b mal">Sin correo</span>@endif
                </div>
            </div>
            <div class="m num">{{ $d($doc->total()) }}</div>

            @if($dif)<div class="fx-aviso"><i class="bi bi-exclamation-triangle me-1"></i> {{ implode('. ', $dif) }}.</div>@endif

            <div class="fx-acc">
                @foreach($archivos as $f)
                    <a href="{{ $doc->enlaceArchivo($f) }}" target="_blank" class="fx-arch"><i class="bi {{ $f->icono }}"></i> {{ strtoupper($f->ext) }}</a>
                @endforeach

                @if($ver === 'por_facturar')
                    <form method="post" action="{{ route('admin.facturas.subir', [$doc->tipo(), $doc->m->id]) }}" enctype="multipart/form-data">
                        @csrf
                        <label class="btn btn-borde btn-sm fx-subir mb-0"><i class="bi bi-upload me-1"></i> Subir PDF y XML
                            <input type="file" name="facturas[]" multiple accept=".pdf,.xml,application/pdf,text/xml" onchange="this.form.submit()">
                        </label>
                    </form>
                    @if($timbrar)
                        @if($doc->fiscalesCompletos())
                            <button type="button" class="btn btn-primario btn-sm" @click="generar = ! generar"><i class="bi bi-receipt me-1"></i> Generar factura</button>
                        @else
                            <a href="{{ $doc->cliente() ? route('admin.clientes.edit', $doc->cliente()) : $doc->url() }}" class="btn btn-borde btn-sm">Completar datos fiscales</a>
                        @endif
                    @endif
                @else
                    @if($correo)
                        <form method="post" action="{{ route('admin.facturas.enviar', [$doc->tipo(), $doc->m->id]) }}">
                            @csrf
                            <button class="btn {{ $ver === 'por_enviar' ? 'btn-primario' : 'btn-borde' }} btn-sm"><i class="bi bi-envelope me-1"></i> {{ $ver === 'enviadas' ? 'Reenviar' : 'Enviar' }} a {{ $correo }}</button>
                        </form>
                    @else
                        <form method="post" action="{{ route('admin.facturas.enviar', [$doc->tipo(), $doc->m->id]) }}" class="d-flex gap-2">
                            @csrf
                            <input type="email" name="para" class="form-control form-control-sm" style="max-width: 230px" placeholder="correo@cliente.com" required>
                            <button class="btn btn-primario btn-sm"><i class="bi bi-envelope me-1"></i> Enviar</button>
                        </form>
                    @endif
                    @if($doc->whatsapp())
                        <a href="{{ route('admin.facturas.whatsapp', [$doc->tipo(), $doc->m->id]) }}" target="_blank" rel="noopener" class="btn btn-borde btn-sm"><i class="bi bi-whatsapp me-1"></i> WhatsApp</a>
                    @endif
                    @if($doc->facturapiId())
                        @if($doc->cancelacion() === 'pendiente')
                            <span class="fx-b mal">Cancelación esperando al cliente</span>
                            <form method="post" action="{{ route('admin.facturas.revisar', [$doc->tipo(), $doc->m->id]) }}">@csrf<button class="btn btn-fantasma btn-sm">Revisar</button></form>
                        @else
                            <button type="button" class="btn btn-fantasma btn-sm text-danger" @click="cancelar = ! cancelar">Cancelar factura</button>
                        @endif
                    @endif
                @endif
            </div>

            @if($ver !== 'por_facturar' && $doc->facturapiId() && $doc->cancelacion() !== 'pendiente')
                <form method="post" action="{{ route('admin.facturas.cancelar', [$doc->tipo(), $doc->m->id]) }}" class="fx-timbrar" x-show="cancelar" x-cloak
                      onsubmit="return confirm('Se cancelará la factura ante el SAT. Según el monto y el tiempo que tiene, el SAT puede pedir que el cliente la acepte. ¿Continuar?')">
                    @csrf
                    <div style="grid-column: span 2"><label class="form-label small mb-1">Motivo</label>
                        <select name="motivo" class="form-select form-select-sm">@foreach(\App\Support\Facturas\Timbrado::MOTIVOS as $k => $t)<option value="{{ $k }}">{{ $k }} · {{ $t }}</option>@endforeach</select></div>
                    <div><button class="btn btn-sm w-100" style="background: var(--red); color: #fff">Cancelar ante el SAT</button></div>
                </form>
            @endif

            @if($ver === 'por_facturar' && $timbrar && $doc->fiscalesCompletos())
                <form method="post" action="{{ route('admin.facturas.timbrar', [$doc->tipo(), $doc->m->id]) }}" class="fx-timbrar" x-show="generar" x-cloak>
                    @csrf
                    <div><label class="form-label small mb-1">Forma de pago</label>
                        <select name="forma_pago" class="form-select form-select-sm">@foreach($formas as $k => $t)<option value="{{ $k }}" @selected($k === $doc->formaSat())>{{ $k }} · {{ $t }}</option>@endforeach</select></div>
                    <div><label class="form-label small mb-1">Método</label>
                        <select name="metodo_pago" class="form-select form-select-sm"><option value="PUE">PUE · ya pagó</option><option value="PPD" @selected(($doc->m->saldo ?? 0) > 0.5)>PPD · pagará después</option></select></div>
                    <div><label class="form-label small mb-1">Uso</label>
                        <select name="uso_cfdi" class="form-select form-select-sm">@foreach($usos as $k => $t)<option value="{{ $k }}" @selected($k === $fis['uso'])>{{ $k }} · {{ $t }}</option>@endforeach</select></div>
                    <div>
                        <label class="d-flex gap-2 align-items-center small mb-2"><input type="checkbox" name="enviar" value="1" class="form-check-input mt-0" @checked((bool) $correo) @disabled(! $correo)> Enviarla al cliente</label>
                        <button class="btn btn-primario btn-sm w-100" onclick="return confirm({{ Js::from('Se timbrará la factura de ' . $d($doc->total()) . ' para ' . $fis['razon_social'] . ' (' . mb_strtoupper($fis['rfc']) . '). Ya no se puede deshacer, solo cancelar ante el SAT.') }})">Timbrar factura</button>
                    </div>
                </form>
            @endif
        </div>
    @empty
        <div class="vacio">
            <div class="ico"><i class="bi bi-{{ $ver === 'enviadas' ? 'send' : 'check2-circle' }}"></i></div>
            @if($ver === 'por_facturar')
                <h3>No hay nada por facturar</h3><p>Aquí aparecen las cotizaciones aceptadas y las ventas de clientes con RFC o que pidieron factura desde su enlace.</p>
            @elseif($ver === 'por_enviar')
                <h3>Todo enviado</h3><p>Cuando subas o generes una factura, aparece aquí lista para mandarla.</p>
            @else
                <h3>Aún no envías facturas</h3><p>Las facturas que mandes desde Nerby se guardan aquí.</p>
            @endif
        </div>
    @endforelse
</div>
@endsection
