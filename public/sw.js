const CACHE_NAME = 'allsers-v7.0';
const OFFLINE_URL = '/offline.html';
const SHELL_URLS = [
    '/dashboard',
    '/menu',
    '/bookmarks',
    '/finder',
    '/notifications',
    '/lila',
    '/chat',
    '/settings/profile',
    '/settings/password',
    '/settings/appearance',
];
const ASSETS_TO_CACHE = [
    OFFLINE_URL,
    '/manifest.json',
    '/favicon.ico',
    '/favicon.svg',
    '/apple-touch-icon.png',
    ...SHELL_URLS,
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(ASSETS_TO_CACHE).catch(() => {
            });
        })
    );
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => {
            return Promise.all(
                keys.filter(key => key !== CACHE_NAME)
                    .map(key => caches.delete(key))
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', event => {
    if (event.request.method !== 'GET') return;

    const url = new URL(event.request.url);

    if (
        url.pathname.includes('/livewire/') ||
        url.pathname.includes('/volt/') ||
        url.pathname.includes('/up') ||
        url.pathname.includes('/login') ||
        url.pathname.includes('/register')
    ) {
        return;
    }

    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request)
                .catch(() => caches.match(OFFLINE_URL))
        );
        return;
    }

    if (
        url.pathname.startsWith('/build/') ||
        url.pathname.match(/\.(css|js|woff2?|ttf|eot)$/)
    ) {
        event.respondWith(
            caches.match(event.request).then(cached => {
                if (cached) return cached;
                return fetch(event.request).then(response => {
                    if (response && response.status === 200) {
                        const cloned = response.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(event.request, cloned));
                    }
                    return response;
                });
            })
        );
        return;
    }

    if (
        url.pathname.match(/\.(png|jpg|jpeg|gif|svg|ico|webp|avif)$/) ||
        url.pathname.startsWith('/images/') ||
        url.pathname.startsWith('/storage/')
    ) {
        event.respondWith(
            caches.match(event.request).then(cached => {
                const fetchPromise = fetch(event.request).then(response => {
                    if (response && response.status === 200) {
                        const cloned = response.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(event.request, cloned));
                    }
                    return response;
                }).catch(() => cached);
                return cached || fetchPromise;
            })
        );
        return;
    }

    event.respondWith(
        fetch(event.request).then(response => {
            if (response && response.status === 200 && response.type === 'basic') {
                const cloned = response.clone();
                caches.open(CACHE_NAME).then(cache => {
                    cache.put(event.request, cloned);
                });
            }
            return response;
        }).catch(() => {
            return caches.match(event.request);
        })
    );
});

self.addEventListener('push', event => {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: event.data ? event.data.text() : 'Allsers', body: '' };
    }
    const title = data.title || 'Allsers';
    const options = {
        body: data.body || data.message || '',
        icon: data.icon || '/apple-touch-icon.png',
        badge: data.badge || '/favicon.ico',
        data: data.data || { url: data.url || '/notifications' },
        tag: data.tag || 'allsers-notification',
        requireInteraction: false,
    };
    if (data.image) options.image = data.image;
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    const url = event.notification.data && event.notification.data.url ? event.notification.data.url : '/notifications';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
            for (const client of windowClients) {
                if (client.url === url && 'focus' in client) return client.focus();
            }
            if (clients.openWindow) return clients.openWindow(url);
        })
    );
});

self.addEventListener('pushsubscriptionchange', event => {
    event.waitUntil(
        self.registration.pushManager.subscribe({ userVisibleOnly: true }).then(subscription => {
            return fetch('/push-subscriptions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(subscription.toJSON()),
            });
        })
    );
});
