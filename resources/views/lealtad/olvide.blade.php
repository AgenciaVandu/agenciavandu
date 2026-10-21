@extends('lealtad.layout')
@section('titulo', 'Olvidé mi contraseña')
@section('contenido')
<h1>¿Olvidaste tu contraseña?</h1>
<p class="sub">Escribe tu correo y te mandamos un enlace para crear una nueva.</p>
<form method="post" class="tarjeta" style="margin-top: 18px">
    @csrf
    <label class="campo" for="email" style="margin-top: 0">Correo</label>
    <input class="campo" type="email" name="email" id="email" required autocomplete="email" autofocus>
    <button class="btn full" style="margin-top: 18px">Mandar enlace</button>
</form>
<p style="text-align: center; font-size: 14px; margin-top: 16px"><a href="{{ route('lealtad.entrar') }}">Volver a entrar</a></p>
@endsection
