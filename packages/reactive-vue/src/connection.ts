import { readonly, ref, type Ref } from 'vue'

import type { ConnectionState, EchoLike } from './types'

export interface ConnectionMonitor {
  state: Readonly<Ref<ConnectionState>>
  /** Fires once per recovered connection. Returns the unsubscribe function. */
  onReconnected(callback: () => void): () => void
  dispose(): void
}

/**
 * Tracks the socket and detects recoveries.
 *
 * The catch-up is armed on `connected → connecting`, not on `unavailable`:
 * a fast drop bounces straight back to connecting and never reaches
 * unavailable, and pushes sent in that gap are gone. Any connected state
 * reached while armed counts as a reconnect.
 */
export const createConnectionMonitor = (echo: EchoLike | null): ConnectionMonitor => {
  const connection = echo?.connector.pusher?.connection ?? null
  const state = ref<ConnectionState>(connection ? normalize(connection.state) : 'none')
  const listeners = new Set<() => void>()
  let armed = false

  const onStateChange = ({ previous, current }: { previous: string; current: string }) => {
    state.value = normalize(current)

    if (previous === 'connected' && current !== 'connected') {
      armed = true
    }

    if (current === 'connected' && armed) {
      armed = false
      for (const listener of listeners) {
        listener()
      }
    }
  }

  connection?.bind('state_change', onStateChange)

  return {
    state: readonly(state),
    onReconnected: (callback) => {
      listeners.add(callback)
      return () => listeners.delete(callback)
    },
    dispose: () => {
      connection?.unbind('state_change', onStateChange)
      listeners.clear()
    },
  }
}

const normalize = (state: string): ConnectionState => {
  switch (state) {
    case 'connected':
    case 'connecting':
    case 'unavailable':
      return state
    case 'disconnected':
    case 'failed':
    case 'initialized':
      return 'disconnected'
    default:
      return 'disconnected'
  }
}
