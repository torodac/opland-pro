const CACHE = 'estudio-pwa-v1';
const ASSETS = [
  '/estudio/index.html',
  '/estudio/app.js',
  '/estudio/manifest.json',
];

self.addEventListener('install', (e) => {
  self.skipWaiting();
  e.waitUntil(
    caches.open(CACHE).then((c) => Promise.allSettled(ASSETS.map((a) => c.add(a))))
  );
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
  );
  self.clients.claim();
});

self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);

  // API: siempre red (los datos no se sirven desde caché)
  if (url.pathname.startsWith('/api/')) return;

  // Assets: network-first para no servir JS/HTML obsoleto tras un deploy; caché si no hay red
  e.respondWith(
    fetch(e.request)
      .then((res) => {
        const copy = res.clone();
        caches.open(CACHE).then((c) => c.put(e.request, copy));
        return res;
      })
      .catch(() => caches.match(e.request))
  );
});
