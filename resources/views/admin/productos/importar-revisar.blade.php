@extends('admin.layout')
@section('titulo', 'Revisar importación')

@push('head')
<style>
    .pasos { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 22px; font-size: 14px; color: var(--muted); }
    .pasos span { display: inline-flex; align-items: center; gap: 8px; }
    .pasos b { width: 24px; height: 24px; border-radius: 50%; display: inline-grid; place-items: center; background: var(--sunken); border: 1px solid var(--line); font-size: 12.5px; color: var(--text-2); }
    .pasos .hecho b { background: var(--green-soft); border-color: #BDF2D6; color: var(--green-ink); }
    .pasos .activo { color: var(--text); font-weight: 500; }
    .pasos .activo b { background: var(--ink); border-color: var(--ink); color: #fff; }
    .pasos i { color: var(--faint); }
    .mapa { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px 18px; }
    .mapa label { font-size: 13.5px; font-weight: 500; margin-bottom: 4px; display: flex; justify-content: space-between; gap: 8px; }
    .mapa label small { font-weight: 400; color: var(--muted); }
    .mapa .form-select.vacio-sel { color: var(--muted); }
    .mapa .ej { font-size: 12.5px; color: var(--muted); margin-top: 4px; min-height: 18px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .cuentas { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .cuentas > div { padding: 16px 20px; }
    .cuentas > div + div { border-left: 1px solid var(--line); }
    .cuentas .v { font-size: 22px; font-weight: 600; }
    .cuentas .k { font-size: 13px; color: var(--muted); }
    .opciones { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
    .opcion { display: flex; gap: 10px; align-items: flex-start; padding: 8px 0; margin: 0; }
    .opcion input { margin-top: 4px; flex: none; }
    .opcion small { display: block; color: var(--muted); font-size: 13px; }
    .tag { font-size: 12px; font-weight: 500; padding: 2px 8px; border-radius: 99px; white-space: nowrap; }
    .tag.nuevo { background: var(--green-soft); color: var(--green-ink); }
    .tag.act { background: var(--blue-soft); color: var(--blue); }
    .tag.salta { background: #EEF0F3; color: #4E5463; }
    .barra-acciones { position: sticky; bottom: 0; z-index: 5; margin: 20px -36px -64px; padding: 14px 36px; background: rgba(244,245,247,.92);
                      backdrop-filter: blur(6px); border-top: 1px solid var(--line); display: flex; gap: 10px; justify-content: flex-end; align-items: center; flex-wrap: wrap; }
    @media (max-width: 991.98px) { .opciones { grid-template-columns: minmax(0, 1fr); } .barra-acciones { margin: 20px -16px -48px; padding: 12px 16px; } }
    @media (max-width: 575.98px) { .cuentas { grid-template-columns: minmax(0, 1fr); } .cuentas > div + div { border-left: 0; border-top: 1px solid var(--line); } }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.productos.index') }}">Productos</a> <i class="bi bi-chevron-right small"></i> <a href="{{ route('admin.productos.importar') }}">Importar</a> <i class="bi bi-chevron-right small"></i> <span>Revisar</span></div>
<div class="page-head">
    <div>
        <h1>¿Qué trae cada columna?</h1>
        <p class="sub"><i class="bi bi-file-earmark-spreadsheet"></i> {{ $archivo }} · {{ number_format($total) }} {{ $total === 1 ? 'fila' : 'filas' }}. Ya acomodamos lo que reconocimos; corrige lo que haga falta.</p>
    </div>
</div>

<div class="pasos" aria-label="Pasos">
    <span class="hecho"><b><i class="bi bi-check"></i></b> Subir archivo</span><i class="bi bi-chevron-right"></i>
    <span class="activo"><b>2</b> Revisar columnas</span><i class="bi bi-chevron-right"></i>
    <span><b>3</b> Importar</span>
</div>

<form method="post" action="{{ route('admin.productos.importar.aplicar') }}" x-data="importacion(@js([
        'encabezados' => $encabezados, 'filas' => $muestra, 'total' => $total, 'mapa' => $mapa, 'opciones' => $opciones,
        'claves' => $claves, 'nombres' => $nombres, 'iva' => (float) config('vandu.iva_porcentaje', 16),
    ]))">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <section class="panel panel-body mb-4">
        <div class="mapa">
            @foreach($campos as $campo => $texto)
                @continue($campo === 'costo' && ! $veCostos)
                <div>
                    <label for="mapa-{{ $campo }}">{{ $texto }} @if(in_array($campo, ['nombre', 'precio'], true))<small>importante</small>@endif</label>
                    <select class="form-select" id="mapa-{{ $campo }}" name="mapa[{{ $campo }}]" x-model="mapa.{{ $campo }}" :class="{ 'vacio-sel': mapa.{{ $campo }} === '' }">
                        <option value="">— No importar —</option>
                        <template x-for="(h, i) in encabezados" :key="i"><option :value="String(i)" x-text="letra(i) + ' · ' + h" :selected="String(mapa.{{ $campo }}) === String(i)"></option></template>
                    </select>
                    <div class="ej" x-text="ejemplo(mapa.{{ $campo }})"></div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="panel panel-body mb-4">
        <div class="opciones">
            <div>
                <h2 class="fs-6 fw-semibold mb-1">Precios del archivo</h2>
                <label class="opcion"><input type="radio" name="iva_incluido" value="0" x-model="opciones.iva_incluido"><span>Son antes de IVA<small>Se guardan tal cual.</small></span></label>
                <label class="opcion"><input type="radio" name="iva_incluido" value="1" x-model="opciones.iva_incluido"><span>Ya incluyen IVA<small>Les quitamos el {{ rtrim(rtrim(number_format((float) config('vandu.iva_porcentaje', 16), 2), '0'), '.') }}% para guardarlos antes de IVA, como en las cotizaciones.</small></span></label>
            </div>
            <div>
                <h2 class="fs-6 fw-semibold mb-1">Existencias del archivo</h2>
                <label class="opcion"><input type="radio" name="existencias" value="reemplazar" x-model="opciones.existencias"><span>Son el conteo actual<small>La existencia queda igual a la del archivo.</small></span></label>
                <label class="opcion"><input type="radio" name="existencias" value="sumar" x-model="opciones.existencias"><span>Es mercancía que llegó<small>Se suma a lo que ya hay.</small></span></label>
                <label class="opcion"><input type="radio" name="existencias" value="ignorar" x-model="opciones.existencias"><span>No tocar existencias<small>Solo actualizar datos y precios.</small></span></label>
            </div>
        </div>
    </section>

    <div class="panel cuentas mb-4">
        <div><div class="k">Productos nuevos</div><div class="v num" x-text="resumen.nuevos"></div></div>
        <div><div class="k">Se actualizan</div><div class="v num" x-text="resumen.actualizados"></div></div>
        <div><div class="k">Se saltan</div><div class="v num" x-text="resumen.saltan"></div></div>
    </div>
    <p class="small text-secondary" x-show="total > filas.length" x-cloak>Las cifras son de las primeras {{ number_format(count($muestra)) }} filas; se importan las {{ number_format($total) }}.</p>

    <section class="panel">
        <div class="panel-head"><h2>Así quedarán</h2><span class="ayuda">Primeros 12 productos</span></div>
        <div class="table-responsive">
            <table class="tabla">
                <thead><tr><th></th><th>Clave</th><th>Nombre</th><th>Categoría</th><th class="text-end">Precio antes de IVA</th><th class="text-end">Existencia</th></tr></thead>
                <tbody>
                    <template x-for="(f, i) in vista" :key="i">
                        <tr>
                            <td><span class="tag" :class="f.estado" x-text="{ nuevo: 'Nuevo', act: 'Actualiza', salta: 'Se salta' }[f.estado]"></span></td>
                            <td class="num" x-text="f.clave || '—'"></td>
                            <td x-text="f.nombre || '—'"></td>
                            <td x-text="f.categoria || '—'"></td>
                            <td class="text-end num" x-text="f.precio === null ? '—' : '$' + f.precio.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></td>
                            <td class="text-end num" x-text="f.existencia === null || opciones.existencias === 'ignorar' ? '—' : (opciones.existencias === 'sumar' ? '+' : '') + f.existencia"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>

    <div class="barra-acciones">
        <span class="small text-secondary me-auto" x-show="mapa.nombre === '' && mapa.clave === ''" x-cloak><i class="bi bi-exclamation-circle"></i> Elige al menos la columna del nombre.</span>
        <a href="{{ route('admin.productos.importar') }}" class="btn btn-borde">Subir otro archivo</a>
        <button class="btn btn-primario" :disabled="mapa.nombre === '' && mapa.clave === ''">Importar <span x-text="resumen.nuevos + resumen.actualizados"></span> productos</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
function importacion(d) {
    const norm = (v) => (v ?? '').toString().trim();
    const numero = (v) => {
        v = norm(v); if (v === '' || v === '-') return null;
        v = v.replace(/[^\d.,\-]/g, ''); if (v === '' || v === '-') return null;
        if (v.includes(',') && v.includes('.')) v = v.lastIndexOf(',') > v.lastIndexOf('.') ? v.replace(/\./g, '').replace(',', '.') : v.replace(/,/g, '');
        else if (v.includes(',')) v = /,\d{3}$/.test(v) ? v.replace(/,/g, '') : v.replace(',', '.');
        const n = parseFloat(v); return isNaN(n) ? null : n;
    };
    const mapa = {};
    Object.entries(d.mapa).forEach(([k, v]) => mapa[k] = v === null ? '' : String(v));
    return {
        encabezados: d.encabezados, filas: d.filas, total: d.total, mapa,
        opciones: { iva_incluido: d.opciones.iva_incluido ? '1' : '0', existencias: d.opciones.existencias },
        claves: new Set(d.claves), nombres: new Set(d.nombres),
        letra(i) { let s = ''; for (i++; i > 0; i = Math.floor((i - 1) / 26)) s = String.fromCharCode(65 + (i - 1) % 26) + s; return s; },
        ejemplo(i) {
            if (i === '' || i === undefined) return '';
            const ej = this.filas.map((f) => norm(f[+i])).filter(Boolean).slice(0, 3);
            return ej.length ? 'Ej.: ' + ej.join(' · ') : 'Columna vacía';
        },
        col(f, campo) { const i = this.mapa[campo]; return i === '' || i === undefined ? '' : norm(f[+i]); },
        fila(f, vistas) {
            const clave = this.col(f, 'clave'), nombre = this.col(f, 'nombre');
            let estado;
            const llave = clave ? 'c:' + clave.toLowerCase() : 'n:' + nombre.toLowerCase();
            if (!clave && !nombre) estado = 'salta';
            else if (vistas.has(llave)) estado = 'salta';
            else if (clave) estado = this.claves.has(clave.toLowerCase()) ? 'act' : (nombre ? 'nuevo' : 'salta');
            else estado = this.nombres.has(nombre.toLowerCase()) ? 'act' : 'nuevo';
            vistas.add(llave);
            const precio = numero(this.col(f, 'precio'));
            const factor = this.opciones.iva_incluido === '1' ? 1 + d.iva / 100 : 1;
            return { estado, clave, nombre, categoria: this.col(f, 'categoria'),
                     precio: precio === null ? null : Math.round(Math.max(0, precio) / factor * 100) / 100, existencia: numero(this.col(f, 'existencia')) };
        },
        get procesadas() { const vistas = new Set(); return this.filas.map((f) => this.fila(f, vistas)); },
        get vista() { return this.procesadas.slice(0, 12); },
        get resumen() {
            const r = { nuevos: 0, actualizados: 0, saltan: 0 };
            this.procesadas.forEach((f) => f.estado === 'nuevo' ? r.nuevos++ : (f.estado === 'act' ? r.actualizados++ : r.saltan++));
            return r;
        },
    };
}
</script>
@endpush
