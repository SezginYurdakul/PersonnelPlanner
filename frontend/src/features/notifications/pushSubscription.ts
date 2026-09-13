import { registerPushSubscription } from './api';

/**
 * PushManager.subscribe() needs the VAPID public key as a raw Uint8Array, not the
 * base64url string the backend/env carries it as.
 */
function urlBase64ToUint8Array(base64String: string): Uint8Array {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = window.atob(base64);
  return Uint8Array.from([...rawData].map((char) => char.charCodeAt(0)));
}

export function isPushSupported(): boolean {
  return 'serviceWorker' in navigator && 'PushManager' in window;
}

/**
 * Requests notification permission and, if granted, subscribes to push and registers the
 * subscription with the backend (ProjectPlan.md §20.2a/§21.2). Called from a deliberate
 * user action (a settings toggle, or the once-after-first-login prompt) - never on page
 * load, per standard Web Push UX guidance.
 */
export async function enablePushNotifications(): Promise<'granted' | 'denied' | 'unsupported'> {
  if (!isPushSupported()) {
    return 'unsupported';
  }

  const permission = await Notification.requestPermission();
  if (permission !== 'granted') {
    return 'denied';
  }

  const vapidPublicKey = import.meta.env.VITE_VAPID_PUBLIC_KEY as string | undefined;
  if (!vapidPublicKey) {
    return 'unsupported';
  }

  const registration = await navigator.serviceWorker.ready;
  const subscription = await registration.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: urlBase64ToUint8Array(vapidPublicKey).buffer as ArrayBuffer,
  });

  await registerPushSubscription(subscription.toJSON() as PushSubscriptionJSON);

  return 'granted';
}
