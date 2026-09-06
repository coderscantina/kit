<script setup lang="ts">
import AuthPanel from '~/components/app/AuthPanel.vue'
import FormField from '~/components/FormField.vue'
import { Alert } from '~/components/ui/alert'
import { Button } from '~/components/ui/button'
import { Checkbox } from '~/components/ui/checkbox'
import { InputOTP, InputOTPGroup, InputOTPSlot } from '~/components/ui/input-otp'
import { Label } from '~/components/ui/label'
import { useAuth } from '~/composables/useAuth'
import { useFormErrors } from '~/composables/useFormErrors'
import { usePageMeta } from '~/composables/usePageMeta'
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
const remember = ref(false)
const totp = ref('')
const requiresTotp = ref(false)
const loading = ref(false)

const verified = computed(() => route.query.verified === '1')

usePageMeta(() => ({ title: t('auth.login.title') }))

const submit = async () => {
  if (loading.value) return

  loading.value = true
  errors.clear()

  /**
   * Password-manager inline menus close on focusout. Submitting navigates
   * away, so an autofilled field would unmount while focused and leave the
   * menu floating over the dashboard. Blur first.
   */
  if (document.activeElement instanceof HTMLElement) document.activeElement.blur()

  try {
    await auth.login(
      email.value,
      password.value,
      requiresTotp.value ? totp.value : undefined,
      remember.value
    )
    await router.push(auth.returnPath(route.query.return))
  } catch (error) {
    if (errorCode(error) === 'TOTP_VERIFICATION_REQUIRED') {
      requiresTotp.value = true
      totp.value = ''
    } else {
      // A rejected code is a retry, not a restart: keep the step, clear the code.
      if (requiresTotp.value) totp.value = ''
      errors.capture(error)
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthPanel
    :title="requiresTotp ? t('auth.login.totpTitle') : t('auth.login.title')"
    :description="requiresTotp ? t('auth.login.totpDescription') : t('auth.login.description')"
  >
    <Alert
      v-if="verified && !requiresTotp"
      color="success"
      variant="modern"
      icon="lucide:mail-check"
    >
      {{ t('auth.login.verified') }}
    </Alert>

    <form
      class="grid gap-5"
      @submit.prevent="submit"
    >
      <!-- The credentials stay mounted through the TOTP step so the browser
           keeps the autofilled values; hiding them would drop the password. -->
      <template v-if="!requiresTotp">
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
        <div class="grid gap-2">
          <FormField
            id="password"
            v-model="password"
            type="password"
            autocomplete="current-password"
            :label="t('auth.fields.password')"
            :error="errors.fields.value.password"
            required
          />
          <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
              <Checkbox
                id="remember"
                v-model="remember"
              />
              <Label
                for="remember"
                class="cursor-pointer text-muted"
              >
                {{ t('auth.login.remember') }}
              </Label>
            </div>
            <RouterLink
              :to="{ name: 'forgot-password' }"
              class="text-sm text-muted hover:text-primary hover:underline"
            >
              {{ t('auth.login.forgot') }}
            </RouterLink>
          </div>
        </div>
      </template>

      <div
        v-else
        class="grid justify-items-center gap-3"
      >
        <InputOTP
          v-model="totp"
          :maxlength="6"
          autofocus
          @complete="submit"
        >
          <InputOTPGroup>
            <InputOTPSlot
              v-for="index in 6"
              :key="index"
              :index="index - 1"
            />
          </InputOTPGroup>
        </InputOTP>
        <button
          type="button"
          class="cursor-pointer text-sm text-muted hover:text-primary hover:underline"
          @click="((requiresTotp = false), (totp = ''), errors.clear())"
        >
          {{ t('auth.login.totpBack') }}
        </button>
      </div>

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
        {{ t('auth.login.submit') }}
      </Button>
    </form>

    <template
      v-if="runtimeConfig.features.registration && !requiresTotp"
      #footer
    >
      {{ t('auth.login.noAccount') }}
      <RouterLink
        :to="{ name: 'register' }"
        class="font-medium text-primary hover:underline"
      >
        {{ t('auth.login.register') }}
      </RouterLink>
    </template>
  </AuthPanel>
</template>
