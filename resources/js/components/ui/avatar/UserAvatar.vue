<script setup lang="ts">
import type { HTMLAttributes } from 'vue'

import { PulseDot } from '~/components/ui/pulse-dot'
import { useI18n, type MessageKey } from '~/plugins/i18n'

import Avatar from './Avatar.vue'
import { presenceDotVariants, type AvatarVariants, type PresenceStatus } from './variants'

const props = defineProps<{
  name: string
  avatar?: string | null
  borderColor?: string | null
  size?: AvatarVariants['size']
  /**
   * Leave it out for no indicator at all. A dot that is always there says
   * nothing, and `offline` on a page with no roster is a lie.
   */
  status?: PresenceStatus | null
  /** What the screen reader says, when "online" is not the whole story. */
  statusLabel?: string
  class?: HTMLAttributes['class']
}>()

const { t } = useI18n()

const labels: Record<PresenceStatus, MessageKey> = {
  online: 'presence.status.online',
  offline: 'presence.status.offline',
  unavailable: 'presence.status.unavailable',
}

const dotSize = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'xs' as const
    case 'lg':
      return 'default' as const
    default:
      return 'sm' as const
  }
})

const label = computed(() => {
  if (!props.status) return ''

  return props.statusLabel ?? t(labels[props.status], { name: props.name })
})
</script>

<template>
  <span class="relative inline-flex">
    <Avatar
      :name="name"
      :avatar="avatar"
      :border-color="borderColor"
      :size="size"
      :class="props.class"
    />
    <span
      v-if="status"
      class="absolute -right-0.5 -bottom-0.5 flex rounded-full bg-card p-0.5"
    >
      <PulseDot
        :variant="presenceDotVariants[status]"
        :size="dotSize"
      />
      <span class="sr-only">{{ label }}</span>
    </span>
  </span>
</template>
