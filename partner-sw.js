/* partner-sw.js — Service Worker der Partner-App (26.09.2026).

   Nur zwei Aufgaben: Hinweise anzeigen und beim Tippen die Partnerseite
   öffnen. Kein Zwischenspeicher für Seiten -- die Partnerseite zeigt Geld,
   und veraltete Zahlen aus einem Cache wären schlimmer als keine Seite.

   Liegt im Wurzelverzeichnis, weil ein Service Worker nur Seiten unter
   seinem eigenen Pfad steuern darf; registriert wird er mit dem engen
   Bereich /partner.php. */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()));
/* Ein Fetch-Handler, der nichts abfaengt: Manche Android-Browser (und aeltere
   Chrome-Fassungen) bieten „App installieren“ nur an, wenn es einen gibt, und
   legen sonst nur ein Lesezeichen mit Browser-Symbol ab (Uwe, 26.09.2026:
   „wird nicht als App auf dem Handy hinterlegt“). Ohne respondWith geht jede
   Anfrage wie immer ans Netz -- nichts wird zwischengespeichert. */
self.addEventListener('fetch', () => {});

self.addEventListener('push', (e) => {
  let d = {};
  try { d = e.data ? e.data.json() : {}; } catch { d = { text: e.data ? e.data.text() : '' }; }
  e.waitUntil(self.registration.showNotification(d.titel || 'Vecom Design', {
    body: d.text || '',
    icon: '/assets/img/app-icon-192.png',
    badge: '/assets/img/app-icon-192.png',
    data: { link: d.link || '/partner.php' },
    lang: d.lang || undefined,
  }));
});

self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const ziel = (e.notification.data && e.notification.data.link) || '/partner.php';
  e.waitUntil((async () => {
    const offen = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const c of offen) {
      if (c.url.includes('/partner.php') && 'focus' in c) { await c.navigate(ziel).catch(() => {}); return c.focus(); }
    }
    return self.clients.openWindow(ziel);
  })());
});
