@push('estilos')
    @vite(['resources/scss/app.scss', 'resources/scss/global.scss', 'resources/scss/contacto.scss', 'resources/js/app.js'])
@endpush
<x-layouts.guest titulo="Cotizar servicios">
    <x-header>
        <x-slot name="header">
            <h2>Nos encantaría llevar tu marca al siguiente nivel</h2>
            <p>Cada proyecto sin importar el tamaño tiene requerimientos diferentes, acércate a nuestros expertos que te
                guiarán en el proceso de saber que es lo mejor para ti y para tus objetivos finales.</p>
        </x-slot>
    </x-header>
    <section id="formulario" class="formulario">
        <div class="container">
            <script src="https://js.hsforms.net/forms/embed/51078765.js" defer></script>
            <div class="hs-form-frame" data-region="na1" data-form-id="016947aa-4806-4b0e-87e5-921f88476d97"
                data-portal-id="51078765"></div>
        </div>
    </section>

</x-layouts.guest>
