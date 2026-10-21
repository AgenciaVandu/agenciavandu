@extends('lealtad.layout')
@section('titulo', 'Nueva contraseña')
@section('contenido')
@if(! $p)
    <h1>Este enlace ya no sirve</h1>
    <p class="sub">Venció o ya se usó. <a href="{{ route('lealtad.olvide') }}">Pide uno nuevo</a>.</p>
@else
    <h1>Hola, {{ $p->primer_nombre }}</h1>
    <p class="sub">Crea tu contraseña nueva. Tu usuario es <b>{{ $p->email }}</b>.</p>
    <form method="post" action="{{ route('lealtad.acceso.guardar', $token) }}" class="tarjeta" style="margin-top: 18px">
        @csrf
        <label class="campo" for="password" style="margin-top: 0">Contraseña nueva</label>
        <input class="campo" type="password" name="password" id="password" required minlength="8" autocomplete="new-password">
        <label class="campo" for="password_confirmation">Repítela</label>
        <input class="campo" type="password" name="password_confirmation" id="password_confirmation" required minlength="8" autocomplete="new-password">
        <button class="btn full" style="margin-top: 18px">Guardar y entrar</button>
    </form>
@endif
@endsection
