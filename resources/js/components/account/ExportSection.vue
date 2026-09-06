<script setup lang="ts">
import { toast } from 'vue-sonner'

import { api } from '~/api'
import SettingsSection from '~/components/account/SettingsSection.vue'
import Icon from '~/components/Icon.vue'
import PasswordConfirmDialog from '~/components/PasswordConfirmDialog.vue'
import { Button } from '~/components/ui/button'
import { useStepUp } from '~/composables/useStepUp'
import { toastError } from '~/lib/toast-error'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const stepUp = useStepUp()

const exporting = ref(false)

const exportData = async () => {
  exporting.value = true
  try {
    const payload = await stepUp.run(() => api.account.export())
    if (!payload) return

    const url = URL.createObjectURL(
      new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' })
    )
    const link = document.createElement('a')

    link.href = url
    link.download = `account-export-${new Date().toISOString().slice(0, 10)}.json`
    link.click()

    URL.revokeObjectURL(url)
    toast.success(t('account.export.done'))
  } catch (error) {
    toastError(t, 'users.error', error)
  } finally {
    exporting.value = false
  }
}
</script>

<template>
  <SettingsSection
    id="export"
    :title="t('account.export.title')"
    :description="t('account.export.description')"
  >
    <div>
      <Button
        variant="default"
        :loading="exporting"
        @click="exportData"
      >
        <Icon name="lucide:download" />
        {{ t('account.export.action') }}
      </Button>
    </div>

    <PasswordConfirmDialog
      v-model:open="stepUp.confirmOpen.value"
      @confirmed="stepUp.onConfirmed"
    />
  </SettingsSection>
</template>
