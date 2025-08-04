/**
 * Service Worker for Gunayatan Gatepass System PWA
 * Handles caching, offline functionality, and background sync
 */

const CACHE_NAME = 'gunayatan-gatepass-v1.0.0';
const STATIC_CACHE_NAME = 'gunayatan-static-v1.0.0';
const DYNAMIC_CACHE_NAME = 'gunayatan-dynamic-v1.0.0';

// Files to cache for offline functionality
const STATIC_ASSETS = [
    '/',
    '/index.php',
    '/assets/css/style.css',
    '/assets/js/script.js',
    '/assets/img/logo.png',
    '/manifest.json',
    // Bootstrap CSS (CDN)
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css',
    // Font Awesome (CDN)
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css',
    // Bootstrap JS (CDN)
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js'
];

// Install event - cache static assets
self.addEventListener('install', event => {
    console.log('Service Worker: Installing...');
    
    event.waitUntil(
        caches.open(STATIC_CACHE_NAME)
            .then(cache => {
                console.log('Service Worker: Caching static assets...');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => {
                console.log('Service Worker: Static assets cached successfully');
                return self.skipWaiting();
            })
            .catch(error => {
                console.error('Service Worker: Error caching static assets:', error);
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    console.log('Service Worker: Activating...');
    
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        // Delete old caches
                        if (cacheName !== STATIC_CACHE_NAME && cacheName !== DYNAMIC_CACHE_NAME) {
                            console.log('Service Worker: Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('Service Worker: Activated successfully');
                return self.clients.claim();
            })
    );
});

// Fetch event - serve cached content when offline
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Handle different types of requests
    if (url.origin === location.origin) {
        // Same origin requests - use cache-first strategy for static assets
        if (request.url.includes('/assets/') || request.url.includes('.css') || request.url.includes('.js') || request.url.includes('.png') || request.url.includes('.jpg')) {
            event.respondWith(cacheFirstStrategy(request));
        } else {
            // Network-first strategy for dynamic content (PHP pages)
            event.respondWith(networkFirstStrategy(request));
        }
    } else {
        // External requests (CDN) - cache-first strategy
        event.respondWith(cacheFirstStrategy(request));
    }
});

// Cache-first strategy - good for static assets
async function cacheFirstStrategy(request) {
    try {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        const networkResponse = await fetch(request);
        
        // Cache successful responses
        if (networkResponse.status === 200) {
            const cache = await caches.open(DYNAMIC_CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.error('Cache-first strategy failed:', error);
        
        // Return offline fallback if available
        if (request.destination === 'document') {
            return caches.match('/offline.html') || new Response('Offline - Please check your internet connection');
        }
        
        return new Response('Network error occurred', { status: 408 });
    }
}

// Network-first strategy - good for dynamic content
async function networkFirstStrategy(request) {
    try {
        const networkResponse = await fetch(request);
        
        // Cache successful responses
        if (networkResponse.status === 200) {
            const cache = await caches.open(DYNAMIC_CACHE_NAME);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.error('Network-first strategy failed:', error);
        
        // Try to serve from cache
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline fallback
        if (request.destination === 'document') {
            return caches.match('/offline.html') || new Response(`
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Offline - Gunayatan Gatepass</title>
                    <style>
                        body { 
                            font-family: Arial, sans-serif; 
                            text-align: center; 
                            padding: 50px; 
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            color: white;
                            min-height: 100vh;
                            margin: 0;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            flex-direction: column;
                        }
                        .offline-container {
                            background: rgba(255,255,255,0.1);
                            padding: 40px;
                            border-radius: 15px;
                            backdrop-filter: blur(10px);
                        }
                        h1 { color: #ffd700; margin-bottom: 20px; }
                        .retry-btn {
                            background: #ffd700;
                            color: #333;
                            border: none;
                            padding: 12px 30px;
                            border-radius: 8px;
                            font-size: 16px;
                            font-weight: 600;
                            cursor: pointer;
                            margin-top: 20px;
                        }
                        .retry-btn:hover { background: #ffed4e; }
                    </style>
                </head>
                <body>
                    <div class="offline-container">
                        <h1>📱 Gunayatan Gatepass</h1>
                        <h2>🌐 You're Offline</h2>
                        <p>Please check your internet connection and try again.</p>
                        <p>Some cached content may still be available.</p>
                        <button class="retry-btn" onclick="window.location.reload()">🔄 Retry</button>
                    </div>
                </body>
                </html>
            `, {
                headers: { 'Content-Type': 'text/html' }
            });
        }
        
        return new Response('Offline - Content not available', { status: 503 });
    }
}

// Background sync for form submissions when back online
self.addEventListener('sync', event => {
    console.log('Service Worker: Background sync triggered:', event.tag);
    
    if (event.tag === 'gatepass-submission') {
        event.waitUntil(syncGatepassSubmissions());
    }
});

// Handle form submissions when offline
async function syncGatepassSubmissions() {
    try {
        // Get pending submissions from IndexedDB or cache
        const pendingSubmissions = await getPendingSubmissions();
        
        for (const submission of pendingSubmissions) {
            try {
                const response = await fetch(submission.url, {
                    method: 'POST',
                    body: submission.data
                });
                
                if (response.ok) {
                    // Remove from pending submissions
                    await removePendingSubmission(submission.id);
                    console.log('Service Worker: Synced submission:', submission.id);
                }
            } catch (error) {
                console.error('Service Worker: Failed to sync submission:', submission.id, error);
            }
        }
    } catch (error) {
        console.error('Service Worker: Background sync failed:', error);
    }
}

// Placeholder functions for IndexedDB operations
async function getPendingSubmissions() {
    // Implementation would use IndexedDB to store offline form submissions
    return [];
}

async function removePendingSubmission(id) {
    // Implementation would remove the submission from IndexedDB
    console.log('Removing pending submission:', id);
}

// Push notification handling
self.addEventListener('push', event => {
    console.log('Service Worker: Push notification received');
    
    const options = {
        body: event.data ? event.data.text() : 'New gatepass notification',
        icon: '/assets/img/logo.png',
        badge: '/assets/img/logo.png',
        vibrate: [200, 100, 200],
        data: {
            url: '/'
        },
        actions: [
            {
                action: 'view',
                title: 'View',
                icon: '/assets/img/logo.png'
            },
            {
                action: 'dismiss',
                title: 'Dismiss'
            }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification('Gunayatan Gatepass', options)
    );
});

// Handle notification clicks
self.addEventListener('notificationclick', event => {
    event.notification.close();
    
    if (event.action === 'view') {
        const url = event.notification.data.url || '/';
        event.waitUntil(
            clients.openWindow(url)
        );
    }
});

// Handle app installation
self.addEventListener('appinstalled', event => {
    console.log('Service Worker: App installed successfully');
});

// Handle app uninstallation
self.addEventListener('beforeinstallprompt', event => {
    console.log('Service Worker: Before install prompt');
});

console.log('Service Worker: Loaded successfully');
