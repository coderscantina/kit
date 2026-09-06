<script setup lang="ts">
import Icon from '~/components/Icon.vue'
import { Button } from '~/components/ui/button'
import { Card } from '~/components/ui/card'
import { useAuth } from '~/composables/useAuth'
import { firstAllowedRoute } from '~/lib/access-control'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const auth = useAuth()
const route = useRoute()
const router = useRouter()

// The first nav item the user may open, so the way out is never a page that
// bounces straight to access-denied.
const fallback = computed(() => firstAllowedRoute({ me: auth.me.value }))
</script>

<template>
  <Card class="mx-auto max-w-md p-6">
    <Icon
      name="lucide:map-pin-off"
      size="32"
      class="mb-3 text-muted"
    />
    <h1 class="mb-2 text-xl font-semibold">{{ t('notFound.title') }}</h1>
    <p class="mb-1 text-sm text-muted">{{ t('notFound.description') }}</p>
    <p class="mb-4 font-mono text-xs break-all text-muted">{{ route.fullPath }}</p>
    <div class="flex gap-2">
      <Button
        v-if="fallback"
        variant="primary"
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
    </div>
  </Card>
</template>
