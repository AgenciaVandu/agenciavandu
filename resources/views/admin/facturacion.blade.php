@extends('admin.layout')
@section('titulo', 'Facturación')

@php
    $em = $a['emisor'] ?? [];
    $cert = $a['certificado'] ?? [];
    $tieneSello = ! empty($cert['tiene']);
    $fecha = fn ($f) => $f ? \Illuminate\Support\Carbon::parse($f)->locale('es')->isoFormat('D [de] MMMM [de] YYYY') : null;
    $porVencer = ! empty($cert['vence']) && \Illuminate\Support\Carbon::parse($cert['vence'])->lt(now()->addDays(45));
@endphp

@push('head')
<style>
    .fc { max-width: 780px; }
    .fc .panel + .panel, .fc form + .panel, .fc .panel + form { margin-top: 20px; }
    .fc-paso { display: flex; gap: 12px; align-items: flex-start; margin-bottom: 14px; }
    .fc-paso .n { width: 28px; height: 28px; border-radius: 50%; display: grid; place-items: center; font-weight: 700; font-size: 14px; background: var(--sunken); color: var(--muted); flex: none; }
    .fc-paso .n.ok { background: var(--green-soft); color: var(--green-ink); }
    .fc-paso h2 { font-size: 16px; font-weight: 600; margin: 3px 0 2px; } .fc-paso p { margin: 0; font-size: 13.5px; color: var(--muted); }
    .fc-estado { display: flex; gap: 14px; align-items: center; padding: 16px 20px; }
    .fc-estado .ico { width: 42px; height: 42px; border-radius: 12px; display: grid; place-items: center; font-size: 20px; flex: none; }
    .fc-estado.ok .ico { background: var(--green-soft); color: var(--green-ink); } .fc-estado.pr .ico { background: var(--amber-soft); color: var(--amber); } .fc-estado.no .ico { background: var(--sunken); color: var(--muted); }
    .fc-estado b { display: block; font-size: 15px; } .fc-estado span { font-size: 13.5px; color: var(--muted); }
    .fc-sello { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; background: var(--sunken); border-radius: 12px; padding: 12px 14px; font-size: 13.5px; }
    .fc-sello .k { color: var(--muted); font-size: 12px; display: block; }
    .fc-aviso { background: var(--amber-soft); color: #7A4A00; border-radius: 10px; padding: 10px 12px; font-size: 13px; }
</style>
@endpush

@section('contenido')
<div class="fc">
    <div class="page-head">
        <div>
            <h1>Facturación</h1>
            <p class="sub">{{ $nerby ? 'Factura desde Nerby con un clic: con tus datos fiscales, tu sello digital y tu logo en el PDF.' : 'Conecta tu cuenta de Facturapi para generar (timbrar) facturas desde Nerby con un clic.' }} Si prefieres hacerlas en otro sistema, no necesitas esto: sube el PDF y el XML en Facturas.</p>
        </div>
    </div>

@if($nerby)
    {{-- Estado --}}
    <div class="panel fc-estado {{ $activo && ! $a['pruebas'] ? 'ok' : ($activo ? 'pr' : 'no') }}">
        <div class="ico"><i class="bi {{ $activo && ! $a['pruebas'] ? 'bi-patch-check' : ($activo ? 'bi-cone-striped' : 'bi-receipt') }}"></i></div>
        <div class="flex-grow-1">
            @if($activo && ! $a['pruebas'])
                <b>Lista para facturar</b><span>Tus facturas se timbran ante el SAT con tu sello. Este mes: {{ $timbradas }} {{ $timbradas === 1 ? 'factura' : 'facturas' }}.</span>
            @elseif($activo)
                <b>En modo de pruebas</b><span>Puedes ensayar: las facturas salen con leyenda de prueba y no tienen validez ante el SAT.</span>
            @else
                <b>Aún no facturas desde Nerby</b><span>Son dos pasos: tus datos fiscales y tu sello digital. Toma unos 5 minutos.</span>
            @endif
        </div>
        @if($a['org_id'] && $tieneSello)
            <form method="post" action="{{ route('admin.facturacion.modo') }}">@csrf @method('PUT')
                <input type="hidden" name="pruebas" value="{{ $a['pruebas'] ? 0 : 1 }}">
                <button class="btn btn-borde btn-sm">{{ $a['pruebas'] ? 'Facturar de verdad' : 'Pasar a pruebas' }}</button></form>
        @endif
    </div>

    {{-- Paso 1: datos fiscales --}}
    <form method="post" action="{{ route('admin.facturacion.emisor') }}" class="panel panel-body mt-4" x-data="{ editar: {{ $a['org_id'] && ! $errors->hasAny(['razon_social', 'regimen', 'cp', 'direccion']) ? 'false' : 'true' }} }">
        @csrf
        <div class="fc-paso">
            <span class="n {{ $a['org_id'] ? 'ok' : '' }}">@if($a['org_id'])<i class="bi bi-check-lg"></i>@else 1 @endif</span>
            <div class="flex-grow-1"><h2>Tus datos fiscales</h2><p>Como aparecen en tu Constancia de Situación Fiscal. Súbela y los llenamos.</p></div>
            @if($a['org_id'])<button type="button" class="btn btn-fantasma btn-sm" x-show="! editar" @click="editar = true">Cambiar</button>@endif
        </div>
        @if($a['org_id'])
            <div class="fc-sello" x-show="! editar">
                <div><span class="k">Razón social</span>{{ $em['razon_social'] ?? '—' }}</div>
                <div><span class="k">Régimen</span>{{ ($em['regimen'] ?? '') ? $em['regimen'] . ' · ' . (config('vandu.sat.regimenes.' . $em['regimen']) ?? '') : '—' }}</div>
                <div><span class="k">C.P. fiscal</span><span class="num">{{ $em['cp'] ?? '—' }}</span></div>
            </div>
        @endif
        <div class="row g-3" x-show="editar" @if($a['org_id']) x-cloak @endif>
            <div class="col-12">
                <label class="form-label" for="constancia-emisor">Constancia de Situación Fiscal <span class="secundario fw-normal">(PDF o foto, opcional; no se guarda)</span></label>
                <input type="file" id="constancia-emisor" class="form-control" accept="application/pdf,image/jpeg,image/png"
                       data-leer-constancia='{"razon_social":"e-razon","regimen_fiscal":"e-regimen","cp_fiscal":"e-cp","direccion":"e-direccion"}'>
                <div class="lc-estado" id="constancia-emisor-estado" role="status" aria-live="polite"></div>
            </div>
            <div class="col-md-8">
                <label class="form-label" for="e-razon">Razón social</label>
                <input id="e-razon" name="razon_social" class="form-control text-uppercase @error('razon_social') is-invalid @enderror" value="{{ old('razon_social', $em['razon_social'] ?? '') }}" maxlength="255" placeholder="Sin «S.A. de C.V.»">
                @error('razon_social')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="e-cp">C.P. fiscal</label>
                <input id="e-cp" name="cp" class="form-control num @error('cp') is-invalid @enderror" value="{{ old('cp', $em['cp'] ?? '') }}" maxlength="5" inputmode="numeric" placeholder="97000">
                @error('cp')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label" for="e-regimen">Régimen fiscal</label>
                <select id="e-regimen" name="regimen" class="form-select @error('regimen') is-invalid @enderror">
                    <option value="">Elige…</option>
                    @foreach(config('vandu.sat.regimenes') as $k => $v)<option value="{{ $k }}" @selected(old('regimen', $em['regimen'] ?? '') == $k)>{{ $k }} · {{ $v }}</option>@endforeach
                </select>
                @error('regimen')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label class="form-label" for="e-direccion">Dirección fiscal <span class="secundario fw-normal">(opcional)</span></label>
                <input id="e-direccion" name="direccion" class="form-control" value="{{ old('direccion', $em['direccion'] ?? '') }}" maxlength="300">
            </div>
            <div class="col-12 d-flex gap-2 align-items-center">
                <button class="btn btn-primario"><i class="bi bi-check2 me-1"></i> {{ $a['org_id'] ? 'Guardar cambios' : 'Guardar y seguir' }}</button>
                <span class="secundario small">Tu logo de Mi negocio sale en el PDF de tus facturas.</span>
            </div>
        </div>
    </form>

    {{-- Paso 2: sello digital --}}
    <section class="panel panel-body" x-data="{ subir: {{ ! $tieneSello || $errors->hasAny(['cer', 'key', 'password', 'acepto']) ? 'true' : 'false' }} }">
        <div class="fc-paso">
            <span class="n {{ $tieneSello ? 'ok' : '' }}">@if($tieneSello)<i class="bi bi-check-lg"></i>@else 2 @endif</span>
            <div class="flex-grow-1"><h2>Tu sello digital (CSD)</h2><p>Con él se firman tus facturas. Es distinto a tu e.firma: Nerby nunca te pide la e.firma.</p></div>
            @if($tieneSello)<button type="button" class="btn btn-fantasma btn-sm" x-show="! subir" @click="subir = true">Cambiar sello</button>@endif
        </div>

        @if($tieneSello)
            <div class="fc-sello mb-3">
                <div><span class="k">RFC</span><span class="num">{{ $cert['rfc'] ?? '—' }}</span></div>
                <div><span class="k">Vence</span>{{ $fecha($cert['vence'] ?? null) ?? '—' }}</div>
                <div><span class="k">Número de serie</span><span class="num small">{{ $cert['serie'] ?? '—' }}</span></div>
            </div>
            @if($porVencer)<div class="fc-aviso mb-3"><i class="bi bi-exclamation-triangle me-1"></i> Tu sello vence pronto. Saca uno nuevo en el SAT y súbelo aquí para no dejar de facturar.</div>@endif
        @endif

        @if(! $a['org_id'])
            <p class="secundario small mb-0">Primero guarda tus datos fiscales.</p>
        @else
            <form method="post" action="{{ route('admin.facturacion.sello') }}" enctype="multipart/form-data" class="row g-3" x-show="subir" @if($tieneSello) x-cloak @endif>
                @csrf
                <div class="col-12">
                    <details class="small secundario">
                        <summary class="fw-semibold" style="cursor: pointer">¿No tienes tu sello? Así lo sacas</summary>
                        <ol class="mt-2 mb-0 ps-3">
                            <li>Entra a <b>CertiSAT Web</b> en el portal del SAT con tu e.firma.</li>
                            <li>Elige «Solicitud de Certificados de Sello Digital» y genera tu solicitud con el programa Certifica.</li>
                            <li>Descarga tu certificado (.cer). La llave (.key) y su contraseña son las que creaste en Certifica.</li>
                        </ol>
                        Si te lo da tu contador, pídele los dos archivos y la contraseña del sello (no la de tu e.firma).
                    </details>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="cer">Certificado <span class="secundario fw-normal">(.cer)</span></label>
                    <input type="file" id="cer" name="cer" accept=".cer" class="form-control @error('cer') is-invalid @enderror">
                    @error('cer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="key">Llave privada <span class="secundario fw-normal">(.key)</span></label>
                    <input type="file" id="key" name="key" accept=".key" class="form-control @error('key') is-invalid @enderror">
                    @error('key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password">Contraseña del sello</label>
                    <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="off">
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-check small m-0">
                        <input type="checkbox" name="acepto" value="1" class="form-check-input @error('acepto') is-invalid @enderror">
                        <span class="form-check-label">Autorizo que Nerby envíe mi sello digital a Facturapi, proveedor autorizado de certificación (PAC), para timbrar mis facturas. Nerby no guarda los archivos ni la contraseña.</span>
                    </label>
                    @error('acepto')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-12"><button class="btn btn-primario"><i class="bi bi-shield-lock me-1"></i> Subir sello</button></div>
            </form>
        @endif
    </section>
@endif

    <form method="post" action="{{ route('admin.facturacion.guardar') }}">
        @csrf @method('PUT')
        @unless($nerby)
        <section class="panel panel-body">
            <div class="d-flex align-items-center gap-2 mb-3">
                <h2 class="fs-6 fw-semibold mb-0">Facturapi</h2>
                @if($activo)
                    <span class="badge rounded-pill" style="background: var(--green-soft); color: var(--green-ink)"><i class="bi bi-check2"></i> Conectado{{ str_starts_with((string) $a['key'], 'sk_test_') ? ' · modo prueba' : '' }}</span>
                @else
                    <span class="badge rounded-pill text-bg-light">Sin conectar</span>
                @endif
            </div>
            <ol class="small secundario ps-3 mb-3">
                <li>Crea tu cuenta en <a href="https://www.facturapi.io" target="_blank" rel="noopener">facturapi.io</a> y sube tu sello digital (CSD) y tu logo. Nunca subas tu e.firma: para facturar no se necesita.</li>
                <li>En su panel, entra a <b>Desarrolladores › API Keys</b> y copia la clave secreta (empieza con <code>sk_live_</code>; con <code>sk_test_</code> haces pruebas sin timbrar de verdad).</li>
                <li>Pégala aquí. Nerby la guarda cifrada y nunca la vuelve a mostrar.</li>
            </ol>
            <label class="form-label" for="key-propia">Clave secreta</label>
            <input id="key-propia" type="password" name="key" class="form-control @error('key') is-invalid @enderror" autocomplete="off" placeholder="{{ $activo ? '•••••••• (escribe una nueva solo para cambiarla)' : 'sk_live_…' }}">
            @error('key')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @if($activo)
                <label class="d-flex gap-2 align-items-center small mt-2"><input type="checkbox" name="quitar" value="1" class="form-check-input mt-0"> Desconectar Facturapi</label>
            @endif
        </section>
        @endunless

        <section class="panel panel-body {{ $nerby ? 'mt-4' : 'mt-4' }}">
            <h2 class="fs-6 fw-semibold mb-1">Claves del SAT por defecto</h2>
            <p class="small secundario mb-3">Se usan en los conceptos de tus facturas. Si no sabes cuál, deja las que vienen o pregúntale a tu contador.</p>
            <div class="row g-3">
                <div class="col-sm-4"><label class="form-label" for="clave_prod">Producto o servicio</label>
                    <input id="clave_prod" name="clave_prod" value="{{ old('clave_prod', $a['clave_prod']) }}" class="form-control num @error('clave_prod') is-invalid @enderror" maxlength="8" inputmode="numeric">
                    @error('clave_prod')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-sm-4"><label class="form-label" for="clave_unidad">Unidad (productos)</label>
                    <input id="clave_unidad" name="clave_unidad" value="{{ old('clave_unidad', $a['clave_unidad']) }}" class="form-control" maxlength="3"><div class="form-text">H87 = pieza</div></div>
                <div class="col-sm-4"><label class="form-label" for="clave_servicio">Unidad (servicios)</label>
                    <input id="clave_servicio" name="clave_servicio" value="{{ old('clave_servicio', $a['clave_servicio']) }}" class="form-control" maxlength="3"><div class="form-text">E48 = unidad de servicio</div></div>
                <div class="col-sm-4"><label class="form-label" for="serie">Serie <span class="secundario">(opcional)</span></label>
                    <input id="serie" name="serie" value="{{ old('serie', $a['serie']) }}" class="form-control" maxlength="10" placeholder="A"></div>
            </div>
        </section>

        <div class="d-flex gap-2 mt-4">
            <button class="btn btn-primario"><i class="bi bi-check2 me-1"></i> Guardar</button>
            <a href="{{ route('admin.facturas') }}" class="btn btn-borde">Ir a Facturas</a>
        </div>
    </form>
</div>
@include('admin._leer-constancia')
@endsection
