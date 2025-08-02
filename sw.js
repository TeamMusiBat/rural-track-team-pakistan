const CACHE_NAME = 'smartort-v1.0.3';
const urlsToCache = [
    '/',
    '/dashboard.php',
    '/index.php',
    '/admin.php',
    '/style.css',
    'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
];

// Install event
self.addEventListener('install', function(event) {
    console.log('Service Worker installing...');
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(function(cache) {
                console.log('Opened cache');
                return cache.addAll(urlsToCache);
            })
    );
    self.skipWaiting();
});

// Fetch event
self.addEventListener('fetch', function(event) {
    if (event.request.url.includes('update_location.php') ||
        event.request.url.includes('get_locations.php') ||
        event.request.url.includes('get_address.php') ||
        event.request.url.includes('logout.php') ||
        event.request.method !== 'GET') {
        return fetch(event.request);
    }
    event.respondWith(
        caches.match(event.request)
            .then(function(response) {
                if (response) {
                    return response;
                }
                return fetch(event.request, {
                    redirect: 'follow',
                    credentials: 'same-origin'
                }).then(function(response) {
                    if (!response || response.status !== 200 || response.type !== 'basic') {
                        return response;
                    }
                    const responseToCache = response.clone();
                    caches.open(CACHE_NAME)
                        .then(function(cache) {
                            cache.put(event.request, responseToCache);
                        });
                    return response;
                }).catch(function(error) {
                    console.log('Fetch failed:', error);
                    return caches.match('/index.php') || new Response('Offline');
                });
            })
    );
});

// Activate event
self.addEventListener('activate', function(event) {
    console.log('Service Worker activating...');
    event.waitUntil(
        caches.keys().then(function(cacheNames) {
            return Promise.all(
                cacheNames.map(function(cacheName) {
                    if (cacheName !== CACHE_NAME) {
                        console.log('Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    return self.clients.claim();
});

// Message event
self.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

console.log('Service Worker script loaded');

// Background Sync for location updates
self.addEventListener('sync', function(event) {
    if (event.tag === 'location-sync') {
        event.waitUntil(sendPendingLocationUpdates());
    }
});

// Send pending location updates to server
async function sendPendingLocationUpdates() {
    try {
        const clientsArr = await self.clients.matchAll({ includeUncontrolled: true });
        for (const client of clientsArr) {
            client.postMessage({ type: 'SYNC_START' });
        }
        // Try to get updates from localStorage via client message
        let pendingUpdates = [];
        if (clientsArr.length > 0) {
            // Ask first client for pending updates
            clientsArr[0].postMessage({ type: 'GET_PENDING_UPDATES' });
            // Listen for response (one-time)
            self.addEventListener('message', function handler(event) {
                if (event.data && event.data.type === 'PENDING_UPDATES') {
                    pendingUpdates = event.data.updates || [];
                    self.removeEventListener('message', handler);
                }
            });
        }
        // Fallback: try IndexedDB or empty
        if (!pendingUpdates || pendingUpdates.length === 0) {
            pendingUpdates = []; // If you use IndexedDB, read from there
        }
        for (const update of pendingUpdates) {
            try {
                await fetch('/update_location.php', {
                    method: 'POST',
                    body: JSON.stringify(update),
                    headers: { 'Content-Type': 'application/json' }
                });
            } catch (e) {
                // Leave for next sync
            }
        }
        for (const client of clientsArr) {
            client.postMessage({ type: 'SYNC_DONE' });
        }
    } catch (err) {
        console.error('Background sync failed:', err);
    }
}
