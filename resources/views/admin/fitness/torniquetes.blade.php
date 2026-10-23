@extends('admin.layout')
@section('titulo', 'Torniquetes')
@include('admin.fitness._estilos')

@push('head')
<style>
    .tq { padding: 0; }
    .tq-cab { display: flex; align-items: center; gap: 14px; padding: 18px 20px; }
    .tq-ico { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; font-size: 20px; background: var(--marca-soft); color: var(--marca); flex: none; }
    .tq-estado { font-size: 12px; font-weight: 600; padding: 2px 9px; border-radius: 99px; background: var(--sunken); color: var(--muted); white-space: nowrap; }
    .tq-estado.on { background: #E3FBEF; color: #047A4B; }
    .tq-estado.on::before { content: ''; display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #10B981; margin-right: 6px; vertical-align: 1px; }
    .tq-sec { padding: 16px 20px; border-top: 1px solid var(--line); }
    .tq-url { display: flex; gap: 8px; }
    .tq-url input { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; }
    .tq-pasos { counter-reset: p; list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; }
    .tq-pasos li { counter-increment: p; display: grid; grid-template-columns: 26px 1fr; gap: 10px; font-size: 14px; }
    .tq-pasos li::before { content: counter(p); width: 24px; height: 24px; border-radius: 50%; background: var(--marca-soft); color: var(--marca); font-weight: 700; font-size: 12px; display: grid; place-items: center; }
    .tq-pasos code { font-size: 12.5px; background: var(--sunken); padding: 1px 6px; border-radius: 6px; word-break: break-all; }
    .tq-api { font-size: 12px; background: var(--sunken); border-radius: 10px; padding: 12px 14px; white-space: pre-wrap; word-break: break-all; margin: 8px 0 0; }
    .tq-log > * { display: grid; grid-template-columns: 64px 1fr auto; gap: 12px; align-items: center; padding: 11px 20px; }
    .tq-log > * + * { border-top: 1px solid var(--line); }
    .tq-log .hora { font-size: 13px; color: var(--muted); }
    .tq-log .msj { font-size: 13px; color: var(--muted); }
    .tq-res { font-size: 12px; font-weight: 600; padding: 2px 9px; border-radius: 99px; white-space: nowrap; }
    .tq-res.si { background: #E3FBEF; color: #047A4B; } .tq-res.no { background: #FDECEC; color: #B42318; } .tq-res.neutro { background: var(--sunken); color: var(--muted); }
    @media (max-width: 575.98px) { .tq-log > * { grid-template-columns: 52px 1fr; } .tq-log .tq-res { grid-column: 2; justify-self: start; } }
</style>
@endpush

@section('contenido')
@php $tz = config('vandu.zona_horaria'); @endphp
<div class="page-head">
    <div>
        <h1>Torniquetes</h1>
        <p class="sub">Tus atletas entran con el QR de su credencial en el celular, que cambia cada minuto. Solo pasa quien tiene una clase reservada a esta hora: desde {{ $ventana[0] }} min antes hasta {{ $ventana[1] }} min después de que empieza (<a href="{{ route('admin.fitness-ajustes') }}">cámbialo en Reglas</a>). Al pasar se le marca la asistencia.</p>
    </div>
</div>

@if($dispositivos->isNotEmpty())
<div class="panel fx-kpis">
    <div><div class="k"><i class="bi bi-box-arrow-in-right"></i> Entradas hoy</div><div class="v num">{{ $hoy['entradas'] }}</div><div class="s">por torniquete</div></div>
    <div><div class="k"><i class="bi bi-slash-circle"></i> No pasaron hoy</div><div class="v num">{{ $hoy['negados'] }}</div><div class="s">sin reserva, QR vencido…</div></div>
    <div><div class="k"><i class="bi bi-door-open"></i> Torniquetes</div><div class="v num">{{ $dispositivos->count() }}</div><div class="s">{{ $dispositivos->where('en_linea', true)->count() }} en línea</div></div>
    <div><div class="k"><i class="bi bi-clock"></i> Ventana de entrada</div><div class="v num">{{ $ventana[0] }}′ / {{ $ventana[1] }}′</div><div class="s">antes / después de empezar</div></div>
</div>
@endif

<div class="row g-4">
    <div class="col-xl-7 d-grid gap-3 align-content-start">
        @foreach($dispositivos as $d)
            <section class="panel tq" x-data="{ guia: {{ $d->ultimo_contacto_at ? 'false' : 'true' }}, mas: false }">
                <div class="tq-cab">
                    <div class="tq-ico"><i class="bi bi-door-open"></i></div>
                    <div class="min-w-0 me-auto">
                        <b class="d-block">{{ $d->nombre }}</b>
                        <span class="secundario small">Control iD · iDFace @if($d->sucursal) · {{ $d->sucursal->nombre }}@endif @unless($d->activo) · <b class="text-danger">apagado en Nerby</b>@endunless</span>
                    </div>
                    @if($d->en_linea)<span class="tq-estado on">En línea</span>
                    @elseif($d->ultimo_contacto_at)<span class="tq-estado">Sin contacto {{ $d->ultimo_contacto_at->locale('es')->diffForHumans() }}</span>
                    @else<span class="tq-estado">Aún no se conecta</span>@endif
                </div>

                <div class="tq-sec">
                    <label class="form-label small" for="url-{{ $d->id }}">Dirección del servidor (se escribe en el iDFace)</label>
                    <div class="tq-url" x-data="{ ok: false }">
                        <input id="url-{{ $d->id }}" class="form-control" value="{{ $d->url }}" readonly @focus="$el.select()">
                        <button type="button" class="btn btn-borde" @click="navigator.clipboard.writeText('{{ $d->url }}'); ok = true; setTimeout(() => ok = false, 1500)"><i class="bi" :class="ok ? 'bi-check2' : 'bi-copy'"></i><span class="d-none d-sm-inline ms-1" x-text="ok ? 'Copiada' : 'Copiar'"></span></button>
                    </div>
                    <button type="button" class="btn btn-fantasma btn-sm px-0 mt-2" @click="guia = !guia"><i class="bi" :class="guia ? 'bi-chevron-up' : 'bi-chevron-down'"></i> Cómo configurarlo en el equipo</button>
                    <div x-show="guia" x-cloak class="mt-2">
                        <ol class="tq-pasos">
                            <li><span>El técnico conecta el <b>relé del módulo de accionamiento</b> del iDFace a la entrada de apertura del torniquete (lado de <b>entrada</b>), y el equipo a la red con internet.</span></li>
                            <li><span>Desde una computadora en la misma red, abre en el navegador la IP del iDFace (se ve en su pantalla, en el menú de información de red) y entra como administrador.</span></li>
                            <li><span>En la configuración del modo de operación elige <b>En línea</b> (identificación en el servidor) y como servidor escribe la dirección de arriba.</span></li>
                            <li><span>En los ajustes del lector de QR elige el modo <b>alfanumérico</b>.</span></li>
                            <li><span>Abre tu credencial en el portal de atleta, ponla frente al iDFace y revisa que aparezca aquí abajo en <b>Entradas</b>. Si no tienes clase reservada en este momento, debe decir “no tienes clase reservada”: así sabes que ya está hablando con Nerby.</span></li>
                        </ol>
                        <details class="mt-3"><summary class="small secundario">Para el técnico: configuración por API</summary>
<pre class="tq-api">POST /create_objects.fcgi?session=…
{"object":"devices","values":[{"name":"Nerby","ip":"{{ $d->url }}","public_key":""}]}

POST /set_configuration.fcgi?session=…
{"online_client":{"server_id":"&lt;id que devolvió el paso anterior&gt;","extract_template":"0","max_request_attempts":"3"},
 "general":{"online":"1","local_identification":"0"},
 "face_id":{"qrcode_legacy_mode_enabled":"0"}}

Para abrir, Nerby responde la acción “{{ $d->accion }}” con “{{ $d->parametros }}”.</pre>
                            <p class="small secundario mt-2 mb-0">Si el equipo no acepta <code>https://</code>, escribe la misma dirección con <code>http://</code>.</p>
                        </details>
                    </div>
                </div>

                <form method="post" action="{{ route('admin.torniquetes.probar', $d) }}" class="tq-sec">
                    @csrf
                    <label class="form-label small" for="prueba-{{ $d->id }}">Probar sin el equipo</label>
                    <div class="d-flex gap-2">
                        <input id="prueba-{{ $d->id }}" name="codigo" class="form-control" placeholder="Pega aquí el texto del QR de un atleta (FIT2:…)" required maxlength="200">
                        <button class="btn btn-borde text-nowrap"><i class="bi bi-play me-1"></i> Probar</button>
                    </div>
                    <div class="secundario small mt-1">Aplica la regla de verdad: si abriría, marca su asistencia.</div>
                </form>

                <div class="tq-sec">
                    <button type="button" class="btn btn-fantasma btn-sm px-0" @click="mas = !mas"><i class="bi bi-gear me-1"></i> Opciones</button>
                    <div x-show="mas" x-cloak><form method="post" action="{{ route('admin.torniquetes.update', $d) }}" class="mt-3 d-grid gap-3">
                        @csrf @method('put')
                        <div class="row g-2">
                            <div class="col-sm-6"><label class="form-label small" for="n-{{ $d->id }}">Nombre</label><input id="n-{{ $d->id }}" name="nombre" class="form-control" value="{{ $d->nombre }}" required maxlength="80"></div>
                            @if($sucursales->isNotEmpty())
                            <div class="col-sm-6"><label class="form-label small" for="s-{{ $d->id }}">Sucursal</label>
                                <select id="s-{{ $d->id }}" name="sucursal_id" class="form-select"><option value="">Todas</option>
                                    @foreach($sucursales as $s)<option value="{{ $s->id }}" @selected($d->sucursal_id === $s->id)>{{ $s->nombre }}</option>@endforeach</select></div>
                            @endif
                        </div>
                        <label class="form-check"><input type="checkbox" name="reingreso" value="1" class="form-check-input" @checked($d->reingreso)>
                            <span class="form-check-label">Dejar volver a entrar a la misma clase <span class="secundario small d-block">Si sale y regresa. Apagado, el QR abre una sola vez por clase.</span></span></label>
                        <label class="form-check"><input type="checkbox" name="credencial_fija" value="1" class="form-check-input" @checked($d->credencial_fija)>
                            <span class="form-check-label">Aceptar la credencial impresa <span class="secundario small d-block">Su QR no cambia: una foto de ella también abre.</span></span></label>
                        <label class="form-check"><input type="checkbox" name="activo" value="1" class="form-check-input" @checked($d->activo)>
                            <span class="form-check-label">Encendido <span class="secundario small d-block">Apagado, Nerby no le contesta y el equipo trabaja con lo que tenga guardado.</span></span></label>
                        <details><summary class="small secundario">Avanzado: cómo abre</summary>
                            <div class="row g-2 mt-1">
                                <div class="col-5"><label class="form-label small" for="a-{{ $d->id }}">Acción</label><input id="a-{{ $d->id }}" name="accion" class="form-control num" value="{{ $d->accion }}" maxlength="30"></div>
                                <div class="col-7"><label class="form-label small" for="p-{{ $d->id }}">Parámetros</label><input id="p-{{ $d->id }}" name="parametros" class="form-control num" value="{{ $d->parametros }}" maxlength="120"></div>
                            </div>
                        </details>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-primario btn-sm">Guardar</button>
                            <button class="btn btn-borde btn-sm" form="token-{{ $d->id }}">Crear dirección nueva</button>
                            <button class="btn btn-fantasma btn-sm text-danger ms-auto" form="borrar-{{ $d->id }}">Eliminar</button>
                        </div>
                    </form></div>
                    <form id="token-{{ $d->id }}" method="post" action="{{ route('admin.torniquetes.token', $d) }}" onsubmit="return confirm('La dirección actual dejará de funcionar y tendrás que escribir la nueva en el equipo. ¿Seguir?')">@csrf</form>
                    <form id="borrar-{{ $d->id }}" method="post" action="{{ route('admin.torniquetes.destroy', $d) }}" onsubmit="return confirm('¿Eliminar {{ addslashes($d->nombre) }}? El equipo dejará de abrir con Nerby.')">@csrf @method('delete')</form>
                </div>
            </section>
        @endforeach

        <form method="post" action="{{ route('admin.torniquetes.store') }}" class="panel panel-body">
            @csrf
            <h2 class="fs-6 fw-semibold mb-1">{{ $dispositivos->isEmpty() ? 'Conecta tu torniquete' : 'Agregar otro torniquete' }}</h2>
            <p class="secundario small mb-3">Funciona con lectores Control iD iDFace conectados a internet. Te damos la dirección que se escribe en el equipo.</p>
            <div class="row g-2 align-items-end">
                <div class="col-sm-{{ $sucursales->isNotEmpty() ? 6 : 8 }}"><label class="form-label small" for="nuevo-n">Nombre</label><input id="nuevo-n" name="nombre" class="form-control" required maxlength="80" placeholder="Torniquete de la entrada" value="{{ $dispositivos->isEmpty() ? 'Torniquete de la entrada' : '' }}"></div>
                @if($sucursales->isNotEmpty())
                <div class="col-sm-3"><label class="form-label small" for="nuevo-s">Sucursal</label>
                    <select id="nuevo-s" name="sucursal_id" class="form-select"><option value="">Todas</option>@foreach($sucursales as $s)<option value="{{ $s->id }}">{{ $s->nombre }}</option>@endforeach</select></div>
                @endif
                <div class="col-sm-3"><button class="btn btn-primario w-100"><i class="bi bi-plus-lg me-1"></i> Agregar</button></div>
            </div>
        </form>
    </div>

    <div class="col-xl-5">
        <section class="panel">
            <div class="d-flex align-items-center justify-content-between gap-2 px-3 pt-3 pb-2">
                <h2 class="fs-6 fw-semibold m-0">Entradas</h2>
                <nav class="segmento" aria-label="Filtrar entradas">
                    <a href="{{ route('admin.torniquetes') }}" class="{{ $negados ? '' : 'activo' }}">Todas</a>
                    <a href="{{ route('admin.torniquetes', ['ver' => 'negados']) }}" class="{{ $negados ? 'activo' : '' }}">No pasaron</a>
                </nav>
            </div>
            @if($accesos->isEmpty())
                <p class="secundario small px-3 pb-3 m-0">{{ $negados ? 'Nadie se ha quedado afuera.' : 'Aquí verás cada lectura del torniquete: quién, a qué hora y si pasó.' }}</p>
            @else
                <div class="tq-log">
                    @foreach($accesos as $x)
                        @php $f = $x->created_at->setTimezone($tz); @endphp
                        <div>
                            <div class="hora num">{{ $f->format('H:i') }}<br><span class="small">{{ $f->isToday() ? 'hoy' : $f->locale('es')->isoFormat('D MMM') }}</span></div>
                            <div class="min-w-0">
                                @if($x->cliente)<a href="{{ route('admin.clientes.show', [$x->cliente, 'ver' => 'atleta']) }}" class="fw-semibold">{{ $x->cliente->nombre }}</a>
                                @else<span class="fw-semibold">{{ $x->motivo === 'boton' ? 'Recepción' : 'Desconocido' }}</span>@endif
                                <div class="msj text-truncate">{{ $x->reservacion?->clase ? $x->reservacion->clase->nombre . ' · ' : '' }}{{ $x->motivo_texto }}{{ ['prueba' => ' · prueba', 'rostro' => ' · rostro'][$x->metodo] ?? '' }}{{ $dispositivos->count() > 1 ? ' · ' . $x->dispositivo?->nombre : '' }}</div>
                            </div>
                            <span class="tq-res {{ $x->motivo === 'boton' ? 'neutro' : ($x->permitido ? 'si' : 'no') }}">{{ $x->motivo === 'boton' ? 'Botón' : ($x->permitido ? 'Pasó' : 'No pasó') }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</div>
@endsection
