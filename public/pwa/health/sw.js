const CACHE = 'health-shell-v1';
const SHELL = [
  '/pwa/health/',
  '/pwa/health/index.html',
  '/pwa/health/app.js',
  '/pwa/health/manifest.json',
];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(SHELL)));
  self.skipWaiting();
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);
  // API siempre en red
  if (url.pathname.startsWith('/api/health/')) return;
  // Shell desde cache, fallback a red
  e.respondWith(
    caches.match(e.request).then(cached => cached || fetch(e.request))
  );
});
