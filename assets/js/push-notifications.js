/**
 * Web Push subscribe/unsubscribe helpers for the settings modal's
 * "Push Notifications" toggle (see assets/js/settings.js).
 */

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = window.atob(base64);
  const outputArray = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; i++) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

async function subscribeToPush() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    throw new Error('Push notifications are not supported in this browser.');
  }

  await navigator.serviceWorker.register('sw.js');
  const reg = await navigator.serviceWorker.ready;
  const sub = await reg.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: urlBase64ToUint8Array(window.TutorMindPushConfig.vapidPublicKey)
  });

  const response = await fetch('api/push_subscribe.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(sub.toJSON())
  });
  const result = await response.json();
  if (!result.success) {
    throw new Error(result.error || 'Failed to save push subscription.');
  }
}

async function unsubscribeFromPush() {
  const reg = await navigator.serviceWorker.getRegistration();
  if (!reg) return;

  const sub = await reg.pushManager.getSubscription();
  if (!sub) return;

  await fetch('api/push_subscribe.php', {
    method: 'DELETE',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ endpoint: sub.endpoint })
  });

  await sub.unsubscribe();
}
