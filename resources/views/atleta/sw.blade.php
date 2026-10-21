/* Service worker del portal del atleta · versión {{ $version }}
 * Páginas siempre de la red. Muestra los avisos (clase por empezar, paquete por vencer) y al tocarlos abre el portal. */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));

const ICONO = @json(route('app.icono', 'icono-192.png'));
self.addEventListener('push', (e) => {
    let d = {};
    try { d = e.data ? e.data.json() : {}; } catch (err) { d = { titulo: 'Tu gimnasio', cuerpo: e.data ? e.data.text() : '' }; }
    e.waitUntil(self.registration.showNotification(d.titulo || 'Tu gimnasio', {
        body: d.cuerpo || '', icon: ICONO, badge: ICONO, tag: d.etiqueta || undefined, renotify: !!d.etiqueta, data: { url: d.url || '/atleta/inicio' },
    }));
});
self.addEventListener('notificationclick', (e) => {
    e.notification.close();
    const destino = new URL((e.notification.data && e.notification.data.url) || '/atleta/inicio', self.location.origin).href;
    e.waitUntil((async () => {
        const ventanas = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const portal = ventanas.find((c) => c.url.startsWith(self.location.origin + '/atleta'));
        if (portal) { try { await portal.focus(); return await portal.navigate(destino); } catch (err) {} }
        return self.clients.openWindow(destino);
    })());
});
