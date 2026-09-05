import { describe, expect, it, vi } from 'vitest'

import { createConnectionMonitor } from '../src/connection'
import type { EchoLike } from '../src/types'

type StateChange = (states: { previous: string; current: string }) => void

const fakeEcho = () => {
  let handler: StateChange | undefined
  const echo: EchoLike = {
    private: () => ({ listen: () => undefined, stopListening: () => undefined }),
    leave: () => undefined,
    connector: {
      pusher: {
        connection: {
          state: 'connected',
          bind: (_event, callback) => {
            handler = callback
          },
          unbind: () => {
            handler = undefined
          },
        },
      },
    },
  }
  return { echo, fire: (previous: string, current: string) => handler?.({ previous, current }) }
}

describe('createConnectionMonitor', () => {
  it('reports none without echo and never fires', () => {
    const monitor = createConnectionMonitor(null)
    const spy = vi.fn()
    monitor.onReconnected(spy)

    expect(monitor.state.value).toBe('none')
    expect(spy).not.toHaveBeenCalled()
  })

  it('arms on connected → connecting and fires on the next connected, once', () => {
    const { echo, fire } = fakeEcho()
    const monitor = createConnectionMonitor(echo)
    const spy = vi.fn()
    monitor.onReconnected(spy)

    // A fast drop never reaches `unavailable`.
    fire('connected', 'connecting')
    expect(monitor.state.value).toBe('connecting')
    fire('connecting', 'connected')
    expect(spy).toHaveBeenCalledTimes(1)

    // Staying connected does not re-fire.
    fire('connected', 'connected')
    expect(spy).toHaveBeenCalledTimes(1)
  })

  it('does not fire for the initial connection', () => {
    const { echo, fire } = fakeEcho()
    const monitor = createConnectionMonitor(echo)
    const spy = vi.fn()
    monitor.onReconnected(spy)

    fire('initialized', 'connecting')
    fire('connecting', 'connected')

    expect(spy).not.toHaveBeenCalled()
  })

  it('maps failed and initialized to disconnected and stops after dispose', () => {
    const { echo, fire } = fakeEcho()
    const monitor = createConnectionMonitor(echo)
    const spy = vi.fn()
    monitor.onReconnected(spy)

    fire('connected', 'failed')
    expect(monitor.state.value).toBe('disconnected')

    monitor.dispose()
    fire('failed', 'connected')
    expect(spy).not.toHaveBeenCalled()
  })
})
