<script setup lang="ts">
import { toast } from 'vue-sonner'

import Icon from '~/components/Icon.vue'
import { Alert } from '~/components/ui/alert'
import { Button } from '~/components/ui/button'
import { useI18n } from '~/plugins/i18n'

/**
 * Backup codes are shown once. Copy and download are the two ways out of the
 * dialog that do not end with the user retyping them.
 */
const props = defineProps<{ codes: string[] }>()

const { t } = useI18n()

const asText = () => props.codes.join('\n')

const copy = async () => {
  await navigator.clipboard.writeText(asText())
  toast.success(t('account.security.codesCopied'))
}

const download = () => {
  const url = URL.createObjectURL(new Blob([asText()], { type: 'text/plain' }))
  const link = document.createElement('a')

  link.href = url
  link.download = 'backup-codes.txt'
  link.click()

  URL.revokeObjectURL(url)
}
</script>

<template>
  <div class="grid gap-3">
    <Alert color="warning">{{ t('account.security.backupCodes') }}</Alert>
    <ul class="grid grid-cols-2 gap-2">
      <li
        v-for="code in codes"
        :key="code"
        class="rounded-md border border-border px-2 py-1 text-center font-mono text-sm"
      >
        {{ code }}
      </li>
    </ul>
    <div class="grid grid-cols-2 gap-2">
      <Button
        type="button"
        variant="default"
        @click="copy"
      >
        <Icon name="lucide:copy" />
        {{ t('account.actions.copy') }}
      </Button>
      <Button
        type="button"
        variant="default"
        @click="download"
      >
        <Icon name="lucide:download" />
        {{ t('account.actions.download') }}
      </Button>
    </div>
  </div>
</template>
