<script setup lang="ts">
import { api } from '~/api'
import FormField from '~/components/FormField.vue'
import { Alert } from '~/components/ui/alert'
import { Button } from '~/components/ui/button'
import { Card } from '~/components/ui/card'
import { useFormErrors } from '~/composables/useFormErrors'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const errors = useFormErrors()

const form = reactive({
  email: typeof route.query.email === 'string' ? route.query.email : '',
  password: '',
  password_confirmation: '',
})
const loading = ref(false)

const submit = async () => {
  loading.value = true
  errors.clear()
  try {
    await api.auth.resetPassword({
      ...form,
      token: typeof route.query.token === 'string' ? route.query.token : '',
    })
    await router.push({ name: 'login' })
  } catch (error) {
    errors.capture(error)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Card class="p-6">
    <h1 class="mb-4 text-xl font-semibold">{{ t('auth.reset.title') }}</h1>
    <form
      class="space-y-4"
      @submit.prevent="submit"
    >
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
        color="destructive"
      >
        {{ errors.message.value }}
      </Alert>
      <Button
        variant="primary"
        type="submit"
        class="w-full"
        :loading="loading"
      >
        {{ t('auth.reset.submit') }}
      </Button>
    </form>
  </Card>
</template>
