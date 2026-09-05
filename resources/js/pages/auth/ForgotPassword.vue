<script setup lang="ts">
import { api } from '~/api'
import FormField from '~/components/FormField.vue'
import { Alert } from '~/components/ui/alert'
import { Button } from '~/components/ui/button'
import { Card } from '~/components/ui/card'
import { useFormErrors } from '~/composables/useFormErrors'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const errors = useFormErrors()
const email = ref('')
const loading = ref(false)
const sent = ref(false)

const submit = async () => {
  loading.value = true
  errors.clear()
  try {
    await api.auth.forgotPassword(email.value)
    sent.value = true
  } catch (error) {
    errors.capture(error)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Card class="p-6">
    <h1 class="mb-4 text-xl font-semibold">{{ t('auth.forgot.title') }}</h1>
    <Alert v-if="sent">{{ t('auth.forgot.sent') }}</Alert>
    <form
      v-else
      class="space-y-4"
      @submit.prevent="submit"
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
        {{ t('auth.forgot.submit') }}
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
