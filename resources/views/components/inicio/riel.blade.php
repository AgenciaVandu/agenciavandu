{{-- Mapa de la página: riel lateral con las secciones numeradas; marca en dónde vas (config/inicio.php → secciones). --}}
@php $I = \App\Support\Inicio::class; @endphp
<nav class="vx-riel" aria-label="Secciones de la página">
    @foreach (config('inicio.secciones') as $k => [$texto, $id])
        <a href="#{{ $id }}" data-riel="#{{ $id }}"><i></i><b>{{ $I::n2($k) }}</b><span>{{ $texto }}</span></a>
    @endforeach
</nav>
