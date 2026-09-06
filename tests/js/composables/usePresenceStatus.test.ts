import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'

import { channels, member, resetEcho, setRealtime } from '../support/echo'
import { withSetup } from '../support/harness'

vi.mock('~/lib/reactive', async () => ({
  echo: (await import('../support/echo')).fakeEcho,
  reactive: {},
}))

vi.mock('~/composables/useAuth', () => ({
  useAuth: () => ({ user: ref({ id: 'me', name: 'Me' }) }),
}))

const { usePresenceStatus } = await import('~/composables/usePresenceStatus')

describe('usePresenceStatus', () => {
  beforeEach(resetEcho)

  it('reads online off the roster and offline off its absence', () => {
    const away = ref<string[]>([])
    const { result, unmount } = withSetup(() =>
      usePresenceStatus('board', { unavailable: () => away.value })
    )

    channels[0]?.here([member('me', 'Me'), member('ada', 'Ada')])

    expect(result.statusOf('ada')).toBe('online')
    expect(result.statusOf('grace')).toBe('offline')

    channels[0]?.leaving(member('ada', 'Ada'))
    expect(result.statusOf('ada')).toBe('offline')

    unmount()
  })

  it('takes unavailable from the caller and never from the roster', () => {
    const away = ref(['ada'])
    const { result, unmount } = withSetup(() =>
      usePresenceStatus('board', { unavailable: () => away.value })
    )

    channels[0]?.here([member('me', 'Me'), member('ada', 'Ada')])

    expect(result.statusOf('ada')).toBe('unavailable')

    away.value = []
    expect(result.statusOf('ada')).toBe('online')

    unmount()
  })

  it('claims nothing with realtime off', () => {
    setRealtime(false)

    const { result, unmount } = withSetup(() => usePresenceStatus('board'))

    expect(result.supported).toBe(false)
    expect(result.statusOf('ada')).toBeNull()

    unmount()
  })
})
