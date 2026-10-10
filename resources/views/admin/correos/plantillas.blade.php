@extends('admin.layout')
@section('titulo', 'Plantillas de correo')

@php
    $donde = ['cliente' => 'Ficha del cliente', 'presupuesto' => 'Cotización', 'proyecto' => 'Proyecto'];
    $fabrica = collect($todas)->filter(fn ($p) => $p['fabrica']);
    $propias = collect($todas)->reject(fn ($p) => $p['fabrica']);
    $esRecordatorio = $clave === 'recordatorio_pago';
    $varsUsables = collect($variables)->filter(fn ($v) => array_intersect($v['para'], array_merge($pl['para'] ?? [], $esRecordatorio ? ['recordatorio'] : [])));
@endphp

@push('head')
<style>
    .pl-grid { display: grid; grid-template-columns: 260px minmax(0, 1fr); gap: 20px; align-items: start; }
    .pl-lista { padding: 8px; }
    .pl-lista .grupo { font-size: 12px; color: var(--muted); font-weight: 500; padding: 10px 10px 6px; }
    .pl-lista a { display: flex; align-items: center; gap: 10px; padding: 9px 10px; border-radius: 9px; color: var(--text); text-decoration: none; font-size: 14.5px; }
    .pl-lista a:hover { background: var(--sunken); }
    .pl-lista a.activo { background: var(--ink); color: #fff; }
    .pl-lista a.activo .etq { background: rgba(255,255,255,.16); color: #fff; }
    .pl-lista a i { width: 18px; text-align: center; color: inherit; opacity: .75; }
    .pl-lista a .n { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pl-lista a.apagada .n { opacity: .5; text-decoration: line-through; }
    .etq { font-size: 11px; padding: 1px 7px; border-radius: 99px; background: var(--sunken); color: var(--text-2); white-space: nowrap; }
    .etq-ed { background: #FFF4D6; color: #8A5A00; }
    .pl-editor { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.05fr); }
    .pl-campos { padding: 20px 22px; display: grid; gap: 14px; align-content: start; }
    .pl-campos textarea { min-height: 230px; font-size: 14.5px; line-height: 1.55; }
    .pl-previa { background: var(--sunken); border-left: 1px solid var(--line); padding: 18px; display: flex; flex-direction: column; gap: 10px; }
    .pl-previa iframe { width: 100%; flex: 1; min-height: 620px; border: 1px solid var(--line); border-radius: 12px; background: #EEF0F3; }
    .pl-previa .asunto { font-size: 13.5px; background: var(--surface); border: 1px solid var(--line); border-radius: 10px; padding: 9px 12px; }
    .pl-previa .asunto b { font-weight: 600; }
    .vars { display: flex; flex-wrap: wrap; gap: 6px; }
    .vars button { border: 1px dashed var(--line-strong); background: var(--surface); border-radius: 8px; padding: 3px 9px; font-size: 13px; color: var(--text-2); font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
    .vars button:hover { border-style: solid; border-color: var(--ink); color: var(--text); }
    .pl-pie { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 14px 22px; border-top: 1px solid var(--line); }
    .chk-donde { display: flex; flex-wrap: wrap; gap: 8px; }
    .chk-donde label { display: inline-flex; gap: 6px; align-items: center; border: 1px solid var(--line-strong); border-radius: 99px; padding: 4px 12px; font-size: 13.5px; cursor: pointer; }
    .chk-donde label:has(input:checked) { background: var(--ink); color: #fff; border-color: var(--ink); }
    .chk-donde input { display: none; }
    .iconos { display: flex; gap: 6px; flex-wrap: wrap; }
    .iconos label { width: 36px; height: 36px; border: 1px solid var(--line-strong); border-radius: 9px; display: grid; place-items: center; cursor: pointer; font-size: 16px; }
    .iconos label:has(input:checked) { background: var(--ink); color: #fff; border-color: var(--ink); }
    .iconos input { display: none; }
    @media (max-width: 1199.98px) { .pl-editor { grid-template-columns: minmax(0, 1fr); } .pl-previa { border-left: 0; border-top: 1px solid var(--line); } .pl-previa iframe { min-height: 520px; } }
    @media (max-width: 991.98px) {
        .pl-grid { grid-template-columns: minmax(0, 1fr); }
        .pl-lista { display: flex; gap: 6px; overflow-x: auto; padding: 8px; scrollbar-width: none; }
        .pl-lista .grupo { display: none; }
        .pl-lista a { flex-shrink: 0; border: 1px solid var(--line); }
        .pl-lista a .n { overflow: visible; }
        .pl-lista form { flex-shrink: 0; }
        .pl-campos { padding: 16px; }
        .pl-pie { padding: 12px 16px; }
    }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <h1>Plantillas de correo</h1>
        <p class="sub">El texto con el que arranca cada correo. Al enviarlo todavía puedes ajustarlo para ese cliente.</p>
    </div>
    <form method="post" action="{{ route('admin.correos.plantillas.store') }}">@csrf
        <button class="btn btn-primario"><i class="bi bi-plus-lg me-1"></i> Nueva plantilla</button>
    </form>
</div>

<div class="pl-grid">
    <nav class="panel pl-lista" aria-label="Plantillas">
        <div class="grupo">De fábrica</div>
        @foreach($fabrica as $k => $p)
            <a href="{{ route('admin.correos.plantillas', ['p' => $k]) }}" class="{{ $k === $clave ? 'activo' : '' }} {{ $p['activa'] ? '' : 'apagada' }}" @if($k === $clave) aria-current="page" @endif>
                <i class="bi {{ $p['icono'] ?? 'bi-envelope' }}"></i><span class="n">{{ $p['nombre'] }}</span>
                @if(! $p['activa'])<span class="etq">Oculta</span>@elseif($p['editada'])<span class="etq etq-ed">Editada</span>@endif
            </a>
        @endforeach
        <div class="grupo">Tuyas</div>
        @forelse($propias as $k => $p)
            <a href="{{ route('admin.correos.plantillas', ['p' => $k]) }}" class="{{ $k === $clave ? 'activo' : '' }} {{ $p['activa'] ? '' : 'apagada' }}" @if($k === $clave) aria-current="page" @endif>
                <i class="bi {{ $p['icono'] }}"></i><span class="n">{{ $p['nombre'] }}</span>
                @if(! $p['activa'])<span class="etq">Oculta</span>@endif
            </a>
        @empty
            <p class="secundario px-2 mb-2" style="font-size:13px">Crea las tuyas: seguimiento, felicitación, aviso de vacaciones…</p>
        @endforelse
    </nav>

    <form method="post" action="{{ route('admin.correos.plantillas.update', $clave) }}" class="panel"
          x-data="editorPlantilla({{ Js::from([
              'previa' => route('admin.correos.plantillas.previa', $clave),
              'asunto' => $pl['asunto'], 'titulo' => $pl['titulo'] ?? '', 'cuerpo' => $pl['cuerpo'], 'boton' => $pl['boton'] ?? '',
              'conBoton' => array_key_exists('boton', $pl) || ! $pl['fabrica'],
              'resumen' => (bool) ($pl['resumen'] ?? false), 'para' => $pl['para'] ?? [],
              'propia' => ! $pl['fabrica'],
              'variables' => array_keys($variables),
          ]) }})">
        @csrf @method('put')
        <div class="panel-head">
            <h2 class="d-flex align-items-center gap-2"><i class="bi {{ $pl['icono'] ?? 'bi-envelope' }} text-secondary"></i> {{ $pl['nombre'] }}</h2>
            <span class="ayuda">
                @if($pl['fabrica'])
                    Se usa en: {{ collect($pl['para'])->map(fn ($d) => $donde[$d])->implode(', ') }}
                @else
                    Plantilla tuya
                @endif
                @if($usos) · enviada {{ $usos }} {{ $usos === 1 ? 'vez' : 'veces' }}@endif
            </span>
        </div>

        <div class="pl-editor">
            <div class="pl-campos">
                <div>
                    <label class="form-label" for="pl-nombre">Nombre de la plantilla</label>
                    <input id="pl-nombre" name="nombre" class="form-control" value="{{ old('nombre', $pl['nombre']) }}" maxlength="60" required>
                </div>

                @unless($pl['fabrica'])
                    <div>
                        <span class="form-label d-block">Ícono</span>
                        <div class="iconos">
                            @foreach($iconos as $ic)
                                <label title="{{ $ic }}"><input type="radio" name="icono" value="{{ $ic }}" @checked(($pl['icono'] ?? 'bi-envelope') === $ic)><i class="bi {{ $ic }}"></i></label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <span class="form-label d-block">Se puede usar desde</span>
                        <div class="chk-donde">
                            @foreach($donde as $dk => $dl)
                                <label><input type="checkbox" name="para[]" value="{{ $dk }}" x-model="para" @change="actualizar()"> {{ $dl }}</label>
                            @endforeach
                        </div>
                    </div>
                @endunless

                <div>
                    <label class="form-label" for="pl-asunto">Asunto</label>
                    <input id="pl-asunto" name="asunto" class="form-control" x-model="asunto" @input="actualizar()" @focus="campo = $el" maxlength="200" required>
                </div>
                <div>
                    <label class="form-label" for="pl-titulo">Título grande <span class="secundario fw-normal">(opcional)</span></label>
                    <input id="pl-titulo" name="titulo" class="form-control" x-model="titulo" @input="actualizar()" @focus="campo = $el" maxlength="120">
                </div>
                <div>
                    <label class="form-label" for="pl-cuerpo">Mensaje</label>
                    <textarea id="pl-cuerpo" name="cuerpo" class="form-control" x-model="cuerpo" @input="actualizar()" @focus="campo = $el" x-init="campo = $el" required></textarea>
                    <div class="form-text">Deja una línea en blanco para separar párrafos.</div>
                </div>

                <div>
                    <span class="form-label d-block">Insertar dato del cliente</span>
                    <div class="vars">
                        @foreach($varsUsables as $v => $info)
                            <button type="button" @click="insertar('{{ $v }}')" title="{{ $info['texto'] }} · ej. {{ $info['ejemplo'] }}">{{ $v }}</button>
                        @endforeach
                    </div>
                    <div class="form-text">Se cambian solos por los datos de cada cliente al enviar. Pasa el cursor sobre cada uno para ver qué pone.</div>
                    <div class="aviso aviso-error mt-2 mb-0 py-2" x-show="raras.length" x-cloak style="font-size:13.5px">
                        <i class="bi bi-exclamation-triangle"></i>
                        <span>Revisa <b x-text="raras.join(', ')"></b>: no es un dato que el panel conozca, se enviaría tal cual.</span>
                    </div>
                </div>

                <div x-show="conBoton">
                    <label class="form-label" for="pl-boton">Texto del botón <span class="secundario fw-normal">(vacío = sin botón)</span></label>
                    <input id="pl-boton" name="boton" class="form-control" x-model="boton" @input="actualizar()" maxlength="60">
                    @if($pl['fabrica'] && ($pl['enlace'] ?? null) === 'entrega')
                        <div class="form-text">Lleva a la página de entrega del proyecto.</div>
                    @elseif($pl['fabrica'])
                        <div class="form-text">Lleva a la cotización o al avance del proyecto.</div>
                    @else
                        <div class="form-text">Lleva al proyecto, a la cotización o a la última cotización del cliente, según desde dónde lo envíes.</div>
                    @endif
                </div>

                @unless($pl['fabrica'])
                    <label class="form-check"><input type="checkbox" class="form-check-input" name="resumen" value="1" x-model="resumen" @change="actualizar()"> <span class="form-check-label">Incluir recuadro de resumen (folio, importe o avance)</span></label>
                @endunless

                @if($pl['fabrica'])
                    @php $extras = array_filter([! empty($pl['pdf']) ? 'el PDF de la cotización' : null, ! empty($pl['banco']) ? 'los datos bancarios' : null, ! empty($pl['miniaturas']) ? 'fotos de la galería' : null, ! empty($pl['resumen']) ? 'el recuadro de resumen' : null, ! empty($pl['adjuntos']) ? 'un espacio para adjuntar archivos al enviarla' : null, ! empty($pl['para_factura']) ? 'se manda al correo de facturación del cliente si lo tiene' : null]); @endphp
                    @if($extras)
                        <p class="secundario m-0" style="font-size:13.5px"><i class="bi bi-info-circle"></i> Además incluye {{ \Illuminate\Support\Arr::join($extras, ', ', ' y ') }}.</p>
                    @endif
                @endif

                <label class="form-check form-switch m-0">
                    <input type="hidden" name="activa" value="0">
                    <input type="checkbox" class="form-check-input" role="switch" name="activa" value="1" @checked($pl['activa'])>
                    <span class="form-check-label">Mostrar esta plantilla al enviar correos</span>
                </label>
            </div>

            <div class="pl-previa">
                <div class="d-flex justify-content-between align-items-center">
                    <b style="font-size:14px">Así lo verá tu cliente</b>
                    <span class="secundario" style="font-size:12.5px">@if($ejemplo) Ejemplo con {{ $ejemplo }} @else Con datos de ejemplo @endif</span>
                </div>
                <div class="asunto"><span class="secundario">Asunto:</span> <b x-text="asuntoPrevia || '—'"></b></div>
                <iframe x-ref="marco" title="Vista previa del correo" sandbox="allow-same-origin"></iframe>
            </div>
        </div>

        <div class="pl-pie">
            <button class="btn btn-primario px-4"><i class="bi bi-check2 me-1"></i> Guardar plantilla</button>
            @if($pl['fabrica'] && $pl['editada'])
                <button type="submit" form="restaurar" class="btn btn-fantasma" onclick="return confirm('¿Volver al texto original? Se pierden tus cambios a esta plantilla.')"><i class="bi bi-arrow-counterclockwise me-1"></i> Restaurar original</button>
            @endif
            @unless($pl['fabrica'])
                <button type="submit" form="eliminar" class="btn btn-fantasma text-danger ms-auto" onclick="return confirm('¿Eliminar la plantilla “{{ $pl['nombre'] }}”? Los correos ya enviados no se borran.')"><i class="bi bi-trash me-1"></i> Eliminar</button>
            @endunless
        </div>
    </form>
</div>

@if($pl['fabrica'] && $pl['editada'])
    <form id="restaurar" method="post" action="{{ route('admin.correos.plantillas.restaurar', $clave) }}" class="d-none">@csrf</form>
@endif
@unless($pl['fabrica'])
    <form id="eliminar" method="post" action="{{ route('admin.correos.plantillas.destroy', $clave) }}" class="d-none">@csrf @method('delete')</form>
@endunless
@endsection

@push('scripts')
<script>
window.editorPlantilla = (cfg) => ({
    ...cfg, campo: null, asuntoPrevia: '', raras: [], _t: null, _n: 0,
    init() { this.actualizar(true); },
    insertar(v) {
        const el = this.campo || this.$root.querySelector('#pl-cuerpo');
        const prop = { 'pl-asunto': 'asunto', 'pl-titulo': 'titulo', 'pl-cuerpo': 'cuerpo' }[el.id] || 'cuerpo';
        const ini = el.selectionStart ?? this[prop].length, fin = el.selectionEnd ?? ini;
        this[prop] = this[prop].slice(0, ini) + v + this[prop].slice(fin);
        this.$nextTick(() => { el.focus(); el.setSelectionRange(ini + v.length, ini + v.length); });
        this.actualizar();
    },
    actualizar(ya = false) {
        const usadas = (this.asunto + ' ' + this.titulo + ' ' + this.cuerpo + ' ' + this.boton).match(/\{[a-zA-Z_]+\}/g) || [];
        this.raras = [...new Set(usadas.filter((v) => !this.variables.includes(v)))];
        clearTimeout(this._t);
        this._t = setTimeout(() => this.pintar(), ya ? 0 : 350);
    },
    async pintar() {
        const n = ++this._n;
        const f = new FormData();
        f.append('_token', this.$root.querySelector('input[name=_token]').value);
        ['asunto', 'titulo', 'cuerpo', 'boton'].forEach((k) => f.append(k, this[k] ?? ''));
        if (this.propia) { f.append('resumen', this.resumen ? 1 : 0); this.para.forEach((p) => f.append('para[]', p)); }
        try {
            const r = await fetch(this.previa, { method: 'POST', body: f, credentials: 'same-origin' });
            if (n !== this._n || !r.ok) return;
            this.asuntoPrevia = decodeURIComponent(r.headers.get('X-Asunto') || '');
            this.$refs.marco.srcdoc = await r.text();
        } catch (e) {}
    },
});
</script>
@endpush
