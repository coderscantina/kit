<script setup lang="ts">
import { toast } from 'vue-sonner'

import { api } from '~/api'
import AuthPanel from '~/components/app/AuthPanel.vue'
import Icon from '~/components/Icon.vue'
import { Button } from '~/components/ui/button'
import { useAuth } from '~/composables/useAuth'
import { usePageMeta } from '~/composables/usePageMeta'
import { toastError } from '~/lib/toast-error'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const auth = useAuth()

const loading = ref(false)
const cooldown = ref(0)

usePageMeta(() => ({ title: t('auth.verify.title') }))

let timer: ReturnType<typeof setInterval> | undefined

/**
 * A resend button that stays live invites five identical emails and a rate
 * limit the user cannot see coming. Thirty seconds, counted down on the label.
 */
const startCooldown = () => {
  cooldown.value = 30
  timer = setInterval(() => {
    cooldown.value -= 1
    if (cooldown.value <= 0) clearInterval(timer)
  }, 1000)
}

onUnmounted(() => clearInterval(timer))

const resend = async () => {
  if (loading.value || cooldown.value > 0) return

  loading.value = true

  try {
    await api.auth.resendVerification()
    toast.success(t('auth.verify.sent'))
    startCooldown()
  } catch (error) {
    toastError(t, 'auth.verify.error', error)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthPanel
    :title="t('auth.verify.title')"
    :description="t('auth.verify.description')"
  >
    <div class="flex items-center gap-3 rounded-lg border border-border bg-card p-4">
      <Icon
        name="lucide:mail"
        size="20"
        class="shrink-0 text-accent"
      />
      <p class="min-w-0 truncate text-sm text-primary">
        {{ auth.user.value?.email ?? t('auth.verify.yourAddress') }}
      </p>
    </div>

    <Button
      v-if="auth.isAuthenticated.value"
      variant="accent"
      class="w-full"
      :loading="loading"
      :disabled="cooldown > 0"
      @click="resend"
    >
      {{ cooldown > 0 ? t('auth.verify.retryIn', { seconds: cooldown }) : t('auth.verify.resend') }}
    </Button>

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
