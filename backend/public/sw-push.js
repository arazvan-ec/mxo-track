/**
 * Service Worker for driver push notifications.
 *
 * Handles incoming push events and notification clicks.
 */

self.addEventListener('push', function (event) {
    if (!event.data) {
        return;
    }

    let payload;
    try {
        payload = event.data.json();
    } catch (e) {
        payload = { title: 'Notificación', body: event.data.text() };
    }

    const title = payload.title || 'Notificación';
    const options = {
        body: payload.body || '',
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        data: payload.data || {},
        tag: 'driver-notification',
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var url = '/driver/routes';
    var data = event.notification.data || {};
    if (data.route_public_id) {
        url = '/driver/routes/' + data.route_public_id;
    }

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if (client.url.indexOf('/driver') !== -1 && 'focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            return clients.openWindow(url);
        })
    );
});
