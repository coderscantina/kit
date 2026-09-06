<script setup lang="ts">
import Icon from '~/components/Icon.vue'
import { Button } from '~/components/ui/button'
import { Card } from '~/components/ui/card'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const route = useRoute()

const error = ref<Error | null>(null)

/**
 * Catches render, watcher and lifecycle failures below this component. An
 * error in an async event handler never reaches here — that is what the API
 * client's error toasts are for. Returning false stops the error from
 * travelling further up, where the app's only remaining option is a blank
 * screen.
 */
onErrorCaptured((caught) => {
  error.value = caught instanceof Error ? caught : new Error(String(caught))
  console.error('[error-boundary]', caught)

  return false
})

// Navigating away is a retry: the failing subtree is gone either way.
watch(
  () => route.fullPath,
  () => (error.value = null)
)

// `v-else` unmounts the failed subtree, so retrying mounts a fresh one rather
// than resuming a half-torn-down instance.
const retry = () => (error.value = null)

const reload = () => window.location.reload()

// The stack is noise for a user; in dev it is the whole point.
const isDev = import.meta.env.DEV
</script>

<template>
  <Card
    v-if="error"
    class="mx-auto max-w-lg p-6"
  >
    <Icon
      name="lucide:triangle-alert"
      size="32"
      class="mb-3 text-destructive"
    />
    <h1 class="mb-2 text-xl font-semibold">{{ t('errorBoundary.title') }}</h1>
    <p class="mb-4 text-sm text-muted">{{ t('errorBoundary.description') }}</p>
    <pre
      v-if="isDev"
      class="mb-4 overflow-x-auto rounded-md bg-muted-background p-3 text-xs whitespace-pre-wrap text-muted"
      >{{ error.message }}</pre>
    <div class="flex gap-2">
      <Button
        variant="primary"
        @click="retry"
      >
        {{ t('errorBoundary.retry') }}
      </Button>
      <Button
        variant="outline"
        @click="reload"
      >
        {{ t('errorBoundary.reload') }}
      </Button>
    </div>
  </Card>
  <slot v-else />
</template>
