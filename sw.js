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
