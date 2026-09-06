type Member = App.Data.PresenceMemberData

/** Enough of a laravel-echo presence channel to drive the callbacks by hand. */
export class FakeChannel {
  here: (members: Member[]) => void = () => {}
  joining: (member: Member) => void = () => {}
  leaving: (member: Member) => void = () => {}
  failed: () => void = () => {}
  whispers: Array<[string, unknown]> = []
  listeners = new Map<string, Set<(payload: never) => void>>()

  constructor(public readonly name: string) {}

  emitWhisper(event: string, payload: unknown): void {
    for (const listener of this.listeners.get(event) ?? [])
      (listener as (p: unknown) => void)(payload)
  }
}

/** Every channel joined so far, in join order, and every channel left. */
export const channels: FakeChannel[] = []
export const left: string[] = []

const join = (name: string) => {
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
    error(callback: () => void) {
      channel.failed = callback
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

/**
 * The object `~/lib/reactive` exports when realtime is on. `join` is what
 * `usePresence` checks for support, so dropping it is how a test says the
 * feature is off.
 */
export const fakeEcho: {
  private: () => { listen: () => void; stopListening: () => void }
  join?: typeof join
  leave: (name: string) => void
  connector: object
} = {
  private: () => ({ listen: () => {}, stopListening: () => {} }),
  join,
  leave: (name: string) => void left.push(name),
  connector: {},
}

/** Realtime off: no channel to join, so no roster and no whispers. */
export function setRealtime(on: boolean): void {
  if (on) fakeEcho.join = join
  else delete fakeEcho.join
}

export function resetEcho(): void {
  channels.length = 0
  left.length = 0
  setRealtime(true)
}

export const member = (id: string, name: string): Member => ({
  id,
  name,
  avatarUrl: null,
  color: 'hsl(1 65% 55%)',
})
