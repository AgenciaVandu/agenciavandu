<nav class="subnav" aria-label="Secciones de Finanzas">
    @foreach(\App\Http\Controllers\Admin\FinanzasController::SECCIONES as $k => $sec)
        <a href="{{ $q(['seccion' => $k === 'resumen' ? null : $k]) }}" class="{{ $seccion === $k ? 'activo' : '' }}" @if($seccion === $k) aria-current="page" @endif><i class="bi {{ $sec['icono'] }}"></i> {{ $sec['texto'] }}</a>
    @endforeach
</nav>
