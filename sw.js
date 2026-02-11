/* Karaoke OS service worker (minimal). */

const CACHE_NAME = 'karaoke-os-static-v1';

const PRECACHE_URLS = [
  './?r=/',
  './manifest.webmanifest',
  './assets/css/app.css',
  './assets/js/theme.js',
  './assets/js/pwa.js',
  './assets/js/song-actions.js',
  './assets/js/songs-search.js',
  './assets/js/artists-search.js',
  './assets/js/admin-bulk.js',
  './assets/js/flash.js',
  './assets/js/no-right-click.js',
  './assets/img/karaoke_os_icon.png',
  './assets/img/pwa-192.png',
  './assets/img/pwa-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches
      .open(CACHE_NAME)
      .then((cache) => cache.addAll(PRECACHE_URLS))
      .then(() => self.skipWaiting())
      .catch(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    (async () => {
      const keys = await caches.keys();
      await Promise.all(keys.map((k) => (k !== CACHE_NAME ? caches.delete(k) : Promise.resolve())));
      await self.clients.claim();
    })()
  );
});

const shouldCache = (req, url) => {
  if (req.method !== 'GET') return false;
  if (url.origin !== self.location.origin) return false;
  if (url.pathname.startsWith('/assets/')) return true;
  const d = req.destination;
  return d === 'style' || d === 'script' || d === 'image' || d === 'manifest';
};

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);
  if (!shouldCache(req, url)) return;

  event.respondWith(
    caches.match(req).then((cached) => {
      if (cached) return cached;
      return fetch(req)
        .then((res) => {
          if (res && res.ok) {
            caches.open(CACHE_NAME).then((cache) => cache.put(req, res.clone())).catch(() => {});
          }
          return res;
        })
        .catch(() => cached);
    })
  );
});
