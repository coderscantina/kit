<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'

import { api } from '~/api'
import Icon from '~/components/Icon.vue'
import { useAuth } from '~/composables/useAuth'
import { queryKeys } from '~/lib/query-keys'
import { useI18n } from '~/plugins/i18n'

/**
 * The state of the account in one row, before any form: is there a second
 * factor, how many browsers hold a session, is the address verified. Each
 * tile links to the section that changes it, so a red tile is one tap from
 * green.
 */
const { t } = useI18n()
const auth = useAuth()

const sessions = useQuery({
  queryKey: queryKeys.sessions(),
  queryFn: () => api.account.sessions(),
})

const twoFactor = computed(() => auth.user.value?.twoFactorEnabled ?? false)
const verified = computed(() => auth.user.value?.emailVerified ?? false)
const deviceCount = computed(() => sessions.data.value?.length ?? null)

interface Tile {
  key: string
  icon: string
  label: string
  value: string
  /** `good` reads as settled; `attention` is the tile worth tapping. */
  tone: 'good' | 'attention'
  to: { name: string; hash: string }
}

const tiles = computed<Tile[]>(() => [
  {
    key: 'two-factor',
    icon: twoFactor.value ? 'lucide:shield-check' : 'lucide:shield-alert',
    label: t('account.security.twoFactor'),
    value: twoFactor.value ? t('account.security.overview.on') : t('account.security.overview.off'),
    tone: twoFactor.value ? 'good' : 'attention',
    to: { name: 'account-security', hash: '#two-factor' },
  },
  {
    key: 'devices',
    icon: 'lucide:monitor-smartphone',
    label: t('sessions.title'),
    value:
      deviceCount.value === null
        ? '…'
        : t('account.security.overview.devices', { count: deviceCount.value }),
    tone: 'good',
    to: { name: 'account-security', hash: '#devices' },
  },
  {
    key: 'email',
    icon: verified.value ? 'lucide:mail-check' : 'lucide:mail-warning',
    label: t('auth.fields.email'),
    value: verified.value ? t('account.profile.verified') : t('account.profile.unverified'),
    tone: verified.value ? 'good' : 'attention',
    to: { name: 'account-profile', hash: '#email' },
  },
])
</script>

<template>
  <ul class="grid gap-3 pb-8 sm:grid-cols-3">
    <li
      v-for="tile in tiles"
      :key="tile.key"
    >
      <RouterLink
        :to="tile.to"
        class="group flex h-full items-start gap-3 rounded-xl border p-3.5 transition-colors duration-200 ease-butter"
        :class="
          tile.tone === 'attention'
            ? 'border-warning/40 bg-warning-background/10 hover:bg-warning-background/20'
            : 'border-border bg-card hover:border-border-strong'
        "
      >
        <span
          class="grid size-9 shrink-0 place-items-center rounded-lg"
          :class="
            tile.tone === 'attention' ? 'bg-warning/15 text-warning' : 'bg-secondary text-primary'
          "
        >
          <Icon
            :name="tile.icon"
            size="18"
            aria-hidden="true"
          />
        </span>
        <span class="grid min-w-0 gap-0.5">
          <span class="truncate text-xs text-muted">{{ tile.label }}</span>
          <span class="text-sm font-medium text-pretty text-primary">{{ tile.value }}</span>
        </span>
        <Icon
          name="lucide:arrow-right"
          size="14"
          class="ml-auto mt-1 shrink-0 text-muted opacity-0 transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100"
          aria-hidden="true"
        />
      </RouterLink>
    </li>
  </ul>
</template>
