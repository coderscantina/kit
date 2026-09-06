<script setup lang="ts">
import Icon from '~/components/Icon.vue'
import { appConfig } from '~/lib/app-config'

defineProps<{
  /** Rail mode: the mark alone, with the name kept for screen readers. */
  compact?: boolean
}>()

const brand = appConfig.brand
</script>

<template>
  <RouterLink
    :to="{ name: brand.homeRouteName }"
    class="flex h-9 items-center gap-2.5 rounded-lg px-2 font-semibold text-sidebar-accent-foreground transition-colors duration-200 ease-butter hover:bg-sidebar-accent"
    :class="compact && 'w-9 justify-center px-0'"
  >
    <component
      :is="brand.logo"
      v-if="brand.logo"
      class="size-5 shrink-0"
      aria-hidden="true"
    />
    <Icon
      v-else
      :name="brand.icon"
      size="20"
      class="shrink-0 text-accent"
      aria-hidden="true"
    />
    <span
      class="truncate"
      :class="compact && 'sr-only'"
    >
      {{ brand.name }}
    </span>
  </RouterLink>
</template>
