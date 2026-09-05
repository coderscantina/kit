<script setup lang="ts">
import { api } from '~/api'
import FormField from '~/components/FormField.vue'
import { Alert } from '~/components/ui/alert'
import { Button } from '~/components/ui/button'
import { Card } from '~/components/ui/card'
import { useAuth } from '~/composables/useAuth'
import { useFormErrors } from '~/composables/useFormErrors'
import { useI18n } from '~/plugins/i18n'

const { t, locale } = useI18n()
const auth = useAuth()
const router = useRouter()
const errors = useFormErrors()

const form = reactive({ name: '', email: '', password: '', password_confirmation: '' })
const loading = ref(false)

const submit = async () => {
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
  <Card class="p-6">
    <h1 class="mb-4 text-xl font-semibold">{{ t('auth.register.title') }}</h1>
    <form
      class="space-y-4"
      @submit.prevent="submit"
    >
      <FormField
        id="name"
        v-model="form.name"
        autocomplete="name"
        :label="t('auth.fields.name')"
        :error="errors.fields.value.name"
        required
      />
      <FormField
        id="email"
        v-model="form.email"
        type="email"
        autocomplete="email"
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
        :error="errors.fields.value.password"
        required
      />
      <FormField
        id="password_confirmation"
        v-model="form.password_confirmation"
        type="password"
        autocomplete="new-password"
        :label="t('auth.fields.passwordConfirmation')"
        required
      />
      <Alert
        v-if="errors.message.value"
        variant="destructive"
      >
        {{ errors.message.value }}
      </Alert>
      <Button
        type="submit"
        class="w-full"
        :loading="loading"
      >
        {{ t('auth.register.submit') }}
      </Button>
    </form>
    <p class="mt-4 text-sm">
      <RouterLink
        :to="{ name: 'login' }"
        class="underline"
      >
        {{ t('auth.register.login') }}
      </RouterLink>
    </p>
  </Card>
</template>
