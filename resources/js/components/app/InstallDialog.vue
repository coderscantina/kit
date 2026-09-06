<script setup lang="ts">
import Icon from '~/components/Icon.vue'
import { Button } from '~/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeaderCombined } from '~/components/ui/dialog'
import { useInstallPrompt } from '~/composables/useInstallPrompt'
import { appConfig } from '~/lib/app-config'
import { useI18n } from '~/plugins/i18n'

/**
 * Where the browser can prompt, this never opens: the menu item prompts
 * directly. It exists for iOS, which only installs through the share sheet,
 * so the steps are shown instead of a button that cannot work.
 */
const open = defineModel<boolean>('open', { default: false })

const { t } = useI18n()
const prompt = useInstallPrompt()

const steps = [
  { icon: 'lucide:share', key: 'pwa.ios.share' },
  { icon: 'lucide:square-plus', key: 'pwa.ios.add' },
  { icon: 'lucide:check', key: 'pwa.ios.confirm' },
] as const

const install = async () => {
  if (await prompt.install()) open.value = false
}
</script>

<template>
  <Dialog v-model:open="open">
    <DialogContent class="max-w-sm">
      <DialogHeaderCombined
        :title="t('pwa.installTitle', { name: appConfig.brand.name })"
        :description="t('pwa.installDescription')"
      />

      <ol
        v-if="!prompt.canPrompt.value"
        class="grid gap-3"
      >
        <li
          v-for="(step, index) in steps"
          :key="step.key"
          class="flex items-center gap-3 text-sm"
        >
          <span
            class="grid size-8 shrink-0 place-items-center rounded-lg bg-secondary text-primary"
          >
            <Icon
              :name="step.icon"
              size="16"
              aria-hidden="true"
            />
          </span>
          <span>
            <span class="sr-only">{{ index + 1 }}.</span>
            {{ t(step.key) }}
          </span>
        </li>
      </ol>

      <DialogFooter>
        <Button
          variant="ghost"
          @click="open = false"
        >
          {{ t('actions.close') }}
        </Button>
        <Button
          v-if="prompt.canPrompt.value"
          variant="primary"
          @click="install"
        >
          <Icon name="lucide:download" />
          {{ t('pwa.installAction') }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
