/**
 * Push handling for the generated service worker.
 *
 * Workbox writes `sw.js` from the build, so these handlers cannot live in it;
 * `workbox.importScripts` in vite.config.ts pulls this file in instead. It is
 * served from /public unhashed and unbundled, so keep it plain and small.
 *
 * The payload is what App\Notifications\* sends through the webpush channel:
 * `{ title, body, icon, badge, tag, data: { url } }`.
 */

self.addEventListener('push', (event) => {
  if (!event.data) return

  let payload = {}

  try {
    payload = event.data.json()
  } catch {
    payload = { title: self.registration.scope, body: event.data.text() }
  }

  const title = payload.title || 'Notification'

  event.waitUntil(
    self.registration.showNotification(title, {
      body: payload.body,
      icon: payload.icon || '/icons/icon-192.png',
      badge: payload.badge || '/icons/icon-192.png',
      tag: payload.tag,
      data: payload.data || {},
      requireInteraction: false,
    })
  )
})

self.addEventListener('notificationclick', (event) => {
  event.notification.close()

  const target = new URL(event.notification.data?.url || '/', self.location.origin)

  // Focus a tab that is already on this origin rather than opening a second
  // one: an installed app has exactly one window, and a duplicate is a bug
  // the user has to clean up.
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clients) => {
      for (const client of clients) {
        if (new URL(client.url).origin !== target.origin) continue
        return client.focus().then(() => client.navigate(target.href))
      }

      return self.clients.openWindow(target.href)
    })
  )
})
