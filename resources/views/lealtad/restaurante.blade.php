@extends('lealtad.layout')
@section('titulo', $c->nombre)
@section('contenido')
<div class="resto" style="margin-top: 10px">
    <div class="top">@include('lealtad._logo', ['c' => $c])<div class="min-w-0"><b style="font-size: 22px">{{ $c->nombre }}</b><span>Programa de lealtad</span></div></div>
</div>

@if($inscrito)
    <div class="aviso ok" style="margin-top: 16px"><i class="bi bi-patch-check-fill"></i> Estás en el programa. Muestra tu QR en cada visita.</div>
    <div class="lista" style="margin-top: 6px">
        @forelse($avance as $a)
            <div class="tarjeta">@include('lealtad._sellos', ['a' => $a])</div>
        @empty
            <div class="tarjeta sub">Por ahora no hay campañas; tus visitas quedan registradas.</div>
        @endforelse
    </div>
    <a href="{{ route('lealtad.qr') }}" class="btn oscuro full" style="margin-top: 16px"><i class="bi bi-qr-code"></i> Mostrar mi QR</a>
@else
    @forelse($campanas as $k)
        <h1 style="margin-top: 22px">{{ $k->nombre }}</h1>
        <p class="sub">{{ $k->descripcion ?: 'Junta ' . $k->meta_visitas . ' visitas y gana premios en el camino.' }} Del {{ $k->vigencia_texto }}.</p>
        <div class="escalera" style="margin-top: 14px">
            @foreach($k->premios as $pr)
                <div><b class="n num">{{ $pr->visitas }}</b><div><div class="t">{{ $pr->nombre }}</div><div class="d">{{ $pr->descripcion ?: 'En tu visita ' . $pr->visitas }}</div></div></div>
            @endforeach
        </div>
    @empty
        <h1 style="margin-top: 22px">Únete a nuestro programa</h1>
        <p class="sub">Suma visitas y recibe premios y sorpresas.</p>
    @endforelse

    <div class="tarjeta" style="margin-top: 22px">
        @if($p)
            <form method="post" action="{{ route('lealtad.unirme', $c->slug) }}">
                @csrf
                <b>Únete con tu cuenta, {{ $p->primer_nombre }}</b>
                <label class="check"><input type="checkbox" name="compartir" value="1" required> <span>Acepto compartir con {{ $c->nombre }} mi nombre, teléfono y correo para su programa de lealtad. Puedo salirme cuando quiera.</span></label>
                <button class="btn full" style="margin-top: 16px"><i class="bi bi-heart-fill"></i> Unirme</button>
            </form>
        @else
            <b>Únete gratis en un minuto</b>
            <p class="sub" style="font-size: 14.5px; margin-top: 4px">Con la misma cuenta juntas visitas en todos los restaurantes de la red de {{ config('vandu.plataforma.nombre') }}.</p>
            <a href="{{ route('lealtad.registro', ['r' => $c->slug]) }}" class="btn full" style="margin-top: 14px">Crear mi cuenta y unirme</a>
            <a href="{{ route('lealtad.entrar', ['r' => $c->slug]) }}" class="btn sec full" style="margin-top: 10px">Ya tengo cuenta</a>
        @endif
    </div>
@endif
@endsection
