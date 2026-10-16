@extends('admin.layout')
@section('titulo', 'Importar productos')

@push('head')
<style>
    .pasos { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 22px; font-size: 14px; color: var(--muted); }
    .pasos span { display: inline-flex; align-items: center; gap: 8px; }
    .pasos b { width: 24px; height: 24px; border-radius: 50%; display: inline-grid; place-items: center; background: var(--sunken); border: 1px solid var(--line); font-size: 12.5px; color: var(--text-2); }
    .pasos .activo { color: var(--text); font-weight: 500; }
    .pasos .activo b { background: var(--ink); border-color: var(--ink); color: #fff; }
    .pasos i { color: var(--faint); }
    .zona { display: block; border: 2px dashed var(--line); border-radius: 16px; padding: 44px 24px; text-align: center; cursor: pointer; background: var(--surface); transition: border-color .15s, background .15s; }
    .zona:hover, .zona.sobre { border-color: var(--ink); background: var(--sunken); }
    .zona .ico { font-size: 34px; color: var(--muted); }
    .zona h2 { font-size: 17px; font-weight: 600; margin: 10px 0 4px; }
    .zona p { color: var(--muted); margin: 0; font-size: 14.5px; }
    .zona .elegido { margin-top: 14px; font-weight: 500; }
    .consejos { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-top: 22px; }
    .consejos > div { padding: 18px; }
    .consejos h3 { font-size: 14.5px; font-weight: 600; margin: 8px 0 4px; }
    .consejos p { font-size: 13.5px; color: var(--muted); margin: 0; }
    .consejos i { font-size: 20px; color: var(--green-ink); }
    @media (max-width: 767.98px) { .consejos { grid-template-columns: minmax(0, 1fr); } }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.productos.index') }}">Productos</a> <i class="bi bi-chevron-right small"></i> <span>Importar</span></div>
<div class="page-head">
    <div>
        <h1>Importar desde Excel</h1>
        <p class="sub">Sube el archivo que ya usas, tal como está. Tú nos dices qué columna es qué y lo recordamos para la próxima.</p>
    </div>
    <a href="{{ route('admin.productos.importar.plantilla') }}" class="btn btn-borde" data-recargar><i class="bi bi-download me-1"></i> Archivo de ejemplo</a>
</div>

<div class="pasos" aria-label="Pasos">
    <span class="activo"><b>1</b> Subir archivo</span><i class="bi bi-chevron-right"></i>
    <span><b>2</b> Revisar columnas</span><i class="bi bi-chevron-right"></i>
    <span><b>3</b> Importar</span>
</div>

@if($guardado)
    <div class="aviso" style="background: var(--blue-soft); color: var(--blue); border: 1px solid #cddcff">
        <i class="bi bi-magic"></i>
        <span>Ya conocemos tu formato{{ ! empty($guardado['archivo']) ? ' (' . $guardado['archivo'] . ')' : '' }}: al subir el archivo actualizado, las columnas ya vienen acomodadas.
            @if(! empty($guardado['ultima']))Última importación: {{ \Illuminate\Support\Carbon::parse($guardado['ultima'])->setTimezone(config('vandu.zona_horaria'))->locale('es')->isoFormat('D MMM YYYY') }}.@endif</span>
    </div>
@endif

<form method="post" action="{{ route('admin.productos.importar.revisar') }}" enctype="multipart/form-data" data-recargar x-data="{ nombre: '', sobre: false }">
    @csrf
    <label class="zona" :class="{ sobre }" @dragover.prevent="sobre = true" @dragleave="sobre = false"
           @drop.prevent="sobre = false; $refs.archivo.files = $event.dataTransfer.files; nombre = $event.dataTransfer.files[0]?.name || ''">
        <div class="ico"><i class="bi bi-file-earmark-spreadsheet"></i></div>
        <h2>Arrastra tu archivo aquí o da clic para elegirlo</h2>
        <p>Excel (.xlsx) o CSV, hasta 10 MB. Se lee la primera hoja.</p>
        <div class="elegido" x-show="nombre" x-cloak><i class="bi bi-check-circle-fill text-success me-1"></i> <span x-text="nombre"></span></div>
        <input type="file" name="archivo" x-ref="archivo" accept=".xlsx,.csv,.txt,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" class="visually-hidden" required @change="nombre = $event.target.files[0]?.name || ''">
    </label>
    <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="{{ route('admin.productos.index') }}" class="btn btn-borde">Cancelar</a>
        <button class="btn btn-primario" :disabled="!nombre">Continuar <i class="bi bi-arrow-right ms-1"></i></button>
    </div>
</form>

<div class="consejos">
    <div class="panel"><i class="bi bi-columns-gap"></i><h3>Cualquier orden de columnas</h3><p>No tienes que cambiar tu Excel. Reconocemos títulos como "Código", "Descripción", "P. Venta" o "Stock".</p></div>
    <div class="panel"><i class="bi bi-arrow-repeat"></i><h3>Actualiza sin duplicar</h3><p>Si la clave ya existe, se actualizan su precio y existencia; si es nueva, se agrega.{{ $hay ? ' Hoy tienes ' . number_format($hay) . ' productos.' : '' }}</p></div>
    <div class="panel"><i class="bi bi-shield-check"></i><h3>Revisas antes de guardar</h3><p>Te mostramos cómo quedará cada producto y cuántos son nuevos antes de importar.</p></div>
</div>
@endsection
