{{-- Ficha del atleta (pestaña "Clases y paquetes" del cliente). $a = Cliente --}}
@include('admin.fitness._estilos')
@php
    $tz = config('vandu.zona_horaria');
    $hoy = now($tz)->toDateString();
    $membresias = $a->membresias()->with('asignador')->get();
    $actual = $membresias->filter->vigente->sortBy(fn ($m) => [$m->con_creditos ? 0 : 1, $m->vence_el->toDateString()])->first();
    $proximas = $a->reservaciones()->getQuery()->reorder()->with('clase.tipo')->where('estado', 'reservada')
        ->whereHas('clase', fn ($q) => $q->where('termina_at', '>', now()))->get()->sortBy(fn ($r) => $r->clase->inicia_at)->values();
    $historial = $a->reservaciones()->with('clase.tipo')->whereIn('estado', ['asistio', 'falta', 'cancelada'])->take(25)->get();
    $asistencias = $a->reservaciones()->where('estado', 'asistio')->count();
    $paquetes = \App\Models\Paquete::where('activo', true)->orderBy('precio')->get();
    // Desde Seguimiento llega con el paquete que toca (prueba, primer paquete o el mismo para renovar)
    $propuesto = $paquetes->firstWhere('id', (int) request('paquete')) ?? $paquetes->first();
    $u = auth()->user();
    $gestiona = $u->puede('membresias');
    $acceso = session('acceso');
@endphp

@push('head')
<style>
    .atleta-g { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 24px; align-items: start; }
    .paquete-actual { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 16px; align-items: center; padding: 18px 20px; }
    .paquete-actual .grande { font-size: 30px; font-weight: 650; line-height: 1; }
    .credencial-mini { display: grid; place-items: center; padding: 14px; }
    .credencial-mini canvas, .credencial-mini img { width: 180px !important; height: 180px !important; }
    @media (max-width: 991.98px) { .atleta-g { grid-template-columns: minmax(0, 1fr); } }
</style>
@endpush

