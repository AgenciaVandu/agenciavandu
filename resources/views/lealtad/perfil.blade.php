@extends('lealtad.layout')
@section('titulo', 'Perfil')
@section('contenido')
<h1>Tu perfil</h1>
<p class="sub">Los restaurantes donde estás inscrito ven estos datos.</p>
<form method="post" action="{{ route('lealtad.perfil.guardar') }}" class="tarjeta" style="margin-top: 16px">
    @csrf
    <label class="campo" for="nombre" style="margin-top: 0">Nombre</label>
    <input class="campo" name="nombre" id="nombre" value="{{ old('nombre', $p->nombre) }}" required maxlength="120">
    <label class="campo" for="telefono">WhatsApp</label>
    <input class="campo num" name="telefono" id="telefono" value="{{ old('telefono', $p->telefono) }}" required maxlength="30" inputmode="tel">
    <label class="campo" for="email">Correo (tu usuario)</label>
    <input class="campo" type="email" name="email" id="email" value="{{ old('email', $p->email) }}" required maxlength="190">
    <details style="margin-top: 14px"><summary style="cursor: pointer; font-weight: 600; color: var(--purpura)">Cambiar contraseña</summary>
        <label class="campo" for="actual">Contraseña actual</label><input class="campo" type="password" name="actual" id="actual" autocomplete="current-password">
        <label class="campo" for="password">Nueva</label><input class="campo" type="password" name="password" id="password" minlength="8" autocomplete="new-password">
        <label class="campo" for="password_confirmation">Repítela</label><input class="campo" type="password" name="password_confirmation" id="password_confirmation" minlength="8" autocomplete="new-password">
    </details>
    <button class="btn full" style="margin-top: 18px">Guardar</button>
</form>

<h2>Tus programas</h2>
@forelse($lugares as $i)
    <div class="tarjeta resto" style="margin-bottom: 10px">
        <div class="top">@include('lealtad._logo', ['c' => $i->cuenta])<div class="min-w-0"><b>{{ $i->cuenta->nombre }}</b><span>Te uniste el {{ $i->consentido_at->locale('es')->isoFormat('D [de] MMMM YYYY') }}</span></div>
            <form method="post" action="{{ route('lealtad.baja', $i->cuenta->slug) }}" style="margin-left: auto" onsubmit="return confirm({{ Js::from('¿Salirte del programa de ' . $i->cuenta->nombre . '? Ya no verá tus datos y dejarás de sumar visitas ahí.') }})">@csrf
                <button class="btn peligro chico">Salirme</button></form></div>
    </div>
@empty
    <p class="sub">Aún no estás en ningún programa.</p>
@endforelse

<form method="post" action="{{ route('lealtad.salir') }}" style="margin-top: 22px">@csrf<button class="btn sec full">Cerrar sesión</button></form>
@endsection
