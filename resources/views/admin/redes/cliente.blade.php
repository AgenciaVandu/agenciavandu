@extends('admin.layout')
@section('titulo', 'Redes · ' . ($cliente->empresa ?: $cliente->nombre))

@php
    $tz = config('vandu.zona_horaria');
    $redes = config('vandu.redes.redes');
    $formatos = config('vandu.redes.formatos');
    $ant = $mes->copy()->subMonthNoOverflow()->format('Y-m');
    $sig = $mes->copy()->addMonthNoOverflow()->format('Y-m');
    $q = fn ($extra) => route('admin.redes.cliente', array_merge([$cliente, 'mes' => $mes->format('Y-m'), 'vista' => $vista], $extra));
    $porDia = $posts->groupBy(fn ($p) => $p->fecha_local->format('Y-m-d'));
    $inicioCal = $mes->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
    $finCal = $mes->copy()->endOfMonth()->endOfWeek(\Carbon\Carbon::SUNDAY);
    $hoy = now($tz)->format('Y-m-d');
    $mini = function ($p) {
        $m = $p->medios->first();
        return $m ? ['url' => route('admin.redes.medio', $m) . ($m->tipo === 'imagen' ? '?v=mini' : ''), 'tipo' => $m->tipo] : null;
    };
    $pf = $perfiles['instagram'];
@endphp

