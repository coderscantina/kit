<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { computed, ref, watch } from 'vue'
import { toast } from 'vue-sonner'

import { api } from '~/api'
import NotificationPreferencesSection from '~/components/account/NotificationPreferencesSection.vue'
import NotificationsSection from '~/components/account/NotificationsSection.vue'
import PhoneSection from '~/components/account/PhoneSection.vue'
import SettingsPage from '~/components/account/SettingsPage.vue'
import { Skeleton } from '~/components/ui/skeleton'
import { usePageMeta } from '~/composables/usePageMeta'
import { usePushNotifications } from '~/composables/usePushNotifications'
import { queryKeys } from '~/lib/query-keys'
import { toastError } from '~/lib/toast-error'
import { useI18n } from '~/plugins/i18n'

/**
 * Where notifications are configured: what reaches this account, on which
 * channels, and the two prerequisites those channels have — a browser that
 * has allowed push, and a phone that has answered its code.
 *
 * Toggles save on change rather than behind a Save button. A settings matrix
 * with thirty switches and one commit is a screen people leave half-applied,
 * and every cell here is independently reversible.
 *
 * A failed save puts the switch back. The server's answer is the state, never
 * the optimistic guess.
 */
const { t } = useI18n()
const push = usePushNotifications()

usePageMeta(() => ({
  title: t('account.notificationsPage.title'),
  breadcrumbs: [{ label: t('account.title') }, { label: t('account.notificationsPage.title') }],
}))

const settings = ref<App.Data.NotificationSettingsData | null>(null)
const saving = ref(false)

const query = useQuery({
  queryKey: queryKeys.notificationSettings(),
  queryFn: () => api.notifications.settings(),
})

watch(
  () => query.data.value,
  (value) => {
    if (value) settings.value = value
  },
  { immediate: true }
)

/** The matrix as the endpoint wants it: every type, with its enabled channels. */
const preferences = computed<Record<string, string[]>>(() =>
  Object.fromEntries((settings.value?.types ?? []).map((type) => [type.key, [...type.enabled]]))
)

const toggle = async (type: string, channel: string, on: boolean) => {
  const current = settings.value
  if (!current) return

  const next = { ...preferences.value }
  next[type] = on
    ? [...(next[type] ?? []), channel]
    : (next[type] ?? []).filter((entry) => entry !== channel)

  saving.value = true

  try {
    settings.value = await api.notifications.saveSettings(next)
  } catch (error) {
    // The server's answer is the state; nothing here guesses it back.
    settings.value = current
    toastError(t, 'account.notificationPrefs.error', error)
  } finally {
    saving.value = false
  }
}

/**
 * A channel that just became reachable changes what the matrix has to say,
 * so signing this browser up or verifying a number reloads the payload.
 */
const refresh = async () => {
  settings.value = await api.notifications.settings()
}

watch(() => push.subscribed.value, refresh)

const onPhoneChanged = (phone: App.Data.PhoneData) => {
  if (settings.value) settings.value = { ...settings.value, phone }
  void refresh()
}
</script>

<template>
  <SettingsPage
    :title="t('account.notificationsPage.title')"
    :description="t('account.notificationsPage.description')"
  >
    <NotificationPreferencesSection
      v-if="settings"
      :settings="settings"
      :saving="saving"
      @toggle="toggle"
    />
    <div
      v-else
      class="grid gap-3 py-8"
    >
      <Skeleton class="h-4 w-40" />
      <Skeleton class="h-24 w-full" />
    </div>

    <NotificationsSection />

    <PhoneSection
      v-if="settings"
      :phone="settings.phone"
      @changed="onPhoneChanged"
    />
  </SettingsPage>
</template>
