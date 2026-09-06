<script setup lang="ts">
import { toast } from 'vue-sonner'

import { api } from '~/api'
import AuthPanel from '~/components/app/AuthPanel.vue'
import FormField from '~/components/FormField.vue'
import { Alert } from '~/components/ui/alert'
import { Button } from '~/components/ui/button'
import { useFormErrors } from '~/composables/useFormErrors'
import { usePageMeta } from '~/composables/usePageMeta'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const errors = useFormErrors()

const token = computed(() => (typeof route.query.token === 'string' ? route.query.token : ''))

const form = reactive({
  email: typeof route.query.email === 'string' ? route.query.email : '',
  password: '',
  password_confirmation: '',
})
const loading = ref(false)

usePageMeta(() => ({ title: t('auth.reset.title') }))

const mismatch = computed(
  () => form.password_confirmation.length > 0 && form.password !== form.password_confirmation
)

const submit = async () => {
  if (loading.value || mismatch.value) return

  loading.value = true
  errors.clear()

  try {
    await api.auth.resetPassword({ ...form, token: token.value })
    toast.success(t('auth.reset.done'))
    await router.push({ name: 'login' })
  } catch (error) {
    errors.capture(error)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthPanel
    :title="t('auth.reset.title')"
    :description="token ? t('auth.reset.description') : undefined"
  >
    <!-- A reset link without a token is a truncated email, not a form to fill
         in. Say so and offer a new link instead of failing on submit. -->
    <template v-if="!token">
      <Alert
        color="warning"
        variant="modern"
        icon="lucide:link-2-off"
      >
        {{ t('auth.reset.missingToken') }}
      </Alert>
      <Button
        variant="accent"
        as-child
      >
        <RouterLink :to="{ name: 'forgot-password' }">{{ t('auth.reset.requestNew') }}</RouterLink>
      </Button>
    </template>

    <form
      v-else
      class="grid gap-5"
      @submit.prevent="submit"
    >
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
        autofocus
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
        {{ t('auth.reset.submit') }}
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
