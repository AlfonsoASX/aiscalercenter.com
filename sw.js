const VERSION = 'aiscaler-pwa-v1';
const STATIC_CACHE = `${VERSION}-static`;
const RUNTIME_CACHE = `${VERSION}-runtime`;
const EXTERNAL_CACHE = `${VERSION}-external`;

const PRECACHE_URLS = [
    appUrl('./'),
    appUrl('index.php'),
    appUrl('index.php?view=login'),
    appUrl('index.php?view=app'),
    appUrl('offline.html'),
    appUrl('manifest.php'),
    appUrl('img/pwa/favicon-32.png'),
    appUrl('img/pwa/apple-touch-icon.png'),
    appUrl('img/pwa/icon-192.png'),
    appUrl('img/pwa/icon-512.png'),
];

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(STATIC_CACHE);
        await cache.addAll(PRECACHE_URLS.map((url) => new Request(url, { cache: 'reload' })));
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const cacheNames = await caches.keys();

        await Promise.all(
            cacheNames
                .filter((cacheName) => ![STATIC_CACHE, RUNTIME_CACHE, EXTERNAL_CACHE].includes(cacheName))
                .map((cacheName) => caches.delete(cacheName))
        );

        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET' || request.headers.has('range')) {
        return;
    }

    const requestUrl = new URL(request.url);

    if (shouldBypassRequest(requestUrl)) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(handleNavigationRequest(request));
        return;
    }

    if (isCacheableAssetRequest(request, requestUrl)) {
        event.respondWith(staleWhileRevalidate(request, requestUrl.origin === self.location.origin ? RUNTIME_CACHE : EXTERNAL_CACHE));
    }
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        void self.skipWaiting();
    }
});

function appUrl(path) {
    return new URL(path, self.registration.scope).toString();
}

function appPath(path) {
    return new URL(path, self.registration.scope).pathname;
}

function shouldBypassRequest(url) {
    if (url.origin !== self.location.origin) {
        return false;
    }

    return url.pathname.startsWith(appPath('api/'))
        || url.pathname === appPath('tool-action.php')
        || url.pathname === appPath('tool-asset.php')
        || url.pathname === appPath('whatsapp-webhook.php');
}

function isCacheableAssetRequest(request, url) {
    const destination = request.destination;

    if (url.origin === self.location.origin) {
        return ['style', 'script', 'font', 'image'].includes(destination);
    }

    return ['style', 'script', 'font'].includes(destination);
}

function shouldCacheNavigation(url) {
    if (url.origin !== self.location.origin) {
        return false;
    }

    return ![
        appPath('tool.php'),
        appPath('tools-browser.php'),
    ].includes(url.pathname);
}

async function handleNavigationRequest(request) {
    const requestUrl = new URL(request.url);
    const runtimeCache = await caches.open(RUNTIME_CACHE);

    try {
        const networkResponse = await fetch(request);

        if (networkResponse.ok && shouldCacheNavigation(requestUrl)) {
            await runtimeCache.put(request, networkResponse.clone());
        }

        return networkResponse;
    } catch (error) {
        const cachedResponse = await runtimeCache.match(request);

        if (cachedResponse) {
            return cachedResponse;
        }

        const precachedResponse = await caches.match(request);

        if (precachedResponse) {
            return precachedResponse;
        }

        const offlineResponse = await caches.match(appUrl('offline.html'));

        if (offlineResponse) {
            return offlineResponse;
        }

        throw error;
    }
}

async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cachedResponse = await cache.match(request);
    const networkResponse = await fetch(request)
        .then(async (networkResponse) => {
            if (networkResponse.ok || networkResponse.type === 'opaque') {
                await cache.put(request, networkResponse.clone());
            }

            return networkResponse;
        })
        .catch(() => cachedResponse);

    if (cachedResponse) {
        return cachedResponse;
    }

    if (networkResponse) {
        return networkResponse;
    }

    return Response.error();
}
