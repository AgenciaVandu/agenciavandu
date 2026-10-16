@extends('admin.layout')
@section('titulo', 'Correo de envío')

@php
    $conectado = $s['activo'];
    $estado = [
        'proveedor' => old('proveedor', $s['proveedor']), 'host' => old('host', $s['host']), 'puerto' => (int) old('puerto', $s['puerto']),
        'cifrado' => old('cifrado', $s['cifrado']), 'desde' => old('desde', $s['desde']), 'usuario' => old('usuario', $s['usuario']),
        'proveedores' => $proveedores, 'editando' => ! $conectado || $errors->any() || session('smtp_error'),
    ];
@endphp

@push('head')
<style>
    .ce { max-width: 760px; }
    .ce-estado { display: flex; gap: 14px; align-items: center; padding: 18px 20px; }
    .ce-estado .ico { width: 44px; height: 44px; border-radius: 12px; display: grid; place-items: center; font-size: 20px; flex: none; }
    .ce-estado .ico.ok { background: var(--green-soft); color: var(--green-ink); }
    .ce-estado .ico.base { background: var(--sunken); color: var(--muted); border: 1px solid var(--line); }
    .ce-estado b { display: block; font-weight: 600; }
    .ce-estado span { color: var(--muted); font-size: 14px; }
    .provs { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
    .provs label { margin: 0; }
    .provs input { position: absolute; opacity: 0; pointer-events: none; }
    .provs span { display: flex; align-items: center; justify-content: center; text-align: center; min-height: 54px; padding: 8px; border: 1.5px solid var(--line); border-radius: 10px; font-size: 13.5px; cursor: pointer; background: var(--surface); }
    .provs input:checked + span { border-color: var(--ink); background: var(--sunken); font-weight: 500; }
    .provs input:focus-visible + span { outline: 2px solid var(--blue); outline-offset: 2px; }
    .ayuda-prov { background: var(--blue-soft); color: var(--blue); border-radius: 10px; padding: 10px 14px; font-size: 13.5px; }
    @media (max-width: 575.98px) { .provs { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
@endpush

@section('contenido')
<div class="ce" x-data="correoEnvio({{ Js::from($estado) }})">
    <div class="page-head">
        <div>
            <h1>Correo de envío</h1>
            <p class="sub">Conecta tu correo para que las cotizaciones, avisos e invitaciones salgan desde tu cuenta y queden en tus enviados.</p>
        </div>
    </div>

    <section class="panel mb-4">
        <div class="ce-estado">
            @if($conectado)
                <span class="ico ok"><i class="bi bi-envelope-check"></i></span>
                <div class="flex-grow-1"><b>Conectado: {{ $s['desde'] }}</b>
                    <span>Probado {{ $s['verificado'] ? \Illuminate\Support\Carbon::parse($s['verificado'])->setTimezone(config('vandu.zona_horaria'))->locale('es')->isoFormat('D MMM YYYY, h:mm a') : '' }} · {{ $s['host'] }}:{{ $s['puerto'] }}</span></div>
                <button type="button" class="btn btn-borde btn-sm" x-show="!editando" @click="editando = true">Cambiar datos</button>
            @else
                <span class="ico base"><i class="bi bi-envelope"></i></span>
                <div><b>Usando el correo de la plataforma</b>
                    <span>Tus correos salen desde {{ config('vandu.correo.desde') }} con el nombre “{{ config('vandu.correo.nombre') }}”, y las respuestas te llegan a {{ config('vandu.correo.responder_a') }}.</span></div>
            @endif
        </div>
    </section>

    @if($message = session('smtp_error'))
        <div class="aviso aviso-error" role="alert"><i class="bi bi-exclamation-circle-fill align-self-start mt-1"></i>
            <div><strong>No se pudo mandar el correo de prueba</strong><div>{{ $message }}</div><div class="small mt-1">Guardamos los datos, pero tus correos siguen saliendo desde la plataforma hasta que la prueba funcione.</div></div></div>
    @endif

    <form method="post" action="{{ route('admin.negocio.correo.guardar') }}" class="panel panel-body d-grid gap-3" x-show="editando" x-cloak>
        @csrf
        <div>
            <span class="form-label d-block">¿Dónde tienes tu correo?</span>
            <div class="provs" role="radiogroup" aria-label="Proveedor de correo">
                @foreach($proveedores as $k => $p)
                    <label><input type="radio" name="proveedor" value="{{ $k }}" x-model="proveedor" @change="elegir('{{ $k }}')"><span>{{ $p['texto'] }}</span></label>
                @endforeach
            </div>
        </div>
        <div class="ayuda-prov" x-text="proveedores[proveedor].ayuda"></div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="ce-desde">Correo de envío</label>
                <input id="ce-desde" type="email" name="desde" class="form-control" x-model="desde" @input="if (!usuarioTocado) usuario = desde" required maxlength="190" placeholder="ventas@tunegocio.com" autocomplete="off">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="ce-usuario">Usuario <span class="secundario fw-normal">(casi siempre el mismo correo)</span></label>
                <input id="ce-usuario" name="usuario" class="form-control" x-model="usuario" @input="usuarioTocado = true" required maxlength="190" autocomplete="off" spellcheck="false">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="ce-clave">Contraseña{{ $s['tiene_clave'] ? '' : '' }}</label>
                <input id="ce-clave" type="password" name="clave" class="form-control" maxlength="255" autocomplete="new-password" @if(! $s['tiene_clave']) required @endif placeholder="{{ $s['tiene_clave'] ? 'Déjala vacía para conservar la guardada' : '' }}">
                <div class="form-text">Se guarda cifrada y solo se usa para enviar.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="ce-host">Servidor de salida (SMTP)</label>
                <input id="ce-host" name="host" class="form-control num" x-model="host" required maxlength="190" placeholder="mail.tunegocio.com" spellcheck="false" autocomplete="off">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="ce-puerto">Puerto</label>
                <select id="ce-puerto" name="puerto" class="form-select num" x-model.number="puerto">
                    @foreach([465, 587, 25, 2525] as $pt)<option value="{{ $pt }}">{{ $pt }}</option>@endforeach
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="ce-cifrado">Seguridad</label>
                <select id="ce-cifrado" name="cifrado" class="form-select" x-model="cifrado">
                    <option value="ssl">SSL</option><option value="tls">TLS</option><option value="ninguno">Ninguna</option>
                </select>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-center">
            <button class="btn btn-primario"><i class="bi bi-send-check me-1"></i> Guardar y mandar correo de prueba</button>
            @if($conectado)<button type="button" class="btn btn-borde" @click="editando = false">Cancelar</button>@endif
            <span class="secundario small">La prueba llega a {{ auth()->user()->email }}.</span>
        </div>
    </form>

    @if($conectado || $s['tiene_clave'])
        <form method="post" action="{{ route('admin.negocio.correo.desactivar') }}" class="mt-3" onsubmit="return confirm('¿Desconectar tu correo? Los correos volverán a salir desde la plataforma.')">
            @csrf @method('delete')
            <button class="btn btn-link text-danger p-0 small">Desconectar mi correo</button>
        </form>
    @endif
</div>
@endsection

@push('scripts')
<script>
function correoEnvio(d) {
    return {
        ...d,
        usuarioTocado: d.usuario !== '' && d.usuario !== d.desde,
        elegir(k) {
            const p = this.proveedores[k];
            if (p.host) this.host = p.host;
            else if (!this.host || Object.values(this.proveedores).some((x) => x.host === this.host)) {
                const dom = (this.desde.split('@')[1] || '');
                this.host = dom ? 'mail.' + dom : '';
            }
            this.puerto = p.puerto;
            this.cifrado = p.cifrado;
        },
    };
}
</script>
@endpush
