@extends('admin.layout')
@section('titulo', 'Notificaciones')

@push('head')
<style>
    .push-estado { display: flex; gap: 14px; align-items: flex-start; }
    .push-estado .ico { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; font-size: 21px; flex-shrink: 0; background: var(--sunken); color: var(--text-2); }
    .push-estado .ico.on { background: var(--green-soft); color: var(--green-ink); }
    .push-estado h3 { font-size: 16px; font-weight: 600; margin: 2px 0 4px; }
    .push-estado p { margin: 0; color: var(--text-2); font-size: 14.5px; }
    .push-eventos { list-style: none; margin: 0; padding: 0; }
    .push-eventos li { display: flex; gap: 12px; align-items: center; padding: 13px 0; border-top: 1px solid var(--line); }
    .push-eventos li > i { font-size: 18px; color: var(--muted); width: 22px; text-align: center; }
    .push-eventos .t { flex: 1; min-width: 0; }
    .push-eventos .t b { display: block; font-weight: 500; font-size: 14.5px; }
    .push-eventos .t span { font-size: 13px; color: var(--muted); }
    .push-eventos .form-switch { margin: 0; padding-left: 2.6em; }
    .push-eventos .form-switch .form-check-input { width: 2.4em; height: 1.35em; cursor: pointer; }
    .push-eventos .form-check-input:checked { background-color: var(--ink); border-color: var(--ink); }
    .push-lista { list-style: none; margin: 0; padding: 0; }
    .push-lista li { display: flex; gap: 10px; align-items: center; padding: 11px 0; border-top: 1px solid var(--line); font-size: 14px; }
    .push-lista li:first-child { border-top: 0; }
    .push-lista .t { flex: 1; min-width: 0; }
    .push-lista .t span { display: block; color: var(--muted); font-size: 12.5px; }
    .push-pasos { margin: 10px 0 0; padding-left: 20px; color: var(--text-2); font-size: 14.5px; }
    .push-pasos li { margin: 4px 0; }
</style>
@endpush

@section('contenido')
<div class="page-head">
    <div>
        <h1>Notificaciones</h1>
        <p class="sub">Avisos en tu celular o tu compu cuando un cliente abre su cotización, te escriben desde el sitio y más.</p>
    </div>
</div>

