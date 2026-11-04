{{-- Servicios: filas que se rellenan de lado a lado al pasar el cursor (config/inicio.php → servicios). --}}
@php $I = \App\Support\Inicio::class; @endphp
<section class="vx-claro vx-servicios" id="servicios">
    <div class="vx-caja">
        <div class="vx-encabezado">
            <div>
                <p class="vx-num" data-aparece><b>01</b> Servicios</p>
                <h2 data-descifra>Diseñamos, programamos y producimos.</h2>
            </div>
            <p class="vx-intro" data-aparece>Siete servicios, un solo equipo. No tienes que coordinar a nadie más.</p>
        </div>
        <div class="vx-raya" data-aparece></div>
        @foreach (config('inicio.servicios') as $k => $s)
            @php $e = $I::enlace($s['destino']); @endphp
            <a class="vx-fila" href="{{ $e['href'] }}" @if ($e['servicio']) data-servicio="{{ $e['servicio'] }}" @endif data-aparece>
                <span class="vx-fila-n">{{ $I::n2($k) }}</span>
                <b>{{ $s['titulo'] }}</b>
                <span class="vx-fila-d">{{ $s['texto'] }}</span>
                <span class="vx-fila-t">@foreach ($s['etiquetas'] as $e)<span class="vx-tag">{{ $e }}</span>@endforeach</span>
                <span class="vx-fila-f" aria-hidden="true">↗</span>
            </a>
        @endforeach
    </div>
</section>
