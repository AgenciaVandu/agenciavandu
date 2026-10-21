@extends('admin.layout')
@section('titulo', 'Paquetes')
@include('admin.fitness._estilos')

@section('contenido')
<div class="page-head">
    <div><h1>Paquetes de clases</h1><p class="sub">Lo que vendes: clase de prueba, paquetes de clases o membresía ilimitada. Al asignarlo a un atleta, su precio cuenta en Finanzas.</p></div>
</div>

<div class="row g-4">
    <div class="col-lg-7 d-grid gap-3 align-content-start">
        @forelse($paquetes as $p)
            <details class="panel fx-editar" @if($errors->any() && old('_paquete') == $p->id) open @endif>
                <summary class="fx-fila">
                    <div class="fx-avatar" style="background: var(--ink); color: var(--green)"><i class="bi {{ $p->ilimitado ? 'bi-infinity' : 'bi-ticket-perforated' }}"></i></div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="principal">{{ $p->nombre }}
                            @if($p->es_prueba)<span class="fx-pastilla ms-1">Prueba</span>@endif
                            @unless($p->activo)<span class="fx-pastilla ms-1">Desactivado</span>@endunless</div>
                        <div class="secundario small">{{ $p->creditos_texto }} · {{ $p->vigencia_texto }}{{ $p->max_por_dia ? ' · máx. ' . $p->max_por_dia . ' al día' : '' }} · {{ $p->vigentes_count }} {{ $p->vigentes_count === 1 ? 'atleta' : 'atletas' }} con él</div>
                    </div>
                    <b class="num">${{ number_format($p->precio, 2) }}</b>
                    <span class="btn btn-fantasma btn-sm ver"><i class="bi bi-pencil"></i></span>
                </summary>
                <form method="post" action="{{ route('admin.paquetes.update', $p) }}" class="panel-body pt-0">
                    @csrf @method('put') <input type="hidden" name="_paquete" value="{{ $p->id }}">
                    @include('admin.fitness._paquete_campos', ['p' => $p, 'pre' => 'p' . $p->id])
                    <div class="d-flex gap-2 mt-3"><button class="btn btn-primario btn-sm">Guardar</button>
                        <button class="btn btn-fantasma btn-sm text-danger ms-auto" form="borrar-paq-{{ $p->id }}">Quitar</button></div>
                </form>
                <form id="borrar-paq-{{ $p->id }}" method="post" action="{{ route('admin.paquetes.destroy', $p) }}" onsubmit="return confirm({{ Js::from('¿Quitar ' . $p->nombre . '?') }})">@csrf @method('delete')</form>
            </details>
        @empty
            <div class="panel"><div class="vacio"><div class="ico"><i class="bi bi-ticket-perforated"></i></div><h3>Aún no tienes paquetes</h3><p>Crea los que vendes para asignarlos a tus atletas.</p></div></div>
        @endforelse
    </div>
    <div class="col-lg-5">
        <form method="post" action="{{ route('admin.paquetes.store') }}" class="panel panel-body" style="position: sticky; top: 24px">
            @csrf <input type="hidden" name="_paquete" value="nuevo">
            <h2 class="fs-6 fw-semibold mb-3">Nuevo paquete</h2>
            @include('admin.fitness._paquete_campos', ['p' => null, 'pre' => 'pn'])
            <button class="btn btn-primario mt-3"><i class="bi bi-plus-lg me-1"></i> Crear paquete</button>
        </form>
    </div>
</div>
@endsection
