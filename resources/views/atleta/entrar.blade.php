@extends('atleta.layout')
@section('titulo', 'Entrar')
@section('contenido')
<div class="entrada">
    <div class="marca-g">@if($cuenta?->logo(true))<img src="{{ $cuenta->logo(true) }}" alt="{{ $cuenta->nombre }}">@else<b>{{ $cuenta?->nombre }}</b>@endif</div>

    <h1>Hola de nuevo</h1>
    <p class="sub">Entra para reservar tus clases en {{ $cuenta->nombre }}.</p>
    <form method="post" action="{{ $negocio ? route('atleta.login', $negocio) : route('atleta.login.dominio') }}" class="tarjeta" style="margin-top: 18px">
        @csrf
        <label class="campo" for="email" style="margin-top: 0">Correo</label>
        <input class="campo" type="email" name="email" id="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
        <label class="campo" for="password">Contraseña</label>
        <input class="campo" type="password" name="password" id="password" required autocomplete="current-password">
        <button class="btn full" style="margin-top: 18px">Entrar</button>
        <p style="text-align: center; margin: 14px 0 0; font-size: 14px"><a style="color: var(--senal-2)" href="{{ $negocio ? route('atleta.olvide', $negocio) : route('atleta.olvide.dominio') }}">Olvidé mi contraseña</a></p>
    </form>
    <p class="sub" style="text-align: center; font-size: 14px; margin-top: 16px">¿Aún no tienes acceso? Pídelo en recepción y te llegará un enlace para crear tu contraseña.</p>
</div>
@endsection
