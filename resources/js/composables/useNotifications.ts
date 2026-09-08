import { computed, toValue, type MaybeRefOrGetter } from 'vue'

import { useAuth } from '~/composables/useAuth'
import { useReactiveMutation, useReactiveQuery } from '~/lib/reactive'

/**
 * The inbox, live.
 *
 * Two subscriptions, kept apart on purpose: the badge is mounted on every
 * screen and the list only where it is open, so a delivery pushes two numbers
 * to everyone and fifty rows only to the people looking at them.
 *
 * Nothing here is a shared composable. Two components asking the same
 * question with the same arguments already resolve to one subscription and
 * one server-side computation, so sharing would only add a second cache.
 */

/** The bell. */
export function useNotificationSummary() {
  const { user } = useAuth()

  const userId = computed(() => user.value?.id ?? '')
  const enabled = computed(() => userId.value !== '')

  const query = useReactiveQuery('notifications.summary', () => ({ userId: userId.value }), {
    enabled,
  })

  return {
    ...query,
    unseen: computed(() => query.data.value?.unseen ?? 0),
    total: computed(() => query.data.value?.total ?? 0),
  }
}

/**
 * The list, plus the three ways a person moves rows around in it.
 *
 * `status` is a getter so a tab change re-subscribes rather than re-filtering
 * client-side: the server already knows how to ask each of the three
 * questions, and a filtered subset of one cached page would go stale the
 * moment something new arrived.
 */
export function useNotificationInbox(
  status: MaybeRefOrGetter<App.Enums.NotificationStatus | null> = null
) {
  const { user } = useAuth()

  const userId = computed(() => user.value?.id ?? '')
  const enabled = computed(() => userId.value !== '')

  const query = useReactiveQuery(
    'notifications.list',
    () => ({ userId: userId.value, status: toValue(status) }),
    { enabled, list: true }
  )

  const markSeenMutation = useReactiveMutation('notifications.markSeen')
  const archiveMutation = useReactiveMutation('notifications.archive')
  const restoreMutation = useReactiveMutation('notifications.restore')

  /** No ids means "all of them in the state this acts on", in one round trip. */
  const scope = (ids?: string[]) => ({ userId: userId.value, ids: ids ?? null })

  return {
    ...query,
    items: computed(() => query.data.value ?? []),
    markSeen: (ids?: string[]) => markSeenMutation.mutateAsync(scope(ids)),
    archive: (ids?: string[]) => archiveMutation.mutateAsync(scope(ids)),
    restore: (ids?: string[]) => restoreMutation.mutateAsync(scope(ids)),
    busy: computed(
      () =>
        markSeenMutation.isPending.value ||
        archiveMutation.isPending.value ||
        restoreMutation.isPending.value
    ),
  }
}
