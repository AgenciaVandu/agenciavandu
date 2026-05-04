@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('cotizar.store') }}" method="POST">
    @csrf <div class="row">
        <div class="col-6">
            <div class="form-group">
                <label class="pb-2">Nombre</label>
                <input type="text" class="form-control" placeholder="Escribe tu nombre" name="name" value="{{ old('name') }}">
            </div>
        </div>
        <div class="col-6">
            <div class="form-group">
                <label class="pb-2">Apellido</label>
                <input type="text" class="form-control" placeholder="Escribe tu apellido" name="lastname" value="{{ old('lastname') }}">
            </div>
        </div>
        <div class="col-6">
            <div class="form-group">
                <label class="pb-2">Teléfono</label>
                <input type="tel" class="form-control" placeholder="¿Cuál es tu teléfono?" name="phone" value="{{ old('phone') }}">
            </div>
        </div>
        <div class="col-6">
            <div class="form-group">
                <label class="pb-2">Correo Electrónico</label>
                <input type="email" class="form-control" placeholder="ejemplo: hola@correo.com" name="email" value="{{ old('email') }}">
            </div>
        </div>
        <div class="col-6">
            <label class="pb-2 pt-2">Selecciona tu servicio de interés</label>
            <select class="form-control" name="service">
                <option value="">Selecciona una opción...</option>
                <option value="web">Diseño o rediseño de sitio web</option>
                <option value="photo">Fotografía de interiores o alimentos.</option>
                <option value="digital">Diseño digital (Equipos de diseño on demand)</option>
                <option value="ia-friendly">Servicios AI Friendly</option>
            </select>
        </div>
    </div>
    <div class="col-6 mt-3">
        <button type="submit" class="btn btn-primary btn-send">Enviar información</button>
    </div>
</form>