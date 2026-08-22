const CACHE = 'mb-asamblea-v4';
const ASSETS = [
  '/mb/manifest.json',
  '/mb/icon-192.png',
  '/mb/icon-512.png',
  '/vendor/html5-qrcode.min.js',
];

self.addEventListener('install', e => {
  self.skipWaiting();
  e.waitUntil(
    caches.open(CACHE).then(c => Promise.allSettled(ASSETS.map(a => c.add(a))))
  );
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
  );
  self.clients.claim();
});

self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);

  // Datos dinámicos (reparto y recuento): siempre red, nunca caché.
  if (/\/asamblea\/reparto\/(vivienda|buscar-nombre|hoja)/.test(url.pathname)) {
    return;
  }
  if (/\/asamblea\/recuento\/(voto|tallies)/.test(url.pathname)) {
    return;
  }
  if (/\/gestion_mb\/(tarjeta|registro)/.test(url.pathname)) {
    return;
  }

  // Resto (página, manifest, iconos, librería QR): red primero, caché como respaldo sin conexión.
  e.respondWith(
    fetch(e.request)
      .then(res => {
        const copy = res.clone();
        caches.open(CACHE).then(c => c.put(e.request, copy));
        return res;
      })
      .catch(() => caches.match(e.request))
  );
});
