{{-- Foto, video e impresión: galería que se destapa al aparecer (config/inicio.php → galeria). --}}
@php $I = \App\Support\Inicio::class; @endphp
<section class="vx-foto" id="foto">
    <div class="vx-caja">
        <div class="vx-encabezado">
            <div>
                <p class="vx-num" data-aparece><b>02</b> Foto, video e impresión</p>
                <h2 data-descifra>Lo que tu cliente ve antes de entrar.</h2>
            </div>
            <a href="{{ route('audiovisuales') }}" class="vx-enlace" data-aparece>Ver audiovisuales <span class="vx-fl" aria-hidden="true">→</span></a>
        </div>
        <div class="vx-galeria">
                @foreach (config('inicio.galeria') as $k => $g)
                    <figure @class(['vx-g-grande' => $k === 0]) data-aparece data-cur="Ver"><a href="{{ route('audiovisuales') }}">
                        @isset($g['video'])
                            <video muted loop playsinline preload="none" poster="{{ asset('img/inicio/' . $g['video'] . '.webp') }}" data-src="{{ asset('img/inicio/' . $g['video'] . '.mp4') }}" aria-label="{{ $g['alt'] }}"></video>
                        @else
                            <img src="{{ asset('img/' . $g['img']) }}" alt="{{ $g['alt'] }}" loading="lazy">
                        @endisset
                        <figcaption @class(['vx-oscuro-txt' => $g['oscuro'] ?? false])>{{ $g['pie'] }}</figcaption>
                    </a></figure>
                @endforeach
            </div>
    </div>
</section>
