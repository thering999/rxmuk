/* RxMuk service worker: offline app shell.
 * Navigation/index.html: network-first (new deploys arrive, reloadIfStale keeps working).
 * hospitals_db.js, icon, manifest: stale-while-revalidate. Versioned cdnjs / cdn.sheetjs.com scripts: cache-first.
 * Everything else (PHP, /api/, Supabase, uploads/, config json, Authorization, non-GET): untouched. */
const CACHE = 'rxmuk-v3';
// keep in step with the <script> tag in index.html (same URL and SRI hash)
const CDN_XLSX = 'https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js';
const CDN_XLSX_SRI = 'sha384-EnyY0/GSHQGSxSgMwaIPzSESbqoOLSexfnSMN2AP+39Ckmn92stwABZynq1JyzdT';
// index.html loads hospitals_db.js as 'public/hospitals_db.js' when served from the root.
const PRECACHE = [
    './', './index.html', './public/hospitals_db.js', './hospitals_db.js',
    './manifest.webmanifest', './icon.svg', CDN_XLSX
];
const STATIC_FILES = ['/hospitals_db.js', '/icon.svg', '/manifest.webmanifest'];

self.addEventListener('install', event => {
    event.waitUntil((async () => {
        const cache = await caches.open(CACHE);
        await Promise.allSettled(PRECACHE.map(u => cache.add(u === CDN_XLSX ? new Request(u, { mode: 'cors', integrity: CDN_XLSX_SRI }) : u)));
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

function isShell(req) {
    const p = new URL(req.url).pathname;
    return p === '/' || p === '/index.html' || p.endsWith('/index.html');
}

async function networkFirst(req) {
    const cache = await caches.open(CACHE);
    try {
        const res = await fetch(req);
        if (res && res.ok && isShell(req)) cache.put(req, res.clone()).catch(() => {});
        return res;
    } catch (e) {
        const hit = await cache.match(req, { ignoreSearch: true })
            || (req.mode === 'navigate' ? (await cache.match('./index.html') || await cache.match('./')) : undefined);
        if (hit) return hit;
        throw e;
    }
}

async function cacheFirst(req) {
    const cache = await caches.open(CACHE);
    const hit = await cache.match(req);
    if (hit) return hit;
    const res = await fetch(req);
    if (res && res.ok) cache.put(req, res.clone()).catch(() => {});
    return res;
}

// Same-origin static files change without a URL version, so serve cache but refresh it in the background
async function staleWhileRevalidate(req) {
    const cache = await caches.open(CACHE);
    const hit = await cache.match(req);
    const update = fetch(req).then(res => {
        if (res && res.ok) cache.put(req, res.clone()).catch(() => {});
        return res;
    });
    if (hit) { update.catch(() => {}); return hit; }
    return update;
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
        event.respondWith(staleWhileRevalidate(req));
        return;
    }
    if ((url.hostname === 'cdnjs.cloudflare.com' && url.pathname.startsWith('/ajax/libs/'))
        || (url.hostname === 'cdn.sheetjs.com' && /^\/xlsx-\d+\.\d+\.\d+\//.test(url.pathname))) {
        event.respondWith(cacheFirst(req));
    }
});
