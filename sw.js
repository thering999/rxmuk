/* RxMuk service worker: offline app shell.
 * Navigation/index.html: network-first (new deploys arrive, reloadIfStale keeps working).
 * hospitals_db.js, icon, manifest, versioned cdnjs scripts: cache-first.
 * Everything else (PHP, /api/, Supabase, uploads/, config json, Authorization, non-GET): untouched. */
const CACHE = 'rxmuk-v1';
const CDN_XLSX = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
// index.html loads hospitals_db.js as 'public/hospitals_db.js' when served from the root.
const PRECACHE = [
    './', './index.html', './public/hospitals_db.js', './hospitals_db.js',
    './manifest.webmanifest', './icon.svg', CDN_XLSX
];
const STATIC_FILES = ['/hospitals_db.js', '/icon.svg', '/manifest.webmanifest'];

self.addEventListener('install', event => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE);
        await Promise.allSettled(PRECACHE.map(u => cache.add(u)));
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', event => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)));
        await self.clients.claim();
    })());
});

function isNeverCache(req, url) {
    if (req.method !== 'GET') return true;
    if (req.headers.has('Authorization')) return true;
    if (url.hostname.endsWith('.supabase.co')) return true;
    if (url.pathname.endsWith('.php')) return true;
    if (url.pathname.includes('/api/') || url.pathname.includes('/uploads/')) return true;
    return false;
}

async function networkFirst(req) {
    const cache = await caches.open(CACHE);
    try {
        const res = await fetch(req);
        if (res && res.ok) cache.put(req, res.clone()).catch(() => {});
        return res;
    } catch (e) {
        const hit = await cache.match(req, { ignoreSearch: true })
            || await cache.match('./index.html') || await cache.match('./');
        if (hit) return hit;
        throw e;
    }
}

async function cacheFirst(req) {
    const cache = await caches.open(CACHE);
    const hit = await cache.match(req);
    if (hit) return hit;
    const res = await fetch(req);
    if (res && (res.ok || res.type === 'opaque')) cache.put(req, res.clone()).catch(() => {});
    return res;
}

self.addEventListener('fetch', event => {
    const req = event.request;
    let url;
    try { url = new URL(req.url); } catch { return; }
    if (isNeverCache(req, url)) return;

    const sameOrigin = url.origin === self.location.origin;
    if (req.mode === 'navigate' || (sameOrigin && (url.pathname.endsWith('/index.html') || url.pathname.endsWith('/')))) {
        event.respondWith(networkFirst(req));
        return;
    }
    if (sameOrigin && STATIC_FILES.some(f => url.pathname.endsWith(f))) {
        event.respondWith(cacheFirst(req));
        return;
    }
    if (url.hostname === 'cdnjs.cloudflare.com' && url.pathname.startsWith('/ajax/libs/')) {
        event.respondWith(cacheFirst(req));
    }
});
