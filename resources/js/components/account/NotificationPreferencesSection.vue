<script setup lang="ts">
import { computed } from 'vue'

import SettingsSection from '~/components/account/SettingsSection.vue'
import Icon from '~/components/Icon.vue'
import { Switch } from '~/components/ui/switch'
import { Tooltip, TooltipContent, TooltipTrigger } from '~/components/ui/tooltip'
import { messageKeyFor, notificationChannelIcons } from '~/lib/notifications'
import { useI18n } from '~/plugins/i18n'

/**
 * The matrix: one row per notification the app can send, one column per
 * channel it can send on.
 *
 * Built entirely from the server payload, which is built from the
 * #[NotificationType] classes that exist, so the screen cannot offer a switch
 * for something that was deleted or miss one that was just added.
 *
 * Three states have to stay apart per cell, because the recoveries differ:
 * the type does not offer this channel at all (nothing rendered), it does but
 * the account cannot be reached there yet (switch, with the reason), and it
 * cannot be turned off (switch, locked on, with the reason).
 *
 * On a phone the matrix stops being a grid: each notification becomes a card
 * with its channels listed under it, because three columns of 44px targets on
 * a 360px screen is a mis-tap waiting to happen.
 */
const props = defineProps<{
  settings: App.Data.NotificationSettingsData
  saving?: boolean
}>()

const emit = defineEmits<{
  toggle: [type: string, channel: string, enabled: boolean]
}>()

const { t, te } = useI18n()

/** Only the channels this installation actually has; the rest are not choices. */
const channels = computed(() => props.settings.channels.filter((channel) => channel.available))

const groups = computed(() => {
  const seen: string[] = []

  for (const type of props.settings.types) {
    if (!seen.includes(type.group)) seen.push(type.group)
  }

  return seen.map((group) => ({
    key: group,
    label: te(`notifications.groups.${group}`) ? t(`notifications.groups.${group}`) : group,
    types: props.settings.types.filter((type) => type.group === group),
  }))
})

const label = (type: App.Data.NotificationTypeData) => {
  const key = `notifications.types.${messageKeyFor(type.key)}.label`

  return te(key) ? t(key) : type.key
}

const hint = (type: App.Data.NotificationTypeData) => {
  const key = `notifications.types.${messageKeyFor(type.key)}.hint`

  return te(key) ? t(key) : ''
}

const offers = (type: App.Data.NotificationTypeData, channel: string) =>
  type.channels.includes(channel)

const enabled = (type: App.Data.NotificationTypeData, channel: string) =>
  type.enabled.includes(channel)

const required = (type: App.Data.NotificationTypeData, channel: string) =>
  type.required.includes(channel)

const deliverable = (channel: string) =>
  channels.value.find((entry) => entry.key === channel)?.deliverable ?? false

/**
 * Why a switch is locked or a channel is not reachable. Empty when there is
 * nothing to explain, so the cell stays quiet in the ordinary case.
 */
const reason = (type: App.Data.NotificationTypeData, channel: string): string => {
  if (required(type, channel)) return t('account.notificationPrefs.required')
  if (enabled(type, channel) && !deliverable(channel)) {
    return t(`account.notificationPrefs.unreachable.${channel}`)
  }

  return ''
}
</script>

<template>
  <SettingsSection
    id="notification-preferences"
    :title="t('account.notificationPrefs.title')"
    :description="t('account.notificationPrefs.description')"
  >
    <template #aside>
      <p class="pt-2 text-xs text-pretty text-muted">
        {{ t('account.notificationPrefs.ladder', { minutes: settings.escalationMinutes }) }}
      </p>
    </template>

    <div
      v-for="group in groups"
      :key="group.key"
      class="grid gap-2"
    >
      <p class="text-xs font-semibold tracking-wide text-muted uppercase">{{ group.label }}</p>

      <!-- Wide: a real grid with one header row. -->
      <div class="hidden @2xl:grid @2xl:gap-1">
        <div
          class="grid items-end gap-3 px-3 pb-1 text-2xs font-medium text-muted uppercase"
          :style="{ gridTemplateColumns: `minmax(0,1fr) repeat(${channels.length}, 4.5rem)` }"
        >
          <span />
          <span
            v-for="channel in channels"
            :key="channel.key"
            class="text-center"
          >
            {{ t(`notifications.channels.${channel.key}`) }}
          </span>
        </div>

        <div
          v-for="type in group.types"
          :key="type.key"
          class="grid items-center gap-3 rounded-lg px-3 py-2.5 transition-colors duration-200 hover:bg-secondary/50"
          :style="{ gridTemplateColumns: `minmax(0,1fr) repeat(${channels.length}, 4.5rem)` }"
        >
          <div class="grid min-w-0 gap-0.5">
            <span class="truncate text-sm font-medium text-primary">{{ label(type) }}</span>
            <span
              v-if="hint(type)"
              class="text-xs text-pretty text-muted"
            >
              {{ hint(type) }}
            </span>
          </div>

          <div
            v-for="channel in channels"
            :key="channel.key"
            class="flex items-center justify-center"
          >
            <Tooltip v-if="offers(type, channel.key)">
              <TooltipTrigger as-child>
                <span class="inline-flex items-center gap-1">
                  <Switch
                    :model-value="enabled(type, channel.key)"
                    :disabled="saving || required(type, channel.key)"
                    :aria-label="`${label(type)} — ${t(`notifications.channels.${channel.key}`)}`"
                    @update:model-value="emit('toggle', type.key, channel.key, $event)"
                  />
                  <Icon
                    v-if="reason(type, channel.key)"
                    :name="required(type, channel.key) ? 'lucide:lock' : 'lucide:triangle-alert'"
                    size="12"
                    class="text-muted"
                    aria-hidden="true"
                  />
                </span>
              </TooltipTrigger>
              <TooltipContent v-if="reason(type, channel.key)">
                {{ reason(type, channel.key) }}
              </TooltipContent>
            </Tooltip>

            <span
              v-else
              class="text-xs text-muted"
              :aria-label="t('account.notificationPrefs.notOffered')"
            >
              —
            </span>
          </div>
        </div>
      </div>

      <!-- Narrow: one card per notification, channels listed under it. -->
      <div class="grid gap-2 @2xl:hidden">
        <div
          v-for="type in group.types"
          :key="type.key"
          class="grid gap-2 rounded-lg border border-border p-3"
        >
          <div class="grid gap-0.5">
            <span class="text-sm font-medium text-primary">{{ label(type) }}</span>
            <span
              v-if="hint(type)"
              class="text-xs text-pretty text-muted"
            >
              {{ hint(type) }}
            </span>
          </div>

          <div class="grid gap-1">
            <label
              v-for="channel in channels.filter((entry) => offers(type, entry.key))"
              :key="channel.key"
              class="flex items-center justify-between gap-3 py-1"
            >
              <span class="flex items-center gap-2 text-sm text-primary">
                <Icon
                  :name="notificationChannelIcons[channel.key] ?? 'lucide:send'"
                  size="14"
                  class="text-muted"
                  aria-hidden="true"
                />
                {{ t(`notifications.channels.${channel.key}`) }}
                <span
                  v-if="reason(type, channel.key)"
                  class="text-xs text-muted"
                >
                  {{ reason(type, channel.key) }}
                </span>
              </span>
              <Switch
                :model-value="enabled(type, channel.key)"
                :disabled="saving || required(type, channel.key)"
                @update:model-value="emit('toggle', type.key, channel.key, $event)"
              />
            </label>
          </div>
        </div>
      </div>
    </div>
  </SettingsSection>
</template>
