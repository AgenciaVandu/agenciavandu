@extends('admin.layout')
@section('titulo', 'Plataforma')

@php $enlace = session('enlace'); @endphp

@push('head')
<style>
    .pf-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(330px, 1fr)); gap: 16px; align-items: start; }
    .pf-card .cab { display: flex; gap: 12px; align-items: center; }
    .pf-card .cab .n { font-weight: 600; font-size: 16px; }
    .pf-card .cab .g { font-size: 13px; color: var(--muted); }
    .pf-nums { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 14px 0 4px; }
    .pf-nums div { background: var(--sunken); border-radius: 10px; padding: 8px 10px; }
    .pf-nums b { display: block; font-size: 17px; font-variant-numeric: tabular-nums; }
    .pf-nums span { font-size: 11.5px; color: var(--muted); }
    .pf-card.apagada { opacity: .65; }
    .pf-card details summary { cursor: pointer; font-size: 13.5px; color: var(--text-2); list-style: none; }
    .pf-card details summary::-webkit-details-marker { display: none; }
    .pf-nuevo { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .pf-nuevo .ancho { grid-column: 1 / -1; }
    .giro-op { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; }
    .giro-op label { display: flex; gap: 10px; align-items: center; border: 1.5px solid var(--line-strong); border-radius: 12px; padding: 12px 14px; cursor: pointer; }
    .giro-op label:has(input:checked) { border-color: var(--ink); background: var(--sunken); }
    .giro-op i { font-size: 20px; }
    .giro-op input { display: none; }
    .enlace-listo { border: 1px solid #BFEBD4; background: var(--green-soft); border-radius: 12px; padding: 14px; display: grid; gap: 10px; margin-bottom: 18px; }
    @media (max-width: 575.98px) { .pf-nuevo { grid-template-columns: minmax(0, 1fr); } .pf-grid { grid-template-columns: minmax(0, 1fr); } }
</style>
@endpush

@section('contenido')
<div x-data="{ nuevo: {{ $errors->any() && old('dueno_email') !== null ? 'true' : 'false' }} }">
<div class="page-head">
    <div>
        <h1>Plataforma</h1>
        <p class="sub">Los negocios que usan el panel. Cada uno ve solo sus clientes, su equipo y su información.</p>
    </div>
    <button type="button" class="btn btn-primario" @click="nuevo = true" x-show="!nuevo"><i class="bi bi-plus-lg me-1"></i> Nueva cuenta</button>
</div>

@if($enlace)
    <div class="enlace-listo">
        <b style="font-size:14px"><i class="bi bi-link-45deg"></i> Invitación para {{ $enlace['cuenta'] }} · sirve 7 días</b>
        <div class="d-flex gap-2">
            <input class="form-control" value="{{ $enlace['url'] }}" readonly onfocus="this.select()" aria-label="Enlace de invitación" style="font-size:13px; background:#fff">
            <button type="button" class="btn btn-borde text-nowrap" data-copiar="{{ $enlace['url'] }}"><i class="bi bi-clipboard"></i> Copiar</button>
        </div>
        <div><a href="{{ $enlace['whatsapp'] }}" target="_blank" rel="noopener" class="btn btn-borde btn-sm"><i class="bi bi-whatsapp me-1"></i> Mandar por WhatsApp</a></div>
    </div>
@endif

<section class="panel mb-4" x-show="nuevo" x-cloak>
    <div class="panel-head"><h2>Nueva cuenta</h2><span class="ayuda">Se crea con los tipos de proyecto y roles de su giro</span></div>
    <form method="post" action="{{ route('admin.plataforma.store') }}" class="panel-body">
        @csrf
        <div class="pf-nuevo">
            <div class="ancho"><label class="form-label" for="pf-nombre">Nombre del negocio</label><input id="pf-nombre" name="nombre" class="form-control" value="{{ old('nombre') }}" required maxlength="120" placeholder="Estudio Ceiba Arquitectura"></div>
            <div class="ancho">
                <span class="form-label d-block">Giro</span>
                <div class="giro-op">
                    @foreach($giros as $k => $g)
                        <label><input type="radio" name="giro" value="{{ $k }}" @checked(old('giro', array_key_first($giros)) === $k)><i class="bi {{ $g['icono'] }}"></i><div><b style="font-weight:600">{{ $g['nombre'] }}</b><div class="secundario" style="font-size:12.5px">{{ count($g['proyectos'] ?? config('vandu.proyectos')) }} tipos de proyecto · {{ count($g['roles']) + 1 }} roles</div></div></label>
                    @endforeach
                </div>
            </div>
            <div><label class="form-label" for="pf-dueno">Quién lo administra</label><input id="pf-dueno" name="dueno_nombre" class="form-control" value="{{ old('dueno_nombre') }}" required maxlength="120"></div>
            <div><label class="form-label" for="pf-correo">Su correo</label><input id="pf-correo" type="email" name="dueno_email" class="form-control" value="{{ old('dueno_email') }}" required maxlength="190"></div>
            <div><label class="form-label" for="pf-tel">Su WhatsApp <span class="secundario fw-normal">(opcional)</span></label><input id="pf-tel" name="dueno_tel" class="form-control num" value="{{ old('dueno_tel') }}" maxlength="30"></div>
            <label class="form-check m-0"><input type="hidden" name="ejemplos" value="0"><input type="checkbox" class="form-check-input" name="ejemplos" value="1" checked> <span class="form-check-label">Cargar datos de ejemplo <span class="secundario">(productos, autos, gimnasio con atletas o programa de lealtad, según su tipo)</span></span></label>
            <label class="form-check m-0 align-self-end"><input type="hidden" name="enviar_correo" value="0"><input type="checkbox" class="form-check-input" name="enviar_correo" value="1" checked> <span class="form-check-label">Mandarle la invitación por correo</span></label>
        </div>
        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primario px-4"><i class="bi bi-check2 me-1"></i> Crear cuenta</button>
            <button type="button" class="btn btn-borde" @click="nuevo = false">Cancelar</button>
        </div>
    </form>
</section>

<div class="pf-grid">
    @foreach($cuentas as $c)
        @php $g = $giros[$c->giro] ?? $giros['agencia']; $ultimo = $acceso[$c->id] ?? null; @endphp
        <section class="panel pf-card {{ $c->activa ? '' : 'apagada' }}">
            <div class="panel-body">
                <div class="cab">
                    @include('admin._avatar', ['nombre' => $c->nombre])
                    <div class="min-w-0 flex-grow-1">
                        <div class="n text-truncate">{{ $c->nombre }}</div>
                        <div class="g"><i class="bi {{ $g['icono'] }}"></i> {{ $g['nombre'] }}@if($c->id === $principal) · principal @endif @if(! $c->activa) · <b>Suspendida</b>@endif</div>
                    </div>
                </div>
                <div class="pf-nums">
                    <div><b>{{ $usuarios[$c->id] ?? 0 }}</b><span>Equipo</span></div>
                    <div><b>{{ $clientes[$c->id] ?? 0 }}</b><span>Clientes</span></div>
                    <div><b>{{ $cotiz[$c->id] ?? 0 }}</b><span>Cotizac.</span></div>
                    <div><b>{{ $proyectos[$c->id] ?? 0 }}</b><span>Proyectos</span></div>
                </div>
                @if($c->dominio)<p class="mb-2" style="font-size:13px"><i class="bi bi-globe2"></i> <a href="https://{{ $c->dominio }}/admin" target="_blank" rel="noopener" class="num">{{ $c->dominio }}</a></p>@endif
                <p class="secundario mb-3" style="font-size:12.5px">Alta {{ $c->created_at->locale('es')->isoFormat('D MMM YYYY') }} · último acceso {{ $ultimo ? \Illuminate\Support\Carbon::parse($ultimo)->locale('es')->diffForHumans() : 'nunca' }}</p>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <form method="post" action="{{ route('admin.plataforma.entrar', $c) }}">@csrf
                        <button class="btn btn-borde btn-sm" @disabled(! $c->activa)><i class="bi bi-box-arrow-in-right me-1"></i> {{ $c->id === auth()->user()->cuenta_id ? 'Ir a mi cuenta' : 'Entrar a su panel' }}</button>
                    </form>
                </div>
                <details class="mt-3">
                <summary><i class="bi bi-gear"></i> Ajustes de la cuenta</summary>
                <form method="post" action="{{ route('admin.plataforma.update', $c) }}" class="d-grid gap-2 mt-2 pt-3" style="border-top:1px solid var(--line)">
                    @csrf @method('put')
                    <input name="nombre" class="form-control form-control-sm" value="{{ $c->nombre }}" required maxlength="120" aria-label="Nombre">
                    <select name="giro" class="form-select form-select-sm" aria-label="Giro">
                        @foreach($giros as $k => $gg)<option value="{{ $k }}" @selected($c->giro === $k)>{{ $gg['nombre'] }}</option>@endforeach
                    </select>
                    @if($c->id !== $principal)
                        <div>
                            <label class="form-label small mb-1" for="dom-{{ $c->id }}">Dominio propio <span class="secundario fw-normal">(opcional)</span></label>
                            <input id="dom-{{ $c->id }}" name="dominio" class="form-control form-control-sm num" value="{{ old('dominio', $c->dominio) }}" maxlength="190" placeholder="app.sunegocio.mx" autocomplete="off" spellcheck="false">
                            <div class="secundario" style="font-size:12px; margin-top:4px">Su equipo entra en ese dominio con su marca, y sus enlaces de cotizaciones y productos salen con él. Antes hay que apuntarlo a este servidor.</div>
                        </div>
                    @endif
                    <div class="d-flex align-items-center gap-2">
                        <label class="small m-0" for="suc-{{ $c->id }}">Sucursales contratadas</label>
                        <input id="suc-{{ $c->id }}" type="number" name="sucursales_max" min="1" max="50" class="form-control form-control-sm num" style="width: 80px" value="{{ $c->ajuste('sucursales_max', 1) }}">
                    </div>
                    <input type="hidden" name="_extras" value="1">
                    <div class="rounded-3 p-2" style="background: var(--sunken)">
                        <div class="small fw-semibold mb-1">Funciones extra</div>
                        @foreach(\App\Http\Controllers\Admin\PlataformaController::EXTRAS as $ex)
                            @php $mx = \App\Support\Giros::MODULOS[$ex]; $porGiro = in_array($ex, $c->giro_info['modulos'] ?? [], true); @endphp
                            <label class="form-check small m-0">
                                <input type="checkbox" name="extras[]" value="{{ $ex }}" class="form-check-input" @checked($porGiro || in_array($ex, (array) $c->ajuste('modulos', []), true)) @disabled($porGiro)>
                                <span class="form-check-label"><i class="bi {{ $mx['icono'] }}"></i> {{ $mx['texto'] }}@if($porGiro) <span class="secundario">(viene con su tipo de empresa)</span>@endif
                                    <span class="secundario d-block" style="font-size:12px">{{ $mx['ayuda'] }}</span></span>
                            </label>
                            @if($ex === 'gastos')
                                <div class="d-flex align-items-center gap-2 mt-1 ms-4">
                                    <label class="small m-0" for="tope-{{ $c->id }}">Tickets leídos con IA al mes</label>
                                    <input id="tope-{{ $c->id }}" type="number" name="gastos_tope" min="0" max="5000" class="form-control form-control-sm num" style="width: 90px" value="{{ $c->ajuste('gastos_tope', \App\Support\Gastos\LectorTicket::TOPE) }}">
                                </div>
                            @endif
                        @endforeach
                    </div>
                    <textarea name="notas" class="form-control form-control-sm" rows="2" maxlength="2000" placeholder="Notas internas (plan, contacto, pagos…)">{{ $c->notas }}</textarea>
                    <div><button class="btn btn-primario btn-sm">Guardar</button></div>
                </form>
                </details>
                @if($c->id !== $principal && $c->id !== auth()->user()->cuenta_id)
                    @php $borra = \App\Support\Plataforma\BorrarNegocio::resumen($c); @endphp
                    <details class="mt-2" @if($errors->has('confirmar') && (int) old('_cuenta') === $c->id) open @endif>
                        <summary class="text-danger"><i class="bi bi-exclamation-octagon"></i> Suspender o eliminar</summary>
                        <div class="d-grid gap-2 mt-2 pt-3" style="border-top:1px solid var(--line)">
                            <form method="post" action="{{ route('admin.plataforma.estado', $c) }}" onsubmit="return confirm({{ Js::from($c->activa ? '¿Suspender ' . $c->nombre . '? Su equipo no podrá entrar y sus enlaces dejarán de abrir. Sus datos se conservan.' : '¿Activar ' . $c->nombre . ' otra vez?') }})">@csrf @method('patch')
                                <button class="btn btn-sm {{ $c->activa ? 'btn-borde' : 'btn-primario' }} w-100"><i class="bi {{ $c->activa ? 'bi-pause-circle' : 'bi-play-circle' }} me-1"></i> {{ $c->activa ? 'Suspender' : 'Activar' }}</button>
                                <div class="secundario mt-1" style="font-size:12px">{{ $c->activa ? 'Para cuando no paga o pausa su plan: no se borra nada.' : 'Su equipo vuelve a entrar con sus mismos datos.' }}</div>
                            </form>
                            <form method="post" action="{{ route('admin.plataforma.destroy', $c) }}" class="p-2 rounded-3" style="background:#FDECEC">@csrf @method('delete')
                                <input type="hidden" name="_cuenta" value="{{ $c->id }}">
                                <div style="font-size:12.5px" class="mb-2"><b>Eliminar para siempre</b>: {{ $borra['equipo'] }} del equipo, {{ $borra['clientes'] }} clientes, {{ $borra['cotizaciones'] }} cotizaciones, {{ $borra['proyectos'] }} proyectos y {{ $borra['productos'] }} productos o paquetes, con sus archivos. No se puede deshacer.
                                    @if($borra['correos'])Los correos {{ implode(', ', $borra['correos']) }} quedan libres para darlos de alta otra vez.@endif</div>
                                <input name="confirmar" class="form-control form-control-sm @if((int) old('_cuenta') === $c->id) @error('confirmar') is-invalid @enderror @endif" placeholder="Escribe: {{ $c->nombre }}" autocomplete="off" aria-label="Escribe el nombre del negocio para confirmar" required>
                                @if((int) old('_cuenta') === $c->id) @error('confirmar')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                                <button class="btn btn-sm w-100 mt-2" style="background:#B42318;color:#fff"><i class="bi bi-trash me-1"></i> Eliminar negocio</button>
                            </form>
                        </div>
                    </details>
                @endif
            </div>
        </section>
    @endforeach
</div>

<section class="mt-5" id="giros">
    <div class="page-head">
        <div>
            <h2 class="fs-5 fw-semibold mb-1">Tipos de empresa</h2>
            <p class="sub mb-0">Con qué arranca cada negocio: módulos, roles y tipos de proyecto. Después cada uno los ajusta en su panel.</p>
        </div>
        <a href="{{ route('admin.plataforma.giros.create') }}" class="btn btn-borde"><i class="bi bi-plus-lg me-1"></i> Nuevo tipo de empresa</a>
    </div>
    @error('giro')<div class="aviso aviso-error">{{ $message }}</div>@enderror
    <div class="pf-grid">
        @foreach($giros as $k => $g)
            @php $propio = $propios[$k] ?? null; $nP = count($g['proyectos'] ?? \App\Support\Cuentas::proyectosDeFabrica(new \App\Models\Cuenta(['giro' => $k]))); @endphp
            <section class="panel pf-card">
                <div class="panel-body">
                    <div class="cab">
                        <span class="avatar" style="background: var(--sunken); color: var(--ink); border: 1px solid var(--line)"><i class="bi {{ $g['icono'] }}"></i></span>
                        <div class="min-w-0 flex-grow-1">
                            <div class="n text-truncate">{{ $g['nombre'] }}</div>
                            <div class="g">{{ $propio ? 'Creado en plataforma' : 'De fábrica' }} · {{ $porGiro[$k] ?? 0 }} {{ ($porGiro[$k] ?? 0) === 1 ? 'negocio' : 'negocios' }}</div>
                        </div>
                        @if($propio)<a href="{{ route('admin.plataforma.giros.edit', $propio) }}" class="btn btn-fantasma btn-icono" title="Editar" aria-label="Editar {{ $g['nombre'] }}"><i class="bi bi-pencil"></i></a>@endif
                    </div>
                    @if(! empty($g['descripcion']))<p class="secundario mt-2 mb-0" style="font-size:13px">{{ $g['descripcion'] }}</p>@endif
                    <div class="d-flex flex-wrap gap-2 mt-3" style="font-size:12.5px">
                        <span class="estado estado-borrador">{{ $nP }} {{ $nP === 1 ? 'tipo de proyecto' : 'tipos de proyecto' }}</span>
                        <span class="estado estado-borrador">{{ count($g['roles'] ?? []) + 1 }} roles</span>
                        @foreach($g['modulos'] ?? [] as $m)
                            @if(isset(\App\Support\Giros::MODULOS[$m]))<span class="estado estado-enviada">{{ \App\Support\Giros::MODULOS[$m]['texto'] }}</span>@endif
                        @endforeach
                    </div>
                </div>
            </section>
        @endforeach
    </div>
</section>
</div>
@endsection
