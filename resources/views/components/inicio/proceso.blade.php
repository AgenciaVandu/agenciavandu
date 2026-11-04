{{-- Cómo trabajamos: cuatro pasos y una línea que avanza con el scroll (config/inicio.php → pasos). --}}
@php $I = \App\Support\Inicio::class; @endphp
<section class="vx-claro vx-proceso" id="proceso">
    <div class="vx-caja">
        <p class="vx-num" data-aparece><b>03</b> Cómo trabajamos</p>
        <h2 data-descifra>De la primera plática a la entrega.</h2>
        <div class="vx-proceso-linea" aria-hidden="true"><span data-proceso-linea></span></div>
        <ol class="vx-pasos">
            @foreach (config('inicio.pasos') as $k => [$titulo, $texto])
                <li data-aparece>
                    <span class="vx-paso-n">{{ $I::n2($k) }}</span>
                    <h3 data-descifra>{{ $titulo }}</h3>
                    <p>{{ $texto }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
