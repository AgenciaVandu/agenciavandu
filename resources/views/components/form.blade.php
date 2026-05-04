<div id="mensaje-exito" class="alert alert-success d-none"></div>
<div id="mensaje-error" class="alert alert-danger d-none"></div>

<form id="form-cotizar">
    @csrf 
    <!-- Token de reCAPTCHA oculto que el JS llenará -->
    <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
    <div class="row">
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
        <button type="submit" class="btn btn-primary btn-send" id="btn-submit">
            <span class="spinner-border spinner-border-sm d-none" id="loader" role="status"></span>
            <span id="btn-text"style="
        background-color: colores.$black;
        border: 1px colores.$green solid;
        color: #fff;
        border-radius: 1em;
        text-decoration: none;
        padding: 10px 1em;
        width: 160px;">Enviar información</span>
        </button>
    </div>
</form>


<!-- Scripts: Google API + Lógica del Loader -->
<script src="https://www.google.com/recaptcha/api.js?render={{ env('RECAPTCHA_SITE_KEY') }}"></script>
<script>
document.getElementById('form-cotizar').addEventListener('submit', function(e) {
    e.preventDefault();

    const form = e.target;
    const btn = document.getElementById('btn-submit');
    const loader = document.getElementById('loader');
    const btnText = document.getElementById('btn-text');
    const successDiv = document.getElementById('mensaje-exito');
    const errorDiv = document.getElementById('mensaje-error');

    // Limpiar estados previos
    successDiv.classList.add('d-none');
    errorDiv.classList.add('d-none');
    errorDiv.innerText = ''; 
    
    btn.disabled = true;
    loader.classList.remove('d-none');
    btnText.innerText = ' Procesando...';

    grecaptcha.ready(function() {
        grecaptcha.execute("{{ env('RECAPTCHA_SITE_KEY') }}", {action: 'submit'}).then(function(token) {
            document.getElementById('g-recaptcha-response').value = token;

            const formData = new FormData(form);

            fetch("{{ route('cotizar.store') }}", {
                method: "POST",
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                    // IMPORTANTE: No pongas Content-Type, el navegador lo hace solo con FormData
                }
            })
            .then(async response => {
                const data = await response.json();
                
                // Si el status no es 200-299, lanzamos el objeto de error
                if (!response.ok) {
                    return Promise.reject(data);
                }
                return data;
            })
            .then(data => {
                loader.classList.add('d-none');
                
                if (data.success) {
                    form.classList.add('d-none');
                    successDiv.innerText = data.message;
                    successDiv.classList.remove('d-none');
                    // Scroll suave hacia arriba para ver el éxito
                    window.scrollTo({ top: form.offsetTop - 100, behavior: 'smooth' });
                }
            })
            .catch(error => {
                loader.classList.add('d-none');
                btn.disabled = false;
                btnText.innerText = 'Reintentar';
                errorDiv.classList.remove('d-none');

                // Si hay errores de validación (Laravel 422)
                if (error.errors) {
                    // Unimos todos los errores en un solo texto
                    const messages = Object.values(error.errors).flat().join(' ');
                    errorDiv.innerText = messages;
                } else {
                    // Si es un error de bot o de servidor
                    errorDiv.innerText = error.message || 'Error de conexión. Inténtalo de nuevo.';
                }
            });
        });
    });
});
</script>