<div class="atleta-g">
    <div class="d-grid gap-4">
        {{-- Paquete vigente --}}
        <section class="panel">
            @if($actual)
                <div class="paquete-actual">
                    <div class="fx-avatar g" style="background: var(--ink); color: var(--green)"><i class="bi {{ $actual->ilimitada ? 'bi-infinity' : 'bi-ticket-perforated' }}"></i></div>
                    <div class="min-w-0">
                        <div class="principal">{{ $actual->nombre }} @if(! $actual->pagada)<span class="estado estado-rechazada ms-1">Pago pendiente</span>@endif</div>
                        <div class="secundario small">Vence el {{ $actual->vence_el->locale('es')->isoFormat('D [de] MMMM') }} ({{ $actual->dias_restantes <= 0 ? 'hoy' : 'en ' . $actual->dias_restantes . ' ' . ($actual->dias_restantes === 1 ? 'día' : 'días') }}) · {{ $asistencias }} {{ $asistencias === 1 ? 'clase tomada' : 'clases tomadas' }} en total</div>
                    </div>
                    <div class="text-end"><div class="grande num">{{ $actual->ilimitada ? '∞' : $actual->restantes }}</div><div class="secundario small">{{ $actual->ilimitada ? 'ilimitada' : 'de ' . $actual->creditos . ' clases' }}</div></div>
                </div>
            @else
                <div class="panel-body d-flex align-items-center gap-3">
                    <i class="bi bi-exclamation-circle fs-4 text-danger"></i>
                    <div><b>Sin paquete vigente</b><div class="secundario small">No puede reservar ni entrar a clase hasta que le asignes uno.</div></div>
                </div>
            @endif
        </section>

        {{-- Asignar paquete --}}
        @if($gestiona)
            <section class="panel" id="asignar">
                <div class="panel-head"><h2>{{ $actual ? 'Renovar o agregar paquete' : 'Asignar paquete' }}</h2></div>
                @if($paquetes->isEmpty())
                    <div class="panel-body secundario">Primero crea tus paquetes. <a href="{{ route('admin.paquetes') }}">Crear paquetes</a></div>
                @else
                <form method="post" action="{{ route('admin.membresias.store', $a) }}" class="panel-body"
                      x-data="{ paquetes: {{ Js::from($paquetes->mapWithKeys(fn ($p) => [$p->id => ['precio' => $p->precio, 'vigencia' => $p->vigencia_texto, 'creditos' => $p->creditos_texto]])) }}, sel: '{{ old('producto_id', $propuesto->id) }}', precio: {{ (float) old('precio', $propuesto->precio) }}, pagado: {{ old('pagado', true) ? 'true' : 'false' }} }">
                    @csrf
                    <div class="fx-grid-form">
                        <div style="grid-column: span 2"><label class="form-label small" for="paq">Paquete</label>
                            <select id="paq" name="producto_id" class="form-select" x-model="sel" @change="precio = paquetes[sel]?.precio ?? 0">
                                @foreach($paquetes as $p)<option value="{{ $p->id }}">{{ $p->nombre }} · ${{ number_format($p->precio, 0) }}</option>@endforeach</select>
                            <div class="secundario small mt-1" x-text="paquetes[sel] ? paquetes[sel].creditos + ' · vigencia ' + paquetes[sel].vigencia : ''"></div>
                            @error('producto_id')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                        <div><label class="form-label small" for="ini">Empieza</label>
                            <input id="ini" name="inicia_el" type="date" class="form-control num" value="{{ old('inicia_el', $actual && $actual->vence_el->toDateString() >= $hoy && $actual->con_creditos ? $actual->vence_el->copy()->addDay()->toDateString() : $hoy) }}" required></div>
                        <div><label class="form-label small" for="pre">Precio</label>
                            <div class="input-group"><span class="input-group-text">$</span><input id="pre" name="precio" type="number" step="0.01" min="0" class="form-control num" x-model.number="precio"></div></div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
                        <label class="form-check m-0"><input type="checkbox" name="pagado" value="1" class="form-check-input" x-model="pagado"> <span class="form-check-label">Ya pagó</span></label>
                        <select name="metodo_pago" class="form-select form-select-sm w-auto" x-show="pagado" aria-label="Método de pago">
                            @foreach(\App\Models\Membresia::METODOS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                        <button class="btn btn-primario ms-auto"><i class="bi bi-check2 me-1"></i> Asignar</button>
                    </div>
                </form>
                @endif
            </section>
        @endif

        {{-- Próximas clases --}}
        <section class="panel">
            <div class="panel-head"><h2>Próximas clases</h2>@if($u->puede('clases'))<a href="{{ route('admin.clases.index') }}" class="btn btn-fantasma btn-sm">Agenda</a>@endif</div>
            <div class="fx-lista">
                @forelse($proximas as $r)
                    <div class="fx-fila">
                        <span class="fx-color" style="background: {{ $r->clase->color }}"></span>
                        <a href="{{ route('admin.clases.show', $r->clase) }}" class="flex-grow-1 text-reset"><div class="principal">{{ $r->clase->nombre }}</div>
                            <div class="secundario small">{{ $r->clase->fecha_texto }} · {{ $r->clase->horario }}</div></a>
                        @if($gestiona)
                            <form method="post" action="{{ route('admin.reservaciones.cancelar', $r) }}" onsubmit="return confirm('¿Quitar de la clase? Se le regresa su clase.')">@csrf<input type="hidden" name="devolver" value="1">
                                <button class="btn btn-fantasma btn-sm text-danger" aria-label="Quitar de la clase"><i class="bi bi-x-lg"></i></button></form>
                        @endif
                    </div>
                @empty
                    <div class="panel-body secundario small">No tiene clases reservadas.</div>
                @endforelse
            </div>
        </section>

        {{-- Membresías --}}
        <section class="panel">
            <div class="panel-head"><h2>Paquetes</h2><span class="ayuda">{{ $membresias->count() }}</span></div>
            <div class="fx-lista">
                @forelse($membresias as $m)
                    @php [$tx, $cl] = $m->estado_vista; @endphp
                    <details class="fx-editar">
                        <summary class="fx-fila">
                            <div class="flex-grow-1 min-w-0">
                                <div class="principal">{{ $m->nombre }} <span class="estado estado-{{ $cl }} ms-1">{{ $tx }}</span></div>
                                <div class="secundario small">{{ $m->inicia_el->locale('es')->isoFormat('D MMM') }} – {{ $m->vence_el->locale('es')->isoFormat('D MMM YYYY') }} · {{ $m->ilimitada ? 'ilimitada' : $m->usados . ' de ' . $m->creditos . ' usadas' }}
                                    · {{ $m->pagada ? 'pagado ' . ($m->metodo_pago ? 'en ' . mb_strtolower(\App\Models\Membresia::METODOS[$m->metodo_pago] ?? $m->metodo_pago) : '') : 'pendiente de pago' }}</div>
                            </div>
                            <b class="num">${{ number_format($m->precio, 0) }}</b>
                            @if($gestiona && $m->estado !== 'cancelada')<span class="btn btn-fantasma btn-sm ver"><i class="bi bi-pencil"></i></span>@endif
                        </summary>
                        @if($gestiona && $m->estado !== 'cancelada')
                            <form method="post" action="{{ route('admin.membresias.update', $m) }}" class="px-3 pb-3" x-data="{ pagado: {{ $m->pagada ? 'true' : 'false' }} }">
                                @csrf @method('put')
                                <div class="fx-grid-form">
                                    <div><label class="form-label small">Precio</label><div class="input-group"><span class="input-group-text">$</span><input name="precio" type="number" step="0.01" min="0" class="form-control num" value="{{ $m->precio }}" required></div></div>
                                    <div><label class="form-label small">Vence</label><input name="vence_el" type="date" class="form-control num" value="{{ $m->vence_el->toDateString() }}" required></div>
                                    @unless($m->ilimitada)<div><label class="form-label small">Clases</label><input name="creditos" type="number" min="{{ max(1, $m->usados) }}" class="form-control num" value="{{ $m->creditos }}"></div>@endunless
                                </div>
                                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                                    <label class="form-check m-0"><input type="checkbox" name="pagado" value="1" class="form-check-input" x-model="pagado"> <span class="form-check-label small">Pagado</span></label>
                                    <input type="date" name="pagado_el" class="form-control form-control-sm num w-auto" value="{{ $m->pagado_el?->toDateString() ?? $hoy }}" x-show="pagado" aria-label="Fecha de pago">
                                    <select name="metodo_pago" class="form-select form-select-sm w-auto" x-show="pagado" aria-label="Método">@foreach(\App\Models\Membresia::METODOS as $k => $l)<option value="{{ $k }}" @selected($m->metodo_pago === $k)>{{ $l }}</option>@endforeach</select>
                                </div>
                                <div class="d-flex gap-2 mt-2"><button class="btn btn-primario btn-sm">Guardar</button>
                                    <button class="btn btn-fantasma btn-sm text-danger ms-auto" form="cancelar-m-{{ $m->id }}">Cancelar paquete</button></div>
                            </form>
                            <form id="cancelar-m-{{ $m->id }}" method="post" action="{{ route('admin.membresias.cancelar', $m) }}" onsubmit="return confirm('¿Cancelar este paquete? Deja de contar en Finanzas y se liberan sus lugares reservados.')">@csrf</form>
                        @endif
                    </details>
                @empty
                    <div class="panel-body secundario small">Aún no tiene paquetes.</div>
                @endforelse
            </div>
        </section>

        {{-- Historial --}}
        <section class="panel">
            <div class="panel-head"><h2>Historial de asistencia</h2><span class="ayuda">{{ $asistencias }} asistencias</span></div>
            <div class="fx-lista">
                @forelse($historial as $r)
                    <div class="fx-fila"><span class="fx-color" style="background: {{ $r->clase->color }}"></span>
                        <div class="flex-grow-1"><div>{{ $r->clase->nombre }}</div><div class="secundario small">{{ $r->clase->fecha_texto }} · {{ $r->clase->horario }}</div></div>
                        <span class="estado estado-{{ $r->estado_clase }}">{{ $r->estado_texto }}</span></div>
                @empty
                    <div class="panel-body secundario small">Todavía no ha tomado clases.</div>
                @endforelse
            </div>
        </section>
    </div>

    <aside class="d-grid gap-4">
        {{-- Foto --}}
        <section class="panel">
            <div class="panel-head"><h2>Foto</h2></div>
            <div class="panel-body d-flex align-items-center gap-3">
                <x-fitness.avatar :atleta="$a" grande />
                @if($gestiona || $u->puede('clientes'))
                    <div class="d-grid gap-2 flex-grow-1">
                        <form method="post" action="{{ route('admin.atletas.foto.subir', $a) }}" enctype="multipart/form-data" data-recargar>@csrf
                            <label class="btn btn-borde btn-sm w-100 m-0"><i class="bi bi-camera me-1"></i> {{ $a->foto ? 'Cambiar' : 'Tomar o subir' }}
                                <input type="file" name="foto" accept="image/*" capture="user" class="d-none" onchange="this.form.submit()"></label></form>
                        @if($a->foto)<form method="post" action="{{ route('admin.atletas.foto.quitar', $a) }}">@csrf @method('delete')<button class="btn btn-fantasma btn-sm w-100 text-danger">Quitar</button></form>@endif
                        @error('foto')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                @endif
            </div>
            <p class="secundario small px-3 pb-3 m-0">Aparece al escanear su QR para que nadie use una credencial prestada.</p>
        </section>

        {{-- Credencial --}}
        <section class="panel">
            <div class="panel-head"><h2>Credencial QR</h2><a href="{{ route('admin.atletas.credencial', $a) }}" class="btn btn-fantasma btn-sm" data-recargar target="_blank"><i class="bi bi-printer"></i></a></div>
            <div class="credencial-mini"><div data-qr="{{ $a->qr }}" aria-label="Código QR de {{ $a->nombre }}"></div></div>
            <div class="panel-body pt-0 d-flex gap-2">
                <form method="post" action="{{ route('admin.atletas.qr', $a) }}" onsubmit="return confirm('¿Generar una credencial nueva? La anterior deja de servir.')" class="flex-grow-1">@csrf
                    <button class="btn btn-fantasma btn-sm w-100"><i class="bi bi-arrow-repeat me-1"></i> Credencial nueva</button></form>
            </div>
        </section>

        {{-- Acceso al portal --}}
        <section class="panel">
            <div class="panel-head"><h2>Su portal</h2></div>
            <div class="panel-body d-grid gap-2">
                <div class="small">@if($a->con_acceso)<i class="bi bi-check-circle-fill text-success"></i> Ya tiene contraseña{{ $a->ultimo_acceso_at ? ' · entró ' . $a->ultimo_acceso_at->locale('es')->diffForHumans() : '' }}.
                    @else<i class="bi bi-circle"></i> Aún no crea su contraseña.@endif</div>
                <div class="secundario small">Entra con su correo{{ $a->email ? ' (' . $a->email . ')' : '' }} en
                    <a href="{{ \App\Support\Fitness\Portal::url() }}" target="_blank" rel="noopener">{{ preg_replace('#^https?://#', '', \App\Support\Fitness\Portal::url()) }}</a></div>
                @if($acceso)
                    <div class="p-2 rounded-3" style="background: var(--green-soft)">
                        <div class="small fw-medium mb-1">Enlace para crear su contraseña (sirve 7 días):</div>
                        <div class="d-flex gap-2"><input class="form-control form-control-sm" value="{{ $acceso['url'] }}" readonly onclick="this.select()" aria-label="Enlace de acceso">
                            <button type="button" class="btn btn-borde btn-sm" data-copiar="{{ $acceso['url'] }}"><i class="bi bi-copy"></i></button></div>
                        @if($acceso['whatsapp'])<a href="{{ $acceso['whatsapp'] }}" target="_blank" rel="noopener" class="btn btn-primario btn-sm w-100 mt-2"><i class="bi bi-whatsapp me-1"></i> Mandar por WhatsApp</a>@endif
                    </div>
                @endif
                @if($gestiona || $u->puede('clientes'))
                    @if($a->email)
                        <form method="post" action="{{ route('admin.atletas.acceso', $a) }}">@csrf<button class="btn btn-borde btn-sm w-100"><i class="bi bi-key me-1"></i> {{ $a->con_acceso ? 'Mandar enlace para cambiar contraseña' : 'Mandarle su acceso' }}</button></form>
                    @else
                        <a href="{{ route('admin.clientes.edit', $a) }}" class="btn btn-borde btn-sm">Captura su correo para darle acceso</a>
                    @endif
                @endif
            </div>
        </section>

        @if($gestiona || $u->puede('clientes'))
            <form method="post" action="{{ route('admin.atletas.activo', $a) }}" onsubmit="return confirm({{ Js::from($a->activo === false ? '¿Reactivar?' : '¿Dar de baja? No podrá reservar ni entrar con su QR.') }})">@csrf @method('patch')
                <button class="btn btn-fantasma w-100 {{ $a->activo === false ? '' : 'text-danger' }}"><i class="bi {{ $a->activo === false ? 'bi-person-check' : 'bi-person-slash' }} me-1"></i> {{ $a->activo === false ? 'Reactivar atleta' : 'Dar de baja' }}</button></form>
        @endif
    </aside>
</div>

@push('scripts')
<script>
(function () {
    const pintar = () => document.querySelectorAll('[data-qr]:not([data-hecho])').forEach((el) => {
        if (!window.QRCode) return;
        el.setAttribute('data-hecho', '1');
        new QRCode(el, { text: el.dataset.qr, width: 360, height: 360, correctLevel: QRCode.CorrectLevel.M });
    });
    if (window.QRCode) pintar();
    else { const s = document.createElement('script'); s.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js'; s.onload = pintar; document.head.appendChild(s); }
})();
</script>
@endpush
