// Only here so Chromium browsers that still require one (Brave) offer
// "Install". It passes every request straight to the network and caches
// nothing, so the installed app always shows the live system.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => {});
