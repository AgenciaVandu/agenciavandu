@extends('admin.layout')
@section('titulo', 'Facturación')

@section('contenido')
<div style="max-width: 760px">
    <div class="page-head">
        <div>
            <h1>Facturación</h1>
            <p class="sub">Conecta tu cuenta de Facturapi para generar (timbrar) facturas desde Nerby con un clic. Si prefieres hacerlas en otro sistema, no necesitas esto: solo sube el PDF y el XML en Facturas.</p>
        </div>
    </div>

    <form method="post" action="{{ route('admin.facturacion.guardar') }}">
        @csrf @method('PUT')
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
                <li>Crea tu cuenta en <a href="https://www.facturapi.io" target="_blank" rel="noopener">facturapi.io</a> y sube tu e.firma o CSD y tu logo.</li>
                <li>En su panel, entra a <b>Desarrolladores › API Keys</b> y copia la clave secreta (empieza con <code>sk_live_</code>; con <code>sk_test_</code> haces pruebas sin timbrar de verdad).</li>
                <li>Pégala aquí. Nerby la guarda cifrada y nunca la vuelve a mostrar.</li>
            </ol>
            <label class="form-label" for="key">Clave secreta</label>
            <input id="key" type="password" name="key" class="form-control @error('key') is-invalid @enderror" autocomplete="off" placeholder="{{ $activo ? '•••••••• (escribe una nueva solo para cambiarla)' : 'sk_live_…' }}">
            @error('key')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @if($activo)
                <label class="d-flex gap-2 align-items-center small mt-2"><input type="checkbox" name="quitar" value="1" class="form-check-input mt-0"> Desconectar Facturapi</label>
            @endif
        </section>

        <section class="panel panel-body mt-4">
            <h2 class="fs-6 fw-semibold mb-1">Claves del SAT por defecto</h2>
            <p class="small secundario mb-3">Se usan en los conceptos que no tienen su propia clave. Si no sabes cuál, deja las que vienen.</p>
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
@endsection
