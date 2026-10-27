@extends('admin.layout')
@section('titulo', 'Sin acceso')

@section('contenido')
<div class="panel" style="max-width: 560px; margin: 40px auto 0">
    <div class="vacio">
        @if($porGiro ?? false)
            <div class="ico"><i class="bi bi-puzzle"></i></div>
            <h3>Esta sección no viene con tu tipo de negocio</h3>
            <p>Tu panel está armado para lo que haces y esta parte no la incluye. Si te serviría, escríbenos y lo vemos.</p>
        @else
            <div class="ico"><i class="bi bi-lock"></i></div>
            <h3>Esta sección no está en tu rol</h3>
            <p>Si la necesitas para tu trabajo, pídele a quien administra el panel que te dé acceso.</p>
        @endif
        <a href="{{ $inicio }}" class="btn btn-primario mt-2">Ir a mi inicio</a>
    </div>
</div>
@endsection
