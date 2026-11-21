/* P1 public-only offline foundation. Bump VERSION when changing cached assets. */
'use strict';
const VERSION = 'p1-wp04-001';
const scope = new URL(self.registration.scope);
const prefix = 'iedpu-public:' + scope.pathname + ':';
const cacheName = prefix + VERSION;
const absolute = path => new URL(path, scope).href;
const files = [
    'offline.html', 'manifest.webmanifest',
    'assets/icons/portal-192.svg', 'assets/icons/portal-512.svg',
    'assets/css/offline.css', 'assets/css/bootstrap.min.css', 'assets/css/app-theme.css',
    'assets/js/vendor/vue.global.prod.js', 'assets/js/vendor/bootstrap.bundle.min.js',
    'assets/js/vendor/dexie.min.js', 'assets/js/core/app.js', 'assets/js/core/navigation.js',
    'assets/js/core/api.js', 'assets/js/core/workspaces.js',
    'assets/js/core/pwa.js', 'assets/js/core/offline-store.js',
];
const allowed = new Set(files.map(absolute));
const publicNavigations = new Set(['','preview/member','preview/branch','preview/committee','preview/technical'].map(absolute));

self.addEventListener('install', event => {
    event.waitUntil((async () => {
        const cache = await caches.open(cacheName);
        try {
            for (const url of allowed) {
                const response = await fetch(url, {cache:'reload', credentials:'omit', redirect:'error'});
                if (!response.ok || response.type !== 'basic' || response.headers.get('content-type')?.includes('text/html') && url !== absolute('offline.html')) {
                    throw new Error('Public asset unavailable: ' + url);
                }
                await cache.put(url, response);
            }
        } catch (error) {
            await caches.delete(cacheName);
            throw error;
        }
    })());
});
self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        for (const key of await caches.keys()) {
            if (key.startsWith(prefix) && key !== cacheName) await caches.delete(key);
        }
        await self.clients.claim();
    })());
});
self.addEventListener('message', event => {
    if (event.data?.type === 'IEDPU_APPLY_UPDATE' && event.source?.url && new URL(event.source.url).origin === scope.origin) {
        event.waitUntil(self.skipWaiting());
    }
});
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== scope.origin || url.search) return;
    if (request.mode === 'navigate' && publicNavigations.has(url.href)) {
        // Never cache server-rendered HTML, even on a public route.
        event.respondWith(fetch(request).catch(async () => {
            const cache = await caches.open(cacheName);
            const offline = await cache.match(absolute('offline.html'));
            if (!offline) return new Response('Portal offline. Reconnect and try again.', {status:503, headers:{'Content-Type':'text/plain; charset=utf-8'}});
            // Relative links in the fallback must resolve against its actual URL.
            return Response.redirect(absolute('offline.html'), 302);
        }));
        return;
    }
    if (!allowed.has(url.href)) return;
    // Only exact, public static files may be served from our installed cache.
    event.respondWith((async () => {
        const cache = await caches.open(cacheName);
        return (await cache.match(url.href)) || fetch(request);
    })());
});
