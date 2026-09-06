<script setup lang="ts">
import { toast } from 'vue-sonner'

import { api } from '~/api'
import BackupCodesDisplay from '~/components/account/BackupCodesDisplay.vue'
import TwoFactorSetupDialog from '~/components/account/TwoFactorSetupDialog.vue'
import PasswordConfirmDialog from '~/components/PasswordConfirmDialog.vue'
import { Alert } from '~/components/ui/alert'
import { Button } from '~/components/ui/button'
import { Card, CardContent, CardHeaderCombined } from '~/components/ui/card'
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
  <Card class="p-6">
    <CardHeaderCombined
      class="p-0 pb-4"
      :title="t('account.security.twoFactor')"
      :description="t('account.security.twoFactorDescription')"
    />
    <CardContent class="grid gap-4 p-0">
      <Alert
        variant="modern"
        :color="enabled ? 'success' : 'warning'"
      >
        {{ enabled ? t('account.security.twoFactorOn') : t('account.security.twoFactorOff') }}
      </Alert>

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
            :loading="busy"
            @click="regenerate"
          >
            {{ t('account.security.regenerateCodes') }}
          </Button>
          <Button
            variant="destructive"
            :loading="busy"
            @click="disable"
          >
            {{ t('account.security.disable') }}
          </Button>
        </template>
      </div>
    </CardContent>

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
  </Card>
</template>
