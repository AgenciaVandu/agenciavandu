@extends('lealtad.layout')
@section('titulo', 'Entrar')
@section('contenido')
<h1>Hola de nuevo</h1>
<p class="sub">{{ $restaurante ? 'Entra para unirte al programa de ' . $restaurante->nombre . '.' : 'Entra para ver tus restaurantes, tus visitas y tus premios.' }}</p>
<form method="post" action="{{ route('lealtad.login') }}" class="tarjeta" style="margin-top: 18px">
    @csrf
    @if($restaurante)<input type="hidden" name="r" value="{{ $restaurante->slug }}">@endif
    <label class="campo" for="email" style="margin-top: 0">Correo</label>
    <input class="campo" type="email" name="email" id="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
    <label class="campo" for="password">Contraseña</label>
    <input class="campo" type="password" name="password" id="password" required autocomplete="current-password">
    <button class="btn full" style="margin-top: 18px">Entrar</button>
    <p style="text-align: center; margin: 14px 0 0; font-size: 14px"><a href="{{ route('lealtad.olvide') }}">Olvidé mi contraseña</a></p>
</form>
<p class="sub" style="text-align: center; margin-top: 18px">¿No tienes cuenta? <a href="{{ route('lealtad.registro', array_filter(['r' => $restaurante?->slug])) }}"><b>Crea una gratis</b></a></p>
@endsection
