/*
 * Lais service worker. It keeps only the offline page and the icons:
 * pages and app data always come from the server, so no figure is ever
 * stale and no business data stays on the phone.
 */
const CACHE = 'lais-v2';
const OFFLINE = '/offline.html';
const SHELL = [OFFLINE, '/pwa/icon-192.png', '/favicon.svg'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((cache) => cache.addAll(SHELL))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter((key) => key !== CACHE)
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Only full page loads. Inertia visits, form posts, and files go to the network untouched.
    if (request.mode !== 'navigate' || request.method !== 'GET') {
        return;
    }

    event.respondWith(fetch(request).catch(() => caches.match(OFFLINE)));
});

// A new order (App\Services\Push): shown even with Lais closed.
self.addEventListener('push', (event) => {
    let message = {};

    try {
        message = event.data ? event.data.json() : {};
    } catch {
        message = {};
    }

    event.waitUntil(
        self.registration.showNotification(message.title || 'Lais', {
            body: message.body || '',
            icon: '/pwa/icon-192.png',
            badge: '/pwa/icon-192.png',
            lang: 'id',
            data: { url: message.url || '/pesanan' },
        }),
    );
});

// Opens the order, in an open Lais tab when there is one.
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(
        event.notification.data?.url || '/pesanan',
        self.location.origin,
    ).href;

    event.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((tabs) => {
                const tab = tabs.find((client) =>
                    client.url.startsWith(self.location.origin),
                );

                return tab
                    ? tab.navigate(url).then((client) => client?.focus())
                    : self.clients.openWindow(url);
            }),
    );
});
