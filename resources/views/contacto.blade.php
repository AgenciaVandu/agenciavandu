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
            <script charset="utf-8" type="text/javascript" src="//js.hsforms.net/forms/embed/v2.js"></script>
            <script>
                hbspt.forms.create({
                    region: "na1",
                    portalId: "47390088",
                    formId: "f1957ffb-1456-4f83-8d46-98d46a0f9c52"
                });
            </script>
        </div>
    </section>

</x-layouts.guest>
