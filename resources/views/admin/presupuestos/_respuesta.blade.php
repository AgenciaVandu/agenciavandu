{{--
    Aceptación en línea e historial de la cotización (barra lateral de la edición).
    Params: $p (Presupuesto)
--}}
@php
    use App\Support\Aceptacion;
    $eventosTodos = $p->eventos()->get();
    $ultAcept = $eventosTodos->firstWhere('tipo', 'aceptada');
    $ultCambios = $eventosTodos->firstWhere('tipo', 'cambios');
    $puede = Aceptacion::puedeResponder($p);
    $vigentes = $p->codigos()->whereNull('usado_at')->where('expira_at', '>', now())->where('intentos', '<', Aceptacion::INTENTOS)->orderByDesc('expira_at')->get();
    $f = fn ($d) => $d->copy()->setTimezone(config('vandu.zona_horaria'))->locale('es')->isoFormat('D MMM, h:mm a');
    $nuevoCodigo = session('codigo_nuevo');
@endphp

<section class="panel" id="respuesta-cliente">
    <div class="panel-head"><h2>Respuesta del cliente</h2>
        @if($p->estado === 'aceptada')<span class="estado estado-aceptada">Aceptada</span>
        @elseif($p->estado === 'negociacion' && $ultCambios)<span class="estado estado-negociacion">Pidió cambios</span>
        @elseif($puede)<span class="ayuda">En línea</span>@endif
    </div>
    <div class="panel-body d-grid gap-3">
        @if($p->estado === 'aceptada')
            <div>
                @if($ultAcept && $ultAcept->actor === 'cliente')
                    <b>Aceptada en línea</b> por {{ $ultAcept->autor }}<br><span class="secundario">{{ $f($ultAcept->created_at) }} · verificada con código</span>
                @else
                    <b>Aceptada</b>{{ $p->aceptada_el ? ' el ' . $p->aceptada_el->locale('es')->isoFormat('D [de] MMMM') : '' }} <span class="secundario">· registrada por la agencia</span>
                @endif
            </div>
            @if($p->proyecto)
                <a href="{{ route('admin.proyectos.show', $p->proyecto) }}" class="btn btn-borde"><i class="bi bi-kanban me-1"></i> Ver proyecto</a>
            @else
                <a href="{{ route('admin.proyectos.create', $p) }}" class="btn btn-primario"><i class="bi bi-kanban me-1"></i> Crear proyecto</a>
            @endif
            <p class="secundario m-0" style="font-size:12.5px">El cliente ya no puede modificarla desde su enlace. Tú sí puedes cambiar el estado aquí arriba.</p>
        @else
            @if($p->estado === 'negociacion' && $ultCambios)
                <div class="cambios-pedidos">
                    <div class="secundario" style="font-size:12.5px">{{ $ultCambios->autor }} · {{ $f($ultCambios->created_at) }}</div>
                    <div class="msg">{{ $ultCambios->detalle }}</div>
                </div>
                <p class="secundario m-0" style="font-size:12.5px">Haz los ajustes, guarda y envíale la versión nueva: quedará registrada en el historial.</p>
                <button type="button" class="btn btn-borde" data-correo="cotizacion"><i class="bi bi-send me-1"></i> Enviar versión actualizada</button>
            @endif

            @if($puede)
                <div>
                    <div class="d-flex justify-content-between align-items-center">
                        <b style="font-size:14px"><i class="bi bi-shield-lock me-1"></i> Código de verificación</b>
                        {{-- El formulario vive fuera del de edición (no se pueden anidar formularios) --}}
                        <button type="submit" form="generar-codigo" class="btn btn-fantasma btn-sm">Generar</button>
                    </div>
                    @if($nuevoCodigo)
                        <div class="codigo-nuevo">
                            <span class="num">{{ $nuevoCodigo['formateado'] }}</span>
                            <button type="button" class="btn btn-borde btn-sm" data-copiar="{{ str_replace(' ', '', $nuevoCodigo['formateado']) }}"><i class="bi bi-clipboard"></i> Copiar</button>
                        </div>
                        <div class="secundario" style="font-size:12.5px">Vale hasta el {{ $nuevoCodigo['vence'] }}. Solo se muestra esta vez.</div>
                    @endif
                    <p class="secundario mt-1 mb-0" style="font-size:12.5px">
                        @if($vigentes->count())
                            {{ $vigentes->count() === 1 ? '1 código vigente' : $vigentes->count() . ' códigos vigentes' }} · el más reciente vence el {{ $f($vigentes->first()->expira_at) }}.
                        @else
                            Sin códigos vigentes. Se genera solo al enviar por correo o WhatsApp.
                        @endif
                    </p>
                </div>
            @else
                <p class="secundario m-0">No se puede responder en línea: {{ $p->estado === 'rechazada' ? 'está rechazada' : 'la vigencia ya pasó (extiéndela para habilitarlo)' }}.</p>
            @endif
        @endif
    </div>
