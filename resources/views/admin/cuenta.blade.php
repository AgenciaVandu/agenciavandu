@extends('admin.layout')
@section('titulo', 'Mi cuenta')

@section('contenido')
<div class="page-head">
    <div>
        <h1>Mi cuenta</h1>
        <p class="sub">{{ $u->puesto ?: 'Sin puesto' }} · {{ $u->rol?->nombre ?? 'Sin rol' }} · {{ $u->rol?->resumen }}</p>
    </div>
</div>

<form method="post" action="{{ route('admin.cuenta.update') }}" class="panel" style="max-width: 720px">
    @csrf @method('put')
    <div class="panel-body row g-3">
        <div class="col-md-6"><label class="form-label" for="c-nombre">Nombre</label><input id="c-nombre" name="name" class="form-control" value="{{ old('name', $u->name) }}" required maxlength="120" autocomplete="name"></div>
        <div class="col-md-6"><label class="form-label" for="c-correo">Correo</label><input id="c-correo" type="email" name="email" class="form-control" value="{{ old('email', $u->email) }}" required maxlength="190" autocomplete="username"></div>
        <div class="col-md-6"><label class="form-label" for="c-tel">WhatsApp</label><input id="c-tel" name="telefono" class="form-control num" value="{{ old('telefono', $u->telefono) }}" maxlength="30"></div>
        <div class="col-md-6"><label class="form-label">Puesto</label><input class="form-control" value="{{ $u->puesto ?: '—' }}" disabled><div class="form-text">Lo asigna quien administra el panel.</div></div>
        <div class="col-12"><hr class="my-1"><b style="font-size:14.5px">Cambiar contraseña</b> <span class="secundario" style="font-size:13px">(déjalo vacío para no cambiarla)</span></div>
        <div class="col-md-4"><label class="form-label" for="c-actual">Contraseña actual</label><input id="c-actual" type="password" name="actual" class="form-control" autocomplete="current-password"></div>
        <div class="col-md-4"><label class="form-label" for="c-nueva">Nueva</label><input id="c-nueva" type="password" name="password" class="form-control" autocomplete="new-password" minlength="8"></div>
        <div class="col-md-4"><label class="form-label" for="c-conf">Repite la nueva</label><input id="c-conf" type="password" name="password_confirmation" class="form-control" autocomplete="new-password"></div>
    </div>
    <div class="px-3 pb-3 px-md-4"><button class="btn btn-primario px-4"><i class="bi bi-check2 me-1"></i> Guardar</button></div>
</form>
@endsection
