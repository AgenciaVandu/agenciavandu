{{--
    Formulario que aparece al dar clic en cualquier enlace a wa.me del sitio.
    Guarda al prospecto en Ventas, avisa al equipo y abre WhatsApp con el mensaje ya escrito.
    Un enlace puede preseleccionar el servicio con data-servicio="Página web".
--}}
@php
    $opciones = ['Página web', 'Sistema con WhatsApp', 'Video con IA', 'Fotografía', 'Video', 'Redes sociales', 'Impresión y rotulación', 'Otro'];
    $directo = 'https://wa.me/' . preg_replace('/\D/', '', (string) config('vandu.whatsapp', '529991460310'));
@endphp
<dialog class="wa-modal" id="wa-modal" aria-labelledby="wa-titulo"
    data-endpoint="{{ route('whatsapp.store') }}" data-recaptcha="{{ env('RECAPTCHA_SITE_KEY') }}">
    <form class="wa-form" id="wa-form" novalidate>
        @csrf
        <input type="hidden" name="pagina" id="wa-pagina">
        <input type="hidden" name="g-recaptcha-response" id="wa-recaptcha">

        <header class="wa-encabezado">
            <span class="wa-icono" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.7-1.4.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.4.1-.7.3-.2.3-.9.9-.9 2.2s.9 2.5 1 2.7c.1.2 1.8 2.8 4.4 3.9 1.6.7 2.3.8 3.1.6.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.6-.3Z"/></svg>
            </span>
            <div>
                <h2 id="wa-titulo">Cotiza por WhatsApp</h2>
                <p>Cuéntanos lo básico y te abrimos WhatsApp con tu mensaje listo.</p>
            </div>
            <button type="button" class="wa-cerrar" data-wa-cerrar aria-label="Cerrar">×</button>
        </header>

        <div class="wa-campos">
            <label class="wa-campo">
                <span>Tu nombre</span>
                <input type="text" name="nombre" id="wa-nombre" autocomplete="name" required maxlength="100" placeholder="Laura Canché">
            </label>
            <label class="wa-campo">
                <span>Tu WhatsApp</span>
                <input type="tel" name="telefono" id="wa-telefono" autocomplete="tel" inputmode="tel" required maxlength="30" placeholder="999 123 4567">
            </label>
            <label class="wa-campo">
                <span>Empresa <em>opcional</em></span>
                <input type="text" name="empresa" id="wa-empresa" autocomplete="organization" maxlength="120" placeholder="Nombre de tu negocio">
            </label>
            <label class="wa-campo">
                <span>Correo <em>opcional</em></span>
                <input type="email" name="email" id="wa-email" autocomplete="email" maxlength="160" placeholder="hola@tunegocio.com">
            </label>

            <fieldset class="wa-campo wa-ancho">
                <legend>¿Qué necesitas?</legend>
                <div class="wa-chips">
                    @foreach ($opciones as $i => $op)
                        <label class="wa-chip">
                            <input type="radio" name="servicio" value="{{ $op }}" @if ($i === 0) required @endif>
                            <span>{{ $op }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <label class="wa-campo wa-ancho">
                <span>Cuéntanos un poco <em>opcional</em></span>
                <textarea name="mensaje" id="wa-mensaje" rows="3" maxlength="1000" placeholder="Por ejemplo: necesito renovar mi página y que me lleguen pedidos por WhatsApp."></textarea>
            </label>
        </div>

        <div class="wa-vista" aria-live="polite">
            <small>Así llegará tu mensaje</small>
            <p id="wa-vista-texto"></p>
        </div>

        <p class="wa-error" id="wa-error" role="alert" hidden></p>

        <footer class="wa-acciones">
            <button type="submit" class="wa-enviar" id="wa-enviar">Abrir WhatsApp</button>
            <a class="wa-directo" href="{{ $directo }}" target="_blank" rel="noopener" data-wa-directo>Prefiero escribir sin llenar esto</a>
        </footer>
    </form>
</dialog>
