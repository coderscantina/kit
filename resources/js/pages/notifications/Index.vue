<script setup lang="ts">
import { computed, ref } from 'vue'

import PageActions from '~/components/app/PageActions.vue'
import Icon from '~/components/Icon.vue'
import NotificationList from '~/components/notifications/NotificationList.vue'
import { Button } from '~/components/ui/button'
import { Card } from '~/components/ui/card'
import { Tabs, TabsList, TabsTrigger } from '~/components/ui/tabs'
import { useConfirm } from '~/composables/useConfirm'
import { useNotificationInbox, useNotificationSummary } from '~/composables/useNotifications'
import { usePageMeta } from '~/composables/usePageMeta'
import { useI18n } from '~/plugins/i18n'

/**
 * The whole inbox: what is still in it, what has not been looked at, and what
 * has been put away.
 *
 * The tab is a subscription argument, not a client-side filter. The server
 * already knows how to ask each of the three questions, and filtering a
 * cached page here would go stale the moment something new arrived.
 */
type Tab = 'inbox' | 'unseen' | 'archived'

const { t } = useI18n()
const { confirm } = useConfirm()

const tab = ref<Tab>('inbox')

const status = computed<App.Enums.NotificationStatus | null>(() =>
  tab.value === 'inbox' ? null : tab.value
)

const summary = useNotificationSummary()
const inbox = useNotificationInbox(status)

usePageMeta(() => ({
  title: t('notifications.title'),
  breadcrumbs: [{ label: t('notifications.title') }],
}))

const archiveAll = async () => {
  const ok = await confirm({
    title: t('notifications.confirm.archiveAll.title'),
    message: t('notifications.confirm.archiveAll.message'),
    confirmLabel: t('notifications.actions.archiveAll'),
  })

  if (ok) await inbox.archive()
}

const open = async (notification: App.Data.NotificationData) => {
  if (notification.status === 'unseen') await inbox.markSeen([notification.id])
}
</script>

<template>
  <div class="grid gap-4">
    <PageActions>
      <Button
        v-if="summary.unseen.value > 0"
        variant="outline"
        size="sm"
        :disabled="inbox.busy.value"
        @click="inbox.markSeen()"
      >
        <Icon name="lucide:mail-check" />
        {{ t('notifications.actions.markAllSeen') }}
      </Button>
      <Button
        v-if="summary.total.value > 0"
        variant="outline"
        size="sm"
        :disabled="inbox.busy.value"
        @click="archiveAll"
      >
        <Icon name="lucide:archive" />
        {{ t('notifications.actions.archiveAll') }}
      </Button>
    </PageActions>

    <Tabs v-model="tab">
      <TabsList>
        <TabsTrigger value="inbox">{{ t('notifications.tabs.inbox') }}</TabsTrigger>
        <TabsTrigger value="unseen">
          {{ t('notifications.tabs.unseen') }}
          <span
            v-if="summary.unseen.value > 0"
            class="ml-1.5 rounded-full bg-accent px-1.5 text-2xs font-semibold text-accent-foreground tabular-nums"
          >
            {{ summary.unseen.value }}
          </span>
        </TabsTrigger>
        <TabsTrigger value="archived">{{ t('notifications.tabs.archived') }}</TabsTrigger>
      </TabsList>
    </Tabs>

    <Card class="p-1">
      <NotificationList
        :notifications="inbox.items.value"
        :loading="inbox.isPending.value"
        :failed="inbox.isError.value"
        :empty-title="t(`notifications.empty.${tab}.title`)"
        :empty-description="t(`notifications.empty.${tab}.description`)"
        @open="open"
        @archive="inbox.archive([$event.id])"
        @restore="inbox.restore([$event.id])"
      />
    </Card>
  </div>
</template>
