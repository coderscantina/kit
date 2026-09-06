<script setup lang="ts">
import { Button } from '~/components/ui/button'
import { Card } from '~/components/ui/card'
import { useAuth } from '~/composables/useAuth'
import { firstAllowedRoute } from '~/lib/access-control'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const auth = useAuth()
const route = useRoute()

const scope = computed(() => (typeof route.query.scope === 'string' ? route.query.scope : 'global'))
// The first nav item the user may open; never a blank page.
const fallback = computed(() => firstAllowedRoute({ me: auth.me.value }))
</script>

<template>
  <Card class="mx-auto max-w-md p-6">
    <h1 class="mb-2 text-xl font-semibold">{{ t(`accessDenied.${scope}.title`) }}</h1>
    <p class="mb-4 text-sm text-muted">{{ t(`accessDenied.${scope}.description`) }}</p>
    <Button
      variant="primary"
      v-if="fallback"
      as-child
    >
      <RouterLink :to="fallback">{{ t('accessDenied.back') }}</RouterLink>
    </Button>
  </Card>
</template>
