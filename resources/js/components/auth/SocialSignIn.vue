<script setup lang="ts">
import { computed } from 'vue'

import Icon from '~/components/Icon.vue'
import { Button } from '~/components/ui/button'
import { runtimeConfig } from '~/lib/runtime-config'
import { useI18n } from '~/plugins/i18n'

/**
 * The identity providers this installation offers, as buttons. The list comes
 * from the server, so adding a provider is an .env change and no edit here.
 *
 * These are full page navigations, not fetches: the provider hands control
 * back to a URL, which a background request cannot follow.
 */
const props = defineProps<{
  /** Where to land after a successful sign-in. A path on this origin. */
  returnPath?: string
}>()

const { t } = useI18n()

const providers = computed(() => runtimeConfig.socialProviders)

const href = (provider: string): string => {
  const url = `/auth/social/${encodeURIComponent(provider)}/redirect`

  return props.returnPath ? `${url}?return=${encodeURIComponent(props.returnPath)}` : url
}
</script>

<template>
  <div
    v-if="providers.length > 0"
    class="grid gap-4"
  >
    <div class="grid gap-2">
      <Button
        v-for="provider in providers"
        :key="provider.key"
        as="a"
        variant="outline"
        class="w-full"
        :href="href(provider.key)"
      >
        <Icon
          :name="provider.icon"
          aria-hidden="true"
        />
        {{ t('auth.social.continueWith', { provider: provider.label }) }}
      </Button>
    </div>

    <div class="flex items-center gap-3 text-xs text-muted">
      <span class="h-px flex-1 bg-border" />
      {{ t('auth.social.or') }}
      <span class="h-px flex-1 bg-border" />
    </div>
  </div>
</template>
