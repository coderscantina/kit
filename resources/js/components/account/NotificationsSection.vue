<script setup lang="ts">
import { onMounted } from 'vue'
import { toast } from 'vue-sonner'

import SettingsSection from '~/components/account/SettingsSection.vue'
import Icon from '~/components/Icon.vue'
import { Alert } from '~/components/ui/alert'
import { Button } from '~/components/ui/button'
import { Switch } from '~/components/ui/switch'
import { usePushNotifications } from '~/composables/usePushNotifications'
import { runtimeConfig } from '~/lib/runtime-config'
import { toastError } from '~/lib/toast-error'
import { useI18n } from '~/plugins/i18n'

/**
 * Push notifications for this browser, and only this browser: a subscription
 * belongs to the device that made it, so the switch says "this device" rather
 * than pretending to be an account-wide preference.
 *
 * The permission prompt is behind the switch on purpose. A prompt fired on
 * page load is the one users deny reflexively, and a denial can only be
 * undone in the browser's own site settings — which is what the blocked state
 * below has to explain, because no button here can fix it.
 */
const { t } = useI18n()
const push = usePushNotifications()

onMounted(() => void push.refresh())

const toggle = async (enabled: boolean) => {
  try {
    if (enabled) {
      await push.subscribe()

      if (push.blocked.value) toast.error(t('account.notifications.blockedToast'))
      else if (push.subscribed.value) toast.success(t('account.notifications.enabled'))
    } else {
      await push.unsubscribe()
      toast.success(t('account.notifications.disabled'))
    }
  } catch (error) {
    toastError(t, 'account.notifications.error', error)
  }
}

const sendTest = async () => {
  try {
    await push.sendTest()
    toast.success(t('account.notifications.testSent'))
  } catch (error) {
    toastError(t, 'account.notifications.error', error)
  }
}
</script>

<template>
  <SettingsSection
    v-if="runtimeConfig.features.push"
    id="notifications"
    :title="t('account.notifications.title')"
    :description="t('account.notifications.description')"
  >
    <Alert
      v-if="!push.supported"
      color="info"
      icon="lucide:info"
    >
      {{ t('account.notifications.unsupported') }}
    </Alert>

    <template v-else>
      <div class="flex items-center justify-between gap-4">
        <div class="min-w-0">
          <p class="text-sm font-medium text-primary">
            {{ t('account.notifications.thisDevice') }}
          </p>
          <p class="text-xs text-muted">
            {{
              push.subscribed.value ? t('account.notifications.on') : t('account.notifications.off')
            }}
          </p>
        </div>
        <Switch
          :model-value="push.subscribed.value"
          :disabled="push.busy.value || push.blocked.value || !push.ready.value"
          :aria-label="t('account.notifications.thisDevice')"
          @update:model-value="toggle($event)"
        />
      </div>

      <Alert
        v-if="push.blocked.value"
        color="warning"
        icon="lucide:bell-off"
      >
        {{ t('account.notifications.blocked') }}
      </Alert>
    </template>

    <template
      v-if="push.supported && push.subscribed.value"
      #footer
    >
      <Button
        variant="outline"
        size="sm"
        @click="sendTest"
      >
        <Icon name="lucide:send" />
        {{ t('account.notifications.test') }}
      </Button>
    </template>
  </SettingsSection>
</template>
