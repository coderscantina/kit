import type { EchoLike } from '@kit/reactive-vue'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

import { api } from '~/api'
import { getXsrfHeaders } from '~/lib/csrf'
import { runtimeConfig } from '~/lib/runtime-config'

declare global {
  interface Window {
    Echo?: Echo<'reverb'>
    Pusher?: typeof Pusher
  }
}

/**
 * Null when realtime is off. The reactive client accepts null and works
 * over HTTP alone, so a self-hosted install without Reverb is a legitimate
 * state, not an error.
 */
export function createEcho(): EchoLike | null {
  const config = runtimeConfig.echo

  if (!config) return null

  window.Pusher = Pusher

  const echo = new Echo({
    broadcaster: 'reverb',
    key: config.key,
    wsHost: config.wsHost,
    wsPort: config.wsPort,
    wssPort: config.wssPort,
    forceTLS: config.forceTLS,
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel: { name: string }) => ({
      authorize: (
        socketId: string,
        callback: (
          error: Error | null,
          data: { auth: string; channel_data?: string } | null
        ) => void
      ) => {
        api.client
          .ensureCsrfCookie()
          .then(() =>
            fetch(`${runtimeConfig.apiBaseUrl}/broadcasting/auth`, {
              method: 'POST',
              credentials: 'include',
              headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...getXsrfHeaders(),
              },
              body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }),
            })
          )
          .then(async (response) => {
            if (!response.ok) throw new Error(`Channel auth failed: ${response.status}`)
            callback(null, (await response.json()) as { auth: string; channel_data?: string })
          })
          .catch((error: unknown) =>
            callback(error instanceof Error ? error : new Error('Authorization failed'), null)
          )
      },
    }),
  })

  window.Echo = echo

  return echo as unknown as EchoLike
}
