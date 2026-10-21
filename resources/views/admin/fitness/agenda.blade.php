@extends('admin.layout')
@section('titulo', 'Agenda de clases')
@include('admin.fitness._estilos')

@php
    use App\Support\Fitness\Calendario;
    $tz = config('vandu.zona_horaria');
    $ahora = now($tz);
    $hoy = $ahora->toDateString();
    $u = auth()->user();
    $programa = $u->puede('clases');
    $q = fn (array $extra) => route('admin.clases.index', array_filter(array_merge(['semana' => $semana ?: null, 'sucursal' => $sucursal, 'coach' => $soloMias ? null : $coach], $extra), fn ($v) => $v !== null));
    $pct = $resumen['lugares'] ? round($resumen['ocupados'] / $resumen['lugares'] * 100) : 0;
    $px = Calendario::PX_HORA;
    $alto = ($horaFin - $horaIni) * $px;
    $diaAbierta = $abierta ? $abierta['c']->inicio->toDateString() : null;
    $claves = array_keys($dias->all());
    $diaInicial = array_search($diaAbierta ?? $hoy, $claves, true);
    $ahoraTop = (int) round((($ahora->hour * 60 + $ahora->minute) - $horaIni * 60) * $px / 60);
    // Hora que se propone al tocar "Programar clase": la siguiente hora en punto
    $sigHora = $ahora->copy()->addHour()->startOfHour();
@endphp

