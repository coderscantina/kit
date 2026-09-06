<script setup lang="ts">
import Icon from '~/components/Icon.vue'
import { Card } from '~/components/ui/card'
import { PulseDot } from '~/components/ui/pulse-dot'
import { useAuth } from '~/composables/useAuth'
import { usePageMeta } from '~/composables/usePageMeta'
import { reactive } from '~/lib/reactive'
import { IS_MAC } from '~/lib/shortcuts'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const auth = useAuth()
const connection = reactive.connection.state

usePageMeta(() => ({ title: t('nav.dashboard') }))

const connected = computed(() => connection.value === 'connected')

const steps = [
  { icon: 'lucide:terminal', key: 'feature' },
  { icon: 'lucide:book-open', key: 'docs' },
  { icon: 'lucide:command', key: 'palette' },
] as const
</script>

<template>
  <div class="@container mx-auto grid max-w-5xl gap-6">
    <header class="grid gap-1">
      <h1 class="text-2xl font-semibold text-primary">
        {{ t('dashboard.title', { name: auth.user.value?.name ?? '' }) }}
      </h1>
      <p class="text-sm text-muted">{{ t('dashboard.subtitle') }}</p>
    </header>

    <!-- Container, not viewport: the grid answers to the width the shell left
         it, which changes with the sidebar as much as with the window. -->
    <div class="grid gap-4 @2xl:grid-cols-3">
      <Card
        v-for="step in steps"
        :key="step.key"
        class="p-4"
      >
        <Icon
          :name="step.icon"
          size="20"
          class="mb-3 text-accent"
          aria-hidden="true"
        />
        <h2 class="text-sm font-semibold text-primary">
          {{ t(`dashboard.steps.${step.key}.title`) }}
        </h2>
        <p class="mt-1 text-sm text-muted">{{ t(`dashboard.steps.${step.key}.body`) }}</p>
        <kbd
          v-if="step.key === 'palette'"
          class="mt-3 inline-block rounded border border-border bg-surface px-1.5 py-0.5 font-mono text-xs"
        >
          {{ IS_MAC ? '⌘' : 'Ctrl' }}K
        </kbd>
      </Card>
    </div>

    <Card class="flex items-center gap-3 p-4">
      <PulseDot
        size="sm"
        :variant="connected ? 'success' : 'default'"
        :live="connected"
      />
      <p class="text-sm text-muted">{{ t('dashboard.connection', { state: connection }) }}</p>
    </Card>
  </div>
</template>
