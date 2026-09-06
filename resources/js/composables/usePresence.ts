import type { EchoPresenceChannelLike } from '@kit/reactive-vue'
import {
  computed,
  onScopeDispose,
  shallowRef,
  toValue,
  watch,
  type ComputedRef,
  type MaybeRefOrGetter,
  type Ref,
} from 'vue'

import { useAuth } from '~/composables/useAuth'
import { echo } from '~/lib/reactive'

export type PresenceMember = App.Data.PresenceMemberData

/** What a whisper carries on top of what the sender put in it. */
export interface WhisperEnvelope {
  senderId: string
}

export interface UsePresenceReturn {
  /** Everyone on the channel, this session included, ordered by name. */
  members: ComputedRef<PresenceMember[]>
  /** Everyone but this session, which is what a roster usually shows. */
  others: ComputedRef<PresenceMember[]>
  count: ComputedRef<number>
  /** False when realtime is off: the roster is empty and whispers go nowhere. */
  supported: boolean
  /** Set when the channel refused the join, almost always a missing ability. */
  error: ComputedRef<boolean>
  whisper: <T extends object>(event: string, payload: T) => void
  onWhisper: <T extends object>(
    event: string,
    handler: (payload: T & WhisperEnvelope) => void
  ) => void
}

interface ChannelState {
  channel: EchoPresenceChannelLike<PresenceMember>
  members: Ref<PresenceMember[]>
  error: Ref<boolean>
  /** How many callers hold this channel; the last one out leaves it. */
  holders: number
}

/**
 * One Echo channel per resource, however many components ask for it. Two
 * components on the same roster otherwise open the channel twice and the
 * first to unmount takes the other one's subscription down with it.
 */
const channels = new Map<string, ChannelState>()

const byName = (a: PresenceMember, b: PresenceMember): number => a.name.localeCompare(b.name)

const channelName = (resource: string): string => `presence.${resource}`

function acquire(resource: string): ChannelState | null {
  const join = echo?.join?.bind(echo)

  if (!join) return null

  const existing = channels.get(resource)

  if (existing) {
    existing.holders += 1

    return existing
  }

  const members = shallowRef<PresenceMember[]>([])
  const error = shallowRef(false)
  const channel = join(channelName(resource)) as EchoPresenceChannelLike<PresenceMember>

  channel
    .here((roster) => {
      members.value = [...roster].sort(byName)
    })
    .joining((member) => {
      // A reconnect replays `joining` for someone already listed.
      if (members.value.some((current) => current.id === member.id)) return

      members.value = [...members.value, member].sort(byName)
    })
    .leaving((member) => {
      members.value = members.value.filter((current) => current.id !== member.id)
    })
    .error(() => {
      error.value = true
    })

  const state: ChannelState = { channel, members, error, holders: 1 }

  channels.set(resource, state)

  return state
}

function release(resource: string): void {
  const state = channels.get(resource)

  if (!state) return

  state.holders -= 1

  if (state.holders > 0) return

  channels.delete(resource)
  echo?.leave(channelName(resource))
}

/**
 * Who else is on this screen right now, and a back channel to tell them
 * something that is not worth a database write.
 *
 * The resource names the channel and the ability behind it: `usePresence('users')`
 * joins `presence.users`, which `App\Support\Presence` opens to whoever has
 * `users.view`. Pass a getter to follow a route parameter; the old channel is
 * left as the new one is joined.
 *
 * Whispers go client to client through Reverb without touching PHP, so they
 * suit cursors, typing flags and selections: state that is stale the moment
 * the sender disconnects. Anything that has to survive a refresh is a
 * mutation. Reverb accepts a limited number of client events per connection,
 * so throttle a stream of them (`useThrottleFn`) rather than sending on every
 * mousemove.
 */
export function usePresence(resource: MaybeRefOrGetter<string | null>): UsePresenceReturn {
  const { user } = useAuth()
  const state = shallowRef<ChannelState | null>(null)
  const handlers: Array<[string, (payload: never) => void]> = []

  /** The channel this instance is holding, which is not always the current resource. */
  let held: string | null = null

  const bind = (target: ChannelState | null): void => {
    for (const [event, handler] of handlers) target?.channel.listenForWhisper(event, handler)
  }

  const unbind = (target: ChannelState | null): void => {
    for (const [event, handler] of handlers) {
      target?.channel.stopListeningForWhisper(event, handler)
    }
  }

  watch(
    () => toValue(resource),
    (next) => {
      if (next === held) return

      unbind(state.value)

      if (held) release(held)

      held = next
      state.value = next ? acquire(next) : null

      bind(state.value)
    },
    { immediate: true }
  )

  onScopeDispose(() => {
    unbind(state.value)

    if (held) release(held)

    held = null
    state.value = null
  })

  const members = computed(() => state.value?.members.value ?? [])

  return {
    members,
    others: computed(() => members.value.filter((member) => member.id !== user.value?.id)),
    count: computed(() => members.value.length),
    supported: typeof echo?.join === 'function',
    error: computed(() => state.value?.error.value ?? false),
    whisper: (event, payload) => {
      const senderId = user.value?.id

      if (!senderId) return

      state.value?.channel.whisper(event, { ...payload, senderId })
    },
    onWhisper: (event, handler) => {
      const entry: [string, (payload: never) => void] = [event, handler as (payload: never) => void]

      handlers.push(entry)
      state.value?.channel.listenForWhisper(entry[0], entry[1])
    },
  }
}
