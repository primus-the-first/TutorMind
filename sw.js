// Without these, an updated service worker sits "waiting" until every open
// tab/installed window of the app is fully closed before it takes over —
// meaning code changes don't show up until the app is force-quit and
// reopened. skipWaiting()/clients.claim() make a new version activate and
// take control immediately instead.
self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(clients.claim());
});

self.addEventListener('push', event => {
  const data = event.data ? event.data.json() : {};
  event.waitUntil(self.registration.showNotification(data.title || 'TutorMind', {
    body: data.body || '',
    icon: 'assets/icons/icon-192.png'
  }));
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  event.waitUntil(clients.openWindow('tutor_mysql.php'));
});
