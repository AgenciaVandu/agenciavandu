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
                        <label><input type="radio" name="giro" value="{{ $k }}" @checked(old('giro', 'arquitectura') === $k)><i class="bi {{ $g['icono'] }}"></i><div><b style="font-weight:600">{{ $g['nombre'] }}</b><div class="secundario" style="font-size:12.5px">{{ count($g['proyectos'] ?? config('vandu.proyectos')) }} tipos de proyecto · {{ count($g['roles']) + 1 }} roles</div></div></label>
                    @endforeach
                </div>
            </div>
            <div><label class="form-label" for="pf-dueno">Quién lo administra</label><input id="pf-dueno" name="dueno_nombre" class="form-control" value="{{ old('dueno_nombre') }}" required maxlength="120"></div>
            <div><label class="form-label" for="pf-correo">Su correo</label><input id="pf-correo" type="email" name="dueno_email" class="form-control" value="{{ old('dueno_email') }}" required maxlength="190"></div>
            <div><label class="form-label" for="pf-tel">Su WhatsApp <span class="secundario fw-normal">(opcional)</span></label><input id="pf-tel" name="dueno_tel" class="form-control num" value="{{ old('dueno_tel') }}" maxlength="30"></div>
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
                    <textarea name="notas" class="form-control form-control-sm" rows="2" maxlength="2000" placeholder="Notas internas (plan, contacto, pagos…)">{{ $c->notas }}</textarea>
                    @if($c->id !== $principal)
                        <label class="form-check form-switch m-0"><input type="checkbox" class="form-check-input" role="switch" name="activa" value="1" @checked($c->activa)> <span class="form-check-label" style="font-size:13.5px">Activa (si la suspendes, su equipo no puede entrar ni se abren sus enlaces)</span></label>
                    @endif
                    <div><button class="btn btn-primario btn-sm">Guardar</button></div>
                </form>
                </details>
            </div>
        </section>
    @endforeach
</div>
</div>
@endsection
