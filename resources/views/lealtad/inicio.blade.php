@extends('lealtad.layout')
@section('titulo', 'Inicio')
@section('contenido')
<h1>Hola, {{ $p->primer_nombre }}</h1>
<p class="sub">Tu misma cuenta y tu mismo QR sirven en todos los restaurantes de la red.</p>
<a href="{{ route('lealtad.qr') }}" class="btn oscuro full" style="margin-top: 18px"><i class="bi bi-qr-code"></i> Mostrar mi QR</a>

<h2>Tus restaurantes</h2>
@if($lugares->isEmpty())
    <div class="tarjeta vacio">
        <img src="{{ route('plataforma.img', 'mascota-chica.webp') }}" alt="">
        <p style="margin: 10px 0 0"><b>Aún no te unes a ningún restaurante.</b><br><span class="sub">Busca el QR de {{ config('vandu.plataforma.nombre') }} en la mesa o en la caja de tu restaurante favorito y únete con esta misma cuenta.</span></p>
    </div>
@else
    <div class="lista">
        @foreach($lugares as $l)
            @php $c = $l['cuenta']; @endphp
            <div class="tarjeta resto">
                <a href="{{ route('lealtad.restaurante', $c->slug) }}" class="top">
                    @include('lealtad._logo', ['c' => $c])
                    <div class="min-w-0"><b>{{ $c->nombre }}</b><span>{{ $l['avance']->isEmpty() ? 'Sin campañas por ahora' : $l['avance']->count() . ' ' . ($l['avance']->count() === 1 ? 'campaña' : 'campañas') }}</span></div>
                    <i class="bi bi-chevron-right" style="margin-left: auto; color: var(--gris)"></i>
                </a>
                @foreach($l['avance'] as $a)@include('lealtad._sellos', ['a' => $a])@endforeach
            </div>
        @endforeach
    </div>
@endif
@endsection
