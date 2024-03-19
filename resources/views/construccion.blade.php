@push ('estilos')
@vite(['resources/scss/app.scss', 'resources/scss/global.scss', 'resources/scss/construccion.scss', 'resources/js/app.js'])
@endpush
<x-layouts.guest title="Sección en construccion">
    <div class="container">
        <header class="header">
            <h1>Estamos construyendo un Vandú mejor</h1>
            <p>Te invitamos a visitar nuestras secciones disponibles por el momento</p>
            <div class="cta">
                <a href="{{route('home')}}" class="btn-cta"> Inicio </a>
                <a href="{{route('servicios')}}" class="btn-cta"> Desarrollo web </a>
            </div>
        </header>

    </div>
</x-layouts.guest>