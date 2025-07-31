
const CACHE_NAME = 'smartort-v1';
const urlsToCache = [
    '/',
    'dashboard.php',
    'admin.php',
    'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css'
];

// Install service worker
self.addEventListener('install', function(event) {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(function(cache) {
                console.log('Cache opened');
                return cache.addAll(urlsToCache);
            })
    );
});

// Fetch event
self.addEventListener('fetch', function(event) {
    event.respondWith(
        caches.match(event.request)
            .then(function(response) {
                // Return cached version or fetch from network
                return response || fetch(event.request);
            }
        )
    );
});

// Background sync for location updates
self.addEventListener('sync', function(event) {
    if (event.tag === 'background-location-sync') {
        event.waitUntil(syncLocationData());
    }
});

// Handle background location sync
async function syncLocationData() {
    try {
        // Get failed location updates from IndexedDB or localStorage
        const failedUpdates = await getFailedLocationUpdates();
        
        for (const update of failedUpdates) {
            try {
                const formData = new FormData();
                formData.append('action', 'update_location');
                formData.append('latitude', update.latitude);
                formData.append('longitude', update.longitude);
                formData.append('ajax', '1');
                
                const response = await fetch('dashboard.php', {
                    method: 'POST',
                    body: formData
                });
                
                if (response.ok) {
                    console.log('Background sync successful for update:', update);
                    // Remove successful update from failed list
                    await removeFailedLocationUpdate(update);
                }
            } catch (error) {
                console.error('Background sync failed for update:', update, error);
            }
        }
    } catch (error) {
        console.error('Background sync error:', error);
    }
}

// Get failed location updates
async function getFailedLocationUpdates() {
    // Try to get from clients (main thread)
    const clients = await self.clients.matchAll();
    if (clients.length > 0) {
        return new Promise((resolve) => {
            clients[0].postMessage({ type: 'GET_FAILED_UPDATES' });
            
            // Listen for response
            self.addEventListener('message', function(event) {
                if (event.data.type === 'FAILED_UPDATES_RESPONSE') {
                    resolve(event.data.updates || []);
                }
            });
        });
    }
    return [];
}

// Remove failed location update
async function removeFailedLocationUpdate(update) {
    const clients = await self.clients.matchAll();
    if (clients.length > 0) {
        clients[0].postMessage({ 
            type: 'REMOVE_FAILED_UPDATE', 
            update: update 
        });
    }
}

// Handle messages from main thread
self.addEventListener('message', function(event) {
    if (event.data.type === 'LOCATION_UPDATE') {
        // Handle location update from main thread
        const update = event.data.update;
        console.log('Service worker received location update:', update);
        
        // Store for background sync if needed
        event.ports[0].postMessage({ success: true });
    }
});

// Periodic background sync (if supported)
self.addEventListener('periodicsync', function(event) {
    if (event.tag === 'location-sync') {
        event.waitUntil(syncLocationData());
    }
});