<div class="row g-4" x-data="notificacionesPush({{ Js::from(['clave' => $clave, 'eventos' => array_keys($eventos)]) }})">
    <div class="col-lg-7 d-grid gap-4 align-content-start">
        <section class="panel">
            <div class="panel-head"><h2>Este dispositivo</h2>
                <span class="estado estado-aceptada" x-show="estado === 'activo'" x-cloak>Activas</span>
            </div>
            <div class="panel-body">
                <div class="push-estado" x-show="estado === 'cargando'"><div class="ico"><i class="bi bi-hourglass-split"></i></div><div><p>Revisando este dispositivo…</p></div></div>

                <div class="push-estado" x-show="estado === 'sin-soporte'" x-cloak>
                    <div class="ico"><i class="bi bi-slash-circle"></i></div>
                    <div><h3>Este navegador no admite notificaciones</h3><p>Usa Chrome, Edge, Firefox o Safari actualizados. En iPhone necesitas iOS 16.4 o más reciente.</p></div>
                </div>

                <div class="push-estado" x-show="estado === 'instalar-ios'" x-cloak>
                    <div class="ico"><i class="bi bi-phone"></i></div>
                    <div><h3>En iPhone, primero instala la app</h3>
                        <p>Apple solo permite notificaciones en apps agregadas a la pantalla de inicio.</p>
                        <ol class="push-pasos">
                            <li>Abre <b>agenciavandu.com/admin</b> en <b>Safari</b>.</li>
                            <li>Toca <i class="bi bi-box-arrow-up"></i> <b>Compartir</b> → <b>Agregar a inicio</b>.</li>
                            <li>Abre Vandu desde su ícono y regresa a esta página.</li>
                        </ol>
                    </div>
                </div>

                <div class="push-estado" x-show="estado === 'bloqueado'" x-cloak>
                    <div class="ico"><i class="bi bi-bell-slash"></i></div>
                    <div><h3>Las notificaciones están bloqueadas</h3>
                        <p x-show="!ios">Haz clic en el candado junto a la dirección, permite las <b>Notificaciones</b> y recarga la página.</p>
                        <p x-show="ios">Ve a <b>Ajustes → Notificaciones → Vandu</b> y activa <b>Permitir notificaciones</b>.</p>
                    </div>
                </div>

                <div x-show="estado === 'inactivo'" x-cloak>
                    <div class="push-estado mb-3">
                        <div class="ico"><i class="bi bi-bell"></i></div>
                        <div><h3>Activa los avisos en este dispositivo</h3><p>Te preguntará si permites notificaciones. Puedes elegir cuáles quieres después.</p></div>
                    </div>
                    <button type="button" class="btn btn-primario" @click="activar()" :disabled="ocupado"><i class="bi bi-bell me-1"></i> Activar notificaciones</button>
                </div>

                <div x-show="estado === 'activo'" x-cloak>
                    <div class="push-estado mb-2">
                        <div class="ico on"><i class="bi bi-bell-fill"></i></div>
                        <div><h3 x-text="dispositivo || 'Este dispositivo'"></h3><p>Elige qué avisos quieres recibir aquí.</p></div>
                    </div>
                    <ul class="push-eventos">
                        @foreach($eventos as $k => $e)
                            <li>
                                <i class="bi {{ $e['icono'] }}"></i>
                                <label class="t" for="ev-{{ $k }}"><b>{{ $e['texto'] }}</b>
                                    <span>{{ $e['ayuda'] }}@if($k === 'resumen_diario') · todos los días a las {{ \Illuminate\Support\Carbon::createFromFormat('H:i', $hora)->format('g:i a') }}@endif</span></label>
                                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="ev-{{ $k }}" value="{{ $k }}" x-model="elegidos" @change="guardar()"></div>
                            </li>
                        @endforeach
                    </ul>
                    <div class="d-flex flex-wrap gap-2 align-items-center mt-3">
                        <button type="button" class="btn btn-borde" @click="probar()" :disabled="ocupado"><i class="bi bi-send me-1"></i> Enviar prueba</button>
                        <button type="button" class="btn btn-fantasma text-danger" @click="desactivar()" :disabled="ocupado"><i class="bi bi-bell-slash me-1"></i> Desactivar aquí</button>
                        <span class="secundario" x-text="mensaje" x-show="mensaje"></span>
                    </div>
                </div>

                <div class="aviso aviso-error mt-3 mb-0" x-show="error" x-cloak><i class="bi bi-exclamation-circle"></i> <span x-text="error"></span></div>
            </div>
        </section>
    </div>

    <div class="col-lg-5 d-grid gap-4 align-content-start">
        @if($cron)
        <section class="panel">
            <div class="panel-head"><h2>Resumen de la mañana</h2>
                @if($cron['activo'])<span class="estado estado-aceptada">Programado</span>@else<span class="estado estado-rechazada">Falta el cron</span>@endif
            </div>
            <div class="panel-body">
                @if($cron['activo'])
                    <p class="m-0">Todos los días a las <b>{{ \Illuminate\Support\Carbon::createFromFormat('H:i', $hora)->format('g:i a') }}</b> te llega lo pendiente del día. Si no hay nada pendiente, no te molesta.</p>
                    <p class="secundario mt-2 mb-0" style="font-size:13px">
                        @if($ultimoResumen) Último resumen: {{ $ultimoResumen->created_at->locale('es')->diffForHumans() }}. @endif
                        El servidor revisa cada minuto (última vez {{ $cron['ultimo']->locale('es')->diffForHumans() }}).
                    </p>
                @else
                    <p class="mt-0">Para que llegue el resumen de las {{ \Illuminate\Support\Carbon::createFromFormat('H:i', $hora)->format('g:i a') }}, el servidor necesita una tarea programada (cron). Los demás avisos funcionan sin ella.</p>
                    <ol class="push-pasos mb-2">
                        <li>En cPanel abre <b>Cron Jobs</b> (Trabajos de cron).</li>
                        <li>En <b>Configuración común</b> elige <b>Una vez por minuto</b>.</li>
                        <li>En <b>Comando</b> pega esto y dale <b>Agregar</b>:</li>
                    </ol>
                    <div class="input-group input-group-sm">
                        <input class="form-control num" value="{{ $cron['comando'] }}" readonly aria-label="Comando del cron" onfocus="this.select()">
                        <button type="button" class="btn btn-borde" data-copiar="{{ $cron['comando'] }}"><i class="bi bi-clipboard"></i> Copiar</button>
                    </div>
                    <p class="secundario mt-2 mb-0" style="font-size:13px">En 1 o 2 minutos esta tarjeta cambia a <b>Programado</b>.
                        @if($cron['ultimo']) Lo último que vimos del cron fue {{ $cron['ultimo']->locale('es')->diffForHumans() }}. @endif</p>
                @endif
            </div>
        </section>
        @endif

        <section class="panel">
            <div class="panel-head"><h2>Tus dispositivos</h2><span class="ayuda">{{ $dispositivos->count() }}</span></div>
            <div class="panel-body">
                @if($dispositivos->isEmpty())
                    <p class="secundario m-0">Todavía no activas las notificaciones en ningún dispositivo. Actívalas en tu celular y en tu compu para no perderte nada.</p>
                @else
                    <ul class="push-lista">
                        @foreach($dispositivos as $d)
                            <li>
                                <i class="bi {{ str_contains($d->dispositivo, 'iPhone') || str_contains($d->dispositivo, 'Android') ? 'bi-phone' : 'bi-laptop' }} text-secondary"></i>
                                <div class="t">{{ $d->dispositivo }} <span x-show="id === {{ $d->id }}" x-cloak class="d-inline ms-1" style="color:var(--green-ink)">· este</span>
                                    <span>{{ $d->ultimo_envio_at ? 'Último aviso ' . $d->ultimo_envio_at->locale('es')->diffForHumans() : 'Activado ' . $d->created_at->locale('es')->diffForHumans() }}</span></div>
                                <form method="post" action="{{ route('admin.notificaciones.destroy', $d) }}" onsubmit="return confirm('¿Dejar de enviar avisos a {{ $d->dispositivo }}?')">@csrf @method('delete')
                                    <button class="btn btn-fantasma btn-icono" title="Quitar" aria-label="Quitar {{ $d->dispositivo }}"><i class="bi bi-x-lg"></i></button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Últimos avisos</h2></div>
            <div class="panel-body">
                @if($recientes->isEmpty())
                    <p class="secundario m-0">Aquí verás lo que se ha enviado.</p>
                @else
                    <ul class="push-lista">
                        @foreach($recientes as $n)
                            <li>
                                <i class="bi {{ $eventos[$n->evento]['icono'] ?? 'bi-bell' }} text-secondary"></i>
                                <div class="t"><a href="{{ $n->url }}" class="text-reset text-decoration-none fw-medium">{{ $n->titulo }}</a>
                                    <span>{{ \Illuminate\Support\Str::limit(str_replace("\n", ' · ', (string) $n->cuerpo), 90) }}</span>
                                    <span>{{ $n->created_at->locale('es')->diffForHumans() }}{{ $n->entregadas ? '' : ' · no se entregó' }}</span></div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
