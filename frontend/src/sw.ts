/// <reference lib="webworker" />
import { precacheAndRoute } from 'workbox-precaching';
import { registerRoute } from 'workbox-routing';
import { NetworkFirst } from 'workbox-strategies';

declare const self: ServiceWorkerGlobalScope;

// Injected at build time by vite-plugin-pwa's `injectManifest` strategy - the app shell's
// static assets, so the installed PWA opens without a network round-trip.
precacheAndRoute(self.__WB_MANIFEST);

// ProjectPlan.md §15.3/§21.2: "the currently-cached week's schedule remains viewable
// offline" - scoped to only this one endpoint, not the whole API surface. A short network
// timeout falls back to whatever was last cached rather than hanging.
registerRoute(
  ({ url }) => url.pathname === '/api/v1/me/schedule',
  new NetworkFirst({
    cacheName: 'employee-schedule',
    networkTimeoutSeconds: 3,
  }),
);

interface SchedulePushPayload {
  title: string;
  body: string;
  url: string;
}

// ProjectPlan.md §20/§21.2: displays a system notification when a ScheduleChanged push
// arrives. The payload shape is produced by the backend's WebPushChannel (§8h).
self.addEventListener('push', (event) => {
  if (!event.data) return;

  let payload: SchedulePushPayload;
  try {
    payload = event.data.json();
  } catch {
    return;
  }

  event.waitUntil(
    self.registration.showNotification(payload.title, {
      body: payload.body,
      icon: '/icons/icon-192.png',
      data: { url: payload.url },
    }),
  );
});

// Opens the app to the affected week when the notification is tapped.
self.addEventListener('notificationclick', (event) => {
  event.notification.close();

  const url = (event.notification.data as { url?: string } | undefined)?.url ?? '/';

  event.waitUntil(self.clients.openWindow(url));
});
