@extends('admin.layout')
@section('titulo', 'Revisar lista de precios')

@php
    $d = fn ($n) => '$' . number_format((float) $n, 2);
    $encontrados = collect($renglones)->filter(fn ($r) => $r['producto']);
    $faltan = collect($renglones)->reject(fn ($r) => $r['producto']);
    $filas = $encontrados->values()->map(fn ($r, $i) => [
        'i' => $i, 'id' => $r['producto']->id, 'precio' => $r['fila']['precio'], 'actual' => (float) $r['producto']->costo,
        'aplicar' => $r['como'] !== 'parecido',
    ]);
@endphp

@push('head')
<style>
    .lr-tabla td, .lr-tabla th { vertical-align: middle; }
    .lr-como { font-size: 11.5px; font-weight: 600; padding: 1px 8px; border-radius: 99px; white-space: nowrap; }
    .lr-como.clave { background: #E3FBEF; color: #047A4B; } .lr-como.nombre { background: var(--marca-soft); color: var(--marca); } .lr-como.parecido { background: #FFF3DD; color: #9A5300; }
    .lr-cambio.sube { color: var(--red); } .lr-cambio.baja { color: #047A4B; }
    .lr-tabla tr.apagada td:not(:first-child) { opacity: .45; }
    .lr-barra { position: sticky; bottom: 0; z-index: 5; margin-top: 16px; padding: 14px 18px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; background: rgba(255,255,255,.95); backdrop-filter: blur(6px); border: 1px solid var(--line); border-radius: 14px; }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.productos.index') }}">Productos</a> <i class="bi bi-chevron-right small"></i> <a href="{{ route('admin.productos.lista') }}">Lista de precios</a> <i class="bi bi-chevron-right small"></i> <span>Revisar</span></div>
<div class="page-head">
    <div>
        <h1>Revisa los costos nuevos</h1>
        <p class="sub">{{ $s['archivo'] }}{{ $proveedor ? ' · ' . $proveedor->empresa : ($s['proveedor_detectado'] ? ' · ' . $s['proveedor_detectado'] : '') }} · leímos {{ count($s['filas']) }} {{ count($s['filas']) === 1 ? 'renglón' : 'renglones' }}, {{ $encontrados->count() }} {{ $encontrados->count() === 1 ? 'está' : 'están' }} en tu catálogo.</p>
    </div>
</div>

<form method="post" action="{{ route('admin.productos.lista.aplicar') }}"
      x-data="{ iva: {{ $s['con_iva'] ? 'true' : 'false' }}, filas: {{ Js::from($filas) }},
                costo(f) { return Math.round((this.iva ? f.precio / 1.16 : f.precio) * 100) / 100; },
                get n() { return this.filas.filter(f => f.aplicar).length; } }">
    @csrf
    <section class="panel">
        <div class="d-flex flex-wrap gap-3 align-items-center px-3 pt-3 pb-2">
            <label class="form-check m-0"><input type="checkbox" class="form-check-input" x-model="iva"> <span class="form-check-label">Los precios de la lista <b>ya incluyen IVA</b> <span class="secundario small">{{ $s['con_iva'] === null ? '(la lista no lo dice)' : '(así lo dice la lista)' }}</span></span></label>
            <span class="secundario small ms-auto">El costo se guarda antes de IVA, como en tu catálogo.</span>
        </div>
        @if($encontrados->isEmpty())
            <div class="vacio"><div class="ico"><i class="bi bi-search"></i></div><h3>Ningún producto coincide</h3><p>Revisa que las claves o nombres de tu catálogo se parezcan a los de la lista.</p></div>
        @else
        <div class="table-responsive">
            <table class="tabla lr-tabla">
                <thead><tr><th style="width: 40px"></th><th>En la lista</th><th>Tu producto</th><th class="text-end">Costo actual</th><th class="text-end" style="width: 150px">Costo nuevo</th><th class="text-end">Cambio</th></tr></thead>
                <tbody>
                @foreach($encontrados->values() as $i => $r)
                    @php $p = $r['producto']; @endphp
                    <tr :class="! filas[{{ $i }}].aplicar && 'apagada'">
                        <td><input type="checkbox" class="form-check-input" name="filas[{{ $i }}][aplicar]" value="1" x-model="filas[{{ $i }}].aplicar" aria-label="Aplicar a {{ $p->nombre }}">
                            <input type="hidden" name="filas[{{ $i }}][producto_id]" value="{{ $p->id }}"></td>
                        <td style="min-width: 220px"><div class="principal">{{ $r['fila']['descripcion'] ?: $r['fila']['clave'] }}</div>
                            <div class="secundario small num">@if($r['fila']['clave']){{ $r['fila']['clave'] }} · @endif{{ $d($r['fila']['precio']) }}@if($r['fila']['unidad']) / {{ $r['fila']['unidad'] }}@endif</div></td>
                        <td style="min-width: 200px"><a href="{{ route('admin.productos.show', $p) }}" class="principal text-reset" target="_blank">{{ $p->nombre }}</a>
                            <div><span class="lr-como {{ $r['como'] }}">{{ ['clave' => 'Misma clave', 'nombre' => 'Mismo nombre', 'parecido' => 'Parecido · revísalo'][$r['como']] }}</span>@if($p->clave) <span class="secundario small num">{{ $p->clave }}</span>@endif</div></td>
                        <td class="text-end num">{{ $p->costo > 0 ? $d($p->costo) : '—' }}</td>
                        <td class="text-end"><input type="number" step="0.01" min="0" class="form-control form-control-sm num text-end" name="filas[{{ $i }}][costo]" :value="costo(filas[{{ $i }}])" aria-label="Costo nuevo de {{ $p->nombre }}"></td>
                        <td class="text-end num text-nowrap">
                            <template x-if="filas[{{ $i }}].actual > 0">
                                <span class="lr-cambio" :class="costo(filas[{{ $i }}]) > filas[{{ $i }}].actual ? 'sube' : (costo(filas[{{ $i }}]) < filas[{{ $i }}].actual ? 'baja' : '')"
                                      x-text="(costo(filas[{{ $i }}]) >= filas[{{ $i }}].actual ? '+' : '') + Math.round((costo(filas[{{ $i }}]) / filas[{{ $i }}].actual - 1) * 1000) / 10 + '%'"></span>
                            </template>
                            <template x-if="! (filas[{{ $i }}].actual > 0)"><span class="secundario small">nuevo</span></template>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </section>

    @if($faltan->isNotEmpty())
        <details class="panel mt-3">
            <summary class="panel-body"><b>{{ $faltan->count() }} {{ $faltan->count() === 1 ? 'renglón no está' : 'renglones no están' }} en tu catálogo</b> <span class="secundario small">· no se cambia nada con ellos</span></summary>
            <div class="px-3 pb-3 secundario small">
                @foreach($faltan as $r)<div class="num">{{ $r['fila']['clave'] ? $r['fila']['clave'] . ' · ' : '' }}{{ $r['fila']['descripcion'] }} · {{ $d($r['fila']['precio']) }}</div>@endforeach
                <div class="mt-2">Si los quieres en tu catálogo, agrégalos desde <a href="{{ route('admin.productos.create') }}">Nuevo producto</a> o con <a href="{{ route('admin.productos.importar') }}">Importar de Excel</a>.</div>
            </div>
        </details>
    @endif

    @if($encontrados->isNotEmpty())
        <div class="lr-barra">
            <label class="form-check m-0"><input type="checkbox" class="form-check-input" name="margen" value="1" checked> <span class="form-check-label">Subir o bajar también mi <b>precio de venta</b> para mantener el margen</span></label>
            <a href="{{ route('admin.productos.lista') }}" class="btn btn-borde ms-auto">Otra lista</a>
            <button class="btn btn-primario" :disabled="n === 0"><i class="bi bi-check2 me-1"></i> Actualizar <span x-text="n"></span> <span x-text="n === 1 ? 'costo' : 'costos'"></span></button>
        </div>
    @endif
</form>
@endsection
