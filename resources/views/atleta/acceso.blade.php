@extends('atleta.layout')
@section('titulo', 'Crea tu contraseña')
@section('contenido')
<div class="entrada">
    <div class="marca-g">@if($cuenta?->logo(true))<img src="{{ $cuenta->logo(true) }}" alt="{{ $cuenta->nombre }}">@else<b>{{ $cuenta?->nombre }}</b>@endif</div>

    @if(! $a)
        <h1>Este enlace ya no sirve</h1>
        <p class="sub">Venció o ya se usó. Pide uno nuevo en recepción.</p>
    @else
        <h1>Hola, {{ $a->primer_nombre }}</h1>
        <p class="sub">Crea tu contraseña para reservar tus clases en {{ $cuenta?->nombre }}. Tu usuario es <b>{{ $a->email }}</b>.</p>
        <form method="post" action="{{ route('atleta.acceso.guardar', $token) }}" class="tarjeta" style="margin-top: 18px">
            @csrf
            <label class="campo" for="password" style="margin-top: 0">Contraseña nueva</label>
            <input class="campo" type="password" name="password" id="password" required minlength="8" autocomplete="new-password">
            <label class="campo" for="password_confirmation">Repítela</label>
            <input class="campo" type="password" name="password_confirmation" id="password_confirmation" required minlength="8" autocomplete="new-password">
            <button class="btn full" style="margin-top: 18px">Guardar y entrar</button>
        </form>
    @endif
</div>
@endsection
