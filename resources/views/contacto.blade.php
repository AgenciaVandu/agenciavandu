@push ('estilos')
@vite(['resources/scss/app.scss', 'resources/scss/global.scss', 'resources/scss/contacto.scss', 'resources/js/app.js'])
@endpush
<x-layouts.guest titulo="Cotizar servicios">
    <x-header>
        <x-slot name="header">
            <h2>Nos encantaría llevar tu marca al siguiente nivel</h2>
            <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Velit, aspernatur at tenetur ullam quae odio necessitatibus consectetur quibusdam ad iste sint obcaecati dolor porro? Blanditiis ex ducimus deleniti dolores molestiae.</p>
        </x-slot>
    </x-header>
    <section id="formulario" class="formulario">
        <div class="container">
            <x-form name="ftitle">
                <x-slot name="nombre">Nombre</x-slot>
                <x-slot name="apellido">Apellido</x-slot>
                
                <x-slot name="telefono"><div class="pt-2">Telefono</div></x-slot>
                <div class="mt-2"></div>
                <x-slot name="correo"><div class="pt-2">Correo</div></x-slot>
            </x-form>
        </div>
    </section>

</x-layouts.guest>