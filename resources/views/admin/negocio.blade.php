@extends('admin.layout')
@section('titulo', 'Mi negocio')

@php
    $val = fn ($k, $def = '') => old(str_replace(['[', ']'], ['.', ''], $k), data_get($a, str_replace(['[', ']'], ['.', ''], $k)) ?? $def);
    $logo = $c->logo(true);
@endphp

@push('head')
<style>
    .ng-grid { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(0, 1fr); gap: 20px; align-items: start; }
    .ng-campos { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .ng-campos .ancho { grid-column: 1 / -1; }
    .ng-logo { background: var(--ink); border-radius: 12px; padding: 18px; display: flex; align-items: center; justify-content: center; min-height: 92px; color: #fff; font-weight: 650; font-size: 20px; }
    .ng-logo img { max-height: 56px; max-width: 220px; object-fit: contain; }
    .ng-previa { border: 1px solid var(--line); border-radius: 12px; overflow: hidden; font-size: 13px; }
    .ng-previa .top { background: var(--ink); color: #fff; padding: 14px 16px; display: flex; justify-content: space-between; align-items: center; gap: 10px; }
    .ng-previa .top img { max-height: 30px; max-width: 140px; }
    .ng-previa .cuerpo { padding: 14px 16px; display: grid; gap: 4px; color: var(--text-2); }
    @media (max-width: 991.98px) { .ng-grid { grid-template-columns: minmax(0, 1fr); } }
    @media (max-width: 575.98px) { .ng-campos { grid-template-columns: minmax(0, 1fr); } }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <h1>Mi negocio</h1>
        <p class="sub">Tu nombre, logo y datos de contacto y pago. Así aparecen en tus cotizaciones, en la página de cada cliente y en tus correos.</p>
    </div>
</div>

<form method="post" action="{{ route('admin.negocio.update') }}" enctype="multipart/form-data" class="ng-grid">
    @csrf
    <div class="d-grid gap-4">
        <section class="panel">
            <div class="panel-head"><h2>Marca</h2></div>
            <div class="panel-body ng-campos">
                <div class="ancho"><label class="form-label" for="ng-nombre">Nombre del negocio</label><input id="ng-nombre" name="nombre" class="form-control" value="{{ old('nombre', $c->nombre) }}" required maxlength="120"></div>
                <div><label class="form-label" for="ng-ciudad">Ciudad</label><input id="ng-ciudad" name="marca[ciudad]" class="form-control" value="{{ $val('marca[ciudad]', $v['marca']['ciudad'] ?? '') }}" maxlength="80" placeholder="Mérida, Yucatán"></div>
                <div><label class="form-label" for="ng-sitio">Sitio web</label><input id="ng-sitio" name="marca[sitio]" class="form-control" value="{{ $val('marca[sitio]', $v['marca']['sitio'] ?? '') }}" maxlength="120" placeholder="tuestudio.com"></div>
                <div class="ancho">
                    <label class="form-label" for="ng-logo">Logo <span class="secundario fw-normal">(versión clara, se muestra sobre fondo oscuro)</span></label>
                    @if($principal)
                        <p class="secundario m-0" style="font-size:13.5px">La cuenta principal usa el logo de Vandu.</p>
                    @else
                        <input id="ng-logo" type="file" name="logo" class="form-control" accept=".png,.jpg,.jpeg,.svg">
                        <div class="form-text">PNG con fondo transparente o SVG, hasta 2 MB. Si no subes uno, se muestra el nombre del negocio.</div>
                        @if($logo)<label class="form-check mt-2"><input type="checkbox" class="form-check-input" name="quitar_logo" value="1"> <span class="form-check-label">Quitar el logo</span></label>@endif
                    @endif
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Contacto</h2><span class="ayuda">Aparece en cada cotización</span></div>
            <div class="panel-body ng-campos">
                <div><label class="form-label" for="ng-en">Persona de contacto</label><input id="ng-en" name="emisor[nombre]" class="form-control" value="{{ $val('emisor[nombre]', $v['emisor']['nombre'] ?? '') }}" maxlength="120"></div>
                <div><label class="form-label" for="ng-et">Teléfono</label><input id="ng-et" name="emisor[telefono]" class="form-control num" value="{{ $val('emisor[telefono]', $v['emisor']['telefono'] ?? '') }}" maxlength="40"></div>
                <div><label class="form-label" for="ng-ee">Correo</label><input id="ng-ee" type="email" name="emisor[email]" class="form-control" value="{{ $val('emisor[email]', $v['emisor']['email'] ?? '') }}" maxlength="190"><div class="form-text">Ahí llegan las respuestas de tus clientes a los correos del panel.</div></div>
                <div><label class="form-label" for="ng-wa">WhatsApp</label><input id="ng-wa" name="whatsapp" class="form-control num" value="{{ $val('whatsapp', $v['whatsapp'] ?? '') }}" maxlength="20" placeholder="999 123 4567"><div class="form-text">Para el botón de WhatsApp en la página del cliente.</div></div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Datos para pagos</h2></div>
            <div class="panel-body ng-campos">
                <div><label class="form-label" for="ng-pb">Banco</label><input id="ng-pb" name="pago[banco]" class="form-control" value="{{ $val('pago[banco]', $v['pago']['banco'] ?? '') }}" maxlength="80"></div>
                <div><label class="form-label" for="ng-pc">CLABE</label><input id="ng-pc" name="pago[clabe]" class="form-control num" value="{{ $val('pago[clabe]', $v['pago']['clabe'] ?? '') }}" maxlength="30"></div>
                <div class="ancho"><label class="form-label" for="ng-pben">Beneficiario</label><input id="ng-pben" name="pago[beneficiario]" class="form-control" value="{{ $val('pago[beneficiario]', $v['pago']['beneficiario'] ?? '') }}" maxlength="120"></div>
                <div class="ancho"><label class="form-label" for="ng-pnc">Nota sobre el comprobante</label><textarea id="ng-pnc" name="pago[nota_comprobante]" class="form-control" rows="2" maxlength="400">{{ $val('pago[nota_comprobante]', $v['pago']['nota_comprobante'] ?? '') }}</textarea></div>
                <div class="ancho"><label class="form-label" for="ng-pnf">Nota sobre la factura</label><textarea id="ng-pnf" name="pago[nota_factura]" class="form-control" rows="2" maxlength="400">{{ $val('pago[nota_factura]', $v['pago']['nota_factura'] ?? '') }}</textarea></div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Cotizaciones y archivos</h2></div>
            <div class="panel-body ng-campos">
                <div><label class="form-label" for="ng-tit">Título de las cotizaciones</label><input id="ng-tit" name="titulo" class="form-control" value="{{ $val('titulo', $v['titulo']) }}" maxlength="80"></div>
                <div><label class="form-label" for="ng-fol">Prefijo del folio</label><input id="ng-fol" name="folio_prefijo" class="form-control" value="{{ $val('folio_prefijo', $v['folio_prefijo']) }}" maxlength="8" placeholder="CT-"><div class="form-text">Ej. CT-0001, EST-0001.</div></div>
                <div><label class="form-label" for="ng-vig">Días de vigencia por defecto</label><input id="ng-vig" type="number" min="1" max="365" name="vigencia_dias" class="form-control num" value="{{ $val('vigencia_dias', $v['vigencia_dias']) }}"></div>
                <div><label class="form-label" for="ng-dbx">Carpeta principal en Dropbox</label><input id="ng-dbx" name="dropbox_carpeta" class="form-control" value="{{ $val('dropbox_carpeta', $v['dropbox']['carpeta']) }}" maxlength="60"><div class="form-text">Dentro de ella se crean las carpetas de clientes y proyectos.</div></div>
            </div>
        </section>
        <div><button class="btn btn-primario px-4"><i class="bi bi-check2 me-1"></i> Guardar</button></div>
    </div>

    <aside class="panel" style="position: sticky; top: 20px">
        <div class="panel-head"><h2>Así se ve</h2></div>
        <div class="panel-body d-grid gap-3">
            <div class="ng-logo">@if($principal)<x-logo-vandu height="40" />@elseif($logo)<img src="{{ $logo }}" alt="Logo">@else{{ $c->nombre }}@endif</div>
            <div class="ng-previa">
                <div class="top"><span>@if($principal)<x-logo-vandu height="24" />@elseif($logo)<img src="{{ $logo }}" alt="">@else<b>{{ $c->nombre }}</b>@endif</span><span style="font-size:12px; opacity:.7">{{ $v['folio_prefijo'] }}0001</span></div>
                <div class="cuerpo">
                    <b style="color:var(--text)">{{ $v['titulo'] }}</b>
                    <span>{{ $v['emisor']['nombre'] ?: '—' }}@if($v['emisor']['telefono']) · {{ $v['emisor']['telefono'] }}@endif</span>
                    <span>{{ $v['emisor']['email'] ?: 'Sin correo' }}</span>
                    @if($v['pago']['banco'] || $v['pago']['clabe'])<span>{{ $v['pago']['banco'] }} · CLABE {{ $v['pago']['clabe'] }}</span>@else<span style="color:var(--amber)">Faltan tus datos bancarios</span>@endif
                </div>
            </div>
            <p class="secundario m-0" style="font-size:12.5px">Los cambios se usan en las cotizaciones nuevas; las que ya existen conservan sus datos.</p>
        </div>
    </aside>
</form>
@endsection
