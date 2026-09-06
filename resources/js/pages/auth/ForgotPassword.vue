<script setup lang="ts">
import { api } from '~/api'
import AuthPanel from '~/components/app/AuthPanel.vue'
import FormField from '~/components/FormField.vue'
import { Alert } from '~/components/ui/alert'
import { Button } from '~/components/ui/button'
import { useFormErrors } from '~/composables/useFormErrors'
import { usePageMeta } from '~/composables/usePageMeta'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const errors = useFormErrors()

const email = ref('')
const loading = ref(false)
const sent = ref(false)

usePageMeta(() => ({ title: t('auth.forgot.title') }))

const submit = async () => {
  if (loading.value) return

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
  <AuthPanel
    :title="t('auth.forgot.title')"
    :description="sent ? undefined : t('auth.forgot.description')"
  >
    <!-- The confirmation replaces the form. Leaving it up invites a second
         send that says the same thing again. -->
    <Alert
      v-if="sent"
      color="success"
      variant="modern"
      icon="lucide:mail-check"
    >
      {{ t('auth.forgot.sent') }}
    </Alert>

    <form
      v-else
      class="grid gap-5"
      @submit.prevent="submit"
    >
      <FormField
        id="email"
        v-model="email"
        type="email"
        autocomplete="username"
        autofocus
        :label="t('auth.fields.email')"
        :error="errors.fields.value.email"
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
        {{ t('auth.forgot.submit') }}
      </Button>
    </form>

    <template #footer>
      <RouterLink
        :to="{ name: 'login' }"
        class="font-medium text-primary hover:underline"
      >
        {{ t('auth.register.login') }}
      </RouterLink>
    </template>
  </AuthPanel>
</template>