@push('head')
@include('redes._previa-recursos', ['parte' => 'estilos'])
<style>
    .rd-cab { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; margin-bottom: 16px; }
    .rd-mes { display: flex; align-items: center; gap: 6px; }
    .rd-mes h2 { font-size: 20px; font-weight: 600; margin: 0 6px; min-width: 150px; text-align: center; text-transform: capitalize; }
    .rd-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
    .rd-chip { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; padding: 4px 10px; border-radius: 99px; background: var(--surface); border: 1px solid var(--line); }
    .rd-chip i { width: 8px; height: 8px; border-radius: 50%; }
    .cal { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); border: 1px solid var(--line); border-radius: 14px; overflow: hidden; background: var(--line); gap: 1px; }
    .cal .dn { background: var(--sunken); font-size: 12px; color: var(--muted); padding: 8px 10px; font-weight: 500; }
    .cal .dia { background: var(--surface); min-height: 128px; padding: 6px; display: flex; flex-direction: column; gap: 4px; position: relative; }
    .cal .dia.fuera { background: #FAFBFC; }
    .cal .dia.fuera .num { color: #B5BAC4; }
    .cal .dia .num { font-size: 12.5px; font-weight: 600; color: var(--text-2); display: flex; justify-content: space-between; align-items: center; }
    .cal .dia.hoy .num span { background: var(--ink); color: #fff; border-radius: 99px; padding: 0 7px; }
    .cal .dia .mas { opacity: 0; border: 0; background: var(--sunken); width: 22px; height: 22px; border-radius: 6px; font-size: 13px; color: var(--text-2); transition: opacity .12s; }
    .cal .dia:hover .mas, .cal .dia .mas:focus { opacity: 1; }
    .cal-post { display: flex; gap: 6px; align-items: center; text-decoration: none; color: var(--text); background: var(--sunken); border-radius: 8px; padding: 4px; border-left: 3px solid var(--c); font-size: 12px; min-width: 0; }
    .cal-post:hover { background: #ECEEF2; color: var(--text); }
    .cal-post .th { width: 34px; height: 34px; border-radius: 6px; background: #dfe2e7; flex: none; overflow: hidden; display: grid; place-items: center; color: #8A90A0; }
    .cal-post .th img, .cal-post .th video { width: 100%; height: 100%; object-fit: cover; }
    .cal-post .tx { min-width: 0; line-height: 1.25; }
    .cal-post .tx b { display: block; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .cal-post .tx span { color: var(--muted); white-space: nowrap; }
    .rd-lista .fila { display: grid; grid-template-columns: 56px minmax(0, 1fr) 150px 140px 110px; gap: 14px; align-items: center; padding: 10px 16px; border-top: 1px solid var(--line); text-decoration: none; color: var(--text); }
    .rd-lista .fila:first-child { border-top: 0; }
    .rd-lista .fila:hover { background: var(--sunken); color: var(--text); }
    .rd-lista .th { width: 56px; height: 56px; border-radius: 8px; background: var(--sunken); overflow: hidden; display: grid; place-items: center; color: #9AA0AC; }
    .rd-lista .th img, .rd-lista .th video { width: 100%; height: 100%; object-fit: cover; }
    .rd-est { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 500; }
    .rd-est::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--c); }
    .rd-iconos i { margin-right: 4px; color: var(--muted); }
    .rd-feed-wrap { display: grid; grid-template-columns: minmax(0, 420px) minmax(0, 1fr); gap: 24px; align-items: start; }
    .rd-ayuda { font-size: 13.5px; color: var(--muted); }
    .rd-ayuda li { margin-bottom: 6px; }
    .perf-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px; }
    .perf-tabs button { border: 1px solid var(--line-strong); background: var(--surface); border-radius: 99px; padding: 5px 12px; font-size: 13.5px; }
    .perf-tabs button.activo { background: var(--ink); color: #fff; border-color: var(--ink); }
    @media (max-width: 991.98px) {
        .cal { display: block; background: none; border: 0; }
        .cal .dn, .cal .dia.fuera, .cal .dia.vacio { display: none; }
        .cal .dia { min-height: 0; border: 1px solid var(--line); border-radius: 12px; margin-bottom: 8px; padding: 10px; }
        .cal .dia .mas { opacity: 1; }
        .rd-feed-wrap { grid-template-columns: minmax(0, 1fr); }
        .rd-lista .fila { grid-template-columns: 56px minmax(0, 1fr); }
        .rd-lista .fila > :nth-child(n+3) { display: none; }
    }
</style>
@endpush

@section('contenido')
<div class="migas"><a href="{{ route('admin.redes') }}">Redes sociales</a> <i class="bi bi-chevron-right small"></i> <span>{{ $cliente->empresa ?: $cliente->nombre }}</span></div>
<div class="page-head" x-data="{ perfiles: false }">
    <div class="d-flex align-items-center gap-3">
        @include('admin._avatar', ['nombre' => $cliente->empresa ?: $cliente->nombre])
        <div>
            <h1>{{ $cliente->empresa ?: $cliente->nombre }}</h1>
            <p class="sub">Calendario de contenido · @foreach($redes as $r)<i class="bi {{ $r['icono'] }} ms-1" title="{{ $r['nombre'] }}"></i>@endforeach</p>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-borde" @click="perfiles = true"><i class="bi bi-person-badge me-1"></i> Perfiles</button>
        <button type="button" class="btn btn-borde" data-copiar="{{ $url }}" title="Enlace para el cliente"><i class="bi bi-link-45deg me-1"></i> Enlace del cliente</button>
        <div class="dropdown">
            <button class="btn btn-borde dropdown-toggle" data-bs-toggle="dropdown"><i class="bi bi-send me-1"></i> Enviar a revisión</button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li class="dropdown-header">Los borradores de {{ $mes->locale('es')->isoFormat('MMMM') }} pasan a revisión</li>
                @if($cliente->email)
                    <li><form method="post" action="{{ route('admin.redes.revision', $cliente) }}">@csrf<input type="hidden" name="mes" value="{{ $mes->format('Y-m') }}"><input type="hidden" name="canal" value="correo">
                        <button class="dropdown-item"><i class="bi bi-envelope"></i> Por correo a {{ $cliente->email }}</button></form></li>
                @endif
                @if($cliente->whatsapp)
                    <li><form method="post" action="{{ route('admin.redes.revision', $cliente) }}" target="_blank">@csrf<input type="hidden" name="mes" value="{{ $mes->format('Y-m') }}"><input type="hidden" name="canal" value="whatsapp">
                        <button class="dropdown-item" onclick="setTimeout(() => window.vanduRefrescar && vanduRefrescar(), 1500)"><i class="bi bi-whatsapp"></i> Por WhatsApp</button></form></li>
                @endif
                <li><form method="post" action="{{ route('admin.redes.revision', $cliente) }}">@csrf<input type="hidden" name="mes" value="{{ $mes->format('Y-m') }}"><input type="hidden" name="canal" value="enlace">
                    <button class="dropdown-item"><i class="bi bi-shield-lock"></i> Solo generar código (lo comparto yo)</button></form></li>
            </ul>
        </div>
        <form method="post" action="{{ route('admin.redes.crear', $cliente) }}">@csrf
            <input type="hidden" name="fecha" value="{{ $mes->isSameMonth(now($tz)) ? now($tz)->addDay()->format('Y-m-d') : $mes->format('Y-m-d') }}">
            <button class="btn btn-primario"><i class="bi bi-plus-lg me-1"></i> Nuevo post</button>
        </form>
    </div>

    {{-- Perfiles: cómo se ve el cliente en cada red --}}
    <div class="correo-velo" x-show="perfiles" x-cloak @click.self="perfiles = false" @keydown.escape.window="perfiles = false">
        <form method="post" action="{{ route('admin.redes.perfiles', $cliente) }}" enctype="multipart/form-data" class="correo-ventana" style="width: min(620px, 100%)" x-data="{ red: 'instagram' }">
            @csrf @method('put')
            <div class="correo-cabeza"><div><h2>Perfiles de {{ $cliente->empresa ?: $cliente->nombre }}</h2><span class="secundario">Así aparecen en las vistas previas y en el feed</span></div>
                <button type="button" class="btn btn-fantasma btn-icono" @click="perfiles = false" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button></div>
            <div class="correo-campos">
                <div>
                    <label class="form-label" for="avatar">Foto de perfil <span class="secundario fw-normal">(la misma para todas las redes)</span></label>
                    <div class="d-flex align-items-center gap-3">
                        <span class="pv-av" style="width:56px;height:56px">@if($pf['avatar'])<img src="{{ $pf['avatar'] }}" alt="">@else{{ $pf['iniciales'] }}@endif</span>
                        <input type="file" name="avatar" id="avatar" accept="image/*" class="form-control">
                    </div>
                </div>
                <div class="perf-tabs" role="tablist">
                    @foreach($redes as $rk => $r)<button type="button" :class="red === '{{ $rk }}' && 'activo'" @click="red = '{{ $rk }}'"><i class="bi {{ $r['icono'] }}"></i> {{ $r['nombre'] }}</button>@endforeach
                </div>
                @foreach($redes as $rk => $r)
                    @php $p = $perfiles[$rk]; @endphp
                    <div class="row g-2" x-show="red === '{{ $rk }}'" @if($rk !== 'instagram') x-cloak @endif>
                        <div class="col-sm-6"><label class="form-label">Usuario</label><div class="input-group"><span class="input-group-text">@</span><input name="perfiles[{{ $rk }}][usuario]" class="form-control" value="{{ $p['usuario'] }}"></div></div>
                        <div class="col-sm-6"><label class="form-label">Nombre</label><input name="perfiles[{{ $rk }}][nombre]" class="form-control" value="{{ $p['nombre'] }}"></div>
                        <div class="col-12"><label class="form-label">Biografía</label><textarea name="perfiles[{{ $rk }}][bio]" rows="3" class="form-control" maxlength="500">{{ $p['bio'] }}</textarea></div>
                        <div class="col-12"><label class="form-label">Enlace</label><input name="perfiles[{{ $rk }}][enlace]" class="form-control" value="{{ $p['enlace'] }}" placeholder="agenciavandu.com"></div>
                        <div class="col-6"><label class="form-label">Seguidores</label><input type="number" min="0" name="perfiles[{{ $rk }}][seguidores]" class="form-control num" value="{{ $p['seguidores'] }}"></div>
                        <div class="col-6"><label class="form-label">Seguidos</label><input type="number" min="0" name="perfiles[{{ $rk }}][seguidos]" class="form-control num" value="{{ $p['seguidos'] }}"></div>
                    </div>
                @endforeach
            </div>
            <div class="correo-pie"><button type="button" class="btn btn-borde ms-auto" @click="perfiles = false">Cancelar</button><button class="btn btn-primario px-4">Guardar perfiles</button></div>
        </form>
    </div>
</div>

@if(session('codigo_redes'))
    <div class="aviso aviso-ok"><i class="bi bi-shield-lock"></i> Código para el cliente: <b class="num ms-1" style="letter-spacing:.1em">{{ session('codigo_redes') }}</b> · vale 24 horas.
        <button type="button" class="btn btn-borde btn-sm ms-auto" data-copiar="Revisa tu contenido aquí: {{ $url }}&#10;Tu código de verificación: {{ session('codigo_redes') }}">Copiar mensaje</button></div>
@endif

<div class="rd-cab">
    <div class="rd-mes">
        <a href="{{ $q(['mes' => $ant]) }}" class="btn btn-borde btn-icono" aria-label="Mes anterior"><i class="bi bi-chevron-left"></i></a>
        <h2>{{ $mes->locale('es')->isoFormat('MMMM YYYY') }}</h2>
        <a href="{{ $q(['mes' => $sig]) }}" class="btn btn-borde btn-icono" aria-label="Mes siguiente"><i class="bi bi-chevron-right"></i></a>
        @unless($mes->isSameMonth(now($tz)))<a href="{{ $q(['mes' => now($tz)->format('Y-m')]) }}" class="btn btn-fantasma btn-sm">Hoy</a>@endunless
    </div>
    <nav class="segmento" aria-label="Vista">
        <a href="{{ $q(['vista' => 'calendario']) }}" class="{{ $vista === 'calendario' ? 'activo' : '' }}"><i class="bi bi-calendar3"></i> Calendario</a>
        <a href="{{ $q(['vista' => 'feed']) }}" class="{{ $vista === 'feed' ? 'activo' : '' }}"><i class="bi bi-grid-3x3"></i> Feed</a>
        <a href="{{ $q(['vista' => 'lista']) }}" class="{{ $vista === 'lista' ? 'activo' : '' }}"><i class="bi bi-list-ul"></i> Lista</a>
    </nav>
</div>

<div class="rd-chips">
    @foreach(\App\Models\RedesPost::ESTADOS as $k => $e)
        <span class="rd-chip"><i style="background: {{ $e['color'] }}"></i> {{ $conteo[$k] }} {{ mb_strtolower($e['texto']) }}</span>
    @endforeach
</div>

@if($vista === 'calendario')
    <div class="cal">
        @foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $dn)<div class="dn">{{ $dn }}</div>@endforeach
        @for($d = $inicioCal->copy(); $d->lte($finCal); $d->addDay())
            @php $k = $d->format('Y-m-d'); $delDia = $porDia->get($k, collect()); $fuera = ! $d->isSameMonth($mes); @endphp
            <div class="dia {{ $fuera ? 'fuera' : '' }} {{ $k === $hoy ? 'hoy' : '' }} {{ $delDia->isEmpty() ? 'vacio' : '' }}">
                <div class="num"><span>{{ $d->day }}<span class="d-lg-none"> · {{ $d->locale('es')->isoFormat('dddd') }}</span></span>
                    @unless($fuera)
                        <form method="post" action="{{ route('admin.redes.crear', $cliente) }}">@csrf<input type="hidden" name="fecha" value="{{ $k }}">
                            <button class="mas" title="Nuevo post el {{ $d->day }}" aria-label="Nuevo post el {{ $d->day }}"><i class="bi bi-plus"></i></button></form>
                    @endunless
                </div>
                @foreach($delDia as $p)
                    @php $m = $mini($p); @endphp
                    <a href="{{ route('admin.redes.post', $p) }}" class="cal-post" style="--c: {{ \App\Models\RedesPost::ESTADOS[$p->estado]['color'] }}" title="{{ $p->estado_texto }}">
                        <span class="th">@if($m && $m['tipo'] === 'imagen')<img src="{{ $m['url'] }}" alt="" loading="lazy">@elseif($m)<video src="{{ $m['url'] }}#t=0.5" muted preload="metadata"></video>@else<i class="bi {{ $formatos[$p->formato]['icono'] ?? 'bi-image' }}"></i>@endif</span>
                        <span class="tx"><b>{{ $p->titulo ?: (\Illuminate\Support\Str::limit((string) $p->texto, 30) ?: $formatos[$p->formato]['nombre']) }}</b>
                            <span>{{ $p->fecha_local->format('H:i') }} @foreach($p->redes ?? [] as $r)<i class="bi {{ $redes[$r]['icono'] ?? '' }}"></i>@endforeach @if($p->comentarios->where('actor', 'cliente')->count())<i class="bi bi-chat-dots-fill" style="color:#D97706"></i>@endif</span></span>
                    </a>
                @endforeach
            </div>
        @endfor
    </div>
    @if($sinFecha->isNotEmpty())
        <section class="panel mt-4">
            <div class="panel-head"><h2>Sin fecha</h2><span class="ayuda">Ideas y borradores sin programar</span></div>
            <div class="panel-body d-flex flex-wrap gap-2">
                @foreach($sinFecha as $p)
                    <a href="{{ route('admin.redes.post', $p) }}" class="cal-post" style="--c: {{ \App\Models\RedesPost::ESTADOS[$p->estado]['color'] }}; width: 240px">
                        <span class="th"><i class="bi {{ $formatos[$p->formato]['icono'] ?? 'bi-image' }}"></i></span>
                        <span class="tx"><b>{{ $p->titulo ?: (\Illuminate\Support\Str::limit((string) $p->texto, 30) ?: 'Sin título') }}</b><span>{{ $p->estado_texto }}</span></span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

@elseif($vista === 'feed')
    <div class="rd-feed-wrap" x-data="feedRedes({{ Js::from(['mover' => route('admin.redes.mover', $cliente), 'token' => csrf_token()]) }})">
        <div class="ig-perfil">
            <div class="usuario"><i class="bi bi-lock me-1" style="font-size:12px"></i>{{ $pf['usuario'] }}</div>
            <div class="datos">
                <span class="pv-av aro">@if($pf['avatar'])<img src="{{ $pf['avatar'] }}" alt="">@else{{ $pf['iniciales'] }}@endif</span>
                <div class="cifras">
                    <div><b>{{ $feed->count() }}</b>publicaciones</div>
                    <div><b>{{ $pf['seguidores'] !== null ? number_format($pf['seguidores']) : '—' }}</b>seguidores</div>
                    <div><b>{{ $pf['seguidos'] !== null ? number_format($pf['seguidos']) : '—' }}</b>seguidos</div>
                </div>
            </div>
            <div class="bio"><b>{{ $pf['nombre'] }}</b>{{ $pf['bio'] }}@if($pf['enlace'])<br><a href="#">{{ $pf['enlace'] }}</a>@endif</div>
            <div class="tabs"><i class="bi bi-grid-3x3 on"></i><i class="bi bi-play-btn"></i><i class="bi bi-person-square"></i></div>
            <div class="ig-grid">
                @forelse($feed as $p)
                    @php $m = $mini($p); @endphp
                    <a href="{{ route('admin.redes.post', $p) }}" class="ig-cel" draggable="{{ $p->estado === 'publicado' ? 'false' : 'true' }}" data-id="{{ $p->id }}"
                       @dragstart="inicio($event)" @dragover.prevent="sobre($event)" @dragleave="fuera($event)" @drop.prevent="soltar($event)" @dragend="fin($event)"
                       title="{{ $p->fecha_local->locale('es')->isoFormat('D MMM, H:mm') }} · {{ $p->estado_texto }}">
                        @if($m && $m['tipo'] === 'imagen')<img src="{{ $m['url'] }}" alt="" loading="lazy" draggable="false">@elseif($m)<video src="{{ $m['url'] }}#t=0.5" muted preload="metadata"></video>@else<span class="vacia"><i class="bi bi-image"></i></span>@endif
                        @if($p->formato === 'carrusel')<i class="bi bi-collection-fill ico"></i>@elseif($p->formato === 'reel')<i class="bi bi-play-btn-fill ico"></i>@endif
                        @if($p->estado !== 'publicado')<span class="est" style="background: {{ \App\Models\RedesPost::ESTADOS[$p->estado]['color'] }}">{{ $p->estado_texto }}</span>@endif
                    </a>
                @empty
                    <div class="p-4 secundario" style="grid-column: 1 / -1; text-align:center">Aún no hay posts para Instagram.</div>
                @endforelse
            </div>
        </div>
        <div class="rd-ayuda">
            <h2 style="font-size:16px; color: var(--text)">Cómo se verá el perfil</h2>
            <ul class="ps-3">
                <li>Así se acomoda el feed de Instagram con lo publicado y lo que viene (lo más nuevo primero).</li>
                <li><b>Arrastra</b> un post sobre otro para intercambiar sus fechas y cambiar su lugar en la cuadrícula.</li>
                <li>Las historias no aparecen aquí porque no se quedan en el perfil.</li>
                <li>Edita el nombre, la biografía y la foto en <b>Perfiles</b>.</li>
            </ul>
            <div class="aviso aviso-error py-2" x-show="error" x-cloak x-text="error"></div>
        </div>
    </div>

@else
    <div class="panel rd-lista">
        @forelse($posts->concat($sinFecha) as $p)
            @php $m = $mini($p); @endphp
            <a href="{{ route('admin.redes.post', $p) }}" class="fila">
                <span class="th">@if($m && $m['tipo'] === 'imagen')<img src="{{ $m['url'] }}" alt="" loading="lazy">@elseif($m)<video src="{{ $m['url'] }}#t=0.5" muted preload="metadata"></video>@else<i class="bi {{ $formatos[$p->formato]['icono'] ?? 'bi-image' }}"></i>@endif</span>
                <span class="min-w-0"><b class="d-block text-truncate">{{ $p->titulo ?: (\Illuminate\Support\Str::limit((string) $p->texto, 60) ?: 'Sin texto') }}</b>
                    <span class="secundario" style="font-size:13px">{{ $p->fecha_local ? ucfirst($p->fecha_local->locale('es')->isoFormat('ddd D MMM, H:mm')) : 'Sin fecha' }} · {{ $formatos[$p->formato]['nombre'] }}</span></span>
                <span class="rd-iconos">@foreach($p->redes ?? [] as $r)<i class="bi {{ $redes[$r]['icono'] ?? '' }}" title="{{ $redes[$r]['nombre'] ?? $r }}"></i>@endforeach</span>
                <span class="rd-est" style="--c: {{ \App\Models\RedesPost::ESTADOS[$p->estado]['color'] }}">{{ $p->estado_texto }}</span>
                <span class="secundario" style="font-size:13px">@if($n = $p->comentarios->where('actor', 'cliente')->count())<i class="bi bi-chat-dots"></i> {{ $n }}@endif</span>
            </a>
        @empty
            <div class="vacio"><div class="ico"><i class="bi bi-calendar3"></i></div><h3>Sin posts este mes</h3><p>Crea el primero con “Nuevo post” o desde un día del calendario.</p></div>
        @endforelse
    </div>
@endif
@endsection

@push('scripts')
<script>
window.feedRedes = (cfg) => ({
    arrastrado: null, error: '',
    inicio(e) { this.arrastrado = e.currentTarget.dataset.id; e.currentTarget.classList.add('arrastrando'); e.dataTransfer.effectAllowed = 'move'; },
    sobre(e) { if (this.arrastrado && e.currentTarget.dataset.id !== this.arrastrado) e.currentTarget.classList.add('destino'); },
    fuera(e) { e.currentTarget.classList.remove('destino'); },
    fin(e) { e.currentTarget.classList.remove('arrastrando'); document.querySelectorAll('.ig-cel.destino').forEach((x) => x.classList.remove('destino')); },
    async soltar(e) {
        const b = e.currentTarget.dataset.id; e.currentTarget.classList.remove('destino');
        if (!this.arrastrado || b === this.arrastrado) return;
        this.error = '';
        const r = await fetch(cfg.mover, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.token }, body: JSON.stringify({ a: this.arrastrado, b }) });
        const d = await r.json().catch(() => ({}));
        if (!r.ok) { this.error = d.mensaje || 'No se pudo mover.'; return; }
        window.vanduRefrescar ? vanduRefrescar() : location.reload();
    },
});
</script>
@endpush
