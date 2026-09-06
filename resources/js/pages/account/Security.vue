<script setup lang="ts">
import DangerZoneCard from '~/components/account/DangerZoneCard.vue'
import PasswordCard from '~/components/account/PasswordCard.vue'
import SecurityActivityCard from '~/components/account/SecurityActivityCard.vue'
import SessionsCard from '~/components/account/SessionsCard.vue'
import TwoFactorCard from '~/components/account/TwoFactorCard.vue'
import { useI18n } from '~/plugins/i18n'

/**
 * One page to check after a scare: what protects the account, what is signed
 * in to it, what has happened to it, and how to leave.
 */
const { t } = useI18n()
</script>

<template>
  <div class="max-w-2xl space-y-6">
    <h1 class="text-2xl font-semibold">{{ t('account.security.title') }}</h1>

    <PasswordCard />
    <TwoFactorCard />
    <SessionsCard />
    <SecurityActivityCard />
    <DangerZoneCard />
  </div>
</template>
