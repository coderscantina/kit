<script setup lang="ts">
import CloseAccountSection from '~/components/account/CloseAccountSection.vue'
import ExportSection from '~/components/account/ExportSection.vue'
import SettingsPage from '~/components/account/SettingsPage.vue'
import { usePageMeta } from '~/composables/usePageMeta'
import { useI18n } from '~/plugins/i18n'

/**
 * Export and closure sit together because both answer "I am leaving": take
 * the data first, then close the account. Neither belongs next to a password
 * field, where a stray scroll could land on the wrong button.
 */
const { t } = useI18n()

usePageMeta(() => ({
  title: t('account.data.title'),
  breadcrumbs: [{ label: t('account.title') }, { label: t('account.data.title') }],
}))
</script>

<template>
  <SettingsPage
    :title="t('account.data.title')"
    :description="t('account.data.description')"
  >
    <ExportSection />
    <CloseAccountSection />
  </SettingsPage>
</template>
