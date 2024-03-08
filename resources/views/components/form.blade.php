<form action="{{route('cotizar.store')}}" method="POST">
    @csrf
    <div class="row">
        <div class="col-6">
            <div class="form-group">
                <label class="pb-2">{{$nombre ?? ''}}</label>
                <input type="text" class="form-control" placeholder="Escribe tu nombre" name="name">
            </div>
        </div>
        <div class="col-6">
            <div class="form-group">
            <label class="pb-2">{{$apellido ?? ''}}</label>
                <input type="text" class="form-control" placeholder="Escribe tu apellido" name="lastname">
            </div>
        </div>
        <div class="col-6">
            <div class="form-group">
            <label class="pb-2">{{$telefono ?? ''}}</label>
                <input type="tel" class="form-control" placeholder="¿Cuál es tu teléfono?" name="phone">
            </div>
        </div>
        <div class="col-6">
            <div class="form-group ">
            <label for=""class="pb-2">{{$correo ?? ''}}</label>
                <input type="email" class="form-control" placeholder="ejemplo: hola@correo.com" name="email">
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