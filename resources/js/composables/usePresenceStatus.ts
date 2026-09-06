import { computed, toValue, type MaybeRefOrGetter } from 'vue'

import type { PresenceStatus } from '~/components/ui/avatar'
import { usePresence, type UsePresenceReturn } from '~/composables/usePresence'

export interface UsePresenceStatusOptions {
  /**
   * Ids the application calls unavailable. Presence cannot derive this: away,
   * in a meeting and do not disturb are the app's words, not the roster's.
   */
  unavailable?: MaybeRefOrGetter<Iterable<string> | null | undefined>
}

export interface UsePresenceStatusReturn extends UsePresenceReturn {
  /**
   * The state to hand `UserAvatar`, or null when there is nothing to claim.
   * A list can call this per row without rebuilding the roster filter itself.
   */
  statusOf: (id: string) => PresenceStatus | null
}

/**
 * The presence roster as a status lookup by user id.
 *
 * With realtime off there is no roster, and calling everybody offline would
 * be a lie, so `statusOf` returns null and the indicator renders nothing.
 * What the caller marked unavailable still shows: the app knew that without
 * a socket.
 */
export function usePresenceStatus(
  resource: MaybeRefOrGetter<string | null>,
  options: UsePresenceStatusOptions = {}
): UsePresenceStatusReturn {
  const presence = usePresence(resource)

  const online = computed(() => new Set(presence.members.value.map((member) => member.id)))
  const unavailable = computed(() => new Set(toValue(options.unavailable) ?? []))

  return {
    ...presence,
    statusOf: (id) => {
      if (unavailable.value.has(id)) return 'unavailable'
      if (!presence.supported) return null

      return online.value.has(id) ? 'online' : 'offline'
    },
  }
}
