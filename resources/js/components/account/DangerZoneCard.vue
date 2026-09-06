<script setup lang="ts">
import { toast } from 'vue-sonner'

import { api } from '~/api'
import Icon from '~/components/Icon.vue'
import PasswordConfirmDialog from '~/components/PasswordConfirmDialog.vue'
import { Alert } from '~/components/ui/alert'
import { Button } from '~/components/ui/button'
import { Card, CardContent, CardHeaderCombined } from '~/components/ui/card'
import { Input } from '~/components/ui/input'
import { Label } from '~/components/ui/label'
import { useAuth } from '~/composables/useAuth'
import { useStepUp } from '~/composables/useStepUp'
import { toastError } from '~/lib/toast-error'
import { useI18n } from '~/plugins/i18n'

/**
 * Export and closure sit together because both answer "I am leaving": take
 * the data first, then close the account.
 */
const { t } = useI18n()
const auth = useAuth()
const router = useRouter()
const stepUp = useStepUp()

const exporting = ref(false)
const closing = ref(false)
const armed = ref(false)
const typed = ref('')

const email = computed(() => auth.user.value?.email ?? '')
/** Typing the address is the last gate: a stray click cannot get past it. */
const canClose = computed(() => typed.value.trim().toLowerCase() === email.value.toLowerCase())

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

const close = async () => {
  closing.value = true
  try {
    await stepUp.run(() => api.account.destroy())
    await auth.logout()
    await router.push({ name: 'login' })
  } catch (error) {
    toastError(t, 'users.error', error)
  } finally {
    closing.value = false
  }
}
</script>

<template>
  <div class="grid gap-6">
    <Card class="p-6">
      <CardHeaderCombined
        class="p-0 pb-4"
        :title="t('account.export.title')"
        :description="t('account.export.description')"
      />
      <CardContent class="p-0">
        <Button
          variant="default"
          :loading="exporting"
          @click="exportData"
        >
          <Icon name="lucide:download" />
          {{ t('account.export.action') }}
        </Button>
      </CardContent>
    </Card>

    <Card class="border-destructive/50 p-6">
      <CardHeaderCombined
        class="p-0 pb-4"
        :title="t('account.security.deleteAccount')"
        :description="t('account.security.deleteDescription')"
      />
      <CardContent class="grid gap-4 p-0">
        <Alert color="destructive">{{ t('account.security.deleteWarning') }}</Alert>

        <Button
          v-if="!armed"
          variant="destructive"
          @click="armed = true"
        >
          {{ t('account.security.deleteAccount') }}
        </Button>

        <div
          v-else
          class="grid gap-3"
        >
          <div class="grid gap-1.5">
            <Label for="close-confirm">
              {{ t('account.security.deleteTypeEmail', { email }) }}
            </Label>
            <Input
              id="close-confirm"
              v-model="typed"
              autocomplete="off"
              class="max-w-sm"
            />
          </div>
          <div class="flex gap-2">
            <Button
              variant="ghost"
              @click="
                armed = false
                typed = ''
              "
            >
              {{ t('actions.cancel') }}
            </Button>
            <Button
              variant="destructive"
              :loading="closing"
              :disabled="!canClose"
              @click="close"
            >
              {{ t('account.security.deleteConfirmAction') }}
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>

    <PasswordConfirmDialog
      v-model:open="stepUp.confirmOpen.value"
      @confirmed="stepUp.onConfirmed"
    />
  </div>
</template>
