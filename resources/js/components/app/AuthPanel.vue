<script setup lang="ts">
import Icon from '~/components/Icon.vue'
import { appConfig } from '~/lib/app-config'

defineProps<{
  title: string
  description?: string
  /** Hides the mark on pages that already sit under one. */
  hideBrand?: boolean
}>()

const brand = appConfig.brand
</script>

<template>
  <section class="grid gap-6">
    <header class="grid gap-2">
      <component
        :is="brand.logo"
        v-if="brand.logo && !hideBrand"
        class="size-7 lg:hidden"
      />
      <Icon
        v-else-if="!hideBrand"
        :name="brand.icon"
        size="28"
        class="text-accent lg:hidden"
      />
      <h1 class="text-2xl font-semibold text-balance text-primary">{{ title }}</h1>
      <p
        v-if="description"
        class="text-sm text-muted"
      >
        {{ description }}
      </p>
    </header>

    <slot />

    <footer
      v-if="$slots.footer"
      class="text-sm text-muted"
    >
      <slot name="footer" />
    </footer>
  </section>
</template>
