<script setup lang="ts">
import { toast } from 'vue-sonner'

import { api } from '~/api'
import BackupCodesDisplay from '~/components/account/BackupCodesDisplay.vue'
import SettingsSection from '~/components/account/SettingsSection.vue'
import TwoFactorSetupDialog from '~/components/account/TwoFactorSetupDialog.vue'
import Icon from '~/components/Icon.vue'
import PasswordConfirmDialog from '~/components/PasswordConfirmDialog.vue'
import { Badge } from '~/components/ui/badge'
import { Button } from '~/components/ui/button'
import { Dialog, DialogContent, DialogHeaderCombined } from '~/components/ui/dialog'
import { useAuth } from '~/composables/useAuth'
import { useConfirm } from '~/composables/useConfirm'
import { useStepUp } from '~/composables/useStepUp'
import { toastError } from '~/lib/toast-error'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const auth = useAuth()
const stepUp = useStepUp()
const { confirm } = useConfirm()

const setupOpen = ref(false)
const codesOpen = ref(false)
const codes = ref<string[]>([])
const busy = ref(false)

const enabled = computed(() => auth.user.value?.twoFactorEnabled ?? false)

const onEnabled = async () => {
  await auth.refresh()
  toast.success(t('account.security.twoFactorEnabled'))
}

const regenerate = async () => {
  const confirmed = await confirm({
    title: t('account.security.regenerateCodes'),
    message: t('account.security.regenerateConfirm'),
    confirmLabel: t('account.actions.continue'),
  })

  if (!confirmed) return

  busy.value = true
  try {
    const result = await stepUp.run(() => api.auth.twoFactorBackupCodes())
    if (result) {
      codes.value = result.backup_codes
      codesOpen.value = true
    }
  } catch (error) {
    toastError(t, 'users.error', error)
  } finally {
    busy.value = false
  }
}

const disable = async () => {
  const confirmed = await confirm({
    title: t('account.security.disable'),
    message: t('account.security.disableConfirm'),
    confirmLabel: t('account.security.disable'),
    variant: 'destructive',
  })

  if (!confirmed) return

  busy.value = true
  try {
    await stepUp.run(() => api.auth.twoFactorDisable())
    await auth.refresh()
    toast.success(t('account.security.twoFactorDisabled'))
  } catch (error) {
    toastError(t, 'users.error', error)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <SettingsSection
    id="two-factor"
    :title="t('account.security.twoFactor')"
    :description="t('account.security.twoFactorDescription')"
  >
    <div
      class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card p-4"
    >
      <div class="flex min-w-0 items-center gap-3">
        <span
          class="grid size-9 shrink-0 place-items-center rounded-lg"
          :class="enabled ? 'bg-success-background/15 text-success' : 'bg-secondary text-muted'"
        >
          <Icon
            :name="enabled ? 'lucide:shield-check' : 'lucide:shield'"
            size="18"
            aria-hidden="true"
          />
        </span>
        <div class="grid min-w-0 gap-0.5">
          <div class="flex items-center gap-2 text-sm font-medium text-primary">
            {{ t('account.security.overview.authenticator') }}
            <Badge
              :variant="enabled ? 'success' : 'secondary'"
              size="xs"
            >
              {{ enabled ? t('account.security.overview.on') : t('account.security.overview.off') }}
            </Badge>
          </div>
          <p class="text-xs text-pretty text-muted">
            {{ enabled ? t('account.security.twoFactorOn') : t('account.security.twoFactorOff') }}
          </p>
        </div>
      </div>

      <div class="flex flex-wrap gap-2">
        <Button
          v-if="!enabled"
          variant="primary"
          @click="setupOpen = true"
        >
          {{ t('account.security.enable') }}
        </Button>
        <template v-else>
          <Button
            variant="default"
            size="sm"
            :loading="busy"
            @click="regenerate"
          >
            {{ t('account.security.regenerateCodes') }}
          </Button>
          <Button
            variant="destructive"
            size="sm"
            :loading="busy"
            @click="disable"
          >
            {{ t('account.security.disable') }}
          </Button>
        </template>
      </div>
    </div>

    <TwoFactorSetupDialog
      v-model:open="setupOpen"
      :run="stepUp.run"
      @enabled="onEnabled"
    />

    <Dialog v-model:open="codesOpen">
      <DialogContent class="max-w-md">
        <DialogHeaderCombined
          :title="t('account.security.regenerateCodes')"
          :description="t('account.security.regeneratedDescription')"
        />
        <BackupCodesDisplay :codes="codes" />
      </DialogContent>
    </Dialog>

    <PasswordConfirmDialog
      v-model:open="stepUp.confirmOpen.value"
      @confirmed="stepUp.onConfirmed"
    />
  </SettingsSection>
</template>
