// Service Worker for UJAN PWA
// Version: 1.0.14

const CACHE_NAME = 'ujan-v1.0.14';
const RUNTIME_CACHE = 'ujan-runtime-v1.0.14';

// Assets to cache on install
const STATIC_ASSETS = [
  '/',
  '/login',
  '/assets/css/style.css',
  '/assets/css/siswa.css',
  '/assets/js/main.js',
  '/manifest.json'
];

// Install event - cache static assets
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        return cache.addAll(STATIC_ASSETS.map(url => new Request(url, { cache: 'reload' })))
          .catch((error) => {
            console.log('Cache addAll failed:', error);
            // Don't fail installation if some assets fail to cache
            return Promise.resolve();
          });
      })
  );
  self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME && cacheName !== RUNTIME_CACHE) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  return self.clients.claim();
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip cross-origin requests
  if (url.origin !== location.origin) {
    return;
  }

  // Skip API requests (they should always be fresh)
  if (url.pathname.startsWith('/api/')) {
    return;
  }

  // Skip admin/guru routes (they need authentication)
  if (url.pathname.startsWith('/admin/') || 
      url.pathname.startsWith('/guru/') || 
      url.pathname.startsWith('/operator/')) {
    return;
  }

  // For static assets, use cache-first strategy
  if (request.destination === 'style' || 
      request.destination === 'script' || 
      request.destination === 'image' ||
      url.pathname.startsWith('/assets/')) {
    event.respondWith(
      caches.match(request)
        .then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse;
          }
          return fetch(request).then((response) => {
            // Don't cache if not a valid response
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }
            // Clone the response
            const responseToCache = response.clone();
            caches.open(RUNTIME_CACHE)
              .then((cache) => {
                cache.put(request, responseToCache);
              });
            return response;
          });
        })
    );
    return;
  }

  // For pages, use network-first strategy
  event.respondWith(
    fetch(request)
      .then((response) => {
        // Don't cache if not a valid response
        if (!response || response.status !== 200 || response.type !== 'basic') {
          return response;
        }
        // Clone the response
        const responseToCache = response.clone();
        caches.open(RUNTIME_CACHE)
          .then((cache) => {
            cache.put(request, responseToCache);
          });
        return response;
      })
      .catch(() => {
        // Network failed, try cache
        return caches.match(request)
          .then((cachedResponse) => {
            if (cachedResponse) {
              return cachedResponse;
            }
            // If no cache, return offline page
            return caches.match('/offline.html')
              .then((offlineResponse) => {
                return offlineResponse || new Response('Offline', { status: 503 });
              });
          });
      })
  );
});

// Handle push notifications (if implemented)
self.addEventListener('push', (event) => {
  const options = {
    body: event.data ? event.data.text() : 'Notifikasi baru',
    icon: '/assets/images/icon-192x192.png',
    badge: '/assets/images/icon-72x72.png',
    vibrate: [200, 100, 200],
    tag: 'ujan-notification',
    requireInteraction: false
  };

  event.waitUntil(
    self.registration.showNotification('UJAN', options)
  );
});

// Handle notification click
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  event.waitUntil(
    clients.openWindow('/siswa-notifications')
  );
});
