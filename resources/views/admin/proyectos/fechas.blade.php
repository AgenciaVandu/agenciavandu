@extends('admin.layout')
@php
    $crear = $modo === 'crear';
    $metodo = config("vandu.proyectos.$tipo");
    $hoy = now(config('vandu.zona_horaria'))->toDateString();
    // Si hubo error de validación, se vuelve a mostrar lo capturado
    $etapasIni = collect(old('etapas', $etapas->all()))->values()->map(fn ($e, $i) => array_merge($etapas[$i] ?? [], $e))->all();
    $forma = old('forma_pago', $forma);
    $basePagos = ($crear && $forma === 'credito') ? collect([$pagoCredito]) : collect($pagos);
    $pagosIni = collect(old('pagos', $basePagos->all()))->values()->map(fn ($p, $i) => array_merge($basePagos[$i] ?? [], $p))->all();
    $estado = [
        'etapas'  => $etapasIni,
        'pagos'   => $pagosIni,
        'inicio'  => old('inicio', $inicio ?? $hoy),
        'fin'     => old('fin', now(config('vandu.zona_horaria'))->subDay()->toDateString()),
        'terminado' => (bool) old('terminado', false),
        'proponer'  => $crear && ! old('etapas'),
        'hoy'     => $hoy,
        'forma'   => $forma,
        'dias'    => (int) old('dias_credito', $diasCredito ?: config('vandu.credito.dias_por_defecto')),
        'pagosContado' => collect($pagos)->values()->all(),
        'pagoCredito'  => $pagoCredito,
        'venceManual'  => (bool) collect($pagosIni)->first(fn ($p) => ! empty($p['vence_el'])),
    ];
    $cancelar = $crear ? route('admin.presupuestos.edit', $presupuesto) : route('admin.proyectos.show', $proyecto);
@endphp
@section('titulo', $crear ? 'Nuevo proyecto' : 'Editar fechas')

