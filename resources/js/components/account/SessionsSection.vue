<script setup lang="ts">
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { toast } from 'vue-sonner'

import { api } from '~/api'
import SettingsSection from '~/components/account/SettingsSection.vue'
import Icon from '~/components/Icon.vue'
import PasswordConfirmDialog from '~/components/PasswordConfirmDialog.vue'
import { Badge } from '~/components/ui/badge'
import { Button } from '~/components/ui/button'
import { Skeleton } from '~/components/ui/skeleton'
import { useConfirm } from '~/composables/useConfirm'
import { useFormat } from '~/composables/useFormat'
import { useStepUp } from '~/composables/useStepUp'
import { queryKeys } from '~/lib/query-keys'
import { toastError } from '~/lib/toast-error'
import { useI18n } from '~/plugins/i18n'

/**
 * The browsers holding a session for this account. Revoking takes effect on
 * the revoked browser's next request, which is also how a password change
 * already drops other sessions.
 */
const { t } = useI18n()
const { relative, dateTime } = useFormat()
const { confirm } = useConfirm()
const stepUp = useStepUp()
const queryClient = useQueryClient()

const busy = ref(false)

const sessions = useQuery({
  queryKey: queryKeys.sessions(),
  queryFn: () => api.account.sessions(),
})

const others = computed(() => (sessions.data.value ?? []).filter((entry) => !entry.current))

const refresh = () => queryClient.invalidateQueries({ queryKey: queryKeys.sessions() })

/** A phone gets a phone glyph; the device string is what the server parsed from the agent. */
const deviceIcon = (device: string): string =>
  /iphone|android|mobile|ipad|tablet/i.test(device) ? 'lucide:smartphone' : 'lucide:monitor'

const revoke = async (session: App.Data.SessionData) => {
  const confirmed = await confirm({
    title: t('sessions.revokeTitle'),
    message: t('sessions.revokeConfirm', { device: session.device }),
    confirmLabel: t('sessions.revoke'),
    variant: 'destructive',
  })

  if (!confirmed) return

  busy.value = true
  try {
    await stepUp.run(() => api.account.revokeSession(session.id))
    await refresh()
    toast.success(t('sessions.revoked'))
  } catch (error) {
    toastError(t, 'users.error', error)
  } finally {
    busy.value = false
  }
}

const revokeOthers = async () => {
  const confirmed = await confirm({
    title: t('sessions.revokeOthers'),
    message: t('sessions.revokeOthersConfirm'),
    confirmLabel: t('sessions.revokeOthers'),
    variant: 'destructive',
  })

  if (!confirmed) return

  busy.value = true
  try {
    const result = await stepUp.run(() => api.account.revokeOtherSessions())
    if (result) {
      await refresh()
      toast.success(t('sessions.revokedCount', { count: result.revoked }))
    }
  } catch (error) {
    toastError(t, 'users.error', error)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <SettingsSection
    id="devices"
    :title="t('sessions.title')"
    :description="t('sessions.description')"
  >
    <div
      v-if="sessions.isPending.value"
      class="grid gap-2"
    >
      <Skeleton class="h-14 rounded-xl" />
      <Skeleton class="h-14 rounded-xl" />
    </div>

    <ul
      v-else
      class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card"
    >
      <li
        v-for="session in sessions.data.value ?? []"
        :key="session.id"
        class="flex items-center justify-between gap-3 px-4 py-3"
      >
        <div class="flex min-w-0 items-center gap-3">
          <span
            class="grid size-9 shrink-0 place-items-center rounded-lg bg-secondary text-primary"
          >
            <Icon
              :name="deviceIcon(session.device)"
              size="18"
              aria-hidden="true"
            />
          </span>
          <div class="min-w-0">
            <div class="flex items-center gap-2 text-sm font-medium text-primary">
              <span class="truncate">{{ session.device }}</span>
              <Badge
                v-if="session.current"
                variant="success"
                size="xs"
              >
                {{ t('sessions.current') }}
              </Badge>
            </div>
            <div
              class="truncate text-xs text-muted"
              :title="dateTime(session.lastActiveAt)"
            >
              {{ session.ipAddress ?? '–' }} · {{ relative(session.lastActiveAt) }}
            </div>
          </div>
        </div>
        <Button
          v-if="!session.current"
          variant="ghost"
          size="sm"
          :disabled="busy"
          @click="revoke(session)"
        >
          {{ t('sessions.revoke') }}
        </Button>
      </li>
    </ul>

    <template
      v-if="others.length > 0"
      #footer
    >
      <Button
        variant="destructive"
        :loading="busy"
        @click="revokeOthers"
      >
        {{ t('sessions.revokeOthers') }}
      </Button>
    </template>

    <PasswordConfirmDialog
      v-model:open="stepUp.confirmOpen.value"
      @confirmed="stepUp.onConfirmed"
    />
  </SettingsSection>
</template>
