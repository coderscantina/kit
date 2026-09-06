<script setup lang="ts">
import { toast } from 'vue-sonner'

import { api } from '~/api'
import FormField from '~/components/FormField.vue'
import PasswordConfirmDialog from '~/components/PasswordConfirmDialog.vue'
import { Button } from '~/components/ui/button'
import { Card, CardContent, CardHeaderCombined } from '~/components/ui/card'
import { useFormErrors } from '~/composables/useFormErrors'
import { useStepUp } from '~/composables/useStepUp'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const errors = useFormErrors()
const stepUp = useStepUp()

const MIN_LENGTH = 8

const form = reactive({ current_password: '', password: '', password_confirmation: '' })
const busy = ref(false)

/**
 * Checked here so the mismatch is caught while the user is still looking at
 * the field, not after a round trip. The server checks it too.
 */
const mismatch = computed(() =>
  form.password_confirmation.length > 0 && form.password !== form.password_confirmation
    ? t('account.security.passwordMismatch')
    : undefined
)

const submittable = computed(
  () =>
    form.current_password.length > 0 &&
    form.password.length >= MIN_LENGTH &&
    form.password === form.password_confirmation
)

const submit = async () => {
  busy.value = true
  errors.clear()
  try {
    const result = await stepUp.run(() => api.account.updatePassword({ ...form }))
    if (result) {
      Object.assign(form, { current_password: '', password: '', password_confirmation: '' })
      toast.success(t('account.security.passwordChanged'))
    }
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
      :title="t('account.security.changePassword')"
      :description="t('account.security.changePasswordDescription')"
    />
    <CardContent class="grid gap-4 p-0">
      <form
        class="grid gap-4"
        @submit.prevent="submit"
      >
        <FormField
          id="current_password"
          v-model="form.current_password"
          type="password"
          autocomplete="current-password"
          :label="t('account.security.currentPassword')"
          :error="errors.fields.value.current_password"
          required
        />
        <FormField
          id="new_password"
          v-model="form.password"
          type="password"
          autocomplete="new-password"
          :label="t('account.security.newPassword')"
          :description="t('account.security.passwordHint')"
          :error="errors.fields.value.password"
          required
        />
        <FormField
          id="new_password_confirmation"
          v-model="form.password_confirmation"
          type="password"
          autocomplete="new-password"
          :label="t('auth.fields.passwordConfirmation')"
          :error="mismatch"
          required
        />
        <p
          v-if="errors.message.value"
          class="text-sm text-destructive"
        >
          {{ errors.message.value }}
        </p>
        <div>
          <Button
            variant="primary"
            type="submit"
            :loading="busy"
            :disabled="!submittable"
          >
            {{ t('actions.save') }}
          </Button>
        </div>
      </form>
    </CardContent>

    <PasswordConfirmDialog
      v-model:open="stepUp.confirmOpen.value"
      @confirmed="stepUp.onConfirmed"
    />
  </Card>
</template>
