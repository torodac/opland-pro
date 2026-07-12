const CACHE = 'vm-pwa-v3';
const ASSETS = [
  '/pwa/',
  '/pwa/app.js',
  '/pwa/manifest.json',
];

self.addEventListener('install', e => {
  self.skipWaiting();
  e.waitUntil(
    caches.open(CACHE).then(c =>
      Promise.allSettled(ASSETS.map(a => c.add(a)))
    )
  );
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

  // API: siempre red
  if (url.pathname.startsWith('/api/')) {
    return;
  }

  // Assets: network-first (evita servir JS/HTML obsoleto tras un deploy),
  // con caché como fallback solo si no hay red.
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

self.addEventListener('push', e => {
  let data = {};
  try { data = e.data ? e.data.json() : {}; } catch {}
  e.waitUntil(
    self.registration.showNotification(data.title || 'VacationMarbella', {
      body: data.body || '',
      icon: '/pwa/icon-192.png',
      badge: '/pwa/icon-192.png',
      data: { url: data.url || '/pwa/index.html' },
    })
  );
});

self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = e.notification.data?.url || '/pwa/index.html';
  e.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
      for (const client of list) {
        if (client.url.includes('/pwa/') && 'focus' in client) {
          client.postMessage({ type: 'navigate', url });
          return client.focus();
        }
      }
      return clients.openWindow(url);
    })
  );
});
