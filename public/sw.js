var CACHE = 'talsa-static-v14';
var ASSETS = [
    '/css/app.css',
    '/css/responsive.css',
    '/js/toasts.js',
    '/js/confirm-dialog.js',
    '/js/offline-queue.js',
    '/js/offline-sync-registro.js',
    '/js/grid-query-filters.js',
    '/js/registro.js',
    '/images/logo-talsa-login.png',
];

function offlineResponse() {
    return new Response('Sin conexión', {
        status: 503,
        statusText: 'Service Unavailable',
        headers: { 'Content-Type': 'text/plain; charset=utf-8' },
    });
}

function cacheFirst(request) {
    return caches.match(request).then(function (cached) {
        if (cached) {
            return cached;
        }

        return fetch(request).then(function (res) {
            if (res && res.ok) {
                var copy = res.clone();
                caches.open(CACHE).then(function (cache) {
                    cache.put(request, copy);
                });
            }
            return res;
        }).catch(function () {
            return offlineResponse();
        });
    });
}

function networkFirst(request) {
    return fetch(request).then(function (res) {
        return res;
    }).catch(function () {
        return caches.match(request).then(function (cached) {
            return cached || offlineResponse();
        });
    });
}

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE).then(function (cache) {
            return cache.addAll(ASSETS);
        }).then(function () {
            return self.skipWaiting();
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.filter(function (k) { return k !== CACHE; }).map(function (k) {
                return caches.delete(k);
            }));
        }).then(function () {
            return self.clients.claim();
        })
    );
});

self.addEventListener('fetch', function (event) {
    var req = event.request;
    if (req.method !== 'GET') {
        return;
    }

    var url = new URL(req.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    if (url.pathname.startsWith('/css/') || url.pathname.startsWith('/js/') || url.pathname.startsWith('/images/')) {
        event.respondWith(networkFirst(req));
        return;
    }

    event.respondWith(networkFirst(req));
});
