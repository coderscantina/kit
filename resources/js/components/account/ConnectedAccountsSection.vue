<script setup lang="ts">
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { toast } from 'vue-sonner'

import { api } from '~/api'
import SettingsSection from '~/components/account/SettingsSection.vue'
import Icon from '~/components/Icon.vue'
import PasswordConfirmDialog from '~/components/PasswordConfirmDialog.vue'
import { Button } from '~/components/ui/button'
import { useConfirm } from '~/composables/useConfirm'
import { useFormat } from '~/composables/useFormat'
import { useStepUp } from '~/composables/useStepUp'
import { runtimeConfig } from '~/lib/runtime-config'
import { toastError } from '~/lib/toast-error'
import { useI18n } from '~/plugins/i18n'

/**
 * The identity providers this account can sign in with.
 *
 * Connecting is a full page navigation through the provider and back, so the
 * outcome arrives as a query parameter rather than a promise; it is read once
 * on mount and then wiped from the URL, or a reload would re-announce it.
 */
const { t } = useI18n()
const { relative } = useFormat()
const { confirm } = useConfirm()
const stepUp = useStepUp()
const route = useRoute()
const router = useRouter()
const queryClient = useQueryClient()

const providers = computed(() => runtimeConfig.socialProviders)

const links = useQuery({
  queryKey: ['account', 'social-links'],
  queryFn: () => api.account.socialLinks(),
  enabled: providers.value.length > 0,
})

const connected = computed(() => new Map((links.data.value ?? []).map((l) => [l.provider, l])))

const linkHref = (provider: string) =>
  `/auth/social/${encodeURIComponent(provider)}/link?return=${encodeURIComponent(route.path)}`

const disconnect = async (link: App.Data.SocialLinkData) => {
  const confirmed = await confirm({
    title: t('account.connected.disconnectTitle'),
    message: t('account.connected.disconnectConfirm', { provider: link.label }),
    confirmLabel: t('account.connected.disconnect'),
    variant: 'destructive',
  })

  if (!confirmed) return

  try {
    await stepUp.run(() => api.account.unlinkSocial(link.provider))
    await queryClient.invalidateQueries({ queryKey: ['account', 'social-links'] })
    toast.success(t('account.connected.disconnected', { provider: link.label }))
  } catch (error) {
    toastError(t, 'account.connected.error', error)
  }
}

onMounted(() => {
  const outcome = route.query.social
  if (typeof outcome !== 'string') return

  const provider = typeof route.query.provider === 'string' ? route.query.provider : ''
  const label = providers.value.find((entry) => entry.key === provider)?.label ?? provider

  if (outcome === 'linked') toast.success(t('account.connected.connected', { provider: label }))
  else if (outcome === 'conflict') toast.error(t('account.connected.conflict', { provider: label }))
  else toast.error(t('account.connected.error'))

  const query = { ...route.query }
  delete query.social
  delete query.provider
  void router.replace({ query })
})
</script>

<template>
  <SettingsSection
    v-if="providers.length > 0"
    id="connected-accounts"
    :title="t('account.connected.title')"
    :description="t('account.connected.description')"
  >
    <ul class="grid divide-y divide-border">
      <li
        v-for="provider in providers"
        :key="provider.key"
        class="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
      >
        <Icon
          :name="provider.icon"
          size="20"
          class="shrink-0"
          aria-hidden="true"
        />

        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium text-primary">{{ provider.label }}</p>
          <p class="truncate text-xs text-muted">
            <template v-if="connected.get(provider.key)">
              {{
                connected.get(provider.key)?.email ??
                connected.get(provider.key)?.nickname ??
                t('account.connected.linked')
              }}
              <template v-if="connected.get(provider.key)?.lastUsedAt">
                ·
                {{
                  t('account.connected.lastUsed', {
                    when: relative(connected.get(provider.key)?.lastUsedAt),
                  })
                }}
              </template>
            </template>
            <template v-else>{{ t('account.connected.notLinked') }}</template>
          </p>
        </div>

        <Button
          v-if="connected.get(provider.key)"
          variant="ghost"
          size="sm"
          class="text-destructive"
          @click="disconnect(connected.get(provider.key)!)"
        >
          {{ t('account.connected.disconnect') }}
        </Button>
        <Button
          v-else
          as="a"
          variant="outline"
          size="sm"
          :href="linkHref(provider.key)"
        >
          {{ t('account.connected.connect') }}
        </Button>
      </li>
    </ul>

    <PasswordConfirmDialog
      v-model:open="stepUp.confirmOpen.value"
      @confirmed="stepUp.onConfirmed"
    />
  </SettingsSection>
</template>
