@push('estilos')
    @vite(['resources/scss/app.scss', 'resources/scss/global.scss', 'resources/scss/contacto.scss', 'resources/js/app.js'])
@endpush
<x-layouts.guest titulo="Cotizar servicios">
    <x-header>
        <x-slot name="header">
            <h1>Nos encantaría llevar tu marca al siguiente nivel</h1>
            <p>Cada proyecto sin importar el tamaño tiene requerimientos diferentes, acércate a nuestros expertos que te
                guiarán en el proceso de saber que es lo mejor para ti y para tus objetivos finales.</p>
        </x-slot>
    </x-header>
    <section class="conversion">
        <div class="container mb-5">
            <x-form></x-form>
        </div>
    </section>
</x-layouts.guest>