@push('head')
<style>
    .fechas { width: 100%; border-collapse: separate; border-spacing: 0; }
    .fechas th { font-size: 12.5px; font-weight: 500; color: var(--muted); padding: 10px 12px; background: var(--sunken); border-bottom: 1px solid var(--line); white-space: nowrap; text-align: left; }
    .fechas td { padding: 10px 12px; border-bottom: 1px solid var(--line); vertical-align: middle; }
    .fechas tr:last-child td { border-bottom: 0; }
    .fechas .form-control, .fechas .form-select { padding: .4rem .6rem; font-size: 14px; }
    .fechas input[type=date] { min-width: 140px; }
    .fechas .nom { min-width: 200px; }
    .fechas .gate { font-size: 12.5px; color: var(--muted); margin-top: 3px; }
    .fechas tr.completa td:first-child { box-shadow: inset 3px 0 0 var(--green-ink); }
    @media (max-width: 991.98px) {
        .fechas, .fechas tbody { display: block; }
        .fechas thead { display: none; }
        .fechas tr { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 10px 12px; padding: 14px 16px; border-bottom: 1px solid var(--line); }
        .fechas tbody tr:last-child { border-bottom: 0; }
        .fechas tr.completa { box-shadow: inset 3px 0 0 var(--green-ink); }
        .fechas tr.completa td:first-child { box-shadow: none; }
        .fechas td { display: block; padding: 0; border: 0 !important; min-width: 0 !important; }
        .fechas td[data-k]::before { content: attr(data-k); display: block; font-size: 12px; color: var(--muted); margin-bottom: 3px; }
        .fechas td.nom { grid-column: 1 / -1; }
        .fechas input[type=date] { min-width: 0; width: 100%; }
    }
    .chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .chips button { border: 1px solid var(--line-strong); background: var(--surface); border-radius: 99px; padding: 4px 12px; font-size: 13.5px; color: var(--text-2); }
    .chips button:hover, .chips button.activo { border-color: var(--ink); color: var(--text); }
    .chips button.activo { background: var(--ink); color: #fff; }
    .herramientas { display: flex; flex-wrap: wrap; gap: 14px 20px; align-items: flex-end; }
    .herramientas > div { min-width: 0; }
    .barra-acciones { position: sticky; bottom: 0; z-index: 5; margin: 24px -36px -64px; padding: 14px 36px; background: rgba(244,245,247,.92);
                      backdrop-filter: blur(6px); border-top: 1px solid var(--line); display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }
    @media (max-width: 991.98px) { .barra-acciones { margin: 24px -16px -48px; padding: 12px 16px; } }
</style>
@endpush

@section('contenido')
<div class="migas">
    @if($crear)
        <a href="{{ route('admin.presupuestos.edit', $presupuesto) }}">{{ $presupuesto->folio }}</a> <i class="bi bi-chevron-right small"></i> <span>Nuevo proyecto</span>
    @else
        <a href="{{ route('admin.proyectos.index') }}">Proyectos</a> <i class="bi bi-chevron-right small"></i>
        <a href="{{ route('admin.proyectos.show', $proyecto) }}">{{ $proyecto->nombre }}</a> <i class="bi bi-chevron-right small"></i> <span>Fechas</span>
    @endif
</div>
<div class="page-head">
    <div>
        <h1>{{ $crear ? 'Nuevo proyecto' : 'Editar fechas' }}</h1>
        <p class="sub">{{ $crear ? 'Revisa cada etapa y pago. Las fechas propuestas son solo un punto de partida: cámbialas como quieras.' : 'Ajusta las fechas, el estado de cada etapa y cuándo se recibió cada pago.' }}</p>
    </div>
</div>

<form method="post" action="{{ $crear ? route('admin.proyectos.store', $presupuesto) : route('admin.proyectos.fechas.guardar', $proyecto) }}"
      x-data="fechasProyecto({{ Js::from($estado) }})">
    @csrf
    @unless($crear) @method('put') @endunless

    <div class="d-grid gap-4">
        @if($crear)
            <section class="panel">
                <div class="panel-head"><h2>Proyecto</h2><span class="ayuda">{{ $presupuesto->cliente_empresa ?: $presupuesto->cliente_nombre }} · {{ $presupuesto->folio }}</span></div>
                <div class="panel-body row g-3">
                    <div class="col-12">
                        <label class="form-label">Tipo</label>
                        <div class="segmento">
                            @foreach(config('vandu.proyectos') as $k => $m)
                                <a href="{{ route('admin.proyectos.create', [$presupuesto, 'tipo' => $k]) }}" class="{{ $tipo === $k ? 'activo' : '' }}"><i class="bi {{ $m['icono'] }}"></i> {{ $m['nombre'] }}</a>
                            @endforeach
                        </div>
                        <input type="hidden" name="tipo" value="{{ $tipo }}">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="nombre">Nombre del proyecto</label>
                        <input name="nombre" id="nombre" class="form-control" value="{{ old('nombre', $nombre) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="monto_total">Monto total</label>
                        <div class="input-group"><span class="input-group-text">$</span>
                            <input type="number" step="0.01" min="0" name="monto_total" id="monto_total" class="form-control num" value="{{ old('monto_total', $monto) }}" required></div>
                    </div>
                </div>
            </section>
        @endif

        @if($crear || $forma === 'credito')
        <section class="panel">
            <div class="panel-head"><h2>Forma de pago</h2><span class="ayuda" x-show="forma === 'credito'" x-cloak>Para empresas que pagan diferido</span></div>
            <div class="panel-body herramientas">
                @if($crear)
                    <div>
                        <div class="segmento" role="radiogroup" aria-label="Forma de pago">
                            <a href="#" role="radio" :aria-checked="forma === 'contado'" :class="forma === 'contado' && 'activo'" @click.prevent="cambiarForma('contado')"><i class="bi bi-cash-coin"></i> Contado · {{ collect($metodo['pagos'])->pluck('concepto')->join(' y ') }}</a>
                            <a href="#" role="radio" :aria-checked="forma === 'credito'" :class="forma === 'credito' && 'activo'" @click.prevent="cambiarForma('credito')"><i class="bi bi-hourglass-split"></i> Crédito / pago diferido</a>
                        </div>
                        <input type="hidden" name="forma_pago" :value="forma">
                    </div>
                @endif
                <div x-show="forma === 'credito'" x-cloak>
                    <label class="form-label" for="dias_credito">Días de crédito</label>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <div class="input-group" style="width: 130px">
                            <input type="number" min="1" max="365" name="dias_credito" id="dias_credito" class="form-control num" x-model.number="dias" :disabled="forma !== 'credito'">
                            <span class="input-group-text">días</span>
                        </div>
                        <div class="chips" role="group" aria-label="Atajos de días de crédito">
                            @foreach(config('vandu.credito.dias') as $dc)
                                <button type="button" :class="dias === {{ $dc }} && 'activo'" @click="dias = {{ $dc }}; venceManual = false">{{ $dc }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
                <p class="secundario mb-0" style="flex-basis: 100%; font-size: 13.5px" x-show="forma === 'credito'" x-cloak>
                    Sin anticipo: las etapas avanzan sin esperar pagos y el cobro vence <b x-text="dias + ' días'"></b> después de la entrega<span x-show="pagos[0] && pagos[0].vence_el" x-text="' (' + fechaLarga(pagos[0] && pagos[0].vence_el) + ')'"></span>.
                </p>
            </div>
        </section>
        @endif

        <section class="panel">
            <div class="panel-head"><h2>Proponer fechas</h2><span class="ayuda">Opcional: llena la tabla de un jalón y luego ajusta</span></div>
            <div class="panel-body herramientas">
                <div>
                    <label class="form-label" for="p-inicio">Empieza el</label>
                    <input type="date" id="p-inicio" class="form-control num" x-model="inicio">
                </div>
                <label class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" x-model="terminado">
                    <span class="form-check-label">Ya se pagó y terminó</span>
                </label>
                <div x-show="terminado" x-cloak>
                    <label class="form-label" for="p-fin">Terminó el</label>
                    <input type="date" id="p-fin" class="form-control num" x-model="fin" :max="hoy">
                </div>
                <button type="button" class="btn btn-borde" @click="proponerFechas()"><i class="bi bi-magic me-1"></i> Proponer fechas</button>
                <span class="secundario" x-show="mensaje" x-text="mensaje" x-cloak></span>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Etapas</h2><span class="ayuda">Deja vacío lo que aún no tenga fecha</span></div>
            <div class="table-responsive">
                <table class="fechas">
                    <thead><tr><th>Etapa</th><th>Inicio</th><th>Fin</th><th>Estado</th><th>Completada el</th></tr></thead>
                    <tbody>
                        <template x-for="(e, i) in etapas" :key="i">
                            <tr :class="{ completa: e.estado === 'completada' }">
                                <td class="nom">
                                    <input class="form-control fw-medium" :name="`etapas[${i}][nombre]`" x-model="e.nombre" required :aria-label="'Nombre de la etapa ' + (i + 1)">
                                    <div class="gate" x-show="bloqueos(e).length" x-text="'Requiere: ' + bloqueos(e).join(' y ')"></div>
                                </td>
                                <td data-k="Inicio">
                                    <input type="date" class="form-control num" :name="`etapas[${i}][fecha_inicio]`" x-model="e.fecha_inicio" @change="alCambiarInicio(e)" :aria-label="(e.es_fecha ? 'Fecha de ' : 'Inicio de ') + e.nombre">
                                </td>
                                <td data-k="Fin">
                                    <template x-if="!e.es_fecha"><input type="date" class="form-control num" :name="`etapas[${i}][fecha_fin]`" x-model="e.fecha_fin" :min="e.fecha_inicio" @change="alCambiarFin(e)" :aria-label="'Fin de ' + e.nombre"></template>
                                    <template x-if="e.es_fecha"><span class="secundario">Un solo día</span></template>
                                </td>
                                <td data-k="Estado">
                                    <select class="form-select" :name="`etapas[${i}][estado]`" x-model="e.estado" @change="if (e.estado === 'completada' && !e.completada_el) e.completada_el = e.fecha_fin || e.fecha_inicio || hoy" :aria-label="'Estado de ' + e.nombre">
                                        @foreach(\App\Models\ProyectoEtapa::ESTADOS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                                    </select>
                                </td>
                                <td data-k="Completada el">
                                    <input type="date" class="form-control num" :name="`etapas[${i}][completada_el]`" x-model="e.completada_el" :max="hoy" :disabled="e.estado !== 'completada'" :aria-label="'Completada el, ' + e.nombre">
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Pagos</h2><span class="ayuda" x-text="'Total ' + dinero(pagos.reduce((s, p) => s + (+p.monto || 0), 0))"></span></div>
            <div class="table-responsive">
                <table class="fechas">
                    <thead><tr><th>Concepto</th><th>Monto</th><th x-show="forma === 'credito'">Vence el</th><th>Pagado el</th><th>Método de pago</th><th>Referencia</th></tr></thead>
                    <tbody>
                        <template x-for="(p, i) in pagos" :key="i">
                            <tr :class="{ completa: !!p.pagado_el }">
                                <td class="nom"><span class="fw-medium" x-text="p.concepto"></span> <span class="secundario num" x-text="'· ' + (+p.porcentaje) + '%'"></span>
                                    <div class="gate" x-show="p.antes_de" x-text="'Antes de ' + nombreEtapa(p.antes_de)"></div></td>
                                <td data-k="Monto" style="min-width:150px"><div class="input-group"><span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0" class="form-control num" :name="`pagos[${i}][monto]`" x-model="p.monto" required :aria-label="'Monto de ' + p.concepto"></div></td>
                                <td data-k="Vence el" x-show="forma === 'credito'">
                                    <input type="date" class="form-control num" :name="`pagos[${i}][vence_el]`" x-model="p.vence_el" @input="venceManual = true" :aria-label="'Vencimiento de ' + p.concepto">
                                    <div class="gate" x-show="!venceManual">Entrega + <span x-text="dias"></span> días</div>
                                </td>
                                <td data-k="Pagado el">
                                    <input type="date" class="form-control num" :name="`pagos[${i}][pagado_el]`" x-model="p.pagado_el" :max="hoy" :aria-label="'Fecha de pago de ' + p.concepto">
                                    <div class="gate" x-show="!p.pagado_el">Vacío = pendiente</div>
                                </td>
                                <td data-k="Método de pago" style="min-width:170px"><select class="form-select" :name="`pagos[${i}][metodo]`" x-model="p.metodo" :aria-label="'Método de pago de ' + p.concepto">
                                    <option value="">Sin especificar</option>
                                    @foreach(config('vandu.metodos_pago') as $mk => $ml)<option value="{{ $mk }}">{{ $ml }}</option>@endforeach
                                </select></td>
                                <td data-k="Referencia"><input class="form-control" :name="`pagos[${i}][referencia]`" x-model="p.referencia" placeholder="Transferencia, folio…" :aria-label="'Referencia de ' + p.concepto"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="barra-acciones">
        <a href="{{ $cancelar }}" class="btn btn-borde">Cancelar</a>
        <button class="btn btn-primario px-4">{{ $crear ? 'Crear proyecto' : 'Guardar fechas' }}</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
function fechasProyecto(init) {
    const iso = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    const leer = (s) => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
    const sumarHabiles = (d, n) => { const r = new Date(d); while (n > 0) { r.setDate(r.getDate() + 1); if (r.getDay() % 6) n--; } return r; };
    const habil = (d) => { const r = new Date(d); while (!(r.getDay() % 6)) r.setDate(r.getDate() + 1); return r; };
    return {
        ...init, mensaje: '',
        init() {
            if (this.proponer) this.proponerFechas(true);
            // El vencimiento del crédito sigue a la fecha de entrega mientras no lo cambies a mano
            this.$watch('dias', () => this.calcularVence());
            this.$watch('etapas', () => this.calcularVence(), { deep: true });
            this.calcularVence();
        },
        cambiarForma(f) {
            if (f === this.forma) return;
            this.forma = f;
            this.pagos = f === 'credito'
                ? [{ ...this.pagoCredito, monto: +(document.getElementById('monto_total')?.value || this.pagoCredito.monto) }]
                : this.pagosContado.map((p) => ({ ...p }));
            this.venceManual = false;
            this.calcularVence();
        },
        calcularVence() {
            if (this.forma !== 'credito' || this.venceManual || !this.pagos.length) return;
            const ult = [...this.etapas].reverse().find((e) => e.fecha_fin || e.fecha_inicio);
            const base = ult ? (ult.fecha_fin || ult.fecha_inicio) : this.inicio;
            if (!base || !this.dias) return;
            const d = leer(base); d.setDate(d.getDate() + (+this.dias || 0));
            this.pagos.forEach((p) => { if (!p.pagado_el) p.vence_el = iso(d); });
        },
        fechaLarga(s) { if (!s) return ''; return leer(s).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' }); },
        dinero(n) { return '$' + (+n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        nombreEtapa(clave) { return (this.etapas.find((e) => e.clave === clave) || {}).nombre || ''; },
        // Mantiene coherentes las fechas mientras editas a mano
        alCambiarInicio(e) {
            if (e.fecha_fin && e.fecha_inicio && e.fecha_fin < e.fecha_inicio) e.fecha_fin = e.fecha_inicio;
            if (e.es_fecha && e.estado === 'completada' && e.fecha_inicio) e.completada_el = e.fecha_inicio <= this.hoy ? e.fecha_inicio : this.hoy;
        },
        alCambiarFin(e) {
            if (e.estado === 'completada' && e.fecha_fin) e.completada_el = e.fecha_fin <= this.hoy ? e.fecha_fin : this.hoy;
        },
        bloqueos(e) { return this.pagos.filter((p) => p.antes_de === e.clave).map((p) => p.concepto); },
        proponerFechas(silencioso = false) {
            if (!this.inicio) { this.mensaje = 'Pon la fecha de inicio.'; return; }
            const ini = leer(this.inicio);
            if (this.terminado) {
                if (!this.fin || this.fin < this.inicio) { this.mensaje = 'La fecha de fin debe ser igual o posterior al inicio.'; return; }
                // Reparte las etapas entre inicio y fin según su duración estimada
                const fin = leer(this.fin), total = this.etapas.reduce((s, e) => s + Math.max(1, +e.dias || 1), 0);
                const dias = Math.round((fin - ini) / 864e5); let acum = 0;
                this.etapas.forEach((e, i) => {
                    const desde = new Date(ini); desde.setDate(desde.getDate() + Math.floor(acum / total * dias));
                    acum += Math.max(1, +e.dias || 1);
                    let hasta = i === this.etapas.length - 1 ? new Date(fin) : new Date(ini);
                    if (i < this.etapas.length - 1) hasta.setDate(hasta.getDate() + Math.max(0, Math.floor(acum / total * dias) - 1));
                    if (hasta < desde) hasta = new Date(desde);
                    e.fecha_inicio = iso(e.es_fecha ? hasta : desde); e.fecha_fin = e.es_fecha ? '' : iso(hasta);
                    e.estado = 'completada'; e.completada_el = iso(hasta);
                });
                this.pagos.forEach((p, i) => {
                    const etapa = this.etapas.find((e) => e.clave === p.antes_de);
                    p.pagado_el = this.forma === 'credito' ? this.fin : (i === 0 ? this.inicio : (etapa ? etapa.fecha_inicio : this.fin));
                });
                this.mensaje = 'Listo: todo completado y pagado. Ajusta lo que no coincida.';
            } else {
                // Etapas en días hábiles una tras otra; las de un solo día quedan por agendar
                let cursor = habil(ini);
                this.etapas.forEach((e) => {
                    if (e.es_fecha) { e.fecha_inicio = ''; e.fecha_fin = ''; }
                    else {
                        const hasta = sumarHabiles(cursor, Math.max(1, +e.dias || 1) - 1);
                        e.fecha_inicio = iso(cursor); e.fecha_fin = iso(hasta); cursor = sumarHabiles(hasta, 1);
                    }
                    e.estado = 'pendiente'; e.completada_el = '';
                });
                this.pagos.forEach((p) => { p.pagado_el = ''; });
                this.mensaje = silencioso ? '' : 'Fechas propuestas. Ajusta lo que necesites.';
            }
        },
    };
}
</script>
@endpush
