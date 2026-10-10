/* Service worker del panel de Vandu · versión {{ $version }}
 * - Páginas: siempre de la red (datos al día). Sin internet, se muestra la pantalla "Sin conexión".
 * - Estilos, scripts, íconos y fuente: se guardan para que la app abra rápido.
 * - Notificaciones push: las muestra y, al tocarlas, abre la página indicada dentro de la app.
 * No guarda páginas con datos de clientes. */
const CACHE = 'vandu-panel-{{ $version }}';
const SIN_CONEXION = @json(route('app.sin-conexion'));
const GUARDAR = @json($guardar);

self.addEventListener('install', (e) => {
    e.waitUntil(caches.open(CACHE).then((c) => Promise.allSettled(GUARDAR.map((u) => c.add(new Request(u, { mode: u.startsWith('http') && !u.startsWith(self.location.origin) ? 'cors' : 'same-origin' }))))).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
    e.waitUntil(caches.keys().then((ks) => Promise.all(ks.filter((k) => k.startsWith('vandu-panel-') && k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return;
    const url = new URL(req.url);

    // Navegación: red primero; si no hay internet, pantalla sin conexión
    if (req.mode === 'navigate') {
        e.respondWith(fetch(req).catch(() => caches.match(SIN_CONEXION)));
        return;
    }

    // Recursos estáticos: lo guardado al instante y se actualiza por detrás
    const estatico = url.hostname === 'cdn.jsdelivr.net' || url.pathname === '/vandu-fuente.woff2' || url.pathname.startsWith('/admin/app/icono/');
    if (estatico) {
        e.respondWith(caches.open(CACHE).then(async (c) => {
            const guardado = await c.match(req);
            const red = fetch(req).then((r) => { if (r && (r.ok || r.type === 'opaque')) c.put(req, r.clone()); return r; }).catch(() => guardado);
            return guardado || red;
        }));
    }
});

// ---------- Notificaciones push ----------
const ICONO = @json(route('app.icono', 'icono-192.png'));

self.addEventListener('push', (e) => {
    let d = {};
    try { d = e.data ? e.data.json() : {}; } catch (err) { d = { titulo: 'Vandu', cuerpo: e.data ? e.data.text() : '' }; }
    e.waitUntil(self.registration.showNotification(d.titulo || 'Vandu', {
        body: d.cuerpo || '',
        icon: ICONO,
        badge: ICONO,
        tag: d.etiqueta || undefined,
        renotify: !!d.etiqueta,
        data: { url: d.url || '/admin' },
    }));
});

self.addEventListener('notificationclick', (e) => {
    e.notification.close();
    const destino = new URL((e.notification.data && e.notification.data.url) || '/admin', self.location.origin).href;
    e.waitUntil((async () => {
        const ventanas = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const panel = ventanas.find((c) => c.url.startsWith(self.location.origin + '/admin'));
        if (panel) {
            try { await panel.focus(); } catch (err) {}
            try { return await panel.navigate(destino); } catch (err) {}
        }
        return self.clients.openWindow(destino);
    })());
});
