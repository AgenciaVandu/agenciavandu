@extends('admin.layout')
@php
    $crear = $modo === 'crear';
    $metodo = config("vandu.proyectos.$tipo");
    $hoy = now(config('vandu.zona_horaria'))->toDateString();
    // Si hubo error de validación, se vuelve a mostrar lo capturado
    $etapasIni = collect(old('etapas', $etapas->all()))->values()->map(fn ($e, $i) => array_merge($etapas[$i] ?? [], $e))->all();
    $pagosIni = collect(old('pagos', $pagos->all()))->values()->map(fn ($p, $i) => array_merge($pagos[$i] ?? [], $p))->all();
    $estado = [
        'etapas'  => $etapasIni,
        'pagos'   => $pagosIni,
        'inicio'  => old('inicio', $inicio ?? $hoy),
        'fin'     => old('fin', now(config('vandu.zona_horaria'))->subDay()->toDateString()),
        'terminado' => (bool) old('terminado', false),
        'proponer'  => $crear && ! old('etapas'),
        'hoy'     => $hoy,
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
                                <td>
                                    <input type="date" class="form-control num" :name="`etapas[${i}][fecha_inicio]`" x-model="e.fecha_inicio" @change="alCambiarInicio(e)" :aria-label="(e.es_fecha ? 'Fecha de ' : 'Inicio de ') + e.nombre">
                                </td>
                                <td>
                                    <template x-if="!e.es_fecha"><input type="date" class="form-control num" :name="`etapas[${i}][fecha_fin]`" x-model="e.fecha_fin" :min="e.fecha_inicio" @change="alCambiarFin(e)" :aria-label="'Fin de ' + e.nombre"></template>
                                    <template x-if="e.es_fecha"><span class="secundario">Un solo día</span></template>
                                </td>
                                <td>
                                    <select class="form-select" :name="`etapas[${i}][estado]`" x-model="e.estado" @change="if (e.estado === 'completada' && !e.completada_el) e.completada_el = e.fecha_fin || e.fecha_inicio || hoy" :aria-label="'Estado de ' + e.nombre">
                                        @foreach(\App\Models\ProyectoEtapa::ESTADOS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                                    </select>
                                </td>
                                <td>
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
                    <thead><tr><th>Concepto</th><th>Monto</th><th>Pagado el</th><th>Referencia</th></tr></thead>
                    <tbody>
                        <template x-for="(p, i) in pagos" :key="i">
                            <tr :class="{ completa: !!p.pagado_el }">
                                <td class="nom"><span class="fw-medium" x-text="p.concepto"></span> <span class="secundario num" x-text="'· ' + (+p.porcentaje) + '%'"></span>
                                    <div class="gate" x-show="p.antes_de" x-text="'Antes de ' + nombreEtapa(p.antes_de)"></div></td>
                                <td style="min-width:150px"><div class="input-group"><span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0" class="form-control num" :name="`pagos[${i}][monto]`" x-model="p.monto" required :aria-label="'Monto de ' + p.concepto"></div></td>
                                <td>
                                    <input type="date" class="form-control num" :name="`pagos[${i}][pagado_el]`" x-model="p.pagado_el" :max="hoy" :aria-label="'Fecha de pago de ' + p.concepto">
                                    <div class="gate" x-show="!p.pagado_el">Vacío = pendiente</div>
                                </td>
                                <td><input class="form-control" :name="`pagos[${i}][referencia]`" x-model="p.referencia" placeholder="Transferencia, folio…" :aria-label="'Referencia de ' + p.concepto"></td>
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
        init() { if (this.proponer) this.proponerFechas(true); },
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
                    p.pagado_el = i === 0 ? this.inicio : (etapa ? etapa.fecha_inicio : this.fin);
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
