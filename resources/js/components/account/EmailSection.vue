<script setup lang="ts">
import { toast } from 'vue-sonner'

import { api } from '~/api'
import SettingsSection from '~/components/account/SettingsSection.vue'
import FormField from '~/components/FormField.vue'
import Icon from '~/components/Icon.vue'
import PasswordConfirmDialog from '~/components/PasswordConfirmDialog.vue'
import { Alert } from '~/components/ui/alert'
import { Badge } from '~/components/ui/badge'
import { Button } from '~/components/ui/button'
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
  <SettingsSection
    id="email"
    :title="t('auth.fields.email')"
    :description="t('account.profile.emailDescription')"
  >
    <div
      class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card px-4 py-3"
    >
      <div class="flex min-w-0 items-center gap-3">
        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-secondary text-primary">
          <Icon
            name="lucide:mail"
            size="18"
            aria-hidden="true"
          />
        </span>
        <div class="grid min-w-0 gap-0.5">
          <span class="truncate text-sm font-medium text-primary">{{ current }}</span>
          <Badge
            :variant="verified ? 'success' : 'warning'"
            size="xs"
            class="w-fit"
          >
            {{ verified ? t('account.profile.verified') : t('account.profile.unverified') }}
          </Badge>
        </div>
      </div>
      <Button
        v-if="!verified"
        variant="default"
        size="sm"
        :loading="busy"
        @click="resendVerification"
      >
        {{ t('account.profile.resendVerification') }}
      </Button>
    </div>

    <Alert
      v-if="pending"
      color="warning"
      variant="modern"
      icon="lucide:hourglass"
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
      id="email-form"
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
      <p
        v-if="errors.message.value"
        class="text-sm text-destructive"
      >
        {{ errors.message.value }}
      </p>
    </form>

    <template #footer>
      <Button
        variant="primary"
        type="submit"
        form="email-form"
        :loading="busy"
        :disabled="!email"
      >
        {{ t('account.profile.changeEmail') }}
      </Button>
    </template>

    <PasswordConfirmDialog
      v-model:open="stepUp.confirmOpen.value"
      @confirmed="stepUp.onConfirmed"
    />
  </SettingsSection>
</template>