</section>

<section class="panel">
    <div class="panel-head"><h2>Historial</h2><span class="ayuda">{{ $eventosTodos->count() }}</span></div>
    <div class="panel-body">
        @if($eventosTodos->isEmpty())
            <p class="secundario m-0">Aquí quedará registrado cada envío, cambio, solicitud y aceptación.</p>
        @else
            <ol class="historial">
                @foreach($eventosTodos as $ev)
                    <li class="h-{{ $ev->tipo }} {{ $ev->actor }}">
                        <span class="pt"><i class="bi {{ $ev->icono }}"></i></span>
                        <div class="t">{{ $ev->titulo }} @unless($ev->visible_cliente)<span class="secundario" title="El cliente no ve esta línea"><i class="bi bi-eye-slash"></i></span>@endunless</div>
                        <div class="f">{{ $f($ev->created_at) }} · {{ $ev->actor === 'cliente' ? ($ev->autor ?: 'Cliente') . ' (cliente)' : ($ev->actor === 'sistema' ? 'Sistema' : ($ev->autor ?: 'Agencia')) }}</div>
                        @if($ev->detalle)<div class="d">{{ $ev->detalle }}</div>@endif
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</section>

@once
@push('head')
<style>
    .cambios-pedidos { border-left: 3px solid #6E32B5; background: #F7F2FD; border-radius: 0 10px 10px 0; padding: 10px 12px; }
    .cambios-pedidos .msg { white-space: pre-line; font-size: 14px; margin-top: 2px; }
    .codigo-nuevo { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 8px; padding: 10px 12px; border-radius: 10px; background: var(--green-soft); border: 1px solid #BDF2D6; }
    .codigo-nuevo span { font-size: 24px; font-weight: 700; letter-spacing: .14em; }
    .historial { list-style: none; margin: 0; padding: 0; }
    .historial li { position: relative; padding: 0 0 16px 34px; }
    .historial li::before { content: ''; position: absolute; left: 12px; top: 26px; bottom: 2px; width: 1.5px; background: var(--line); }
    .historial li:last-child { padding-bottom: 0; }
    .historial li:last-child::before { display: none; }
    .historial .pt { position: absolute; left: 0; top: 0; width: 25px; height: 25px; border-radius: 50%; background: var(--sunken); display: grid; place-items: center; font-size: 12px; color: var(--text-2); }
    .historial li.cliente .pt { background: #EAF2FF; color: #2459C7; }
    .historial li.h-aceptada .pt { background: var(--green-soft); color: var(--green-ink); }
    .historial li.h-cambios .pt { background: #F2EAFB; color: #6E32B5; }
    .historial li.h-bloqueo .pt { background: var(--red-soft); color: var(--red); }
    .historial .t { font-size: 14px; font-weight: 500; }
    .historial .f { font-size: 12.5px; color: var(--muted); }
    .historial .d { font-size: 13.5px; color: var(--text-2); white-space: pre-line; margin-top: 3px; }
</style>
@endpush
@endonce
