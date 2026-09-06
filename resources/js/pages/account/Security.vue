<script setup lang="ts">
import ConnectedAccountsSection from '~/components/account/ConnectedAccountsSection.vue'
import NotificationsSection from '~/components/account/NotificationsSection.vue'
import PasswordSection from '~/components/account/PasswordSection.vue'
import SecurityActivitySection from '~/components/account/SecurityActivitySection.vue'
import SecurityOverview from '~/components/account/SecurityOverview.vue'
import SessionsSection from '~/components/account/SessionsSection.vue'
import SettingsPage from '~/components/account/SettingsPage.vue'
import TwoFactorSection from '~/components/account/TwoFactorSection.vue'
import { usePageMeta } from '~/composables/usePageMeta'
import { useI18n } from '~/plugins/i18n'

/**
 * One page to check after a scare: what protects the account, what is signed
 * in to it, and what has happened to it. The overview at the top answers all
 * three at a glance; the sections below are where you act on them.
 */
const { t } = useI18n()

usePageMeta(() => ({
  title: t('account.security.title'),
  breadcrumbs: [{ label: t('account.title') }, { label: t('account.security.title') }],
}))
</script>

<template>
  <SettingsPage
    :title="t('account.security.title')"
    :description="t('account.security.description')"
  >
    <SecurityOverview />
    <PasswordSection />
    <TwoFactorSection />
    <ConnectedAccountsSection />
    <NotificationsSection />
    <SessionsSection />
    <SecurityActivitySection />
  </SettingsPage>
</template>
