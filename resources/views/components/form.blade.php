<h5 class="pb-3">{{$ftitle ?? '¿Comenzamos?'}}</h5>
<form action="">
    <div class="row">
        <div class="col-6">
            <div class="form-group">
                <input type="text" class="form-control" placeholder="Nombre">
            </div>
        </div>
        <div class="col-6">
            <div class="form-group">
                <input type="text" class="form-control" placeholder="Apellido">
            </div>
        </div>
        <div class="col-6">
            <div class="form-group mt-2">
                <input type="tel" class="form-control" placeholder="Teléfono">
            </div>
        </div>
        <div class="col-6">
            <div class="form-group mt-2">
                <input type="email" class="form-control" placeholder="Correo electrónico">
            </div>
        </div>
        <div class="col-6">
            <select class="form-control mt-2">
                <option selected>Servicio de interés</option>
                <option>Web, diseño o rediseño de sitio web</option>
                <option>Fotografía de interiores o alimentos.</option>
                <option>Diseño digital (Equipos de diseño on demand)</option>
                <option>Diseño de impresos o empaque </option>
            </select>
        </div>
        <div class="col-6">
            <select class="form-control mt-2">
                <option selected>Presupuesto</option>
                <option>> 30,0000 MXN (1.7K USD)</option>
                <option>$50,000 - $90,000 MXN (2.9K - 5.2K USD)</option>
                <option>$100,000 - $300,000 MXN (5.8K - 17.5K USD)</option>
                <option>Diseño de impresos o empaque </option>
            </select>
        </div>
    </div>
    <div class="col-12 boton">
        <button type="button" class="btn btn-primary">Enviar información</button>
    </div>
</form>