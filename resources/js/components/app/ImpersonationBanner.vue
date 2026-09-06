<script setup lang="ts">
import { toast } from 'vue-sonner'

import { api } from '~/api'
import Icon from '~/components/Icon.vue'
import { useAuth } from '~/composables/useAuth'
import { toastError } from '~/lib/toast-error'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const auth = useAuth()
const router = useRouter()

const leaving = ref(false)

/**
 * A banner that only says "you are impersonating" leaves the operator hunting
 * for the way back, so the way back is the banner.
 */
const stop = async () => {
  leaving.value = true

  try {
    await api.auth.stopImpersonating()
    await auth.refresh()
    toast.success(t('auth.impersonation.stopped'))
    await router.push({ name: 'dashboard' })
  } catch (error) {
    toastError(t, 'auth.impersonation.error', error)
  } finally {
    leaving.value = false
  }
}
</script>

<template>
  <div
    v-if="auth.me.value?.impersonating"
    role="status"
    class="flex items-center justify-center gap-3 bg-warning-background/20 px-shell-gutter-x py-1.5 text-xs text-warning"
  >
    <Icon
      name="lucide:venetian-mask"
      size="14"
      aria-hidden="true"
    />
    <span>{{ t('auth.impersonation.active', { name: auth.user.value?.name ?? '' }) }}</span>
    <button
      type="button"
      class="cursor-pointer font-semibold underline underline-offset-2 disabled:opacity-60"
      :disabled="leaving"
      @click="stop"
    >
      {{ t('auth.impersonation.stop') }}
    </button>
  </div>
</template>
