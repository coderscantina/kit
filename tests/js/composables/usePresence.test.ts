import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'

import { withSetup, type Harness } from '../support/harness'

type Member = App.Data.PresenceMemberData

const member = (id: string, name: string): Member => ({
  id,
  name,
  avatarUrl: null,
  color: 'hsl(1 65% 55%)',
})

/** Enough of a laravel-echo presence channel to drive the callbacks by hand. */
class FakeChannel {
  here: (members: Member[]) => void = () => {}
  joining: (member: Member) => void = () => {}
  leaving: (member: Member) => void = () => {}
  whispers: Array<[string, unknown]> = []
  listeners = new Map<string, Set<(payload: never) => void>>()

  constructor(public readonly name: string) {}

  emitWhisper(event: string, payload: unknown): void {
    for (const listener of this.listeners.get(event) ?? [])
      (listener as (p: unknown) => void)(payload)
  }
}

const channels: FakeChannel[] = []
const left: string[] = []

const fakeChannel = (name: string) => {
  const channel = new FakeChannel(name)

  channels.push(channel)

  const api = {
    here(callback: (members: Member[]) => void) {
      channel.here = callback
      return api
    },
    joining(callback: (member: Member) => void) {
      channel.joining = callback
      return api
    },
    leaving(callback: (member: Member) => void) {
      channel.leaving = callback
      return api
    },
    error() {
      return api
    },
    whisper(event: string, payload: unknown) {
      channel.whispers.push([event, payload])
      return api
    },
    listenForWhisper(event: string, callback: (payload: never) => void) {
      const set = channel.listeners.get(event) ?? new Set()

      set.add(callback)
      channel.listeners.set(event, set)

      return api
    },
    stopListeningForWhisper(event: string, callback?: (payload: never) => void) {
      if (callback) channel.listeners.get(event)?.delete(callback)
      else channel.listeners.delete(event)

      return api
    },
  }

  return api
}

vi.mock('~/lib/reactive', () => ({
  echo: {
    private: () => ({ listen: () => {}, stopListening: () => {} }),
    join: (name: string) => fakeChannel(name),
    leave: (name: string) => void left.push(name),
    connector: {},
  },
  reactive: {},
}))

vi.mock('~/composables/useAuth', () => ({
  useAuth: () => ({ user: ref({ id: 'me', name: 'Me' }) }),
}))

const { usePresence } = await import('~/composables/usePresence')

type Presence = ReturnType<typeof usePresence>

const mount = (resource: string): Harness<Presence> => withSetup(() => usePresence(resource))

describe('usePresence', () => {
  beforeEach(() => {
    channels.length = 0
    left.length = 0
  })

  it('joins the channel named after the resource and orders the roster by name', () => {
    const { result, unmount } = mount('board')

    expect(channels[0]?.name).toBe('presence.board')

    channels[0]?.here([member('me', 'Zoe'), member('two', 'Ada')])

    expect(result.members.value.map((entry) => entry.name)).toEqual(['Ada', 'Zoe'])
    expect(result.others.value.map((entry) => entry.id)).toEqual(['two'])
    expect(result.count.value).toBe(2)

    unmount()
  })

  it('adds and drops members without duplicating a replayed join', () => {
    const { result, unmount } = mount('board')

    channels[0]?.here([member('me', 'Me')])
    channels[0]?.joining(member('two', 'Ada'))
    channels[0]?.joining(member('two', 'Ada'))

    expect(result.count.value).toBe(2)

    channels[0]?.leaving(member('two', 'Ada'))

    expect(result.members.value.map((entry) => entry.id)).toEqual(['me'])

    unmount()
  })

  it('shares one channel between callers and leaves it when the last one goes', () => {
    const first = mount('board')
    const second = mount('board')

    expect(channels).toHaveLength(1)

    first.unmount()
    expect(left).toEqual([])

    second.unmount()
    expect(left).toEqual(['presence.board'])
  })

  it('stamps the sender on a whisper and delivers it to a listener', () => {
    const { result, unmount } = mount('board')
    const received: unknown[] = []

    result.onWhisper<{ x: number }>('cursor', (payload) => received.push(payload))
    result.whisper('cursor', { x: 4 })

    expect(channels[0]?.whispers).toEqual([['cursor', { x: 4, senderId: 'me' }]])

    channels[0]?.emitWhisper('cursor', { x: 4, senderId: 'two' })
    expect(received).toEqual([{ x: 4, senderId: 'two' }])

    unmount()
    expect(channels[0]?.listeners.get('cursor')?.size ?? 0).toBe(0)
  })

  it('follows a changing resource onto the next channel', async () => {
    const resource = ref('board')
    const { unmount } = withSetup(() => usePresence(resource))

    resource.value = 'other'
    await Promise.resolve()

    expect(channels.map((channel) => channel.name)).toEqual(['presence.board', 'presence.other'])
    expect(left).toEqual(['presence.board'])

    unmount()
  })
})
