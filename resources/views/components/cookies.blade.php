{{-- Barra de cookies: discreta, abajo, sin tapar la página. La elección se guarda en el navegador. --}}
<div class="ck-barra" id="ck-barra" role="region" aria-label="Preferencias de cookies" hidden>
    <p>Usamos cookies para que el sitio funcione y, si lo aceptas, para medir visitas y mejorar nuestros anuncios.
        <a href="{{ route('privacidad') }}#cookies">Aviso de privacidad</a></p>
    <div class="ck-botones">
        <button type="button" class="ck-btn ck-secundario" data-cookies="necesarias">Solo necesarias</button>
        <button type="button" class="ck-btn ck-primario" data-cookies="todas">Aceptar</button>
    </div>
</div>
