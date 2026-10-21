@extends('admin.layout')
@section('titulo', 'Campañas')
@include('admin.lealtad._estilos')

@php
    $titulos = ['vigente' => 'Vigentes', 'proxima' => 'Próximas', 'canje' => 'Terminaron, aún se pueden canjear', 'otras' => 'Terminadas y en pausa'];
    $etq = ['vigente' => 'Vigente', 'proxima' => 'Próxima', 'canje' => 'En canje', 'terminada' => 'Terminó', 'pausada' => 'En pausa'];
    $total = collect($grupos)->flatten()->count();
@endphp

@section('contenido')
<div class="page-head">
    <div><h1>Campañas</h1><p class="sub">Cada campaña tiene su vigencia, su meta de visitas y premios que se desbloquean en el camino. Una visita cuenta en todas las campañas vigentes.</p></div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.lealtad.qr') }}" class="btn btn-borde"><i class="bi bi-printer me-1"></i> Cartel con QR</a>
        <a href="{{ route('admin.lealtad.campanas.create') }}" class="btn btn-primario"><i class="bi bi-plus-lg me-1"></i> Nueva campaña</a>
    </div>
</div>

@if(! $total)
    <div class="panel"><div class="vacio"><div class="ico"><i class="bi bi-gift"></i></div><h3>Aún no tienes campañas</h3>
        <p>Por ejemplo: «Temporada navideña», del 1 de noviembre al 1 de enero. Junta 10 visitas: a la 3 un postre, a la 6 una bebida y a la 10 una cena para dos.</p>
        <a href="{{ route('admin.lealtad.campanas.create') }}" class="btn btn-primario">Crear campaña</a></div></div>
@endif

@foreach($grupos as $clave => $lista)
    @continue($lista->isEmpty())
    <h2 class="h6 secundario mt-4 mb-3">{{ $titulos[$clave] }}</h2>
    <div class="row g-3">
        @foreach($lista as $k)
            @php $st = $stats[$k->id] ?? null; @endphp
            <div class="col-md-6">
                <section class="panel h-100">
                    <div class="panel-head">
                        <div class="min-w-0"><h2 class="text-truncate">{{ $k->nombre }}</h2><span class="ayuda">{{ ucfirst($k->vigencia_texto) }}</span></div>
                        <span class="lk-estado {{ $k->estado }}">{{ $etq[$k->estado] }}</span>
                    </div>
                    <div class="panel-body">
                        @if($k->descripcion)<p class="secundario small">{{ $k->descripcion }}</p>@endif
                        <div class="lk-camino" aria-hidden="true">@for($i = 1; $i <= min(30, $k->meta_visitas); $i++)<span class="p {{ $k->premios->contains('visitas', $i) ? 'on' : '' }}"></span>@endfor</div>
                        <div class="secundario small mb-2">Meta: {{ $k->meta_visitas }} visitas · se canjea hasta el {{ $k->canje_hasta->locale('es')->isoFormat('D MMM YYYY') }}</div>
                        @foreach($k->premios as $p)
                            <div class="lk-premio"><span class="n num">{{ $p->visitas }}</span><div class="flex-grow-1"><b>{{ $p->nombre }}</b>@if($p->descripcion)<div class="secundario small">{{ $p->descripcion }}</div>@endif</div></div>
                        @endforeach
                        @if($st)
                            <div class="d-flex flex-wrap gap-3 small mt-2 pt-2 border-top">
                                <span><b class="num">{{ $st['participan'] }}</b> <span class="secundario">participan</span></span>
                                <span><b class="num">{{ $st['meta'] }}</b> <span class="secundario">en la meta</span></span>
                                <span><b class="num">{{ $st['canjes'] }}</b> <span class="secundario">canjes</span></span>
                            </div>
                        @endif
                        <div class="d-flex gap-2 mt-3">
                            <a href="{{ route('admin.lealtad.campanas.edit', $k) }}" class="btn btn-borde btn-sm"><i class="bi bi-pencil me-1"></i> Editar</a>
                            <form method="post" action="{{ route('admin.lealtad.campanas.estado', $k) }}">@csrf @method('patch')<button class="btn btn-fantasma btn-sm">{{ $k->activa ? 'Pausar' : 'Reactivar' }}</button></form>
                        </div>
                    </div>
                </section>
            </div>
        @endforeach
    </div>
@endforeach
@endsection
