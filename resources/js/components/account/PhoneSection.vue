<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { toast } from 'vue-sonner'

import { api } from '~/api'
import SettingsSection from '~/components/account/SettingsSection.vue'
import FormField from '~/components/FormField.vue'
import Icon from '~/components/Icon.vue'
import { Alert } from '~/components/ui/alert'
import { Badge } from '~/components/ui/badge'
import { Button } from '~/components/ui/button'
import { InputOTP, InputOTPGroup, InputOTPSlot } from '~/components/ui/input-otp'
import { Label } from '~/components/ui/label'
import { useConfirm } from '~/composables/useConfirm'
import { useFormErrors } from '~/composables/useFormErrors'
import { runtimeConfig } from '~/lib/runtime-config'
import { useI18n } from '~/plugins/i18n'

/**
 * The phone the SMS channel depends on, in the two steps it can move: a code
 * goes to the number, the code comes back.
 *
 * The number is not saved on the way in. Until the code returns the account
 * shows a pending number, exactly as a pending email change does, because
 * both are "we have not proved this reaches you yet".
 */
const props = defineProps<{ phone: App.Data.PhoneData }>()

const emit = defineEmits<{ changed: [phone: App.Data.PhoneData] }>()

const { t } = useI18n()
const { confirm } = useConfirm()
const errors = useFormErrors()

const number = ref('')
const code = ref('')
const busy = ref(false)
const cooldown = ref(props.phone.resendIn)

let ticker: ReturnType<typeof setInterval> | undefined

const pending = computed(() => props.phone.pendingPhone)

/** Counts the resend window down locally so the button says when, not just no. */
const startCooldown = (seconds: number) => {
  cooldown.value = seconds
  clearInterval(ticker)

  if (seconds <= 0) return

  ticker = setInterval(() => {
    cooldown.value -= 1
    if (cooldown.value <= 0) clearInterval(ticker)
  }, 1000)
}

watch(() => props.phone.resendIn, startCooldown, { immediate: true })
onUnmounted(() => clearInterval(ticker))

const settle = (phone: App.Data.PhoneData) => {
  emit('changed', phone)
  startCooldown(phone.resendIn)
}

const sendCode = async () => {
  busy.value = true
  errors.clear()
  try {
    settle(await api.notifications.requestPhoneCode(number.value || (pending.value ?? '')))
    code.value = ''
    toast.success(t('account.phone.codeSent'))
  } catch (error) {
    errors.capture(error)
  } finally {
    busy.value = false
  }
}

const verify = async () => {
  busy.value = true
  errors.clear()
  try {
    settle(await api.notifications.verifyPhone(code.value))
    number.value = ''
    code.value = ''
    toast.success(t('account.phone.verified'))
  } catch (error) {
    code.value = ''
    errors.capture(error)
  } finally {
    busy.value = false
  }
}

const remove = async () => {
  const ok = await confirm({
    title: t('account.phone.removeConfirm.title'),
    message: t('account.phone.removeConfirm.message'),
    confirmLabel: t('account.phone.remove'),
    variant: 'destructive',
  })

  if (!ok) return

  busy.value = true
  try {
    settle(await api.notifications.removePhone())
    toast.success(t('account.phone.removed'))
  } catch (error) {
    errors.capture(error)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <SettingsSection
    v-if="runtimeConfig.features.sms"
    id="phone"
    :title="t('account.phone.title')"
    :description="t('account.phone.description')"
  >
    <div
      v-if="phone.verified"
      class="flex items-center justify-between gap-4"
    >
      <div class="flex min-w-0 items-center gap-2">
        <Icon
          name="lucide:smartphone"
          size="16"
          class="shrink-0 text-muted"
        />
        <span class="truncate text-sm font-medium text-primary">{{ phone.phone }}</span>
        <Badge
          variant="success"
          size="xs"
        >
          {{ t('account.phone.verifiedBadge') }}
        </Badge>
      </div>
    </div>

    <template v-else-if="pending">
      <Alert
        color="info"
        icon="lucide:message-square-text"
      >
        {{ t('account.phone.codeSentTo', { phone: pending }) }}
      </Alert>

      <div class="grid gap-1.5">
        <Label for="phone-code">{{ t('account.phone.code') }}</Label>
        <InputOTP
          id="phone-code"
          v-model="code"
          :maxlength="6"
          @complete="verify"
        >
          <InputOTPGroup>
            <InputOTPSlot
              v-for="index in 6"
              :key="index"
              :index="index - 1"
            />
          </InputOTPGroup>
        </InputOTP>
        <p
          v-if="errors.fields.value.code"
          class="text-sm text-destructive"
        >
          {{ errors.fields.value.code }}
        </p>
      </div>
    </template>

    <form
      v-else
      id="phone-form"
      class="grid gap-1.5"
      @submit.prevent="sendCode"
    >
      <FormField
        id="phone"
        v-model="number"
        type="tel"
        autocomplete="tel"
        :label="t('account.phone.number')"
        :description="t('account.phone.numberHint')"
        :error="errors.fields.value.phone"
        required
      />
    </form>

    <template #footer>
      <template v-if="phone.verified">
        <Button
          variant="outline"
          size="sm"
          :loading="busy"
          @click="remove"
        >
          {{ t('account.phone.remove') }}
        </Button>
      </template>

      <template v-else-if="pending">
        <Button
          variant="primary"
          size="sm"
          :loading="busy"
          :disabled="code.length < 6"
          @click="verify"
        >
          {{ t('account.phone.verify') }}
        </Button>
        <Button
          variant="ghost"
          size="sm"
          :disabled="busy || cooldown > 0"
          @click="sendCode"
        >
          {{
            cooldown > 0
              ? t('account.phone.resendIn', { seconds: cooldown })
              : t('account.phone.resend')
          }}
        </Button>
      </template>

      <Button
        v-else
        variant="primary"
        type="submit"
        form="phone-form"
        size="sm"
        :loading="busy"
        :disabled="number.trim() === ''"
      >
        {{ t('account.phone.send') }}
      </Button>
    </template>
  </SettingsSection>
</template>
