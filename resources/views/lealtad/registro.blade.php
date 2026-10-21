@extends('lealtad.layout')
@section('titulo', 'Crear cuenta')
@section('contenido')
<div style="display: flex; align-items: flex-end; gap: 10px">
    <div style="flex: 1"><h1>{{ $restaurante ? 'Únete a ' . $restaurante->nombre : 'Crea tu cuenta' }}</h1>
        <p class="sub">Una sola cuenta para juntar visitas y premios en todos los restaurantes de {{ config('vandu.plataforma.nombre') }}.</p></div>
    <img src="{{ route('plataforma.img', 'mascota-chica.webp') }}" alt="" style="width: 84px">
</div>
<form method="post" action="{{ route('lealtad.registrar') }}" class="tarjeta" style="margin-top: 16px">
    @csrf
    @if($restaurante)<input type="hidden" name="r" value="{{ $restaurante->slug }}">@endif
    <label class="campo" for="nombre" style="margin-top: 0">Nombre</label>
    <input class="campo" name="nombre" id="nombre" value="{{ old('nombre') }}" required maxlength="120" autocomplete="name">
    <label class="campo" for="telefono">WhatsApp</label>
    <input class="campo num" name="telefono" id="telefono" value="{{ old('telefono') }}" required maxlength="30" inputmode="tel" autocomplete="tel">
    <label class="campo" for="email">Correo</label>
    <input class="campo" type="email" name="email" id="email" value="{{ old('email') }}" required maxlength="190" autocomplete="email">
    <label class="campo" for="password">Contraseña</label>
    <input class="campo" type="password" name="password" id="password" required minlength="8" autocomplete="new-password">
    <label class="campo" for="password_confirmation">Repítela</label>
    <input class="campo" type="password" name="password_confirmation" id="password_confirmation" required minlength="8" autocomplete="new-password">
    @if($restaurante)
        <label class="check"><input type="checkbox" name="compartir" value="1" required @checked(old('compartir'))> <span>Acepto compartir con <b>{{ $restaurante->nombre }}</b> mi nombre, teléfono y correo para su programa de lealtad. Puedo salirme cuando quiera.</span></label>
    @endif
    <label class="check"><input type="checkbox" name="privacidad" value="1" required @checked(old('privacidad'))> <span>Leí y acepto el <a href="{{ route('privacidad') }}" target="_blank" rel="noopener">aviso de privacidad</a>. Cada restaurante solo ve mis datos si me uno a su programa.</span></label>
    <button class="btn full" style="margin-top: 18px">{{ $restaurante ? 'Crear cuenta y unirme' : 'Crear cuenta' }}</button>
</form>
<p class="sub" style="text-align: center; margin-top: 18px">¿Ya tienes cuenta? <a href="{{ route('lealtad.entrar', array_filter(['r' => $restaurante?->slug])) }}"><b>Entra</b></a></p>
@endsection
