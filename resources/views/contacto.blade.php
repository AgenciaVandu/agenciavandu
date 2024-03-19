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
            <form action="{{route('cotizar.store')}}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-6">
                        <div class="form-group">
                            <label class="pb-2">Nombre</label>
                            <input type="text" class="form-control" placeholder="Escribe tu nombre" name="name">
                            @error('name')
                            <strong>{{$message}}</strong>
                            @enderror
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group">
                            <label class="pb-2">Apellido</label>
                            <input type="text" class="form-control" placeholder="Escribe tu apellido" name="lastname">
                            @error('lastname')
                            <strong>{{$message}}</strong>
                            @enderror
                        </div>
                    </div>
                    <div class="col-6 pt-2">
                        <div class="form-group">
                            <label class="pb-2">Teléfono</label>
                            <input type="tel" class="form-control" placeholder="¿Cuál es tu teléfono?" name="phone">
                            @error('tel')
                            <strong>{{$message}}</strong>
                            @enderror
                        </div>
                    </div>
                    <div class="col-6 pt-2">
                        <div class="form-group ">
                            <label for="" class="pb-2">Correo</label>
                            <input type="email" class="form-control" placeholder="ejemplo: hola@correo.com" name="email">
                            @error('email')
                            <strong>{{$message}}</strong>
                            @enderror
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="pb-2 pt-2">Selecciona tu servicio de interés</label>
                        <select class="form-control">

                            <option name="web">Web, diseño o rediseño de sitio web</option>
                            <option name="photo">Fotografía de interiores o alimentos.</option>
                            <option name="digital">Diseño digital (Equipos de diseño on demand)</option>
                            <option name="impresos">Diseño de impresos o empaque </option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="pb-2 pt-2">Selecciona tu presupuesto</label>
                        <select class="form-control">

                            <option name="30k">> 30,0000 MXN (1.7K USD)</option>
                            <option name="50k">$50,000 - $90,000 MXN (2.9K - 5.2K USD)</option>
                            <option name="100k">$100,000 - $300,000 MXN (5.8K - 17.5K USD)</option>
                        </select>
                    </div>
                </div>
                <div class="col-12 boton">
                    <button type="submit" class="btn btn-primary btn-send">Enviar información</button>
                </div>
            </form>
            <!--  <x-form name="ftitle">
                <x-slot name="nombre">Nombre</x-slot>
                <x-slot name="apellido">Apellido</x-slot>
                
                <x-slot name="telefono"><div class="pt-2">Telefono</div></x-slot>
                <div class="mt-2"></div>
                <x-slot name="correo"><div class="pt-2">Correo</div></x-slot>
            </x-form> -->
        </div>
    </section>

</x-layouts.guest>