/* Service worker: relógio e assets funcionam offline; APIs, anúncios e
   métricas nunca são interceptados. Suba a versão ao publicar mudanças. */

const VERSION = 'hora-v2.0.0';

const PRECACHE = [
    './',
    './index.html',
    './privacidade.html',
    './manifest.webmanifest',
    './assets/favicon.svg',
    './assets/css/style.css',
    './assets/js/main.js',
    './assets/js/format.js',
    './assets/js/i18n.js',
    './assets/js/settings.js',
    './assets/js/theme.js',
    './assets/js/clock.js',
    './assets/js/weather.js',
    './assets/js/consent.js',
];

const BYPASS = /googlesyndication|googletagmanager|google-analytics|doubleclick|adservice\.google|ipgeolocation\.io|openweathermap\.org|hits\.sh/;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(VERSION)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
            .then(() => self.clients.claim()),
    );
});

function cachePut(request, response) {
    const copy = response.clone();
    caches.open(VERSION).then((cache) => cache.put(request, copy));
    return response;
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (BYPASS.test(url.href)) return;

    // Navegação: rede primeiro, cai para o cache quando offline.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((res) => cachePut('./index.html', res))
                .catch(() => caches.match('./index.html', { ignoreSearch: true })),
        );
        return;
    }

    // Fontes do Google: cache primeiro, revalidando em segundo plano.
    if (url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com') {
        event.respondWith(
            caches.match(request).then((hit) => {
                const network = fetch(request).then((res) => cachePut(request, res));
                return hit || network;
            }),
        );
        return;
    }

    // Assets do próprio site: cache primeiro.
    if (url.origin === self.location.origin) {
        event.respondWith(
            caches.match(request).then((hit) => hit || fetch(request).then((res) => cachePut(request, res))),
        );
    }
});
