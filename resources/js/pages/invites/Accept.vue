<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'

import { api } from '~/api'
import FormField from '~/components/FormField.vue'
import { Alert } from '~/components/ui/alert'
import { Button } from '~/components/ui/button'
import { Card } from '~/components/ui/card'
import { useAuth } from '~/composables/useAuth'
import { useFormErrors } from '~/composables/useFormErrors'
import { queryKeys } from '~/lib/query-keys'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuth()
const errors = useFormErrors()

const id = computed(() => String(route.params.id ?? ''))
const token = computed(() => (typeof route.query.token === 'string' ? route.query.token : ''))

const invite = useQuery({
  queryKey: computed(() => queryKeys.publicInvite(id.value, token.value)),
  queryFn: () => api.invites.show(id.value, token.value),
  enabled: computed(() => id.value !== '' && token.value !== ''),
  retry: false,
})

const form = reactive({ name: '', password: '', password_confirmation: '' })
const loading = ref(false)
const declined = ref(false)

// A signed-in user with the invited address accepts in place; anyone else
// creates the account the invite is for.
const registering = computed(() => !auth.isAuthenticated.value)

const accept = async () => {
  loading.value = true
  errors.clear()
  try {
    await api.invites.accept(
      id.value,
      registering.value ? { token: token.value, ...form } : { token: token.value }
    )
    await auth.refresh()
    await router.push({ name: 'dashboard' })
  } catch (error) {
    errors.capture(error)
  } finally {
    loading.value = false
  }
}

const decline = async () => {
  loading.value = true
  try {
    await api.invites.decline(id.value, token.value)
    declined.value = true
  } catch (error) {
    errors.capture(error)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Card class="p-6">
    <h1 class="mb-4 text-xl font-semibold">{{ t('invites.accept.title') }}</h1>
    <Alert v-if="declined">{{ t('invites.accept.declined') }}</Alert>
    <Alert
      v-else-if="invite.isError.value"
      color="destructive"
    >
      {{ t('invites.accept.invalid') }}
    </Alert>
    <p
      v-else-if="invite.isPending.value"
      class="text-sm text-muted"
    >
      {{ t('app.loading') }}
    </p>
    <form
      v-else-if="invite.data.value"
      class="space-y-4"
      @submit.prevent="accept"
    >
      <p class="text-sm">
        {{
          t('invites.accept.description', {
            email: invite.data.value.email,
            role: invite.data.value.role,
            by: invite.data.value.invitedBy ?? '',
          })
        }}
      </p>
      <template v-if="registering">
        <FormField
          id="name"
          v-model="form.name"
          autocomplete="name"
          :label="t('auth.fields.name')"
          :error="errors.fields.value.name"
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
      </template>
      <Alert
        v-if="errors.message.value"
        color="destructive"
      >
        {{ errors.message.value }}
      </Alert>
      <div class="flex gap-2">
        <Button
          variant="primary"
          type="submit"
          class="flex-1"
          :loading="loading"
        >
          {{ t('invites.accept.submit') }}
        </Button>
        <Button
          type="button"
          variant="outline"
          :disabled="loading"
          @click="decline"
        >
          {{ t('invites.accept.decline') }}
        </Button>
      </div>
    </form>
  </Card>
</template>
