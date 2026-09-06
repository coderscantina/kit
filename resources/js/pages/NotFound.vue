<script setup lang="ts">
import { Button } from '~/components/ui/button'
import { EmptyState } from '~/components/ui/empty-state'
import { useAuth } from '~/composables/useAuth'
import { usePageMeta } from '~/composables/usePageMeta'
import { firstAllowedRoute } from '~/lib/access-control'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const auth = useAuth()
const route = useRoute()
const router = useRouter()

usePageMeta(() => ({ title: t('notFound.title') }))

// The first nav item the user may open, so the way out is never a page that
// bounces straight to access-denied.
const fallback = computed(() => firstAllowedRoute({ me: auth.me.value }))
</script>

<template>
  <EmptyState
    icon="lucide:map-pin-off"
    :title="t('notFound.title')"
    :description="t('notFound.description')"
    class="mx-auto max-w-xl"
  >
    <p class="w-full font-mono text-xs break-all text-muted">{{ route.fullPath }}</p>
    <Button
      v-if="fallback"
      variant="accent"
      as-child
    >
      <RouterLink :to="fallback">{{ t('notFound.home') }}</RouterLink>
    </Button>
    <Button
      variant="outline"
      @click="router.back()"
    >
      {{ t('notFound.back') }}
    </Button>
  </EmptyState>
</template>
