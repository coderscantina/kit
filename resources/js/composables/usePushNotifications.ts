import { createSharedComposable } from '@vueuse/core'
import { computed, ref } from 'vue'

import { api } from '~/api'
import { isClient } from '~/lib/env'
import { runtimeConfig } from '~/lib/runtime-config'

/**
 * The browser's push subscription for this account, as the account page needs
 * it: is it possible here, has the user been asked, and is this device signed
 * up.
 *
 * Three states have to stay apart, because the recovery differs. The browser
 * may not support push at all (nothing to offer), the user may have denied
 * the permission (only the browser's own site settings can undo that, so the
 * UI has to say so rather than offer a button that silently fails), or the
 * subscription may simply not exist yet.
 */

/**
 * The VAPID key travels as URL-safe base64; PushManager wants the bytes.
 * Typed as ArrayBuffer rather than Uint8Array: `applicationServerKey` rejects
 * a view that might sit on a SharedArrayBuffer.
 */
const decodeVapidKey = (key: string): ArrayBuffer => {
  const padded = (key + '='.repeat((4 - (key.length % 4)) % 4))
    .replace(/-/g, '+')
    .replace(/_/g, '/')
  const binary = window.atob(padded)
  const bytes = new Uint8Array(binary.length)

  for (let index = 0; index < binary.length; index += 1) bytes[index] = binary.charCodeAt(index)

  return bytes.buffer
}

const usePushNotificationsBase = () => {
  const supported =
    isClient &&
    'serviceWorker' in navigator &&
    'PushManager' in window &&
    'Notification' in window &&
    runtimeConfig.vapidPublicKey !== null

  const permission = ref<NotificationPermission>(supported ? Notification.permission : 'default')
  const subscribed = ref(false)
  const busy = ref(false)
  const ready = ref(false)

  /** Denied is the browser's own setting; only its site settings can undo it. */
  const blocked = computed(() => permission.value === 'denied')

  const registration = async (): Promise<ServiceWorkerRegistration | null> => {
    if (!supported) return null

    // `ready` resolves only once a worker controls the page, which is exactly
    // the condition a subscription needs.
    return navigator.serviceWorker.ready
  }

  const refresh = async (): Promise<void> => {
    if (!supported) {
      ready.value = true
      return
    }

    permission.value = Notification.permission
    subscribed.value = Boolean(await (await registration())?.pushManager.getSubscription())
    ready.value = true
  }

  const subscribe = async (): Promise<void> => {
    if (!supported || busy.value) return

    busy.value = true

    try {
      permission.value = await Notification.requestPermission()
      if (permission.value !== 'granted') return

      const worker = await registration()
      if (!worker) return

      const subscription =
        (await worker.pushManager.getSubscription()) ??
        (await worker.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: decodeVapidKey(runtimeConfig.vapidPublicKey ?? ''),
        }))

      const payload = subscription.toJSON()

      await api.account.subscribeToPush({
        endpoint: subscription.endpoint,
        keys: {
          p256dh: payload.keys?.p256dh ?? '',
          auth: payload.keys?.auth ?? '',
        },
      })

      subscribed.value = true
    } finally {
      busy.value = false
    }
  }

  const unsubscribe = async (): Promise<void> => {
    if (busy.value) return

    busy.value = true

    try {
      const subscription = await (await registration())?.pushManager.getSubscription()

      // The server is told first: a subscription this browser has already
      // dropped is an endpoint the server would keep pushing to forever.
      await api.account.unsubscribeFromPush(subscription?.endpoint)
      await subscription?.unsubscribe()

      subscribed.value = false
    } finally {
      busy.value = false
    }
  }

  const sendTest = (): Promise<{ sent: boolean }> => api.account.sendTestPush()

  return {
    supported,
    permission,
    blocked,
    subscribed,
    busy,
    ready,
    refresh,
    subscribe,
    unsubscribe,
    sendTest,
  }
}

/** One source of truth: the settings switch and anything else stay in step. */
export const usePushNotifications = createSharedComposable(usePushNotificationsBase)
