<script setup lang="ts">
import { Badge } from '~/components/ui/badge'
import { Skeleton } from '~/components/ui/skeleton'
import { useFormat } from '~/composables/useFormat'
import { useReactiveQuery } from '~/lib/reactive'
import { useI18n } from '~/plugins/i18n'

/**
 * One endpoint's latest deliveries, live: a retry or a receiver finally
 * answering changes the row in place. Each row opens to the exact body that
 * was signed and what the receiver said back.
 */
const props = defineProps<{ endpointId: string }>()

const { t } = useI18n()
const { relative, dateTime } = useFormat()

const deliveries = useReactiveQuery('webhooks.deliveries', () => ({ id: props.endpointId }))

const statusVariant = (status: string) =>
  status === 'succeeded' ? 'success' : status === 'failed' ? 'destructive' : 'warning'
</script>

<template>
  <div
    v-if="deliveries.isPending.value"
    class="grid gap-2"
  >
    <Skeleton class="h-12" />
    <Skeleton class="h-12" />
  </div>

  <p
    v-else-if="(deliveries.data.value ?? []).length === 0"
    class="rounded-xl border border-dashed border-border py-8 text-center text-sm text-muted"
  >
    {{ t('webhooks.noDeliveries') }}
  </p>

  <ul
    v-else
    class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card"
  >
    <li
      v-for="delivery in deliveries.data.value ?? []"
      :key="delivery.id"
    >
      <details class="group">
        <summary
          class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 text-sm hover:bg-muted-background/50"
        >
          <span class="flex min-w-0 items-center gap-2">
            <Badge
              :variant="statusVariant(delivery.status)"
              size="sm"
            >
              {{ t(`webhooks.statuses.${delivery.status}`) }}
            </Badge>
            <code class="truncate font-mono text-xs text-primary">{{ delivery.event }}</code>
          </span>
          <span class="flex shrink-0 items-center gap-3 text-xs text-muted">
            <span v-if="delivery.responseStatus">HTTP {{ delivery.responseStatus }}</span>
            <span>{{ t('webhooks.attempts', { count: delivery.attempts }) }}</span>
            <time
              :datetime="delivery.createdAt"
              :title="dateTime(delivery.createdAt)"
            >
              {{ relative(delivery.createdAt) }}
            </time>
          </span>
        </summary>
        <div class="grid gap-3 border-t border-border px-4 py-3">
          <div class="grid gap-1">
            <span class="text-xs font-medium text-muted">{{ t('webhooks.payload') }}</span>
            <pre
              class="max-h-64 overflow-auto rounded-lg bg-muted-background p-3 font-mono text-xs text-primary"
            ><code>{{ delivery.payload }}</code></pre>
          </div>
          <div
            v-if="delivery.responseBody"
            class="grid gap-1"
          >
            <span class="text-xs font-medium text-muted">{{ t('webhooks.response') }}</span>
            <pre
              class="max-h-40 overflow-auto rounded-lg bg-muted-background p-3 font-mono text-xs whitespace-pre-wrap text-primary"
            ><code>{{ delivery.responseBody }}</code></pre>
          </div>
        </div>
      </details>
    </li>
  </ul>
</template>
