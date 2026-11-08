/* =========================================================================
   TripCosmos PWA Service Worker (Varanasi Pilgrimage & Luxury Tours)
   Version: tc-pwa-v1.0.1
   ========================================================================= */

const CACHE_NAME = 'tc-pwa-v1.0.1';
const OFFLINE_FALLBACK_URL = '/offline.html';

// Critical core assets to pre-cache on install
const PRECACHE_ASSETS = [
  '/',
  OFFLINE_FALLBACK_URL,
  '/wp-content/uploads/2026/07/cropped-tripcosmos-logo-180x180.jpg',
  '/wp-content/uploads/2026/07/cropped-tripcosmos-logo-192x192.jpg',
  '/manifest.webmanifest'
];

// Install: Pre-cache core shell
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(PRECACHE_ASSETS).catch((err) => {
        console.warn('[TripCosmos SW] Precache warning:', err);
      });
    }).then(() => self.skipWaiting())
  );
});

// Activate: Purge obsolete caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.map((key) => {
          if (key !== CACHE_NAME) {
            return caches.delete(key);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Strategy
self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  // Skip non-GET and external domains
  if (req.method !== 'GET') return;

  // Never cache admin, login, checkout, cart, or internal REST APIs
  if (
    url.pathname.includes('/wp-admin') ||
    url.pathname.includes('/wp-login.php') ||
    url.pathname.includes('/cart') ||
    url.pathname.includes('/checkout') ||
    url.pathname.includes('/wc-api') ||
    url.pathname.includes('/wc-ajax') ||
    url.pathname.includes('/tc-agents/v1')
  ) {
    return;
  }

  // 1. HTML Navigation Requests -> Network First, Fallback to Cache, then Offline Page
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req)
        .then((networkRes) => {
          if (networkRes && networkRes.status === 200) {
            const copy = networkRes.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(req, copy));
          }
          return networkRes;
        })
        .catch(async () => {
          const cachedPage = await caches.match(req);
          if (cachedPage) return cachedPage;

          const fallback = await caches.match(OFFLINE_FALLBACK_URL);
          return fallback || new Response('You are offline. Please check your connection.', {
            headers: { 'Content-Type': 'text/plain' }
          });
        })
    );
    return;
  }

  // 2. Static Assets (CSS, JS, Fonts, Images) -> Stale-While-Revalidate
  const isStatic =
    url.pathname.match(/\.(css|js|woff2?|ttf|otf|eot|svg|png|jpe?g|webp|ico)(\?.*)?$/i) &&
    url.origin === self.location.origin;

  if (isStatic) {
    event.respondWith(
      caches.match(req).then((cachedRes) => {
        const fetchPromise = fetch(req)
          .then((networkRes) => {
            if (networkRes && networkRes.status === 200) {
              const copy = networkRes.clone();
              caches.open(CACHE_NAME).then((cache) => cache.put(req, copy));
            }
            return networkRes;
          })
          .catch(() => cachedRes);

        return cachedRes || fetchPromise;
      })
    );
    return;
  }
});
