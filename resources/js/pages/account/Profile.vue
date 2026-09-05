<script setup lang="ts">
import { toast } from 'vue-sonner'

import { api } from '~/api'
import FormField from '~/components/FormField.vue'
import PasswordConfirmDialog from '~/components/PasswordConfirmDialog.vue'
import { Alert } from '~/components/ui/alert'
import { Button } from '~/components/ui/button'
import { Card } from '~/components/ui/card'
import { Label } from '~/components/ui/label'
import { useAuth } from '~/composables/useAuth'
import { useFormErrors } from '~/composables/useFormErrors'
import { useStepUp } from '~/composables/useStepUp'
import { useI18n, isSupportedLocale, setLocale, type LocaleCode } from '~/plugins/i18n'

const { t, locales } = useI18n()
const auth = useAuth()
const errors = useFormErrors()
const stepUp = useStepUp()

const name = ref(auth.user.value?.name ?? '')
const locale = ref<LocaleCode>(
  isSupportedLocale(auth.user.value?.locale ?? '') ? (auth.user.value?.locale as LocaleCode) : 'en'
)
const email = ref(auth.user.value?.email ?? '')
const saving = ref(false)
const savingEmail = ref(false)

const saveProfile = async () => {
  saving.value = true
  errors.clear()
  try {
    await api.account.updateProfile({ name: name.value, locale: locale.value })
    setLocale(locale.value)
    await auth.refresh()
    toast.success(t('account.profile.saved'))
  } catch (error) {
    errors.capture(error)
  } finally {
    saving.value = false
  }
}

const saveEmail = async () => {
  savingEmail.value = true
  errors.clear()
  try {
    await stepUp.run(() => api.account.updateEmail(email.value))
    await auth.refresh()
    toast.success(t('account.profile.emailChanged'))
  } catch (error) {
    errors.capture(error)
  } finally {
    savingEmail.value = false
  }
}
</script>

<template>
  <div class="max-w-lg space-y-6">
    <h1 class="text-2xl font-semibold">{{ t('account.profile.title') }}</h1>
    <Card class="p-6">
      <form
        class="space-y-4"
        @submit.prevent="saveProfile"
      >
        <FormField
          id="name"
          v-model="name"
          autocomplete="name"
          :label="t('auth.fields.name')"
          :error="errors.fields.value.name"
          required
        />
        <div class="space-y-2">
          <Label for="locale">{{ t('account.profile.language') }}</Label>
          <select
            id="locale"
            v-model="locale"
            class="flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
          >
            <option
              v-for="entry in locales"
              :key="entry.code"
              :value="entry.code"
            >
              {{ entry.name }}
            </option>
          </select>
        </div>
        <Button
          type="submit"
          :loading="saving"
        >
          {{ t('actions.save') }}
        </Button>
      </form>
    </Card>
    <Card class="p-6">
      <form
        class="space-y-4"
        @submit.prevent="saveEmail"
      >
        <FormField
          id="email"
          v-model="email"
          type="email"
          autocomplete="email"
          :label="t('auth.fields.email')"
          :error="errors.fields.value.email"
          required
        />
        <p
          v-if="auth.user.value && !auth.user.value.emailVerified"
          class="text-sm text-muted-foreground"
        >
          {{ t('account.profile.unverified') }}
        </p>
        <Button
          type="submit"
          variant="secondary"
          :loading="savingEmail"
        >
          {{ t('account.profile.changeEmail') }}
        </Button>
      </form>
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
