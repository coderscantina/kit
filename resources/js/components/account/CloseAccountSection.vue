<script setup lang="ts">
import { api } from '~/api'
import SettingsSection from '~/components/account/SettingsSection.vue'
import PasswordConfirmDialog from '~/components/PasswordConfirmDialog.vue'
import { Alert } from '~/components/ui/alert'
import { Button } from '~/components/ui/button'
import { Input } from '~/components/ui/input'
import { Label } from '~/components/ui/label'
import { useAuth } from '~/composables/useAuth'
import { useStepUp } from '~/composables/useStepUp'
import { toastError } from '~/lib/toast-error'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const auth = useAuth()
const router = useRouter()
const stepUp = useStepUp()

const closing = ref(false)
const armed = ref(false)
const typed = ref('')

const email = computed(() => auth.user.value?.email ?? '')
/** Typing the address is the last gate: a stray click cannot get past it. */
const canClose = computed(() => typed.value.trim().toLowerCase() === email.value.toLowerCase())

const disarm = () => {
  armed.value = false
  typed.value = ''
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
  <SettingsSection
    id="close"
    destructive
    :title="t('account.security.deleteAccount')"
    :description="t('account.security.deleteDescription')"
  >
    <Alert
      color="destructive"
      variant="modern"
      icon="lucide:triangle-alert"
    >
      {{ t('account.security.deleteWarning') }}
    </Alert>

    <div v-if="!armed">
      <Button
        variant="destructive"
        @click="armed = true"
      >
        {{ t('account.security.deleteAccount') }}
      </Button>
    </div>

    <form
      v-else
      class="grid gap-3"
      @submit.prevent="close"
    >
      <div class="grid gap-1.5">
        <Label for="close-confirm">
          {{ t('account.security.deleteTypeEmail', { email }) }}
        </Label>
        <Input
          id="close-confirm"
          v-model="typed"
          autocomplete="off"
          autofocus
          class="max-w-sm"
        />
      </div>
      <div class="flex gap-2">
        <Button
          type="button"
          variant="ghost"
          @click="disarm"
        >
          {{ t('actions.cancel') }}
        </Button>
        <Button
          type="submit"
          variant="destructive"
          :loading="closing"
          :disabled="!canClose"
        >
          {{ t('account.security.deleteConfirmAction') }}
        </Button>
      </div>
    </form>

    <PasswordConfirmDialog
      v-model:open="stepUp.confirmOpen.value"
      @confirmed="stepUp.onConfirmed"
    />
  </SettingsSection>
</template>
