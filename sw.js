/* ============================================================================
   LLEGAPP 3 · Service Worker
   Estrategia: network-first para páginas y API; cache-first para estáticos.
   ============================================================================ */
const CACHE = 'llegapp3-v3.0.0';

const ESTATICOS = [
    '/LLEGAPP3/assets/css/styles.css',
    '/LLEGAPP3/assets/js/app.js',
    '/LLEGAPP3/assets/img/favicon.svg',
    '/LLEGAPP3/assets/img/logo.svg',
    '/LLEGAPP3/offline.php',
];

const TILES = 'https://basemaps.cartocdn.com';
const LEAFLET = ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'];

self.addEventListener('install', e => {
    e.waitUntil(
        caches.open(CACHE).then(async cache => {
            await Promise.allSettled([...ESTATICOS, ...LEAFLET].map(u => cache.add(u)));
        }).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', e => {
    e.waitUntil(
        caches.keys().then(claves =>
            Promise.all(claves.filter(c => c !== CACHE).map(c => caches.delete(c)))
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', e => {
    const url = new URL(e.request.url);

    // Solo GET
    if (e.request.method !== 'GET') return;

    // API → siempre red, sin cachear (datos en vivo)
    if (url.pathname.includes('/api/') || url.pathname.includes('posiciones')) {
        return;
    }

    // Tiles del mapa → cache-first con límite implícito
    if (url.origin === TILES || url.hostname.includes('cartocdn')) {
        e.respondWith(
            caches.match(e.request).then(hit => hit || fetch(e.request).then(resp => {
                const copia = resp.clone();
                caches.open(CACHE).then(c => c.put(e.request, copia));
                return resp;
            }))
        );
        return;
    }

    // Resto → network-first con fallback a caché y página offline
    e.respondWith(
        fetch(e.request)
            .then(resp => {
                const copia = resp.clone();
                caches.open(CACHE).then(c => c.put(e.request, copia));
                return resp;
            })
            .catch(() =>
                caches.match(e.request).then(hit =>
                    hit || (e.request.mode === 'navigate' ? caches.match('/LLEGAPP3/offline.php') : Response.error())
                )
            )
    );
});
