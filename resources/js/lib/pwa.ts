import { registerSW } from 'virtual:pwa-register'

/**
 * Registers the service worker the build emitted and reports when a newer
 * one is waiting. With the PWA plugin disabled (dev without VITE_PWA=1) the
 * virtual module is a no-op, so this is safe to call unconditionally.
 *
 * Checks for a new worker every hour while a tab stays open, on top of the
 * check the browser does on navigation: an installed app is rarely navigated
 * away from, so without the timer it could sit on an old build for days.
 */
export function registerServiceWorker(onNeedRefresh: (reload: () => void) => void): void {
  const HOUR = 60 * 60 * 1000

  const update = registerSW({
    onNeedRefresh: () => onNeedRefresh(() => void update(true)),
    onRegisteredSW: (_url, registration) => {
      if (!registration) return
      setInterval(() => void registration.update(), HOUR)
    },
  })
}
