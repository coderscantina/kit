import { createSharedComposable } from '@vueuse/core'
import { computed, ref } from 'vue'

import { isClient } from '~/lib/env'

/** Chromium's install event. Not in lib.dom, because only Chromium fires it. */
interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
}

const isStandalone = (): boolean =>
  isClient &&
  (window.matchMedia('(display-mode: standalone)').matches ||
    // iOS Safari reports its home-screen mode here, not through display-mode.
    ('standalone' in navigator && Boolean((navigator as { standalone?: boolean }).standalone)))

/**
 * iOS has no install event: the only path is Share → Add to Home Screen, so
 * the UI shows the steps instead of a button that does nothing.
 */
const isIosSafari = (): boolean =>
  isClient &&
  /iphone|ipad|ipod/i.test(navigator.userAgent) &&
  /safari/i.test(navigator.userAgent) &&
  !/crios|fxios/i.test(navigator.userAgent)

const useInstallPromptBase = () => {
  const deferred = ref<BeforeInstallPromptEvent | null>(null)
  const installed = ref(isStandalone())

  if (isClient) {
    window.addEventListener('beforeinstallprompt', (event) => {
      // Keep the browser's own mini-infobar out of the way: the account menu
      // offers the install at a moment the user chose.
      event.preventDefault()
      deferred.value = event as BeforeInstallPromptEvent
    })
    window.addEventListener('appinstalled', () => {
      deferred.value = null
      installed.value = true
    })
  }

  /** True when there is something to offer: a native prompt, or iOS steps. */
  const available = computed(() => !installed.value && (deferred.value !== null || isIosSafari()))

  /** Whether `install()` will open a real prompt. False on iOS, where it cannot. */
  const canPrompt = computed(() => deferred.value !== null)

  const install = async (): Promise<boolean> => {
    const event = deferred.value
    if (!event) return false

    await event.prompt()
    const { outcome } = await event.userChoice
    if (outcome === 'accepted') deferred.value = null

    return outcome === 'accepted'
  }

  return { available, canPrompt, installed, install, isIos: isIosSafari() }
}

/** One listener for the whole app: the event fires once and must be caught once. */
export const useInstallPrompt = createSharedComposable(useInstallPromptBase)
