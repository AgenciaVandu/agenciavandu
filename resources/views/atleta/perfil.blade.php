@extends('atleta.layout')
@section('titulo', 'Perfil')
@section('contenido')
<h1>Tu perfil</h1>

<div class="tarjeta" style="margin-top: 14px" id="avisos-push" data-clave="{{ $clavePush }}" data-url="{{ route('atleta.avisos') }}">
    <b><i class="bi bi-bell-fill" style="color: var(--senal-2)"></i> Avisos en este celular</b>
    <p class="sub" style="font-size: 14px; margin: 4px 0 10px">Te avisamos antes de tu clase y cuando tu paquete esté por terminarse.</p>
    <button type="button" class="btn full" id="btn-avisos">Activar avisos</button>
    <p class="sub" id="msg-avisos" style="font-size: 13.5px; margin: 8px 0 0"></p>
</div>

<form method="post" action="{{ route('atleta.perfil.guardar') }}" enctype="multipart/form-data" class="tarjeta" style="margin-top: 12px">
    @csrf
    <div style="display: flex; align-items: center; gap: 14px">
        <div style="width: 64px; height: 64px; border-radius: 50%; overflow: hidden; background: var(--placa-2); border: 1px solid var(--riel); display: grid; place-items: center; font-weight: 650">
            @if($a->foto)<img src="{{ route('atleta.foto', ['v' => substr(md5((string) $a->foto), 0, 8)]) }}" alt="" style="width: 100%; height: 100%; object-fit: cover">@else{{ $a->iniciales }}@endif</div>
        <label class="btn sec chico" style="margin: 0">Cambiar foto<input type="file" name="foto" accept="image/*" hidden onchange="this.form.submit()"></label>
    </div>
    <label class="campo" for="nombre">Nombre</label>
    <input class="campo" name="nombre" id="nombre" value="{{ old('nombre', $a->nombre) }}" required maxlength="120">
    <label class="campo" for="telefono">Teléfono</label>
    <input class="campo" name="telefono" id="telefono" value="{{ old('telefono', $a->telefono) }}" maxlength="30" inputmode="tel">
    <label class="campo">Correo (tu usuario)</label>
    <input class="campo" value="{{ $a->email }}" disabled>
    <details style="margin-top: 16px"><summary style="cursor: pointer; font-weight: 500; color: var(--senal-2)">Cambiar contraseña</summary>
        <label class="campo" for="actual">Contraseña actual</label><input class="campo" type="password" name="actual" id="actual" autocomplete="current-password">
        <label class="campo" for="password">Nueva</label><input class="campo" type="password" name="password" id="password" minlength="8" autocomplete="new-password">
        <label class="campo" for="password_confirmation">Repítela</label><input class="campo" type="password" name="password_confirmation" id="password_confirmation" minlength="8" autocomplete="new-password">
    </details>
    <button class="btn full" style="margin-top: 18px">Guardar</button>
</form>

<a href="{{ route('atleta.historial') }}" class="btn sec full" style="margin-top: 12px"><i class="bi bi-clock-history"></i> Historial de clases</a>
<form method="post" action="{{ route('atleta.salir') }}" style="margin-top: 10px">@csrf<button class="btn sec full">Salir</button></form>
@endsection

@push('scripts')
<script>
(function () {
    const caja = document.getElementById('avisos-push'), btn = document.getElementById('btn-avisos'), msg = document.getElementById('msg-avisos');
    const ios = /iphone|ipad|ipod/i.test(navigator.userAgent), instalada = matchMedia('(display-mode: standalone)').matches || navigator.standalone;
    const decir = (t) => { msg.textContent = t; };
    if (!caja.dataset.clave || !('serviceWorker' in navigator) || !('PushManager' in window)) {
        btn.disabled = true;
        decir(ios && !instalada ? 'En iPhone: toca Compartir → "Agregar a inicio", abre la app desde ahí y activa los avisos.' : 'Este navegador no permite avisos.');
        return;
    }
    if (Notification.permission === 'granted') { btn.textContent = 'Avisos activos ✓'; }
    const clave = (b64) => { const p = '='.repeat((4 - b64.length % 4) % 4), s = atob((b64 + p).replace(/-/g, '+').replace(/_/g, '/')); return Uint8Array.from([...s].map((c) => c.charCodeAt(0))); };
    btn.addEventListener('click', async () => {
        btn.disabled = true;
        try {
            const permiso = await Notification.requestPermission();
            if (permiso !== 'granted') { decir('Necesitas permitir las notificaciones en tu navegador.'); return; }
            const reg = await navigator.serviceWorker.register(@json(route('atleta.sw')), { scope: '/atleta' });
            await navigator.serviceWorker.ready;
            const sub = await reg.pushManager.getSubscription() || await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: clave(caja.dataset.clave) });
            const r = await fetch(caja.dataset.url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) }, body: JSON.stringify(sub.toJSON()) });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            btn.textContent = 'Avisos activos ✓'; decir('Listo. Te llegará un aviso de prueba.');
        } catch (e) { decir('No se pudieron activar: ' + (e.message || e)); }
        finally { btn.disabled = false; }
    });
})();
</script>
@endpush