@push('head')
<style>
    .ag-barra { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 14px; }
    .ag-barra .rango { font-weight: 600; font-size: 17px; margin-right: auto; }
    .ag-barra .rango small { display: block; font-size: 13px; font-weight: 400; color: var(--muted); }
    .ag-cal { background: var(--surface, #fff); border: 1px solid var(--line); border-radius: 14px; overflow: hidden; }
    .ag-scroll { overflow: auto; max-height: max(480px, calc(100vh - 290px)); position: relative; }
    .ag-rejilla { display: grid; grid-template-columns: 52px repeat(7, minmax(110px, 1fr)); min-width: 820px; }
    .ag-cab { position: sticky; top: 0; z-index: 4; background: var(--surface, #fff); border-bottom: 1px solid var(--line); padding: 8px 6px; text-align: center; font-size: 13px; color: var(--muted); }
    .ag-cab b { display: block; font-size: 18px; color: var(--text); font-weight: 600; line-height: 1.15; }
    .ag-cab.hoy b { color: #fff; background: var(--ink); border-radius: 99px; width: 32px; height: 32px; line-height: 32px; margin: 0 auto; }
    .ag-cab .n { font-size: 11.5px; }
    .ag-esquina { position: sticky; top: 0; left: 0; z-index: 5; background: var(--surface, #fff); border-bottom: 1px solid var(--line); }
    .ag-horas { position: relative; height: var(--alto); border-right: 1px solid var(--line); }
    .ag-horas span { position: absolute; right: 8px; font-size: 11.5px; color: var(--muted); transform: translateY(-50%); }
    .ag-dia { position: relative; height: var(--alto); border-right: 1px solid var(--line);
              background-image: linear-gradient(to bottom, var(--line) 1px, transparent 1px), linear-gradient(to bottom, #F3F4F6 1px, transparent 1px);
              background-size: 100% {{ $px }}px, 100% {{ $px / 2 }}px; }
    .ag-dia:last-child { border-right: 0; }
    .ag-dia.pasado { background-color: #FAFAFB; }
    .ag-dia.puede { cursor: copy; }
    .ag-ahora { position: absolute; left: 0; right: 0; height: 2px; background: var(--red, #DC2626); z-index: 3; pointer-events: none; }
    .ag-ahora::before { content: ''; position: absolute; left: -5px; top: -4px; width: 10px; height: 10px; border-radius: 50%; background: inherit; }
    .ag-c { position: absolute; z-index: 2; display: block; overflow: hidden; text-decoration: none; color: var(--text); border-radius: 8px; padding: 4px 7px;
            background: color-mix(in srgb, var(--c) 14%, white); border-left: 3px solid var(--c); font-size: 12px; line-height: 1.25; cursor: pointer; transition: box-shadow .12s; }
    .ag-c:hover, .ag-c.abierta { box-shadow: 0 0 0 2px var(--c), 0 6px 16px rgba(16,24,40,.12); z-index: 3; }
    .ag-c .t { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ag-c .h { color: var(--text-2); white-space: nowrap; }
    .ag-c .o { margin-top: 2px; display: flex; align-items: center; gap: 5px; color: var(--text-2); white-space: nowrap; }
    .ag-c .o .b { flex: 1; height: 4px; border-radius: 99px; background: rgba(0,0,0,.08); overflow: hidden; min-width: 18px; }
    .ag-c .o .b i { display: block; height: 100%; background: var(--c); }
    .ag-c.llena .o { color: var(--red); font-weight: 600; }
    .ag-c.pasada { opacity: .6; }
    .ag-c.cancelada { background: repeating-linear-gradient(135deg, #F3F4F6 0 6px, #fff 6px 12px); border-left-color: #9CA3AF; color: var(--muted); }
    .ag-c.cancelada .t { text-decoration: line-through; }
    .ag-c.corta .o { display: none; }
    .ag-c.mini .h { display: none; }
    .ag-dias-m { display: none; }
    @media (max-width: 767.98px) {
        .ag-scroll { max-height: none; overflow: visible; }
        .ag-rejilla { grid-template-columns: 46px minmax(0, 1fr); min-width: 0; }
        .ag-rejilla > .ag-cab, .ag-rejilla > .ag-esquina { display: none; }
        .ag-rejilla > .ag-dia:not(.visible) { display: none; }
        .ag-dias-m { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 4px; margin-bottom: 10px; }
        .ag-dias-m button { border: 1px solid var(--line); background: var(--surface, #fff); border-radius: 12px; padding: 6px 0; font-size: 11.5px; color: var(--muted); line-height: 1.2; }
        .ag-dias-m button b { display: block; font-size: 16px; color: var(--text); }
        .ag-dias-m button .p { display: block; height: 5px; }
        .ag-dias-m button .p i { display: inline-block; width: 5px; height: 5px; border-radius: 50%; background: var(--ink); }
        .ag-dias-m button.sel { background: var(--ink); border-color: var(--ink); color: #C9CDD6; }
        .ag-dias-m button.sel b { color: #fff; } .ag-dias-m button.sel .p i { background: var(--green); }
        .ag-dias-m button.hoy:not(.sel) b { color: var(--green-ink); }
        .ag-c { font-size: 13px; padding: 6px 9px; }
    }

    /* Ventana para programar */
    .ag-velo { position: fixed; inset: 0; background: rgba(15,18,25,.38); z-index: 1060; display: grid; place-items: center; padding: 16px; }
    .ag-modal { background: var(--surface, #fff); border-radius: 16px; width: min(560px, 100%); max-height: calc(100vh - 32px); overflow-y: auto; box-shadow: 0 24px 60px rgba(16,24,40,.25); }
    .ag-modal .cab { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px 6px; }
    .ag-modal .cab h2 { font-size: 18px; margin: 0; }
    .ag-modal .cuerpo { padding: 8px 20px 20px; display: grid; gap: 14px; }
    .ag-tipos { display: flex; flex-wrap: wrap; gap: 6px; }
    .ag-tipos label { cursor: pointer; }
    .ag-tipos input { position: absolute; opacity: 0; pointer-events: none; }
    .ag-tipos span { display: inline-flex; align-items: center; gap: 7px; padding: 6px 12px; border: 1px solid var(--line); border-radius: 99px; font-size: 13.5px; }
    .ag-tipos span i { width: 10px; height: 10px; border-radius: 50%; background: var(--c); }
    .ag-tipos input:checked + span { border-color: var(--c); box-shadow: inset 0 0 0 1px var(--c); background: color-mix(in srgb, var(--c) 10%, white); font-weight: 550; }
    .ag-tipos input:focus-visible + span { outline: 2px solid var(--ink); outline-offset: 2px; }
    .ag-g4 { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
    @media (max-width: 575.98px) { .ag-g4 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .ag-semana { display: flex; gap: 6px; flex-wrap: wrap; }
    .ag-semana label { cursor: pointer; }
    .ag-semana input { position: absolute; opacity: 0; pointer-events: none; }
    .ag-semana span { display: grid; place-items: center; width: 38px; height: 38px; border-radius: 50%; border: 1px solid var(--line); font-size: 13px; font-weight: 600; }
    .ag-semana input:checked + span { background: var(--ink); color: #fff; border-color: var(--ink); }
    .ag-semana input:focus-visible + span { outline: 2px solid var(--ink); outline-offset: 2px; }
    .ag-repite { background: var(--sunken); border-radius: 12px; padding: 12px; display: grid; gap: 10px; }

    /* Panel lateral de la clase */
    .ag-lado { position: fixed; top: 0; right: 0; bottom: 0; width: min(440px, 100%); background: var(--surface, #fff); z-index: 1050; box-shadow: -12px 0 40px rgba(16,24,40,.18); display: flex; flex-direction: column; }
    .ag-lado .top { padding: 16px 18px 12px; border-bottom: 1px solid var(--line); border-top: 5px solid var(--c); }
    .ag-lado .top h2 { font-size: 19px; margin: 0; }
    .ag-lado .cuerpo { overflow-y: auto; flex: 1; }
    .ag-lado .sec { padding: 14px 18px; border-bottom: 1px solid var(--line); }
    .ag-lado .sec > .tt { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; }
    .ag-lado .persona { display: flex; align-items: center; gap: 10px; padding: 7px 0; }
    .ag-lado .persona + .persona { border-top: 1px solid var(--line); }
    .ag-lado .persona .fx-avatar { width: 34px; height: 34px; font-size: 12px; }
    .ag-lado .persona .acc { display: flex; gap: 4px; margin-left: auto; }
    .ag-lado .persona .acc form { margin: 0; }
    .ag-lado .persona .acc .btn { padding: 3px 8px; }
    .ag-lado .persona .acc .on-si { background: var(--green-ink, #15803D); color: #fff; border-color: transparent; }
    .ag-lado .persona .acc .on-no { background: var(--red); color: #fff; border-color: transparent; }
    .ag-busca { position: relative; }
    .ag-busca .res { border: 1px solid var(--line); border-radius: 10px; margin-top: 6px; overflow: hidden; }
    .ag-busca .res button { display: flex; width: 100%; text-align: left; gap: 8px; align-items: center; border: 0; background: none; padding: 8px 10px; font-size: 14px; }
    .ag-busca .res button + button { border-top: 1px solid var(--line); }
    .ag-busca .res button:hover, .ag-busca .res button:focus { background: var(--sunken); }
    .ag-velo-lado { position: fixed; inset: 0; z-index: 1049; background: rgba(15,18,25,.18); }
</style>
@endpush

@section('contenido')
<div x-data="agendaFx({{ Js::from([
        'clave' => 'ag-' . $semana,
        'diaInicial' => $diaInicial === false ? 0 : $diaInicial,
        'horaIni' => $horaIni, 'px' => $px, 'ahora' => $ahora->format('Y-m-d H:i'),
        'tipos' => $tipos->mapWithKeys(fn ($t) => [$t->id => ['duracion' => $t->duracion, 'cupo' => $t->cupo]]),
        'abrirNueva' => $errors->any() && old('volver') === 'agenda',
        'viejo' => ['fecha' => old('fecha'), 'hora' => old('hora'), 'tipo' => (string) old('clase_tipo_id'), 'duracion' => (int) old('duracion', 60), 'cupo' => (int) old('cupo', 12),
                    'repetir' => (bool) old('repetir'), 'dias' => array_map('strval', (array) old('dias', []))],
        'scrollA' => max(0, ($abierta ? (int) $abierta['c']->inicio->format('G') : min(max($ahora->hour - 1, 6), 18)) - $horaIni) * $px,
    ]) }})" x-init="iniciar()" @keydown.escape.window="nueva = false">

<div class="page-head">
    <div>
        <h1>{{ $soloMias ? 'Mis clases' : 'Agenda' }}</h1>
        <p class="sub">{{ $resumen['clases'] }} {{ $resumen['clases'] === 1 ? 'clase' : 'clases' }} esta semana · {{ $resumen['ocupados'] }} de {{ $resumen['lugares'] }} lugares reservados ({{ $pct }}%)</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        @if($u->puede('asistencia'))<a href="{{ route('admin.escanear') }}" class="btn btn-borde"><i class="bi bi-qr-code-scan me-1"></i> Escanear</a>@endif
        @if($programa)<button type="button" class="btn btn-primario" @click="abrirNueva('{{ $sigHora->toDateString() }}', '{{ $sigHora->format('H:i') }}')"><i class="bi bi-plus-lg me-1"></i> Programar clase</button>@endif
    </div>
</div>
@include('admin.fitness._submenu')

<div class="ag-barra">
    <div class="rango">{{ ucfirst($lunes->locale('es')->isoFormat('D [de] MMMM')) }} – {{ $domingo->locale('es')->isoFormat('D [de] MMMM') }}
        @if($programa)<small class="d-none d-md-block">Toca un espacio vacío para programar una clase ahí; toca una clase para ver su lista e inscribir.</small>@endif</div>
    @if(! $soloMias && ($sucursales->count() > 1 || $coaches->count() > 1))
        <form method="get" class="d-flex flex-wrap gap-2">
            @if($semana)<input type="hidden" name="semana" value="{{ $semana }}">@endif
            @if($sucursales->count() > 1)
                <select name="sucursal" class="form-select form-select-sm w-auto" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()" aria-label="Sucursal"><option value="">Todas las sucursales</option>
                    @foreach($sucursales as $s)<option value="{{ $s->id }}" @selected($sucursal === $s->id)>{{ $s->nombre }}</option>@endforeach</select>
            @endif
            @if($coaches->count() > 1)
                <select name="coach" class="form-select form-select-sm w-auto" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()" aria-label="Entrenador"><option value="">Todos los entrenadores</option>
                    @foreach($coaches as $co)<option value="{{ $co->id }}" @selected($coach === $co->id)>{{ $co->name }}</option>@endforeach</select>
            @endif
        </form>
    @endif
    <div class="d-flex align-items-center gap-1">
        <a class="btn btn-borde btn-icono" href="{{ $q(['semana' => $semana - 1]) }}" aria-label="Semana anterior"><i class="bi bi-chevron-left"></i></a>
        <a class="btn btn-borde btn-sm px-3" href="{{ $q(['semana' => null]) }}" @if(! $semana) aria-current="true" @endif>Hoy</a>
        <a class="btn btn-borde btn-icono" href="{{ $q(['semana' => $semana + 1]) }}" aria-label="Semana siguiente"><i class="bi bi-chevron-right"></i></a>
    </div>
</div>

{{-- Celular: un día a la vez --}}
<div class="ag-dias-m" role="tablist" aria-label="Días de la semana">
    @foreach($dias as $fecha => $d)
        <button type="button" role="tab" :class="{ sel: dia === {{ $loop->index }} }" :aria-selected="dia === {{ $loop->index }}" class="{{ $fecha === $hoy ? 'hoy' : '' }}" @click="elegirDia({{ $loop->index }})">
            {{ ucfirst(rtrim($d['fecha']->locale('es')->isoFormat('ddd'), '.')) }}<b class="num">{{ $d['fecha']->day }}</b>
            <span class="p">@if($d['clases']->where('estado', 'programada')->isNotEmpty())<i></i>@endif</span>
        </button>
    @endforeach
</div>

<div class="ag-cal">
    <div class="ag-scroll" x-ref="scroll" @scroll.passive="guardarScroll()">
        <div class="ag-rejilla" style="--alto: {{ $alto }}px">
            <div class="ag-esquina"></div>
            @foreach($dias as $fecha => $d)
                <div class="ag-cab {{ $fecha === $hoy ? 'hoy' : '' }}">{{ ucfirst(rtrim($d['fecha']->locale('es')->isoFormat('ddd'), '.')) }}<b class="num">{{ $d['fecha']->day }}</b>
                    <span class="n">{{ ($n = $d['clases']->where('estado', 'programada')->count()) ? $n . ' ' . ($n === 1 ? 'clase' : 'clases') : ' ' }}</span></div>
            @endforeach

            <div class="ag-horas" aria-hidden="true">
                @for($h = $horaIni + 1; $h < $horaFin; $h++)<span class="num" style="top: {{ ($h - $horaIni) * $px }}px">{{ $h }}:00</span>@endfor
            </div>
            @foreach($dias as $fecha => $d)
                @php $puede = $programa && $fecha >= $hoy; @endphp
                <div class="ag-dia {{ $fecha < $hoy ? 'pasado' : '' }} {{ $puede ? 'puede' : '' }}" :class="{ visible: dia === {{ $loop->index }} }"
                     @if($puede) @click.self="tocarDia($event, '{{ $fecha }}')" title="Toca para programar una clase" @endif
                     aria-label="{{ $d['fecha']->locale('es')->isoFormat('dddd D [de] MMMM') }}">
                    @if($fecha === $hoy && $ahoraTop >= 0 && $ahoraTop <= $alto)<div class="ag-ahora" style="top: {{ $ahoraTop }}px"></div>@endif
                    @foreach($d['bloques'] as $b)
                        @php
                            $c = $b['clase'];
                            $ancho = 100 / $b['carriles'];
                            $llena = ! $c->cancelada && $c->ocupadas_count >= $c->cupo;
                        @endphp
                        <a href="{{ $q(['clase' => $c->id]) }}" @click.prevent="abrirClase($el.href)"
                           class="ag-c {{ $c->cancelada ? 'cancelada' : '' }} {{ $c->pasada ? 'pasada' : '' }} {{ $llena ? 'llena' : '' }} {{ $b['alto'] < 50 ? 'corta' : '' }} {{ $b['alto'] < 32 ? 'mini' : '' }} {{ $abierta && $abierta['c']->id === $c->id ? 'abierta' : '' }}"
                           style="--c: {{ $c->color }}; top: {{ $b['top'] }}px; height: {{ $b['alto'] }}px; left: calc({{ $ancho * $b['carril'] }}% + 2px); width: calc({{ $ancho }}% - 4px)"
                           title="{{ $c->nombre }} · {{ $c->horario }} · {{ $c->ocupadas_count }}/{{ $c->cupo }}">
                            <div class="t">@if($c->serie_id)<i class="bi bi-arrow-repeat me-1" aria-label="Horario fijo"></i>@endif{{ $c->nombre }}</div>
                            <div class="h num">{{ $c->horario }}{{ $c->coach ? ' · ' . $c->coach->primer_nombre : '' }}</div>
                            @if($c->cancelada)<div class="o">Cancelada</div>
                            @else<div class="o num"><span class="b"><i style="width: {{ $c->cupo ? min(100, round($c->ocupadas_count / $c->cupo * 100)) : 0 }}%"></i></span>{{ $c->ocupadas_count }}/{{ $c->cupo }}</div>@endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>
</div>
@if($programa && $dias->every(fn ($d) => $d['clases']->isEmpty()))
    <p class="secundario small mt-2">No hay clases esta semana. Toca cualquier espacio del calendario para programar la primera, o marca que se repita cada semana para armar tu horario fijo.</p>
@endif

@if($programa)@include('admin.fitness._agenda_nueva')@endif
@if($abierta)@include('admin.fitness._agenda_clase', $abierta)@endif
</div>
@endsection

@push('scripts')
<script>
window.agendaFx = function (cfg) {
    window.__agenda = window.__agenda || {};
    const mem = window.__agenda[cfg.clave] || {};
    return {
        dia: mem.dia ?? cfg.diaInicial,
        nueva: cfg.abrirNueva,
        f: { fecha: '', hora: '', tipo: '', duracion: 60, cupo: 12, repetir: false, dias: [] },
        iniciar() {
            const s = this.$refs.scroll;
            if (s) s.scrollTop = mem.scroll ?? cfg.scrollA;
            if (cfg.abrirNueva) this.f = Object.assign(this.f, cfg.viejo);
        },
        guardarScroll() { (window.__agenda[cfg.clave] ||= {}).scroll = this.$refs.scroll.scrollTop; },
        elegirDia(i) { this.dia = i; (window.__agenda[cfg.clave] ||= {}).dia = i; },
        abrirClase(url) { this.guardarScroll(); window.vanduRefrescar ? window.vanduRefrescar(url) : (location.href = url); },
        tocarDia(e, fecha) {
            const min = Math.max(0, Math.floor(e.offsetY / cfg.px * 60 / 30) * 30) + cfg.horaIni * 60;
            const hora = String(Math.floor(min / 60)).padStart(2, '0') + ':' + String(min % 60).padStart(2, '0');
            if (fecha + ' ' + hora < cfg.ahora) return;   // ya pasó
            this.abrirNueva(fecha, hora);
        },
        abrirNueva(fecha, hora) {
            const primero = Object.keys(cfg.tipos)[0] || '';
            const dow = ((new Date(fecha + 'T12:00').getDay() + 6) % 7) + 1;
            this.f = { fecha, hora, tipo: this.f.tipo || primero, duracion: 60, cupo: 12, repetir: false, dias: [String(dow)] };
            this.ponerTipo(this.f.tipo);
            this.nueva = true;
            this.$nextTick(() => this.$refs.primero?.focus());
        },
        ponerTipo(id) {
            this.f.tipo = String(id);
            const t = cfg.tipos[id];
            if (t) { this.f.duracion = t.duracion; this.f.cupo = t.cupo; }
        },
        cambiarFecha() {
            const dow = String(((new Date(this.f.fecha + 'T12:00').getDay() + 6) % 7) + 1);
            if (!this.f.dias.length || this.f.dias.length === 1) this.f.dias = [dow];
        },
    };
};
window.agendaClaseFx = function (cfg) {
    return {
        abierto: true, buscar: '', atletas: cfg.atletas,
        get resultados() {
            const q = this.buscar.trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
            const l = q ? this.atletas.filter((a) => a.b.includes(q)) : this.atletas;
            return l.slice(0, 6);
        },
        inscribir(id) { const f = this.$refs.inscribir; f.cliente_id.value = id; f.requestSubmit ? f.requestSubmit() : f.submit(); },
        cerrar() {
            this.abierto = false;
            const u = new URL(location.href); u.searchParams.delete('clase'); history.replaceState(null, '', u.href);
            document.querySelectorAll('.ag-c.abierta').forEach((x) => x.classList.remove('abierta'));
        },
    };
};
</script>
@endpush
