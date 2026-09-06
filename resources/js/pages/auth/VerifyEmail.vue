<script setup lang="ts">
import { toast } from 'vue-sonner'

import { api } from '~/api'
import { Button } from '~/components/ui/button'
import { Card } from '~/components/ui/card'
import { useAuth } from '~/composables/useAuth'
import { toastError } from '~/lib/toast-error'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const auth = useAuth()
const loading = ref(false)

const resend = async () => {
  loading.value = true
  try {
    await api.auth.resendVerification()
    toast.success(t('auth.verify.sent'))
  } catch (error) {
    toastError(t, 'auth.verify.error', error)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Card class="p-6">
    <h1 class="mb-2 text-xl font-semibold">{{ t('auth.verify.title') }}</h1>
    <p class="mb-4 text-sm text-muted">{{ t('auth.verify.description') }}</p>
    <Button
      variant="primary"
      v-if="auth.isAuthenticated.value"
      :loading="loading"
      @click="resend"
    >
      {{ t('auth.verify.resend') }}
    </Button>
    <RouterLink
      v-else
      :to="{ name: 'login' }"
      class="text-sm underline"
    >
      {{ t('auth.register.login') }}
    </RouterLink>
  </Card>
</template>
