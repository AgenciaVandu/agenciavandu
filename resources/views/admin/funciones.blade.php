@extends('admin.layout')
@section('titulo', 'Funciones del panel')

@section('contenido')
<div class="page-head">
    <div><h1>Funciones del panel</h1><p class="sub">Deja prendido solo lo que usas. Apagar una función la quita del menú; no borra nada y la puedes volver a prender cuando quieras.</p></div>
</div>

<form method="post" action="{{ route('admin.funciones.guardar') }}" class="panel" style="max-width: 760px">
    @csrf @method('PUT')
    @forelse($funciones as $k => $f)
        <label class="fn-fila" for="fn-{{ $k }}">
            <span class="fn-ico"><i class="bi {{ $f['icono'] }}"></i></span>
            <span class="fn-txt"><b>{{ $f['titulo'] }}</b><span>{{ $f['ayuda'] }}</span>
                @if($f['datos'])<span class="fn-datos">Tienes {{ $f['datos'] }}: si la apagas se guardan y vuelven al prenderla.</span>@endif</span>
            <span class="form-check form-switch m-0"><input type="hidden" name="funciones[{{ $k }}]" value="0">
                <input class="form-check-input" type="checkbox" role="switch" id="fn-{{ $k }}" name="funciones[{{ $k }}]" value="1" @checked($f['prendida'])></span>
        </label>
    @empty
        <div class="vacio"><p>Tu tipo de negocio no tiene funciones opcionales.</p></div>
    @endforelse
    @if($funciones)
        <div class="p-3 border-top"><button class="btn btn-primario"><i class="bi bi-check2 me-1"></i> Guardar</button></div>
    @endif
</form>
<style>
    .fn-fila { display: flex; gap: 14px; align-items: center; padding: 16px 20px; border-top: 1px solid var(--line); cursor: pointer; margin: 0; }
    .fn-fila:first-of-type { border-top: 0; }
    .fn-ico { width: 38px; height: 38px; border-radius: 10px; background: var(--sunken); display: grid; place-items: center; font-size: 17px; flex: none; }
    .fn-txt { display: grid; gap: 2px; flex: 1; min-width: 0; } .fn-txt span { font-size: 13.5px; color: var(--muted); }
    .fn-txt .fn-datos { font-size: 12.5px; color: var(--text-2); }
    .fn-fila .form-switch .form-check-input { width: 2.6em; height: 1.4em; cursor: pointer; }
</style>
@endsection
