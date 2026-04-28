const CACHE_NAME = 'ndu-pwa-cache-v2';

// Static assets to cache (cache-first)
const STATIC_CACHE_PATTERNS = [
    /\.png$/i,
    /\.jpg$/i,
    /\.jpeg$/i,
    /\.gif$/i,
    /\.svg$/i,
    /\.ico$/i,
    /\.woff2?$/i,
    /\.ttf$/i,
    /\.css$/i,
];

// Patterns that should ALWAYS go to network (dynamic/PHP/API)
const NETWORK_ONLY_PATTERNS = [
    /\.php(\?.*)?$/i,
    /\/api\//i,
    /\/teacher\//i,
    /\/student\//i,
];

self.addEventListener('install', event => {
    // Activate new SW immediately
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(['./assets/logo.png']).catch(() => {});
        })
    );
});

self.addEventListener('activate', event => {
    // Remove old caches
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys.filter(k => k !== CACHE_NAME).map(k => caches.delete(k))
            )
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    const url = event.request.url;

    // Always use network for dynamic/PHP/API requests
    const isNetworkOnly = NETWORK_ONLY_PATTERNS.some(p => p.test(url));
    if (isNetworkOnly) {
        event.respondWith(
            fetch(event.request).catch(err => {
                console.warn("SW: Fetch failed for dynamic resource:", url);
                return new Response("Network error", { status: 503 });
            })
        );
        return;
    }

    // Cache-first for static assets
    const isStaticAsset = STATIC_CACHE_PATTERNS.some(p => p.test(url));
    if (isStaticAsset) {
        event.respondWith(
            caches.match(event.request).then(cached => {
                return cached || fetch(event.request).then(response => {
                    const clone = response.clone();
                    caches.open(CACHE_NAME).then(cache => cache.put(event.request, clone));
                    return response;
                });
            }).catch(() => fetch(event.request))
        );
        return;
    }

    // Default: network-first for everything else
    event.respondWith(fetch(event.request).catch(() => caches.match(event.request)));
});
