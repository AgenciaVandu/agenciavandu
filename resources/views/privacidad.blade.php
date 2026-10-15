@push('estilos')
    @vite(['resources/scss/app.scss', 'resources/scss/global.scss', 'resources/scss/privacidad.scss', 'resources/js/app.js'])
@endpush
@php
    $p = config('vandu.privacidad');
@endphp
<x-layouts.guest titulo="Aviso de privacidad"
    meta-description="Cómo Agencia Vandu recaba, usa y protege tus datos personales, qué cookies usa el sitio y cómo ejercer tus derechos ARCO.">

    <section class="pv">
        <div class="pv-contenedor">
            <p class="pv-ceja">Aviso de privacidad integral</p>
            <h1>Aviso de privacidad</h1>
            <p class="pv-fecha">Última actualización: {{ $p['actualizado'] }}</p>

            <nav class="pv-indice" aria-label="Contenido">
                <a href="#responsable">Responsable</a>
                <a href="#datos">Datos que recabamos</a>
                <a href="#finalidades">Para qué los usamos</a>
                <a href="#compartir">Con quién los compartimos</a>
                <a href="#arco">Tus derechos ARCO</a>
                <a href="#cookies">Cookies</a>
                <a href="#cambios">Cambios a este aviso</a>
            </nav>

            <h2 id="responsable">1. Quién es el responsable de tus datos</h2>
            <p><b>{{ $p['responsable'] }}</b>, con nombre comercial <b>{{ $p['nombre_comercial'] }}</b>, con domicilio en {{ $p['domicilio'] }}, es responsable del uso y protección de tus datos personales, conforme a la Ley Federal de Protección de Datos Personales en Posesión de los Particulares.</p>
            <p>Para cualquier asunto relacionado con tus datos, escríbenos a <a href="mailto:{{ $p['correo'] }}">{{ $p['correo'] }}</a>.</p>

            <h2 id="datos">2. Qué datos personales recabamos</h2>
            <p>Según cómo te relaciones con nosotros, podemos recabar:</p>
            <ul>
                <li><b>Cuando nos escribes</b> por el formulario de cotización o el botón de WhatsApp: nombre, teléfono o WhatsApp, correo electrónico, empresa, el servicio que te interesa y el mensaje que nos envías.</li>
                <li><b>Si te vuelves cliente:</b> además, datos de facturación (RFC, razón social, régimen fiscal, domicilio fiscal y constancia de situación fiscal) y la información necesaria para tu proyecto.</li>
                <li><b>Al navegar el sitio:</b> datos técnicos como páginas visitadas, tipo de dispositivo y navegador, y la ubicación aproximada, por medio de cookies (ver la sección de cookies).</li>
            </ul>
            <p>No recabamos datos personales sensibles.</p>

            <h2 id="finalidades">3. Para qué usamos tus datos</h2>
            <p><b>Finalidades necesarias</b> para atenderte y darte el servicio, que no requieren tu consentimiento:</p>
            <ul>
                <li>Responder tus mensajes y solicitudes de cotización.</li>
                <li>Elaborar, enviar y dar seguimiento a cotizaciones.</li>
                <li>Prestar los servicios que contrates y darte acceso a tu portal de cliente.</li>
                <li>Emitir facturas y cumplir obligaciones fiscales y legales.</li>
            </ul>
            <p><b>Finalidades adicionales</b>, para las que sí puedes negarte:</p>
            <ul>
                <li>Enviarte novedades, promociones o casos de éxito.</li>
                <li>Medir el uso del sitio y mostrar publicidad de nuestros servicios (Google Analytics y Google Ads).</li>
            </ul>
            <p>Si no quieres que usemos tus datos para estas finalidades adicionales, escríbenos a <a href="mailto:{{ $p['correo'] }}">{{ $p['correo'] }}</a> o elige "Solo necesarias" en las <button type="button" class="pv-enlace" data-cookies-abrir>preferencias de cookies</button>. Negarte no afecta los servicios que nos contrates.</p>

            <h2 id="compartir">4. Con quién compartimos tus datos</h2>
            <p>No vendemos ni rentamos tus datos. Solo los compartimos con proveedores que nos ayudan a operar y que los tratan por nuestra cuenta, bajo confidencialidad:</p>
            <ul>
                <li>Servicios de hospedaje web y correo electrónico.</li>
                <li>Google (Analytics, Ads y reCAPTCHA, que protege los formularios contra spam).</li>
                <li>Almacenamiento en la nube para los archivos de tu proyecto.</li>
                <li>WhatsApp (Meta), cuando decides escribirnos por ese medio.</li>
            </ul>
            <p>También podemos compartirlos con autoridades cuando una ley lo exija.</p>

            <h2 id="arco">5. Tus derechos ARCO y cómo ejercerlos</h2>
            <p>Tienes derecho a <b>acceder</b> a tus datos, <b>rectificarlos</b> si son incorrectos, <b>cancelarlos</b> cuando ya no sean necesarios y <b>oponerte</b> a su uso para fines específicos. También puedes revocar tu consentimiento o limitar el uso de tus datos.</p>
            <p>Para hacerlo, envía un correo a <a href="mailto:{{ $p['correo'] }}">{{ $p['correo'] }}</a> con:</p>
            <ul>
                <li>Tu nombre y un medio para responderte.</li>
                <li>Una copia de tu identificación oficial (o la de tu representante y el documento que lo acredite).</li>
                <li>Qué derecho quieres ejercer y sobre qué datos.</li>
            </ul>
            <p>Te responderemos en un plazo máximo de 20 días hábiles y, si procede, haremos efectiva tu solicitud dentro de los 15 días hábiles siguientes.</p>

            <h2 id="cookies">6. Cookies y tecnologías de rastreo</h2>
            <p>Este sitio usa cookies, que son pequeños archivos que se guardan en tu navegador:</p>
            <div class="pv-tabla">
                <table>
                    <thead><tr><th>Tipo</th><th>Quién</th><th>Para qué</th></tr></thead>
                    <tbody>
                        <tr><td>Necesarias</td><td>Agencia Vandu</td><td>Mantener tu sesión segura y proteger los formularios (XSRF-TOKEN y sesión). No se pueden desactivar.</td></tr>
                        <tr><td>Seguridad</td><td>Google reCAPTCHA</td><td>Distinguir personas de robots al enviar un formulario.</td></tr>
                        <tr><td>Medición</td><td>Google Analytics</td><td>Saber qué páginas se visitan y cómo mejorar el sitio.</td></tr>
                        <tr><td>Publicidad</td><td>Google Ads</td><td>Medir si nuestros anuncios funcionan y mostrarte publicidad relevante.</td></tr>
                    </tbody>
                </table>
            </div>
            <p>Además, el formulario de WhatsApp recuerda en tu navegador tu nombre y teléfono para que no tengas que escribirlos otra vez. Ese dato no sale de tu dispositivo hasta que envías el formulario.</p>
            <p>Puedes aceptar o rechazar las cookies de medición y publicidad en cualquier momento desde las <button type="button" class="pv-enlace" data-cookies-abrir>preferencias de cookies</button>, o borrarlas desde la configuración de tu navegador.</p>

            <h2 id="cambios">7. Cambios a este aviso</h2>
            <p>Si actualizamos este aviso, publicaremos la nueva versión en esta misma página con su fecha de actualización.</p>
        </div>
    </section>

</x-layouts.guest>
