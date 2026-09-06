<script setup lang="ts">
import { keepPreviousData, useQuery } from '@tanstack/vue-query'

import { api } from '~/api'
import Icon from '~/components/Icon.vue'
import { Button } from '~/components/ui/button'
import { Card, CardContent, CardHeaderCombined } from '~/components/ui/card'
import { Skeleton } from '~/components/ui/skeleton'
import { useFormat } from '~/composables/useFormat'
import { queryKeys } from '~/lib/query-keys'
import { useI18n } from '~/plugins/i18n'

/** What happened to this account, so a surprise has somewhere to be noticed. */
const { t } = useI18n()
const { relative, dateTime } = useFormat()

/**
 * The events App\Models\SecurityEvent can record. An event this table does
 * not know still renders, under its raw name: a trail that hides what it
 * cannot label is worse than one that shows it plainly.
 */
const EVENTS: Record<string, { icon: string; label: string }> = {
  signed_in: { icon: 'lucide:log-in', label: 'security.events.signedIn' },
  signed_out: { icon: 'lucide:log-out', label: 'security.events.signedOut' },
  password_changed: { icon: 'lucide:key-round', label: 'security.events.passwordChanged' },
  email_change_requested: { icon: 'lucide:mail', label: 'security.events.emailChangeRequested' },
  email_changed: { icon: 'lucide:mail-check', label: 'security.events.emailChanged' },
  two_factor_enabled: { icon: 'lucide:shield-check', label: 'security.events.twoFactorEnabled' },
  two_factor_disabled: { icon: 'lucide:shield-off', label: 'security.events.twoFactorDisabled' },
  backup_codes_regenerated: {
    icon: 'lucide:refresh-cw',
    label: 'security.events.backupCodesRegenerated',
  },
  session_revoked: { icon: 'lucide:monitor-x', label: 'security.events.sessionRevoked' },
  data_exported: { icon: 'lucide:download', label: 'security.events.dataExported' },
}

const page = ref(1)

const activity = useQuery({
  queryKey: computed(() => queryKeys.securityActivity(page.value)),
  queryFn: () => api.account.securityActivity(page.value),
  placeholderData: keepPreviousData,
})

const rows = computed(() => activity.data.value?.data ?? [])
const hasMore = computed(() => {
  const meta = activity.data.value

  return Boolean(meta && meta.current_page < meta.last_page)
})

const icon = (event: string): string => EVENTS[event]?.icon ?? 'lucide:activity'

const label = (event: string): string => {
  const known = EVENTS[event]

  return known ? t(known.label) : event
}
</script>

<template>
  <Card class="p-6">
    <CardHeaderCombined
      class="p-0 pb-4"
      :title="t('security.activityTitle')"
      :description="t('security.activityDescription')"
    />
    <CardContent class="grid gap-4 p-0">
      <div
        v-if="activity.isPending.value"
        class="grid gap-2"
      >
        <Skeleton class="h-10" />
        <Skeleton class="h-10" />
        <Skeleton class="h-10" />
      </div>

      <p
        v-else-if="rows.length === 0"
        class="py-6 text-center text-sm text-muted"
      >
        {{ t('security.activityEmpty') }}
      </p>

      <ul
        v-else
        class="divide-y divide-border"
      >
        <li
          v-for="event in rows"
          :key="event.id"
          class="flex items-center justify-between gap-3 py-2.5 text-sm"
        >
          <div class="flex min-w-0 items-center gap-3">
            <Icon
              :name="icon(event.event)"
              class="shrink-0 text-muted"
            />
            <div class="min-w-0">
              <div class="truncate font-medium">{{ label(event.event) }}</div>
              <div class="truncate text-xs text-muted">
                {{ event.device }} · {{ event.ipAddress ?? '–' }}
              </div>
            </div>
          </div>
          <span
            class="shrink-0 text-xs text-muted"
            :title="dateTime(event.createdAt)"
          >
            {{ relative(event.createdAt) }}
          </span>
        </li>
      </ul>

      <div v-if="hasMore">
        <Button
          variant="ghost"
          size="sm"
          :loading="activity.isFetching.value"
          @click="page += 1"
        >
          {{ t('account.actions.loadMore') }}
        </Button>
      </div>
    </CardContent>
  </Card>
</template>
