import { config } from '@vue/test-utils'
import { beforeEach } from 'vitest'

import { i18n } from '~/plugins/i18n'

// `~/lib/runtime-config` reads window.__APP_CONFIG__ once at module load, and
// other modules read it at *their* load. Pinning it here, before any test
// file imports anything, keeps that evaluation deterministic.
window.__APP_CONFIG__ = {
  version: 'test',
  apiBaseUrl: '',
  locale: 'en',
  features: {
    realtime: false,
    registration: true,
    impersonation: true,
    ai: true,
    push: false,
    sms: false,
    social: false,
  },
  echo: null,
  analytics: null,
  socialProviders: [],
  vapidPublicKey: null,
}

// jsdom ships no ResizeObserver; reka-ui primitives construct one on mount.
globalThis.ResizeObserver ??= class {
  observe() {}
  unobserve() {}
  disconnect() {}
} as unknown as typeof ResizeObserver

// jsdom has no matchMedia; anything that reads a media query — the colour
// mode composable, reduced-motion guards — throws without it.
window.matchMedia ??= ((query: string) =>
  ({
    matches: false,
    media: query,
    onchange: null,
    addEventListener: () => {},
    removeEventListener: () => {},
    addListener: () => {},
    removeListener: () => {},
    dispatchEvent: () => false,
  }) as unknown as MediaQueryList) as typeof window.matchMedia

// reka-ui listboxes drive pointer capture and scroll the active option into
// view. jsdom implements none of it, and without these stubs the portalled
// content never opens, so it cannot be asserted at all.
Element.prototype.hasPointerCapture ??= () => false
Element.prototype.setPointerCapture ??= () => {}
Element.prototype.releasePointerCapture ??= () => {}
Element.prototype.scrollIntoView ??= () => {}

// Node installs its own `localStorage` global, undefined without
// --localstorage-file, and because jsdom's window === globalThis that
// shadows jsdom's real implementation. Every storage-backed composable
// would silently degrade to a detached ref. Give them a real backend.
if (!window.localStorage) {
  const store = new Map<string, string>()

  const memoryStorage: Storage = {
    get length() {
      return store.size
    },
    key: (index) => [...store.keys()][index] ?? null,
    getItem: (key) => store.get(key) ?? null,
    setItem: (key, value) => void store.set(key, String(value)),
    removeItem: (key) => void store.delete(key),
    clear: () => store.clear(),
  }

  Object.defineProperty(window, 'localStorage', { value: memoryStorage, configurable: true })
}

beforeEach(() => {
  window.localStorage.clear()
})

// The real i18n instance rather than a `$t` stub: `~/plugins/i18n` exports a
// standalone Composer that works without an app, so composables translate
// for real. Asserting on real copy catches a missing key; a stub would echo it.
config.global.plugins = [i18n]
