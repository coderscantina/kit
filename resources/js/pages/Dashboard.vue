<script setup lang="ts">
import { Card } from '~/components/ui/card'
import { useAuth } from '~/composables/useAuth'
import { reactive } from '~/lib/reactive'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const auth = useAuth()
const connection = reactive.connection.state
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-semibold">
      {{ t('dashboard.title', { name: auth.user.value?.name ?? '' }) }}
    </h1>
    <Card class="p-6">
      <p class="text-sm text-muted-foreground">{{ t('dashboard.empty') }}</p>
      <p class="mt-2 text-xs text-muted-foreground">
        {{ t('dashboard.connection', { state: connection }) }}
      </p>
    </Card>
  </div>
</template>
