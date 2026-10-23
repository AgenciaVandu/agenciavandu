@extends('admin.layout')
@section('titulo', 'Lista de precios')

@push('head')
<style>
    .zona { position: relative; display: block; border: 2px dashed var(--line); border-radius: 16px; padding: 40px 24px; text-align: center; cursor: pointer; background: var(--surface); }
    .zona:hover, .zona.lleno { border-color: var(--marca); background: var(--marca-soft); }
    .zona .ico { font-size: 34px; color: var(--marca); }
    .zona h2 { font-size: 17px; font-weight: 600; margin: 10px 0 4px; } .zona p { color: var(--muted); margin: 0; font-size: 14.5px; }
    .zona input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    .lp-pasos { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin-top: 20px; }
    .lp-pasos > div { padding: 16px 18px; } .lp-pasos b { display: block; font-size: 14.5px; margin-bottom: 2px; } .lp-pasos span { font-size: 13.5px; color: var(--muted); }
    .lp-pasos i { color: var(--marca); font-size: 19px; }
    .lp-leyendo { display: none; align-items: center; gap: 12px; margin-top: 16px; padding: 14px 16px; border-radius: 12px; background: var(--sunken); }
    .lp-leyendo.si { display: flex; }
    .lp-giro { width: 20px; height: 20px; border: 2px solid var(--marca-soft); border-top-color: var(--marca); border-radius: 50%; animation: lpg .8s linear infinite; flex: none; }
    @keyframes lpg { to { transform: rotate(360deg); } }
    @media (max-width: 767.98px) { .lp-pasos { grid-template-columns: minmax(0, 1fr); } }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.productos.index') }}">Productos</a> <i class="bi bi-chevron-right small"></i> <span>Lista de precios</span></div>
<div class="page-head">
    <div>
        <h1>Actualizar costos con la lista del proveedor</h1>
        <p class="sub">Sube el PDF o una foto de su lista de precios. La IA la lee, la empareja con tu catálogo y tú revisas antes de cambiar nada.</p>
    </div>
    <a href="{{ route('admin.productos.importar') }}" class="btn btn-borde"><i class="bi bi-file-earmark-spreadsheet me-1"></i> ¿La tienes en Excel?</a>
</div>

@if(! $ia)
    <div class="aviso mb-4" style="background: var(--amber-soft); color: var(--amber)"><i class="bi bi-info-circle-fill"></i> La lectura con IA todavía no está activa en el servidor. Mientras, actualiza costos con Importar de Excel.</div>
@endif

<form method="post" action="{{ route('admin.productos.lista.leer') }}" enctype="multipart/form-data" style="max-width: 760px" x-data="{ nombre: '', leyendo: false }" @submit="leyendo = true">
    @csrf
    <label class="zona" :class="nombre && 'lleno'">
        <div class="ico"><i class="bi bi-file-earmark-text"></i></div>
        <h2 x-text="nombre || 'Elige la lista de precios'">Elige la lista de precios</h2>
        <p x-show="! nombre">PDF o foto · hasta 15 MB · hasta {{ \App\Support\Documentos\ListaPrecios::MAX_FILAS }} productos por lista</p>
        <input type="file" name="archivo" accept="application/pdf,image/jpeg,image/png,image/webp" required @change="nombre = $event.target.files[0]?.name || ''" aria-label="Lista de precios">
    </label>
    @error('archivo')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
    <div class="d-flex flex-wrap gap-3 align-items-end mt-3">
        @if($proveedores->isNotEmpty())
            <div style="min-width: 260px">
                <label class="form-label" for="lp-prov">¿De qué proveedor es? <span class="secundario fw-normal">(ayuda a emparejar)</span></label>
                <select id="lp-prov" name="proveedor_id" class="form-select">
                    <option value="">No sé / varios</option>
                    @foreach($proveedores as $p)<option value="{{ $p->id }}" @selected(old('proveedor_id') == $p->id)>{{ $p->empresa }}</option>@endforeach
                </select>
            </div>
        @endif
        <button class="btn btn-primario" :disabled="! nombre || leyendo" @disabled(! $ia)><i class="bi bi-stars me-1"></i> Leer la lista</button>
        @if($ia)<span class="secundario small">Quedan {{ $quedan }} lecturas con IA este mes.</span>@endif
    </div>
    <div class="lp-leyendo" :class="leyendo && 'si'" role="status" aria-live="polite"><span class="lp-giro"></span> Leyendo la lista… una lista larga puede tardar hasta un minuto.</div>
</form>

<div class="lp-pasos" style="max-width: 760px">
    <div class="panel"><i class="bi bi-stars"></i><b>Lee cualquier formato</b><span>PDF del proveedor, foto de la lista impresa o captura del WhatsApp.</span></div>
    <div class="panel"><i class="bi bi-link-45deg"></i><b>Empareja tu catálogo</b><span>Por clave, por nombre o por parecido. Lo que no encuentre te lo dice.</span></div>
    <div class="panel"><i class="bi bi-check2-square"></i><b>Tú decides</b><span>Ves costo actual contra nuevo y eliges qué aplicar. Puedes mantener tu margen.</span></div>
</div>
@endsection
