{{-- Cinta de marcas que han confiado en nosotros (config/inicio.php → marcas). --}}
@php $I = \App\Support\Inicio::class; @endphp
<section class="vx-marcas" aria-label="Marcas que han confiado en nosotros">
    <p class="vx-marcas-titulo">Han confiado en nosotros</p>
    <div class="vx-marcas-pista">
        <div class="vx-cinta">
            @foreach ([false, true] as $copia)
                @foreach (config('inicio.marcas') as [$archivo, $nombre, $alto])
                    <img src="{{ asset('img/logos-marcas/' . $archivo) }}" alt="{{ $copia ? '' : $nombre }}" @if ($copia) aria-hidden="true" @endif style="height:{{ $alto }}px" loading="lazy">
                @endforeach
            @endforeach
        </div>
    </div>
</section>
