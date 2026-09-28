<script setup lang="ts">
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { toast } from 'vue-sonner'

import { api } from '~/api'
import AccessTokenDialog from '~/components/account/AccessTokenDialog.vue'
import SettingsSection from '~/components/account/SettingsSection.vue'
import Icon from '~/components/Icon.vue'
import { Badge } from '~/components/ui/badge'
import { Button } from '~/components/ui/button'
import { Skeleton } from '~/components/ui/skeleton'
import { useConfirm } from '~/composables/useConfirm'
import { useFormat } from '~/composables/useFormat'
import { queryKeys } from '~/lib/query-keys'
import { toastError } from '~/lib/toast-error'
import { useI18n } from '~/plugins/i18n'

/**
 * Personal access tokens. Each one opens the MCP endpoint for this account,
 * limited to the abilities it lists; the account's own abilities still apply
 * on top, so a token can only ever do less than its owner.
 */
const { t } = useI18n()
const { relative, date } = useFormat()
const { confirm } = useConfirm()
const queryClient = useQueryClient()

const creating = ref(false)

const tokens = useQuery({
  queryKey: queryKeys.tokens(),
  queryFn: () => api.account.tokens(),
})

const refresh = () => queryClient.invalidateQueries({ queryKey: queryKeys.tokens() })

/** `app.access` is on every token, so listing it says nothing. */
const shownAbilities = (token: App.Data.AccessTokenData): string[] =>
  token.abilities.filter((ability) => ability !== 'app.access')

const revoke = async (token: App.Data.AccessTokenData) => {
  const confirmed = await confirm({
    title: t('tokens.revokeTitle'),
    message: t('tokens.revokeConfirm', { name: token.name }),
    confirmLabel: t('actions.revoke'),
    variant: 'destructive',
  })
  if (!confirmed) return

  try {
    await api.account.revokeToken(token.id)
    await refresh()
    toast.success(t('tokens.revoked'))
  } catch (error) {
    toastError(t, 'tokens.error', error)
  }
}
</script>

<template>
  <SettingsSection
    id="tokens"
    :title="t('tokens.title')"
    :description="t('tokens.description')"
  >
    <div
      v-if="tokens.isPending.value"
      class="grid gap-2"
    >
      <Skeleton class="h-14 rounded-xl" />
    </div>

    <p
      v-else-if="(tokens.data.value ?? []).length === 0"
      class="rounded-xl border border-dashed border-border py-8 text-center text-sm text-muted"
    >
      {{ t('tokens.empty') }}
    </p>

    <ul
      v-else
      class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card"
    >
      <li
        v-for="token in tokens.data.value ?? []"
        :key="token.id"
        class="flex items-start justify-between gap-3 px-4 py-3"
      >
        <div class="grid min-w-0 gap-1.5">
          <span class="truncate text-sm font-medium text-primary">{{ token.name }}</span>
          <div class="flex flex-wrap gap-1">
            <Badge
              v-for="ability in shownAbilities(token)"
              :key="ability"
              size="sm"
              class="font-mono"
            >
              {{ ability }}
            </Badge>
          </div>
          <span class="text-xs text-muted">
            {{
              token.lastUsedAt
                ? t('tokens.lastUsed', { when: relative(token.lastUsedAt) })
                : t('tokens.neverUsed')
            }}
            ·
            {{
              token.expiresAt
                ? t('tokens.expiresOn', { date: date(token.expiresAt) })
                : t('tokens.noExpiry')
            }}
          </span>
        </div>
        <Button
          variant="ghost"
          size="icon"
          class="shrink-0 text-destructive-foreground"
          :aria-label="t('tokens.revokeNamed', { name: token.name })"
          @click="revoke(token)"
        >
          <Icon name="lucide:trash-2" />
        </Button>
      </li>
    </ul>

    <template #footer>
      <Button
        variant="outline"
        size="sm"
        @click="creating = true"
      >
        <Icon name="lucide:plus" />
        {{ t('tokens.create') }}
      </Button>
    </template>

    <AccessTokenDialog
      v-model:open="creating"
      @created="refresh"
    />
  </SettingsSection>
</template>
