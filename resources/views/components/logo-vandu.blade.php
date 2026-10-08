@props(['archivo' => 'logo-vandu-blanco.svg', 'alt' => 'Agencia Vandu'])
{{-- El logo va incrustado en la página: no depende de que public/img esté copiado en el servidor --}}
<img src="data:image/svg+xml;base64,{{ base64_encode(file_get_contents(resource_path('img/' . $archivo))) }}" alt="{{ $alt }}" {{ $attributes }}>
