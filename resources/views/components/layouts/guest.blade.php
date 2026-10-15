<!DOCTYPE html>
<html lang="es-MX">

<head>
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-8X4HJZK7C7"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        // Consent Mode v2: en México se informa con la barra y se puede rechazar;
        // en el Espacio Económico Europeo, Reino Unido y Suiza nada se guarda hasta que la persona acepte.
        gtag('consent', 'default', {
            analytics_storage: 'granted', ad_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted'
        });
        gtag('consent', 'default', {
            analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied',
            wait_for_update: 500,
            region: ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IS','IE','IT','LV','LI','LT','LU','MT','NL','NO','PL','PT','RO','SK','SI','ES','SE','GB','CH']
        });
        try {
            var eleccionCookies = localStorage.getItem('vandu-cookies');
            if (eleccionCookies) {
                var estadoCookies = eleccionCookies === 'todas' ? 'granted' : 'denied';
                gtag('consent', 'update', {
                    analytics_storage: estadoCookies, ad_storage: estadoCookies, ad_user_data: estadoCookies, ad_personalization: estadoCookies
                });
            }
        } catch (e) {}

        gtag('js', new Date());

        // Configuración de Google Analytics 4
        gtag('config', 'G-8X4HJZK7C7');

        // Configuración de Google Ads
        gtag('config', 'AW-18235064782');
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $tituloCompleto = ($titulo ?? 'Páginas web, sistemas y video con IA') . ' | Agencia Vandu';
        $descripcion = $metaDescription ?? 'Agencia con base en Mérida para negocios de todo México: sitios web con SEO y GEO, sistemas conectados a WhatsApp, video con IA, fotografía, redes sociales e impresión.';
        // Dominio oficial fijo: así la canónica y las vistas previas nunca apuntan a .test ni a otro host.
        $dominio = 'https://agenciavandu.com';
        $urlCanonica = rtrim($dominio . '/' . ltrim(request()->path(), '/'), '/') ?: $dominio;
        $imagenSocial = $dominio . '/img/og-vandu.jpg';
    @endphp
    <title>{{ $tituloCompleto }}</title>
    <link rel="icon" href="/favi.svg" type="image/svg+xml">
    <meta name="description" content="{{ $descripcion }}">
    <link rel="canonical" href="{{ $urlCanonica }}">
    @if ($noindex ?? false)
        <meta name="robots" content="noindex, follow">
    @endif

    {{-- Vista previa al compartir (WhatsApp, Facebook, LinkedIn, X) --}}
    <meta property="og:type" content="website">
    <meta property="og:locale" content="es_MX">
    <meta property="og:site_name" content="Agencia Vandu">
    <meta property="og:title" content="{{ $tituloCompleto }}">
    <meta property="og:description" content="{{ $descripcion }}">
    <meta property="og:url" content="{{ $urlCanonica }}">
    <meta property="og:image" content="{{ $imagenSocial }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Agencia Vandu: sitios web, sistemas, fotografía y video">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $tituloCompleto }}">
    <meta name="twitter:description" content="{{ $descripcion }}">
    <meta name="twitter:image" content="{{ $imagenSocial }}">
    <script type="application/ld+json">
    {
  "@context": "https://schema.org",
  "@type": "AdvertisingAgency",
  "@id": "https://agenciavandu.com/#agency",
  "name": "Agencia Vandu",
  "url": "https://agenciavandu.com",
  "logo": "https://agenciavandu.com/img/logo-vandu.png",
  "image": "https://agenciavandu.com/img/og-vandu.jpg",
  "description": "Agencia con base en Mérida para negocios de todo México: sitios web con SEO y GEO, sistemas conectados a WhatsApp, video con IA, fotografía, redes sociales e impresión.",
  "areaServed": { "@type": "Country", "name": "México" },
  "knowsLanguage": ["es"],
  "address": {
    "@type": "PostalAddress",
    "addressLocality": "Mérida",
    "addressRegion": "Yucatán",
    "postalCode": "97000",
    "addressCountry": "MX"
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
    "optimización de sitios web para IA",
    "sistemas y automatización con WhatsApp",
    "video con inteligencia artificial",
    "impresión, rotulación y bordado"
  ]
}
    </script>
    @stack('estilos')
</head>

<body class="body">

    <div class="flotante">
        <div class="mensaje">Cotizar por whatsapp 👉🏻</div>
        <div class="notificacion">
            <img src="{{ asset('/img/bell-solid.svg') }}" class="imagen" alt="">
        </div>
        <a href="https://wa.me/+529991460310" class="btn-contacto">
            <img src="{{ asset('/img/whatsapp.svg') }}" width="40" alt="mensaje">
        </a>
    </div>
    <x-nav />
    <main>
        {{ $slot }}
    </main>

    <x-footer />

    <x-whatsapp-modal />
    <x-cookies />

</body>
@stack('js')

</html>
