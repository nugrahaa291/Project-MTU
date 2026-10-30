// Service worker aplikasi absensi.
// Aturan penting: permintaan /api/* TIDAK PERNAH disentuh. Absensi harus online karena waktu dan
// lokasi diverifikasi server; antrean offline justru membuka celah memalsukan jam.
const CACHE = 'absen-v1';
const SHELL = ['/absen', '/pwa/style.css', '/pwa/app.js', '/pwa/lib.js', '/pwa/api.js', '/pwa/icons/icon-192.png'];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(CACHE)
            .then((cache) => Promise.allSettled(SHELL.map((url) => cache.add(url))))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;
    if (url.pathname.startsWith('/api/')) return;
    if (!(url.pathname === '/absen' || url.pathname.startsWith('/pwa/'))) return;

    event.respondWith(networkFirst(req));
});

// Online: selalu ambil versi terbaru (dan simpan). Offline: pakai salinan terakhir.
async function networkFirst(req) {
    const cache = await caches.open(CACHE);
    try {
        const res = await fetch(req);
        if (res.ok) cache.put(req, res.clone());
        return res;
    } catch {
        const salinan = (await cache.match(req, { ignoreSearch: true })) || (req.mode === 'navigate' ? await cache.match('/absen') : null);
        return salinan || new Response('Tidak ada koneksi.', { status: 503, headers: { 'Content-Type': 'text/plain; charset=UTF-8' } });
    }
}
