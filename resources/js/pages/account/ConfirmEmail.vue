<script setup lang="ts">
import { api } from '~/api'
import { Alert } from '~/components/ui/alert'
import { Button } from '~/components/ui/button'
import { Card } from '~/components/ui/card'
import { Spinner } from '~/components/ui/spinner'
import { useAuth } from '~/composables/useAuth'
import { errorMessage } from '~/lib/toast-error'
import { useI18n } from '~/plugins/i18n'

/**
 * The landing page for the link mailed to the new address. The token in the
 * URL is the proof, so this works in a browser that has never signed in.
 */
const { t } = useI18n()
const route = useRoute()
const auth = useAuth()

const state = ref<'working' | 'done' | 'failed'>('working')
const error = ref<string | null>(null)

const param = (name: string): string => {
  const value = route.query[name]
  const first = Array.isArray(value) ? value[0] : value

  return typeof first === 'string' ? first : ''
}

onMounted(async () => {
  const id = param('id')
  const token = param('token')

  if (!id || !token) {
    state.value = 'failed'
    error.value = t('account.confirmEmail.missingToken')
    return
  }

  try {
    await api.account.confirmEmailChange(id, token)
    state.value = 'done'
    // The signed-in tab, if this is one, is now looking at a stale address.
    if (auth.isAuthenticated.value) await auth.refresh()
  } catch (failure) {
    state.value = 'failed'
    error.value = errorMessage(failure)
  }
})
</script>

<template>
  <Card class="mx-auto w-full max-w-md space-y-4 p-6 text-center">
    <h1 class="text-xl font-semibold">{{ t('account.confirmEmail.title') }}</h1>

    <div
      v-if="state === 'working'"
      class="flex justify-center py-6"
    >
      <Spinner />
    </div>

    <template v-else-if="state === 'done'">
      <Alert color="success">{{ t('account.confirmEmail.done') }}</Alert>
      <Button
        variant="primary"
        as-child
      >
        <RouterLink :to="{ name: auth.isAuthenticated.value ? 'account-profile' : 'login' }">
          {{ t('account.confirmEmail.continue') }}
        </RouterLink>
      </Button>
    </template>

    <template v-else>
      <Alert color="destructive">{{ error ?? t('account.confirmEmail.failed') }}</Alert>
      <Button
        variant="default"
        as-child
      >
        <RouterLink :to="{ name: 'login' }">
          {{ t('account.confirmEmail.backToSignIn') }}
        </RouterLink>
      </Button>
    </template>
  </Card>
</template>
