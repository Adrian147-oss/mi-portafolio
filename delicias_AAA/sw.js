const CACHE_NAME = 'delicias-app-v1.0';
const urlsToCache = [
    '/',
    '/index.php',
    '/facturar.php',
    '/cierre_dia.php',
    '/generar_pdf.php',
    '/combos.php',
    '/editar_productos.php',
    '/styles.css',
    '/style2.css',
    '/config.php',
    '/logo.png',
    '/manifest.json'
];

// Instalar el Service Worker
self.addEventListener('install', function(event) {
event.waitUntil(
    caches.open(CACHE_NAME)
    .then(function(cache) {
        return cache.addAll(urlsToCache);
    })
);
});

// Interceptar solicitudes
self.addEventListener('fetch', function(event) {
event.respondWith(
    caches.match(event.request)
    .then(function(response) {
        // Devuelve el recurso cacheado o haz la petición
        return response || fetch(event.request);
    }
    )
);
});

// Actualizar el Service Worker
self.addEventListener('activate', function(event) {
event.waitUntil(
    caches.keys().then(function(cacheNames) {
    return Promise.all(
        cacheNames.map(function(cacheName) {
        if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
        }
        })
    );
    })
);
});