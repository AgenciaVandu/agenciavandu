{{-- Preguntas frecuentes con su JSON-LD para Google (config/inicio.php → preguntas). --}}
@php $preguntas = config('inicio.preguntas'); @endphp
<section class="vx-preguntas" id="preguntas">
    <div class="vx-caja vx-preguntas-grid">
        <div>
            <p class="vx-num" data-aparece><b>05</b> Preguntas</p>
            <h2 data-descifra>Lo que más nos preguntan.</h2>
        </div>
        <div class="vx-faq">
            @foreach ($preguntas as $k => [$p, $r])
                <details @if ($k === 0) open @endif data-aparece>
                    <summary>{{ $p }} <span aria-hidden="true"></span></summary>
                    <p>{{ $r }}</p>
                </details>
            @endforeach
        </div>
    </div>
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => collect($preguntas)->map(fn ($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]])->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
</section>
