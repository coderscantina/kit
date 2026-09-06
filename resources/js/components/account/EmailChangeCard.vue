<script setup lang="ts">
import { toast } from 'vue-sonner'

import { api } from '~/api'
import FormField from '~/components/FormField.vue'
import PasswordConfirmDialog from '~/components/PasswordConfirmDialog.vue'
import { Alert } from '~/components/ui/alert'
import { Button } from '~/components/ui/button'
import { Card, CardContent, CardHeaderCombined } from '~/components/ui/card'
import { useAuth } from '~/composables/useAuth'
import { useFormErrors } from '~/composables/useFormErrors'
import { useStepUp } from '~/composables/useStepUp'
import { useI18n } from '~/plugins/i18n'

/**
 * The address does not move here. Asking mails a confirmation link to the new
 * address and a warning to the old one; the change lands when the link is
 * opened. Until then the account shows a pending address.
 */
const { t } = useI18n()
const auth = useAuth()
const errors = useFormErrors()
const stepUp = useStepUp()

const email = ref('')
const busy = ref(false)

const current = computed(() => auth.user.value?.email ?? '')
const pending = computed(() => auth.user.value?.pendingEmail ?? null)
const verified = computed(() => auth.user.value?.emailVerified ?? false)

const request = async () => {
  busy.value = true
  errors.clear()
  try {
    const result = await stepUp.run(() => api.account.requestEmailChange(email.value))
    if (result) {
      email.value = ''
      await auth.refresh()
      toast.success(t('account.profile.emailPendingToast'))
    }
  } catch (error) {
    errors.capture(error)
  } finally {
    busy.value = false
  }
}

const cancel = async () => {
  busy.value = true
  errors.clear()
  try {
    await api.account.cancelEmailChange()
    await auth.refresh()
  } catch (error) {
    errors.capture(error)
  } finally {
    busy.value = false
  }
}

const resendVerification = async () => {
  busy.value = true
  try {
    await api.auth.resendVerification()
    toast.success(t('account.profile.verificationSent'))
  } catch (error) {
    errors.capture(error)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <Card class="p-6">
    <CardHeaderCombined
      class="p-0 pb-4"
      :title="t('auth.fields.email')"
      :description="t('account.profile.emailDescription')"
    />
    <CardContent class="grid gap-4 p-0">
      <div class="flex flex-wrap items-center gap-2 text-sm">
        <span class="font-medium">{{ current }}</span>
        <span
          v-if="verified"
          class="inline-flex items-center gap-1 text-xs text-success-foreground"
        >
          {{ t('account.profile.verified') }}
        </span>
        <template v-else>
          <span class="text-xs text-muted">{{ t('account.profile.unverified') }}</span>
          <Button
            variant="ghost"
            size="sm"
            :loading="busy"
            @click="resendVerification"
          >
            {{ t('account.profile.resendVerification') }}
          </Button>
        </template>
      </div>

      <Alert
        v-if="pending"
        color="warning"
      >
        <div class="flex flex-wrap items-center justify-between gap-2">
          <span>{{ t('account.profile.emailPending', { email: pending }) }}</span>
          <Button
            variant="ghost"
            size="sm"
            :loading="busy"
            @click="cancel"
          >
            {{ t('actions.cancel') }}
          </Button>
        </div>
      </Alert>

      <form
        class="grid gap-4"
        @submit.prevent="request"
      >
        <FormField
          id="new-email"
          v-model="email"
          type="email"
          autocomplete="email"
          :label="t('account.profile.newEmail')"
          :description="t('account.profile.newEmailHint')"
          :error="errors.fields.value.email"
          required
        />
        <div>
          <Button
            variant="primary"
            type="submit"
            :loading="busy"
            :disabled="!email"
          >
            {{ t('account.profile.changeEmail') }}
          </Button>
        </div>
        <p
          v-if="errors.message.value"
          class="text-sm text-destructive"
        >
          {{ errors.message.value }}
        </p>
      </form>
    </CardContent>

    <PasswordConfirmDialog
      v-model:open="stepUp.confirmOpen.value"
      @confirmed="stepUp.onConfirmed"
    />
  </Card>
</template>
