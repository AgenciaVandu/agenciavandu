@extends('admin.layout')
@section('titulo', 'Comensales')
@include('admin.lealtad._estilos')

@section('contenido')
<div class="page-head">
    <div><h1>Comensales</h1><p class="sub">Las personas que se unieron a tu programa. Aceptaron compartirte su nombre, teléfono y correo; si se salen, dejas de ver sus datos.</p></div>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
    <nav class="segmento" aria-label="Filtrar">
        @foreach(\App\Http\Controllers\Admin\Lealtad\ComensalesController::VISTAS as $k => $t)
            <a href="{{ route('admin.comensales', array_filter(['ver' => $k === 'todos' ? null : $k, 'q' => $q ?: null])) }}" class="{{ $ver === $k ? 'activo' : '' }}">{{ $t }} @if($conteos[$k])<span class="n num">{{ $conteos[$k] }}</span>@endif</a>
        @endforeach
    </nav>
    <form class="buscador" method="get" role="search">
        @if($ver !== 'todos')<input type="hidden" name="ver" value="{{ $ver }}">@endif
        <i class="bi bi-search"></i><input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Nombre, teléfono o correo" aria-label="Buscar">
    </form>
</div>

@if($lista->isEmpty())
    <div class="panel"><div class="vacio"><div class="ico"><i class="bi bi-person-hearts"></i></div>
        <h3>{{ $q || $ver !== 'todos' ? 'Nadie coincide' : 'Aún no hay comensales' }}</h3>
        <p>{{ $q || $ver !== 'todos' ? 'Prueba con otra búsqueda.' : 'Se unen desde el QR de tu cartel o en caja al escanear su QR.' }}</p></div></div>
@else
    <div class="panel"><div class="table-responsive"><table class="tabla">
        <thead><tr><th>Comensal</th><th>Contacto</th><th class="text-end">Visitas</th><th>Última visita</th><th class="text-end">Consumo</th><th class="text-end">Premios</th></tr></thead>
        <tbody>
        @foreach($lista as $i)
            @php $p = $i->comensal; $baja = (bool) $i->baja_at; @endphp
            <tr>
                <td style="min-width: 200px"><div class="d-flex align-items-center gap-2">
                    <span class="avatar" style="width: 34px; height: 34px; font-size: 12px">{{ $p->iniciales }}</span>
                    <div class="min-w-0"><div class="principal text-truncate">{{ $baja ? $p->primer_nombre : $p->nombre }}</div>
                        <div class="secundario small">{{ $baja ? 'Se salió ' . $i->baja_at->locale('es')->diffForHumans() : 'Desde ' . $i->consentido_at->locale('es')->isoFormat('D MMM YYYY') . ($i->origen === 'caja' ? ' · en caja' : '') }}</div></div></div></td>
                <td class="small">@if($baja)<span class="secundario">Sin acceso a sus datos</span>
                    @else<div>{{ $p->email }}</div>@if($p->telefono)<div class="d-flex align-items-center gap-2"><span class="num">{{ $p->telefono }}</span>
                        @if($p->whatsapp)<a href="https://wa.me/{{ $p->whatsapp }}" target="_blank" rel="noopener" class="text-success" title="WhatsApp"><i class="bi bi-whatsapp"></i></a>@endif</div>@endif
                    @endif</td>
                <td class="text-end num fw-semibold">{{ $i->visitas }}</td>
                <td class="secundario small">{{ $i->ultima ? ($i->ultima->isToday() ? 'Hoy' : $i->ultima->locale('es')->diffForHumans()) : 'Aún no' }}</td>
                <td class="text-end num">{{ $i->gastado ? '$' . number_format($i->gastado, 0) : '—' }}</td>
                <td class="text-end num">{{ $i->canjes ?: '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div></div>
@endif
@endsection
