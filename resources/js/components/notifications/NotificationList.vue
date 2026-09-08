<script setup lang="ts">
import NotificationItem from '~/components/notifications/NotificationItem.vue'
import { EmptyState } from '~/components/ui/empty-state'
import { Skeleton } from '~/components/ui/skeleton'
import { useI18n } from '~/plugins/i18n'

/**
 * The rows, and the three things that can be there instead: still loading,
 * nothing yet, or the query failed. Shared by the bell and the page so both
 * say the same thing in each case.
 */
defineProps<{
  notifications: readonly App.Data.NotificationData[]
  loading?: boolean
  failed?: boolean
  compact?: boolean
  emptyTitle: string
  emptyDescription?: string
}>()

const emit = defineEmits<{
  open: [notification: App.Data.NotificationData]
  archive: [notification: App.Data.NotificationData]
  restore: [notification: App.Data.NotificationData]
}>()

const { t } = useI18n()
</script>

<template>
  <div
    v-if="loading"
    class="grid gap-2 p-1"
  >
    <div
      v-for="row in 4"
      :key="row"
      class="flex items-start gap-3 px-3 py-2.5"
    >
      <Skeleton class="size-8 shrink-0 rounded-full" />
      <div class="grid flex-1 gap-1.5">
        <Skeleton class="h-3.5 w-2/3" />
        <Skeleton class="h-3 w-full" />
      </div>
    </div>
  </div>

  <EmptyState
    v-else-if="failed"
    icon="lucide:cloud-alert"
    tone="error"
    :title="t('notifications.error.title')"
    :description="t('notifications.error.description')"
  />

  <EmptyState
    v-else-if="notifications.length === 0"
    icon="lucide:bell-off"
    :title="emptyTitle"
    :description="emptyDescription"
    :class="compact ? 'border-0 py-8' : ''"
  />

  <div
    v-else
    class="grid gap-0.5 p-1"
  >
    <NotificationItem
      v-for="notification in notifications"
      :key="notification.id"
      :notification="notification"
      :compact="compact"
      @open="emit('open', $event)"
      @archive="emit('archive', $event)"
      @restore="emit('restore', $event)"
    />
  </div>
</template>
