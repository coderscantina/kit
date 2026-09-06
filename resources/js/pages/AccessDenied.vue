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

const scope = computed(() => (typeof route.query.scope === 'string' ? route.query.scope : 'global'))
// The first nav item the user may open; never a blank page.
const fallback = computed(() => firstAllowedRoute({ me: auth.me.value }))

usePageMeta(() => ({ title: t(`accessDenied.${scope.value}.title`) }))
</script>

<template>
  <EmptyState
    icon="lucide:lock"
    :title="t(`accessDenied.${scope}.title`)"
    :description="t(`accessDenied.${scope}.description`)"
    class="mx-auto max-w-xl"
  >
    <Button
      v-if="fallback"
      variant="accent"
      as-child
    >
      <RouterLink :to="fallback">{{ t('accessDenied.back') }}</RouterLink>
    </Button>
  </EmptyState>
</template>