window.notificacionesPush = (cfg) => ({
    estado: 'cargando', ios: false, ocupado: false, error: '', mensaje: '',
    id: null, dispositivo: '', elegidos: [], sub: null,
    token: @json(csrf_token()),

    async init() {
        const ua = navigator.userAgent;
        this.ios = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1 && !/Macintosh.*Chrome/.test(ua) && 'ontouchend' in document);
        const instalada = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
            this.estado = this.ios && !instalada ? 'instalar-ios' : 'sin-soporte';
            return;
        }
        if (Notification.permission === 'denied') { this.estado = 'bloqueado'; return; }
        try {
            const reg = await this.registro();
            this.sub = await reg.pushManager.getSubscription();
            if (this.sub && Notification.permission === 'granted') await this.registrar(false);
            else this.estado = 'inactivo';
        } catch (e) { this.estado = 'inactivo'; }
    },

    async registro() {
        const reg = await navigator.serviceWorker.getRegistration('/admin/') || await navigator.serviceWorker.register(@json(route('app.sw')), { scope: '/admin' });
        return navigator.serviceWorker.ready.then(() => reg);
    },

    async activar() {
        this.ocupado = true; this.error = '';
        try {
            const permiso = await Notification.requestPermission();
            if (permiso !== 'granted') { this.estado = permiso === 'denied' ? 'bloqueado' : 'inactivo'; return; }
            const reg = await this.registro();
            this.sub = await reg.pushManager.getSubscription() || await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: this.clave(cfg.clave) });
            await this.registrar(true);
            window.vanduRefrescar?.(); // que aparezca en "Tus dispositivos"
        } catch (e) {
            this.error = 'No se pudieron activar: ' + (e.message || e);
        } finally { this.ocupado = false; }
    },

    async registrar(bienvenida) {
        const r = await this.pedir('POST', @json(route('admin.notificaciones.suscribir')), { ...this.sub.toJSON(), bienvenida });
        this.id = r.id; this.dispositivo = r.dispositivo; this.elegidos = r.eventos;
        this.estado = 'activo';
    },

    async guardar() {
        this.mensaje = '';
        try { await this.pedir('PATCH', @json(url('admin/notificaciones')) + '/' + this.id, { eventos: this.elegidos }); this.aviso('Guardado'); }
        catch (e) { this.error = 'No se guardó: ' + e.message; }
    },

    async probar() {
        this.ocupado = true; this.error = ''; this.mensaje = '';
        try { const r = await this.pedir('POST', @json(url('admin/notificaciones')) + '/' + this.id + '/prueba', {}); r.ok ? this.aviso(r.mensaje) : this.error = r.mensaje; }
        catch (e) { this.error = e.message; }
        finally { this.ocupado = false; }
    },

    async desactivar() {
        this.ocupado = true; this.error = '';
        try {
            if (this.id) await this.pedir('DELETE', @json(url('admin/notificaciones')) + '/' + this.id, {});
            if (this.sub) await this.sub.unsubscribe();
            this.sub = null; this.id = null; this.estado = 'inactivo';
            window.vanduRefrescar?.();
        } catch (e) { this.error = e.message; }
        finally { this.ocupado = false; }
    },

    aviso(t) { this.mensaje = t; clearTimeout(this._t); this._t = setTimeout(() => this.mensaje = '', 3500); },

    async pedir(metodo, url, datos) {
        const r = await fetch(url, { method: metodo, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.token, 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(datos), credentials: 'same-origin' });
        if (!r.ok) throw new Error(r.status === 419 ? 'La sesión expiró, recarga la página.' : 'Error ' + r.status);
        return r.json();
    },

    clave(b64) {
        const s = (b64 + '='.repeat((4 - b64.length % 4) % 4)).replace(/-/g, '+').replace(/_/g, '/');
        return Uint8Array.from(atob(s), (c) => c.charCodeAt(0));
    },
});
</script>
@endpush
