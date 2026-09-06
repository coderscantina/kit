<script setup lang="ts">
import { api } from '~/api'
import AuthPanel from '~/components/app/AuthPanel.vue'
import FormField from '~/components/FormField.vue'
import { Alert } from '~/components/ui/alert'
import { Button } from '~/components/ui/button'
import { useAuth } from '~/composables/useAuth'
import { useFormErrors } from '~/composables/useFormErrors'
import { usePageMeta } from '~/composables/usePageMeta'
import { useI18n } from '~/plugins/i18n'

const { t, locale } = useI18n()
const auth = useAuth()
const router = useRouter()
const errors = useFormErrors()

const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const loading = ref(false)

usePageMeta(() => ({ title: t('auth.register.title') }))

/** Client-side, before the round trip: a mismatch the server has to tell you about is a slow no. */
const mismatch = computed(
  () => form.password_confirmation.length > 0 && form.password !== form.password_confirmation
)

const submit = async () => {
  if (loading.value || mismatch.value) return

  loading.value = true
  errors.clear()

  try {
    await api.auth.register({ ...form, locale: locale.value })
    await auth.refresh()
    await router.push({ name: 'verify-email' })
  } catch (error) {
    errors.capture(error)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthPanel
    :title="t('auth.register.title')"
    :description="t('auth.register.description')"
  >
    <form
      class="grid gap-5"
      @submit.prevent="submit"
    >
      <FormField
        id="name"
        v-model="form.name"
        autocomplete="name"
        autofocus
        :label="t('auth.fields.name')"
        :error="errors.fields.value.name"
        required
      />
      <FormField
        id="email"
        v-model="form.email"
        type="email"
        autocomplete="username"
        :label="t('auth.fields.email')"
        :error="errors.fields.value.email"
        required
      />
      <FormField
        id="password"
        v-model="form.password"
        type="password"
        autocomplete="new-password"
        :label="t('auth.fields.password')"
        :description="t('auth.fields.passwordHint')"
        :error="errors.fields.value.password"
        required
      />
      <FormField
        id="password_confirmation"
        v-model="form.password_confirmation"
        type="password"
        autocomplete="new-password"
        :label="t('auth.fields.passwordConfirmation')"
        :error="mismatch ? t('auth.fields.passwordMismatch') : undefined"
        required
      />

      <Alert
        v-if="errors.message.value"
        color="destructive"
        icon="lucide:circle-alert"
      >
        {{ errors.message.value }}
      </Alert>

      <Button
        variant="accent"
        type="submit"
        class="w-full"
        :loading="loading"
      >
        {{ t('auth.register.submit') }}
      </Button>
    </form>

    <template #footer>
      {{ t('auth.register.haveAccount') }}
      <RouterLink
        :to="{ name: 'login' }"
        class="font-medium text-primary hover:underline"
      >
        {{ t('auth.register.login') }}
      </RouterLink>
    </template>
  </AuthPanel>
</template>
