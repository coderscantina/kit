<script setup lang="ts">
import { computed } from 'vue'

import Icon from '~/components/Icon.vue'
import { Button } from '~/components/ui/button'
import { useFormat } from '~/composables/useFormat'
import { messageKeyFor, notificationChannelIcons, presentationFor } from '~/lib/notifications'
import { useI18n } from '~/plugins/i18n'

/**
 * One row of the inbox.
 *
 * The whole row is the link, so reading a notification is one click wherever
 * the pointer lands; the archive button sits on top of it and stops the
 * click, because "put this away" is the one action that must not also
 * navigate.
 *
 * Copy comes from the message tree keyed by the notification's stable type,
 * with `data` as the parameters. A type this build has no copy for still
 * renders: it falls back to the key rather than leaving a blank row, which is
 * what makes an old notification survive a rename.
 */
const props = defineProps<{
  notification: App.Data.NotificationData
  /** Trims the row for the bell's popover. */
  compact?: boolean
}>()

const emit = defineEmits<{
  open: [notification: App.Data.NotificationData]
  archive: [notification: App.Data.NotificationData]
  restore: [notification: App.Data.NotificationData]
}>()

const { t, te } = useI18n()
const format = useFormat()

const presentation = computed(() => presentationFor(props.notification.type))
const messageKey = computed(() => `notifications.types.${messageKeyFor(props.notification.type)}`)

const title = computed(() => {
  const key = `${messageKey.value}.title`

  return te(key) ? t(key, props.notification.data) : props.notification.type
})

const body = computed(() => {
  const key = `${messageKey.value}.body`

  return te(key) ? t(key, props.notification.data) : ''
})

const unseen = computed(() => props.notification.status === 'unseen')
const archived = computed(() => props.notification.status === 'archived')

/** What actually went out, so "why did I get a text?" has an answer here. */
const delivered = computed(() =>
  Object.keys(props.notification.deliveries).filter((channel) => channel !== 'database')
)
</script>

<template>
  <component
    :is="presentation.to ? 'RouterLink' : 'div'"
    :to="presentation.to"
    class="group relative flex w-full items-start gap-3 rounded-lg px-3 py-2.5 text-left transition-colors duration-200 ease-butter hover:bg-secondary/70"
    :class="unseen ? 'bg-secondary/40' : ''"
    @click="emit('open', notification)"
  >
    <span
      class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full"
      :class="
        presentation.tone === 'alert'
          ? 'bg-warning-background text-warning-foreground'
          : 'bg-muted-background text-muted'
      "
    >
      <Icon
        :name="presentation.icon"
        size="16"
        aria-hidden="true"
      />
    </span>

    <span class="grid min-w-0 flex-1 gap-0.5">
      <span class="flex items-baseline gap-2">
        <span
          class="min-w-0 flex-1 truncate text-sm text-primary"
          :class="unseen ? 'font-semibold' : 'font-medium'"
        >
          {{ title }}
        </span>
        <span class="shrink-0 text-2xs text-muted tabular-nums">
          {{ format.relative(notification.createdAt) }}
        </span>
      </span>

      <span
        v-if="body"
        class="text-xs text-pretty text-muted"
        :class="compact ? 'line-clamp-2' : ''"
      >
        {{ body }}
      </span>

      <span
        v-if="!compact && delivered.length > 0"
        class="mt-1 flex items-center gap-1.5 text-muted"
      >
        <Icon
          v-for="channel in delivered"
          :key="channel"
          :name="notificationChannelIcons[channel] ?? 'lucide:send'"
          size="12"
          :aria-label="t(`notifications.channels.${channel}`)"
        />
      </span>
    </span>

    <span
      v-if="unseen"
      class="mt-2 size-2 shrink-0 rounded-full bg-accent"
      :aria-label="t('notifications.status.unseen')"
    />

    <Button
      variant="ghost"
      size="icon"
      class="absolute top-1.5 right-1.5 opacity-0 transition-opacity duration-200 group-hover:opacity-100 focus-visible:opacity-100"
      :aria-label="
        archived ? t('notifications.actions.restore') : t('notifications.actions.archive')
      "
      @click.stop.prevent="archived ? emit('restore', notification) : emit('archive', notification)"
    >
      <Icon :name="archived ? 'lucide:archive-restore' : 'lucide:archive'" />
    </Button>
  </component>
</template>
