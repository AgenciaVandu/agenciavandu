<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $titulo ?? 'Diseño y desarrollo web a medida' }} - Agencia Vandu</title>
    <link rel="icon" href="/favi.svg" type="image/svg+xml">
    <meta name="description" content="{{ $metaDescription ?? 'sin meta' }}">
    <script type="application/ld+json">
    {
  "@context": "https://schema.org",
  "@type": "AdvertisingAgency",
  "@id": "https://agenciavandu.com/#agency",
  "name": "Agencia Vandu",
  "url": "https://agenciavandu.com",
  "logo": "https://agenciavandu.com/Logo-vandu.png",
  "image": "https://agenciavandu.com/datos.jpg",
  "description": "Agencia de marketing digital especializada en SEO, optimización y estrategias de IA en 2026, fotografía empresarial, fotografía de alimentos",
  "knowsLanguage": ["es"],
  "address": {
    "@type": "PostalAddress",
    "addressLocality": "Mérida",
    "addressRegion": "Yucatán",
    "postalCode": "97000",
    "addressCountry": "ES"
  },
  "geo": {
    "@type": "GeoCoordinates",
    "latitude": 20.967778,
    "longitude": -89.621667
  },
  "contactPoint": {
    "@type": "ContactPoint",
    "telephone": "+52-999-146-0310",
    "contactType": "ventas",
    "availableLanguage": ["Spanish"]
  },
  "sameAs": [
    "https://www.linkedin.com/company/vandu-mx",
    "https://www.instagram.com/vandumx"
  ],
  "knowsAbout": [
    "Search Engine Optimization",
    "creación de contenido en video",
    "Fotografía profesional para empresas",
    "Fotografía de alimentos",
    "desarrollo web",
    "Marketing digital",
    "optimización de sitios web para IA"
  ]
}
    </script>
    @stack('estilos')
</head>

<body class="body">

    <div class="flotante">
        <div class="mensaje">Bienvenido a Vandú 😊</div>
        <div class="notificacion">
            <img src="{{ asset('/img/bell-solid.svg') }}" class="imagen" alt="">
        </div>
        <a href="https://wa.me/+529991460310" class="btn-contacto">
            <img src="{{ asset('/img/message-solid.svg') }}" width="40" alt="mensaje">
        </a>
    </div>
    <x-nav />
    <main>
        {{ $slot }}
    </main>

    <x-footer />

    <!-- Start of HubSpot Embed Code -->
    <script type="text/javascript" id="hs-script-loader" async defer src="//js.hs-scripts.com/47390088.js"></script>
    <!-- End of HubSpot Embed Code -->
</body>
@stack('js')

</html>
