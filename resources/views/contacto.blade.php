@push ('estilos')
@vite(['resources/scss/app.scss', 'resources/scss/global.scss', 'resources/scss/contacto.scss', 'resources/js/app.js'])
@endpush
<x-layouts.guest titulo="Cotizar servicios">
    <x-header>
        <x-slot name="header">
            <h1>Nos encantaría llevar tu marca al siguiente nivel</h1>
            <p>Hagamos que tu </p>
        </x-slot>

    </x-header>

</x-layouts.guest>