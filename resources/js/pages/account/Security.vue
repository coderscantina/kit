<script setup lang="ts">
import QRCode from 'qrcode'
import { toast } from 'vue-sonner'

import { api } from '~/api'
import FormField from '~/components/FormField.vue'
import PasswordConfirmDialog from '~/components/PasswordConfirmDialog.vue'
import { Alert } from '~/components/ui/alert'
import { Button } from '~/components/ui/button'
import { Card } from '~/components/ui/card'
import { useAuth } from '~/composables/useAuth'
import { useFormErrors } from '~/composables/useFormErrors'
import { useStepUp } from '~/composables/useStepUp'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const auth = useAuth()
const router = useRouter()
const errors = useFormErrors()
const stepUp = useStepUp()

const passwordForm = reactive({ current_password: '', password: '', password_confirmation: '' })
const savingPassword = ref(false)

const setup = ref<{ secret: string; url: string; qr: string } | null>(null)
const code = ref('')
const backupCodes = ref<string[] | null>(null)
const busy = ref(false)
const deleting = ref(false)

const twoFactorEnabled = computed(() => auth.user.value?.twoFactorEnabled ?? false)

const changePassword = async () => {
  savingPassword.value = true
  errors.clear()
  try {
    await stepUp.run(() => api.account.updatePassword(passwordForm))
    Object.assign(passwordForm, { current_password: '', password: '', password_confirmation: '' })
    toast.success(t('account.security.passwordChanged'))
  } catch (error) {
    errors.capture(error)
  } finally {
    savingPassword.value = false
  }
}

const startSetup = async () => {
  busy.value = true
  errors.clear()
  try {
    const result = await stepUp.run(() => api.auth.twoFactorSetup())
    if (result) {
      setup.value = { ...result, qr: await QRCode.toDataURL(result.url) }
    }
  } catch (error) {
    errors.capture(error)
  } finally {
    busy.value = false
  }
}

const confirmSetup = async () => {
  busy.value = true
  errors.clear()
  try {
    const result = await stepUp.run(() => api.auth.twoFactorConfirm(code.value))
    if (result) {
      backupCodes.value = result.backup_codes
      setup.value = null
      code.value = ''
      await auth.refresh()
    }
  } catch (error) {
    errors.capture(error)
  } finally {
    busy.value = false
  }
}

const disable = async () => {
  busy.value = true
  errors.clear()
  try {
    await stepUp.run(() => api.auth.twoFactorDisable())
    backupCodes.value = null
    await auth.refresh()
    toast.success(t('account.security.twoFactorDisabled'))
  } catch (error) {
    errors.capture(error)
  } finally {
    busy.value = false
  }
}

const regenerate = async () => {
  busy.value = true
  errors.clear()
  try {
    const result = await stepUp.run(() => api.auth.twoFactorBackupCodes())
    if (result) backupCodes.value = result.backup_codes
  } catch (error) {
    errors.capture(error)
  } finally {
    busy.value = false
  }
}

const deleteAccount = async () => {
  if (!window.confirm(t('account.security.deleteConfirm'))) return
  deleting.value = true
  errors.clear()
  try {
    await stepUp.run(() => api.account.destroy())
    await auth.logout()
    await router.push({ name: 'login' })
  } catch (error) {
    errors.capture(error)
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <div class="max-w-lg space-y-6">
    <h1 class="text-2xl font-semibold">{{ t('account.security.title') }}</h1>

    <Card class="p-6">
      <h2 class="mb-4 font-medium">{{ t('account.security.changePassword') }}</h2>
      <form
        class="space-y-4"
        @submit.prevent="changePassword"
      >
        <FormField
          id="current_password"
          v-model="passwordForm.current_password"
          type="password"
          autocomplete="current-password"
          :label="t('account.security.currentPassword')"
          :error="errors.fields.value.current_password"
          required
        />
        <FormField
          id="new_password"
          v-model="passwordForm.password"
          type="password"
          autocomplete="new-password"
          :label="t('account.security.newPassword')"
          :error="errors.fields.value.password"
          required
        />
        <FormField
          id="new_password_confirmation"
          v-model="passwordForm.password_confirmation"
          type="password"
          autocomplete="new-password"
          :label="t('auth.fields.passwordConfirmation')"
          required
        />
        <Button
          type="submit"
          :loading="savingPassword"
        >
          {{ t('actions.save') }}
        </Button>
      </form>
    </Card>

    <Card class="p-6">
      <h2 class="mb-2 font-medium">{{ t('account.security.twoFactor') }}</h2>
      <p class="mb-4 text-sm text-muted-foreground">
        {{
          twoFactorEnabled ? t('account.security.twoFactorOn') : t('account.security.twoFactorOff')
        }}
      </p>

      <div
        v-if="setup"
        class="space-y-4"
      >
        <img
          :src="setup.qr"
          alt=""
          class="size-40"
        />
        <p class="font-mono text-xs break-all">{{ setup.secret }}</p>
        <form
          class="space-y-4"
          @submit.prevent="confirmSetup"
        >
          <FormField
            id="totp-confirm"
            v-model="code"
            autocomplete="one-time-code"
            :label="t('auth.fields.totp')"
            required
          />
          <Button
            type="submit"
            :loading="busy"
          >
            {{ t('account.security.enable') }}
          </Button>
        </form>
      </div>

      <div
        v-else
        class="flex gap-2"
      >
        <Button
          v-if="!twoFactorEnabled"
          :loading="busy"
          @click="startSetup"
        >
          {{ t('account.security.enable') }}
        </Button>
        <template v-else>
          <Button
            variant="secondary"
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

      <div
        v-if="backupCodes"
        class="mt-4"
      >
        <p class="mb-2 text-sm">{{ t('account.security.backupCodes') }}</p>
        <ul class="grid grid-cols-2 gap-1 font-mono text-sm">
          <li
            v-for="backupCode in backupCodes"
            :key="backupCode"
          >
            {{ backupCode }}
          </li>
        </ul>
      </div>
    </Card>

    <Card class="border-destructive/50 p-6">
      <h2 class="mb-2 font-medium">{{ t('account.security.deleteAccount') }}</h2>
      <p class="mb-4 text-sm text-muted-foreground">
        {{ t('account.security.deleteDescription') }}
      </p>
      <Button
        variant="destructive"
        :loading="deleting"
        @click="deleteAccount"
      >
        {{ t('account.security.deleteAccount') }}
      </Button>
    </Card>

    <Alert
      v-if="errors.message.value"
      variant="destructive"
    >
      {{ errors.message.value }}
    </Alert>
    <PasswordConfirmDialog
      v-model:open="stepUp.confirmOpen.value"
      @confirmed="stepUp.onConfirmed"
    />
  </div>
</template>
