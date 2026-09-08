<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import Icon from '~/components/Icon.vue'
import NotificationList from '~/components/notifications/NotificationList.vue'
import { Button } from '~/components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '~/components/ui/popover'
import { ScrollArea } from '~/components/ui/scroll-area'
import { useNotificationInbox, useNotificationSummary } from '~/composables/useNotifications'
import { useI18n } from '~/plugins/i18n'

/**
 * The bell in the header.
 *
 * The badge is subscribed all the time; the list only once the popover has
 * been opened, and it stays subscribed afterwards so re-opening is instant.
 * That is the whole reason the count is its own query: mounting this on every
 * screen costs two numbers, not a page of rows.
 *
 * Opening the popover does not mark anything seen. Seeing is what stops the
 * escalation ladder, so it has to mean the person actually looked at the
 * notification rather than at a panel that happened to contain it.
 */
const { t } = useI18n()

const open = ref(false)
const opened = ref(false)

const summary = useNotificationSummary()
const inbox = useNotificationInbox(null)

watch(open, (isOpen) => {
  if (isOpen) opened.value = true
})

/** The bell shows the newest handful; the page shows the rest. */
const recent = computed(() => inbox.items.value.slice(0, 8))

const badge = computed(() => (summary.unseen.value > 9 ? '9+' : String(summary.unseen.value)))

const markAllSeen = async () => {
  await inbox.markSeen()
}

const archive = async (notification: App.Data.NotificationData) => {
  await inbox.archive([notification.id])
}

const openNotification = async (notification: App.Data.NotificationData) => {
  open.value = false

  if (notification.status === 'unseen') await inbox.markSeen([notification.id])
}
</script>

<template>
  <Popover v-model:open="open">
    <PopoverTrigger>
      <Button
        variant="ghost"
        size="icon"
        class="relative"
        :aria-label="t('notifications.bell.label', { count: summary.unseen.value })"
      >
        <Icon :name="summary.unseen.value > 0 ? 'lucide:bell-dot' : 'lucide:bell'" />
        <span
          v-if="summary.unseen.value > 0"
          class="absolute -top-0.5 -right-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-accent px-1 text-2xs font-semibold text-accent-foreground tabular-nums"
        >
          {{ badge }}
        </span>
      </Button>
    </PopoverTrigger>

    <PopoverContent
      align="end"
      class="w-[22rem] max-w-[calc(100vw-1.5rem)] p-0"
    >
      <div class="flex items-center justify-between gap-2 border-b border-border px-3 py-2">
        <p class="text-sm font-semibold text-primary">{{ t('notifications.title') }}</p>
        <Button
          v-if="summary.unseen.value > 0"
          variant="ghost"
          size="xs"
          :disabled="inbox.busy.value"
          @click="markAllSeen"
        >
          {{ t('notifications.actions.markAllSeen') }}
        </Button>
      </div>

      <ScrollArea class="max-h-[24rem]">
        <NotificationList
          :notifications="recent"
          :loading="opened && inbox.isPending.value"
          :failed="inbox.isError.value"
          compact
          :empty-title="t('notifications.empty.inbox.title')"
          :empty-description="t('notifications.empty.inbox.description')"
          @open="openNotification"
          @archive="archive"
        />
      </ScrollArea>

      <div class="border-t border-border p-1">
        <Button
          as-child
          variant="ghost"
          size="sm"
          class="w-full justify-center"
        >
          <RouterLink
            :to="{ name: 'notifications' }"
            @click="open = false"
          >
            {{ t('notifications.bell.viewAll') }}
            <Icon name="lucide:arrow-right" />
          </RouterLink>
        </Button>
      </div>
    </PopoverContent>
  </Popover>
</template>
