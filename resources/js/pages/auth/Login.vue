<script setup lang="ts">
import FormField from '~/components/FormField.vue'
import { Alert } from '~/components/ui/alert'
import { Button } from '~/components/ui/button'
import { Card } from '~/components/ui/card'
import { useAuth } from '~/composables/useAuth'
import { useFormErrors } from '~/composables/useFormErrors'
import { runtimeConfig } from '~/lib/runtime-config'
import { useI18n } from '~/plugins/i18n'
import { errorCode } from '~/types/api'

const { t } = useI18n()
const auth = useAuth()
const route = useRoute()
const router = useRouter()
const errors = useFormErrors()

const email = ref('')
const password = ref('')
const totp = ref('')
const requiresTotp = ref(false)
const loading = ref(false)
const verified = computed(() => route.query.verified === '1')

const submit = async () => {
  loading.value = true
  errors.clear()

  try {
    await auth.login(email.value, password.value, requiresTotp.value ? totp.value : undefined)
    await router.push(auth.returnPath(route.query.return))
  } catch (error) {
    if (errorCode(error) === 'TOTP_VERIFICATION_REQUIRED') {
      requiresTotp.value = true
    } else {
      errors.capture(error)
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <Card class="p-6">
    <h1 class="mb-4 text-xl font-semibold">{{ t('auth.login.title') }}</h1>
    <Alert
      v-if="verified"
      class="mb-4"
    >
      {{ t('auth.login.verified') }}
    </Alert>
    <form
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
      <FormField
        id="password"
        v-model="password"
        type="password"
        autocomplete="current-password"
        :label="t('auth.fields.password')"
        :error="errors.fields.value.password"
        required
      />
      <FormField
        v-if="requiresTotp"
        id="totp"
        v-model="totp"
        autocomplete="one-time-code"
        :label="t('auth.fields.totp')"
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
        {{ t('auth.login.submit') }}
      </Button>
    </form>
    <div class="mt-4 flex justify-between text-sm">
      <RouterLink
        :to="{ name: 'forgot-password' }"
        class="underline"
      >
        {{ t('auth.login.forgot') }}
      </RouterLink>
      <RouterLink
        v-if="runtimeConfig.features.registration"
        :to="{ name: 'register' }"
        class="underline"
      >
        {{ t('auth.login.register') }}
      </RouterLink>
    </div>
  </Card>
</template>
