{{-- Clientes: testimonio y tres datos de la agencia (config/inicio.php → testimonio, datos). --}}
@php $t = config('inicio.testimonio'); @endphp
<section class="vx-clientes">
    <div class="vx-caja">
        <p class="vx-num" data-aparece><b>04</b> Clientes</p>
        <blockquote data-aparece>
            <p>“{{ $t['frase'] }}”</p>
            <footer><span class="vx-guion" aria-hidden="true"></span><span><b>{{ $t['nombre'] }}</b><br>{{ $t['puesto'] }}</span></footer>
        </blockquote>
        <div class="vx-raya vx-raya-clara" data-aparece></div>
        <dl class="vx-datos">
            @foreach (config('inicio.datos') as [$dt, $dd])
                <div data-aparece><dt>{{ $dt }}</dt><dd>{{ $dd }}</dd></div>
            @endforeach
        </dl>
    </div>
</section>
