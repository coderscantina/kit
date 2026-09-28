<script setup lang="ts">
import Icon from '~/components/Icon.vue'
import { Badge } from '~/components/ui/badge'
import { Button } from '~/components/ui/button'
import { Sheet, SheetContent, SheetHeaderCombined } from '~/components/ui/sheet'
import { Skeleton } from '~/components/ui/skeleton'
import { SimpleTooltip } from '~/components/ui/tooltip'
import WebhookDeliveries from '~/components/webhooks/WebhookDeliveries.vue'
import WebhookDialog from '~/components/webhooks/WebhookDialog.vue'
import { useConfirm } from '~/composables/useConfirm'
import { useFormat } from '~/composables/useFormat'
import { usePageMeta } from '~/composables/usePageMeta'
import { useReactiveMutation, useReactiveQuery } from '~/lib/reactive'
import { useI18n } from '~/plugins/i18n'

/**
 * Where record changes are posted. The list is live, so a receiver that
 * starts failing shows its red badge here without a reload, and the delivery
 * log beside it says why.
 */
const { t } = useI18n()
const { relative } = useFormat()
const { confirm } = useConfirm()

usePageMeta(() => ({ title: t('webhooks.title') }))

const endpoints = useReactiveQuery('webhooks.list')

const editing = ref<App.Data.WebhookEndpointData | null>(null)
const dialogOpen = ref(false)
const logOf = ref<App.Data.WebhookEndpointData | null>(null)
const logOpen = computed({
  get: () => logOf.value !== null,
  set: (open: boolean) => {
    if (!open) logOf.value = null
  },
})

const sendTest = useReactiveMutation('webhooks.test')
const remove = useReactiveMutation('webhooks.delete')

const openDialog = (endpoint: App.Data.WebhookEndpointData | null) => {
  editing.value = endpoint
  dialogOpen.value = true
}

const destroy = async (endpoint: App.Data.WebhookEndpointData) => {
  const confirmed = await confirm({
    title: t('webhooks.deleteTitle'),
    message: t('webhooks.deleteConfirm', { url: endpoint.url }),
    confirmLabel: t('actions.remove'),
    variant: 'destructive',
  })

  if (confirmed) remove.mutate({ id: endpoint.id })
}

const statusDot = (status: string) =>
  status === 'succeeded' ? 'bg-success' : status === 'failed' ? 'bg-destructive' : 'bg-warning'
</script>

<template>
  <div class="grid gap-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="grid gap-1">
        <h1 class="text-2xl font-semibold text-primary">{{ t('webhooks.title') }}</h1>
        <p class="max-w-2xl text-sm text-muted">{{ t('webhooks.description') }}</p>
      </div>
      <Button
        variant="accent"
        @click="openDialog(null)"
      >
        <Icon name="lucide:plus" />
        {{ t('webhooks.create') }}
      </Button>
    </div>

    <div
      v-if="endpoints.isPending.value"
      class="grid gap-2"
    >
      <Skeleton class="h-20 rounded-xl" />
      <Skeleton class="h-20 rounded-xl" />
    </div>

    <p
      v-else-if="(endpoints.data.value ?? []).length === 0"
      class="rounded-xl border border-dashed border-border py-12 text-center text-sm text-muted"
    >
      {{ t('webhooks.empty') }}
    </p>

    <ul
      v-else
      class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card"
    >
      <li
        v-for="endpoint in endpoints.data.value ?? []"
        :key="endpoint.id"
        class="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
      >
        <div class="grid min-w-0 flex-1 gap-1.5">
          <div class="flex min-w-0 items-center gap-2">
            <code class="truncate font-mono text-sm text-primary">{{ endpoint.url }}</code>
            <Badge
              v-if="!endpoint.active"
              size="sm"
            >
              {{ t('webhooks.paused') }}
            </Badge>
          </div>
          <p
            v-if="endpoint.description"
            class="truncate text-sm text-muted"
          >
            {{ endpoint.description }}
          </p>
          <div class="flex flex-wrap items-center gap-1.5">
            <Badge
              v-for="event in endpoint.events"
              :key="event"
              size="sm"
              class="font-mono"
            >
              {{ event }}
            </Badge>
            <span
              v-if="endpoint.lastStatus && endpoint.lastEventAt"
              class="ml-1 flex items-center gap-1.5 text-xs text-muted"
            >
              <span
                class="size-2 rounded-full"
                :class="statusDot(endpoint.lastStatus)"
                aria-hidden="true"
              />
              {{ t(`webhooks.statuses.${endpoint.lastStatus}`) }} ·
              {{ relative(endpoint.lastEventAt) }}
            </span>
          </div>
        </div>

        <div class="flex shrink-0 gap-1">
          <SimpleTooltip :tooltip="t('webhooks.deliveries')">
            <Button
              variant="ghost"
              size="icon"
              :aria-label="t('webhooks.deliveries')"
              @click="logOf = endpoint"
            >
              <Icon name="lucide:list" />
            </Button>
          </SimpleTooltip>
          <SimpleTooltip :tooltip="t('webhooks.sendTest')">
            <Button
              variant="ghost"
              size="icon"
              :aria-label="t('webhooks.sendTest')"
              :disabled="!endpoint.active"
              @click="sendTest.mutate({ id: endpoint.id })"
            >
              <Icon name="lucide:send" />
            </Button>
          </SimpleTooltip>
          <SimpleTooltip :tooltip="t('webhooks.editTitle')">
            <Button
              variant="ghost"
              size="icon"
              :aria-label="t('webhooks.editTitle')"
              @click="openDialog(endpoint)"
            >
              <Icon name="lucide:pencil" />
            </Button>
          </SimpleTooltip>
          <SimpleTooltip :tooltip="t('actions.remove')">
            <Button
              variant="ghost"
              size="icon"
              class="text-destructive-foreground"
              :aria-label="t('actions.remove')"
              @click="destroy(endpoint)"
            >
              <Icon name="lucide:trash-2" />
            </Button>
          </SimpleTooltip>
        </div>
      </li>
    </ul>

    <WebhookDialog
      v-model:open="dialogOpen"
      :endpoint="editing"
    />

    <Sheet v-model:open="logOpen">
      <SheetContent class="grid w-full content-start overflow-y-auto p-5 sm:max-w-xl">
        <SheetHeaderCombined
          :title="t('webhooks.deliveries')"
          :description="logOf?.url"
        />
        <WebhookDeliveries
          v-if="logOf"
          :endpoint-id="logOf.id"
        />
      </SheetContent>
    </Sheet>
  </div>
</template>
