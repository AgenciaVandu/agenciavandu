@extends('admin.layout')
@section('titulo', 'Planes y precios')

@php
    $mods = \App\Support\Giros::MODULOS;
    $extrasMod = \App\Http\Controllers\Admin\PlataformaController::EXTRAS;
@endphp

@push('head')
<style>
    .pl-tabla td { vertical-align: top; }
    .pl-tabla input.form-control, .pl-tabla .form-control-sm { min-width: 0; }
    .pl-tabla .w-num { width: 110px; } .pl-tabla .w-mini { width: 84px; }
    .pl-incluye { display: grid; gap: 2px; font-size: 13px; white-space: nowrap; }
    .pl-pago { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end; }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <a href="{{ route('admin.plataforma') }}" class="secundario small text-decoration-none"><i class="bi bi-arrow-left"></i> Plataforma</a>
        <h1>Planes y precios</h1>
        <p class="sub">Lo que ofreces a los negocios. Al asignar una licencia, su precio se toma de aquí y después se puede negociar; cambiar un precio aquí no cambia lo ya acordado.</p>
    </div>
</div>

<form method="post" action="{{ route('admin.plataforma.planes.update') }}">
    @csrf @method('PUT')
    @foreach(['plan' => [$planes, 'Planes', 'Usuarios (máx.)', 'Vacío = sin límite'], 'extra' => [$extras, 'Extras', 'Usuarios que suma', 'Se cobran por mes, por cada uno']] as $tipo => [$lista, $titulo, $colUsu, $ayuda])
        <section class="panel mb-4">
            <div class="panel-head"><div><h2>{{ $titulo }}</h2><span class="ayuda">{{ $ayuda }}</span></div></div>
            <div class="table-responsive">
                <table class="tabla pl-tabla">
                    <thead><tr><th>Nombre</th><th>Mensual</th>@if($tipo === 'plan')<th>Anual</th>@endif<th>{{ $colUsu }}</th><th>Sucursales</th><th>Prende</th><th>Activo</th>@if($tipo === 'plan')<th class="text-end">Negocios</th>@endif</tr></thead>
                    <tbody>
                    @foreach($lista as $p)
                        <tr>
                            <td style="min-width: 220px">
                                <input name="p[{{ $p->id }}][nombre]" value="{{ $p->nombre }}" class="form-control form-control-sm fw-semibold" required maxlength="80" aria-label="Nombre">
                                <input name="p[{{ $p->id }}][descripcion]" value="{{ $p->descripcion }}" class="form-control form-control-sm mt-1" maxlength="255" placeholder="Descripción" aria-label="Descripción">
                            </td>
                            <td><input type="number" step="0.01" min="0" name="p[{{ $p->id }}][precio_mensual]" value="{{ $p->precio_mensual + 0 }}" class="form-control form-control-sm num w-num" required aria-label="Precio mensual"></td>
                            @if($tipo === 'plan')
                                <td><input type="number" step="0.01" min="0" name="p[{{ $p->id }}][precio_anual]" value="{{ $p->precio_anual !== null ? $p->precio_anual + 0 : '' }}" placeholder="{{ $p->precio_mensual * 10 }}" class="form-control form-control-sm num w-num" aria-label="Precio anual"></td>
                            @endif
                            <td><input type="number" min="1" name="p[{{ $p->id }}][usuarios]" value="{{ $p->usuarios }}" placeholder="{{ $tipo === 'plan' ? '∞' : '—' }}" class="form-control form-control-sm num w-mini" aria-label="Usuarios"></td>
                            <td><input type="number" min="0" name="p[{{ $p->id }}][sucursales]" value="{{ $p->sucursales }}" placeholder="—" class="form-control form-control-sm num w-mini" aria-label="Sucursales"></td>
                            <td><div class="pl-incluye">
                                @foreach($extrasMod as $m)
                                    <label class="form-check m-0"><input type="checkbox" name="p[{{ $p->id }}][incluye][]" value="{{ $m }}" class="form-check-input" @checked(in_array($m, (array) $p->incluye, true))> <span class="form-check-label">{{ $mods[$m]['texto'] ?? $m }}</span></label>
                                @endforeach
                            </div></td>
                            <td><input type="hidden" name="p[{{ $p->id }}][activo]" value="0"><input type="checkbox" name="p[{{ $p->id }}][activo]" value="1" class="form-check-input" @checked($p->activo) aria-label="Activo"></td>
                            @if($tipo === 'plan')<td class="text-end num">{{ $usoPlan[$p->id] ?? 0 }}</td>@endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
    <div class="d-flex gap-2 mb-4"><button class="btn btn-primario"><i class="bi bi-check2 me-1"></i> Guardar planes y extras</button></div>
</form>

<form method="post" action="{{ route('admin.plataforma.planes.store') }}" class="d-flex flex-wrap gap-2 align-items-end mb-5">
    @csrf
    <div><label class="form-label small" for="pl-n">Agregar</label>
        <select name="tipo" class="form-select" style="min-width: 110px" aria-label="Tipo"><option value="plan">Plan</option><option value="extra">Extra</option></select></div>
    <div><input id="pl-n" name="nombre" class="form-control" placeholder="Nombre" required maxlength="80"></div>
    <button class="btn btn-borde"><i class="bi bi-plus-lg me-1"></i> Agregar</button>
</form>

<section class="panel">
    <div class="panel-head"><div><h2>Datos para que te paguen</h2><span class="ayuda">Salen en el aviso de vencimiento del panel, en la pantalla de suspensión y en los correos</span></div></div>
    <form method="post" action="{{ route('admin.plataforma.planes.pago') }}" class="panel-body">
        @csrf @method('PUT')
        <div class="pl-pago">
            <div><label class="form-label small">Banco</label><input name="banco" value="{{ $pago['banco'] ?? '' }}" class="form-control" maxlength="60"></div>
            <div><label class="form-label small">A nombre de</label><input name="titular" value="{{ $pago['titular'] ?? '' }}" class="form-control" maxlength="120"></div>
            <div><label class="form-label small">CLABE</label><input name="clabe" value="{{ $pago['clabe'] ?? '' }}" class="form-control num" inputmode="numeric" maxlength="18" pattern="\d{18}"></div>
            <div><label class="form-label small">WhatsApp para comprobantes</label><input name="whatsapp" value="{{ $pago['whatsapp'] ?? '' }}" class="form-control num" maxlength="30" placeholder="999 123 4567"></div>
            <div><label class="form-label small">Tus precios</label>
                <select name="iva" class="form-select"><option value="sin" @selected(($pago['iva'] ?? 'sin') === 'sin')>No llevan IVA</option><option value="incluido" @selected(($pago['iva'] ?? '') === 'incluido')>Ya incluyen IVA (16%)</option></select></div>
            <div style="grid-column: 1 / -1"><label class="form-label small">Nota (opcional)</label><input name="nota" value="{{ $pago['nota'] ?? '' }}" class="form-control" maxlength="200" placeholder="Pon tu nombre de negocio en el concepto de la transferencia"></div>
        </div>
        <button class="btn btn-primario mt-3">Guardar datos de pago</button>
    </form>
</section>
@endsection
