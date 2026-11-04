{{--
    Inicio de agenciavandu.com. Cada sección es un componente en resources/views/components/inicio
    y sus textos viven en config/inicio.php. La barra y el botón de WhatsApp son del sitio
    (components/barra y components/whatsapp-flotante) y se pueden usar en cualquier página.
--}}
@push('estilos')
    <script>
        // Las animaciones de entrada solo se preparan si el JS del inicio carga; si en 3 s no cargó, se muestra todo
        (function (d) {
            d.classList.add('vx-js');
            setTimeout(function () { if (!window.vxListo) d.classList.remove('vx-js'); }, 3000);
        })(document.documentElement);
    </script>
    @vite(['resources/scss/app.scss', 'resources/scss/global.scss', 'resources/scss/vandu-inicio.scss', 'resources/js/app.js', 'resources/js/vandu-inicio.js'])
@endpush
<x-layouts.guest titulo="Páginas web, sistemas y video con IA en Mérida" tema="vx" :propio="true"
    meta-description="Agencia en Mérida para negocios de todo México: páginas web con SEO, sistemas conectados a WhatsApp con Nerby, video con IA, fotografía e impresión. Cotiza en 24 h.">

    <x-barra :espacio="false" />

    <x-inicio.portada />
    <x-inicio.marcas />
    <x-inicio.servicios />
    <x-inicio.nerby />
    <x-inicio.foto />
    <x-inicio.proceso />
    <x-inicio.clientes />
    <x-inicio.preguntas />
    <x-inicio.contacto />

    <x-inicio.riel />
    <x-whatsapp-flotante />
    <x-inicio.cursor />

</x-layouts.guest>
