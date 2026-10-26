<nav class="subnav" aria-label="Secciones de Finanzas">
    @foreach(\App\Http\Controllers\Admin\FinanzasController::SECCIONES as $k => $sec)
        <a href="{{ $q(['seccion' => $k === 'resumen' ? null : $k] + (in_array($k, ['contabilidad', 'flujo', 'cuentas'], true) && $periodo === 'todo' ? ['periodo' => 'mes'] : [])) }}" class="{{ $seccion === $k ? 'activo' : '' }}" @if($seccion === $k) aria-current="page" @endif><i class="bi {{ $sec['icono'] }}"></i> {{ $sec['texto'] }}</a>
    @endforeach
    @if(\App\Support\Cuentas::actual()?->tiene('gastos'))<a href="{{ route('admin.gastos') }}"><i class="bi bi-receipt"></i> Gastos</a>@endif
</nav>
