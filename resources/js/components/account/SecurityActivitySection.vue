<script setup lang="ts">
import { keepPreviousData, useQuery } from '@tanstack/vue-query'

import { api } from '~/api'
import SettingsSection from '~/components/account/SettingsSection.vue'
import Icon from '~/components/Icon.vue'
import { Button } from '~/components/ui/button'
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
  token_created: { icon: 'lucide:key-round', label: 'security.events.tokenCreated' },
  token_revoked: { icon: 'lucide:key-round', label: 'security.events.tokenRevoked' },
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
  <SettingsSection
    id="activity"
    :title="t('security.activityTitle')"
    :description="t('security.activityDescription')"
  >
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
      class="rounded-xl border border-dashed border-border py-8 text-center text-sm text-muted"
    >
      {{ t('security.activityEmpty') }}
    </p>

    <ol
      v-else
      class="relative grid gap-0 before:absolute before:top-3 before:bottom-3 before:left-[15px] before:w-px before:bg-border"
    >
      <li
        v-for="event in rows"
        :key="event.id"
        class="relative flex items-start gap-3 py-2 text-sm"
      >
        <span
          class="relative z-10 grid size-8 shrink-0 place-items-center rounded-full border border-border bg-card text-muted"
        >
          <Icon
            :name="icon(event.event)"
            size="14"
            aria-hidden="true"
          />
        </span>
        <div class="grid min-w-0 flex-1 gap-0.5 pt-1">
          <div class="flex items-baseline justify-between gap-3">
            <span class="truncate font-medium text-primary">{{ label(event.event) }}</span>
            <time
              class="shrink-0 text-xs text-muted"
              :datetime="event.createdAt"
              :title="dateTime(event.createdAt)"
            >
              {{ relative(event.createdAt) }}
            </time>
          </div>
          <div class="truncate text-xs text-muted">
            {{ event.device }} · {{ event.ipAddress ?? '–' }}
          </div>
        </div>
      </li>
    </ol>

    <template
      v-if="hasMore"
      #footer
    >
      <Button
        variant="ghost"
        size="sm"
        :loading="activity.isFetching.value"
        @click="page += 1"
      >
        {{ t('account.actions.loadMore') }}
      </Button>
    </template>
  </SettingsSection>
</template>
