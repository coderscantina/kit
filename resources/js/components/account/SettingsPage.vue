<script setup lang="ts">
/**
 * The frame every account page shares: a title, one line of intent, and the
 * sections below it separated by rules. Pages put `SettingsSection`s in the
 * slot and nothing else.
 */
defineProps<{
  title: string
  description?: string
}>()
</script>

<template>
  <article class="grid gap-8">
    <header class="grid gap-1">
      <h1 class="text-2xl font-semibold text-pretty text-primary">{{ title }}</h1>
      <p
        v-if="description"
        class="max-w-prose text-sm text-pretty text-muted"
      >
        {{ description }}
      </p>
    </header>

    <div class="divide-y divide-border">
      <slot />
    </div>
  </article>
</template>
