<script setup lang="ts">
import AccessTokensSection from '~/components/account/AccessTokensSection.vue'
import McpSection from '~/components/account/McpSection.vue'
import SettingsPage from '~/components/account/SettingsPage.vue'
import { usePageMeta } from '~/composables/usePageMeta'
import { useI18n } from '~/plugins/i18n'

/** Machine access to this account: the tokens, and where a model connects with one. */
const { t } = useI18n()

usePageMeta(() => ({
  title: t('account.api.title'),
  breadcrumbs: [{ label: t('account.title') }, { label: t('account.api.title') }],
}))
</script>

<template>
  <SettingsPage
    :title="t('account.api.title')"
    :description="t('account.api.description')"
  >
    <AccessTokensSection />
    <McpSection />
  </SettingsPage>
</template>
