<script setup lang="ts">
import QRCode from 'qrcode'

import { api } from '~/api'
import BackupCodesDisplay from '~/components/account/BackupCodesDisplay.vue'
import FormField from '~/components/FormField.vue'
import { Alert } from '~/components/ui/alert'
import { Button } from '~/components/ui/button'
import { Checkbox } from '~/components/ui/checkbox'
import { Dialog, DialogContent, DialogHeaderCombined } from '~/components/ui/dialog'
import { Input } from '~/components/ui/input'
import { Label } from '~/components/ui/label'
import { errorMessage } from '~/lib/toast-error'
import { useI18n } from '~/plugins/i18n'

/**
 * Three screens: what this is, scan and verify, then the backup codes. The
 * codes screen cannot be dismissed until the user says they saved them,
 * because it is the only time they are ever shown.
 *
 * The password step-up the endpoints require is handled by the caller's
 * step-up dialog, so there is no password field in here.
 */
const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ enabled: [] }>()

const props = defineProps<{
  /** Runs the request through the caller's step-up, so a 423 opens one dialog, not two. */
  run: <T>(request: () => Promise<T>) => Promise<T | undefined>
}>()

const { t } = useI18n()

type Step = 'intro' | 'scan' | 'codes'

const step = ref<Step>('intro')
const secret = ref('')
const qr = ref<string | null>(null)
const code = ref('')
const codes = ref<string[]>([])
// reka-ui's checkbox models a third, indeterminate state.
const saved = ref<boolean | 'indeterminate'>(false)
const busy = ref(false)
const error = ref<string | null>(null)

const reset = () => {
  step.value = 'intro'
  secret.value = ''
  qr.value = null
  code.value = ''
  codes.value = []
  saved.value = false
  error.value = null
}

watch(open, (value) => {
  if (!value) reset()
})

const start = async () => {
  busy.value = true
  error.value = null
  try {
    const result = await props.run(() => api.auth.twoFactorSetup())
    if (!result) return

    secret.value = result.secret
    qr.value = await QRCode.toDataURL(result.url, { margin: 2, width: 320 })
    step.value = 'scan'
  } catch (failure) {
    error.value = errorMessage(failure)
  } finally {
    busy.value = false
  }
}

const confirm = async () => {
  busy.value = true
  error.value = null
  try {
    const result = await props.run(() => api.auth.twoFactorConfirm(code.value))
    if (!result) return

    codes.value = result.backup_codes
    step.value = 'codes'
    emit('enabled')
  } catch (failure) {
    error.value = errorMessage(failure)
    code.value = ''
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent class="max-w-md">
      <DialogHeaderCombined
        :title="t('account.security.twoFactor')"
        :description="t(`account.security.setup.${step}Description`)"
      />

      <div
        v-if="step === 'intro'"
        class="grid gap-4"
      >
        <Alert
          color="info"
          variant="modern"
        >
          {{ t('account.security.setup.what') }}
        </Alert>
        <ol class="grid list-decimal gap-1 pl-5 text-sm text-muted">
          <li>{{ t('account.security.setup.step1') }}</li>
          <li>{{ t('account.security.setup.step2') }}</li>
          <li>{{ t('account.security.setup.step3') }}</li>
        </ol>
        <div class="flex justify-end gap-2">
          <Button
            type="button"
            variant="ghost"
            @click="open = false"
            >{{ t('actions.cancel') }}</Button
          >
          <Button
            variant="primary"
            :loading="busy"
            @click="start"
            >{{ t('account.actions.continue') }}</Button
          >
        </div>
      </div>

      <form
        v-else-if="step === 'scan'"
        class="grid gap-4"
        @submit.prevent="confirm"
      >
        <img
          v-if="qr"
          :src="qr"
          alt=""
          class="mx-auto size-48 rounded-lg border border-border bg-white p-2"
        />
        <div class="grid gap-1.5">
          <Label for="totp-secret">{{ t('account.security.setup.manualEntry') }}</Label>
          <Input
            id="totp-secret"
            :model-value="secret"
            readonly
            class="text-center font-mono tracking-widest"
          />
        </div>
        <FormField
          id="totp-code"
          v-model="code"
          autocomplete="one-time-code"
          inputmode="numeric"
          :label="t('auth.fields.totp')"
          required
          autofocus
        />
        <p
          v-if="error"
          class="text-sm text-destructive"
        >
          {{ error }}
        </p>
        <div class="flex justify-end gap-2">
          <Button
            type="button"
            variant="ghost"
            @click="step = 'intro'"
            >{{ t('account.actions.back') }}</Button
          >
          <Button
            variant="primary"
            type="submit"
            :loading="busy"
            :disabled="code.length < 6"
            >{{ t('account.security.enable') }}</Button
          >
        </div>
      </form>

      <div
        v-else
        class="grid gap-4"
      >
        <BackupCodesDisplay :codes="codes" />
        <div class="flex items-center gap-2">
          <Checkbox
            id="codes-saved"
            v-model="saved"
          />
          <Label for="codes-saved">{{ t('account.security.codesSaved') }}</Label>
        </div>
        <div class="flex justify-end">
          <Button
            variant="primary"
            :disabled="saved !== true"
            @click="open = false"
            >{{ t('account.actions.done') }}</Button
          >
        </div>
      </div>
    </DialogContent>
  </Dialog>
</template>
