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
        <div class="col-12">
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
   <div class="col-12 mt-3">
        <button type="submit" class="btn btn-primary btn-send btn-block" id="btn-submit">
            <span class="spinner-border spinner-border-sm d-none" id="loader" role="status"></span>
            <span id="btn-text"style="
        background-color: colores.$black;
        border: 1px colores.$green solid;
        color: #fff;
        border-radius: 1em;
        text-decoration: none;
        padding: 10px 1em;
        ">Enviar información</span>
        </button>
    </div>
</form>

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
                    'Accept': 'application/json',
                    // Agregamos el token CSRF manualmente para evitar fallos de seguridad
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
                }
            })
            .then(async response => {
                const data = await response.json();
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
                    window.scrollTo({ top: successDiv.offsetTop - 100, behavior: 'smooth' });
                }
            })
            .catch(error => {
                loader.classList.add('d-none');
                btn.disabled = false;
                btnText.innerText = 'Reintentar';
                errorDiv.classList.remove('d-none');

                if (error.errors) {
                    const messages = Object.values(error.errors).flat().join(' ');
                    errorDiv.innerText = messages;
                } else {
                    errorDiv.innerText = error.message || 'Error de conexión. Inténtalo de nuevo.';
                }
            });
        });
    });
});
</script>