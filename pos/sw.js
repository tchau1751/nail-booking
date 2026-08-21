/* ============================================================
   Service worker for the salon POS.

   A till must never show stale money. So: pages and the cart API
   are ALWAYS fetched from the network, never served from cache.
   Only the shell assets (CSS, JS, icons) are cached, and only so
   the app opens instantly and survives a brief Wi-Fi drop with a
   clear "you're offline" screen instead of a browser error.
   ============================================================ */
const VERSION = 'pos-v8';
const SCOPE = new URL(self.registration.scope).pathname;   // e.g. /nail-booking/pos/

const SHELL = [
  SCOPE + 'assets/pos.css',
  SCOPE + 'assets/pos.js',
  SCOPE + 'assets/icons/icon-192.png',
  SCOPE + 'assets/icons/icon-512.png',
  SCOPE + 'offline.html',
];

self.addEventListener('install', (ev) => {
  ev.waitUntil(
    caches.open(VERSION)
      // Don't let one missing file block the whole install.
      .then((c) => Promise.allSettled(SHELL.map((u) => c.add(u))))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (ev) => {
  ev.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (ev) => {
  const req = ev.request;
  if (req.method !== 'GET') return;                       // never touch POSTs

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  const isAsset = /\.(css|js|png|jpg|svg|woff2?)$/i.test(url.pathname);

  if (isAsset) {
    // Cache-first, but refresh in the background so a deploy lands next open.
    ev.respondWith(
      caches.match(req).then((hit) => {
        const net = fetch(req).then((res) => {
          if (res && res.status === 200) {
            const copy = res.clone();
            caches.open(VERSION).then((c) => c.put(req, copy));
          }
          return res;
        }).catch(() => hit);
        return hit || net;
      })
    );
    return;
  }

  // Everything else (PHP pages, the cart API) is live-only.
  ev.respondWith(
    fetch(req).catch(() =>
      caches.match(SCOPE + 'offline.html').then(
        (page) => page || new Response('Offline', { status: 503, headers: { 'Content-Type': 'text/plain' } })
      )
    )
  );
});
