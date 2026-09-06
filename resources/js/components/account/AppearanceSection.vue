<script setup lang="ts">
import SettingsSection from '~/components/account/SettingsSection.vue'
import Icon from '~/components/Icon.vue'
import { type ColorMode, useColorMode } from '~/composables/useColorMode'
import { appConfig } from '~/lib/app-config'
import { useI18n } from '~/plugins/i18n'

/**
 * The theme, as three swatches rather than a dropdown: what each option looks
 * like is the whole question, so the option should show it. Applies on click
 * and stays in this browser, which the description says out loud.
 */
const { t } = useI18n()
const { mode, setMode, colorModes } = useColorMode()

const icons: Record<ColorMode, string> = {
  light: 'lucide:sun',
  dark: 'lucide:moon',
  system: 'lucide:monitor',
}

/** Swatch surfaces per option; `system` is split down the middle. */
const swatch: Record<ColorMode, string> = {
  light: 'bg-[oklch(96%_0.004_271)]',
  dark: 'bg-[oklch(20%_0.008_278)]',
  system: 'bg-[linear-gradient(110deg,oklch(96%_0.004_271)_50%,oklch(20%_0.008_278)_50%)]',
}
</script>

<template>
  <SettingsSection
    v-if="appConfig.features.colorModeToggle"
    id="appearance"
    :title="t('account.appearance.title')"
    :description="t('account.appearance.description')"
  >
    <div
      role="radiogroup"
      :aria-label="t('colorMode.label')"
      class="grid grid-cols-3 gap-3"
    >
      <button
        v-for="option in colorModes"
        :key="option"
        type="button"
        role="radio"
        :aria-checked="mode === option"
        class="group grid gap-2 rounded-xl p-1.5 text-left transition-colors duration-200 ease-butter hover:bg-secondary/60 aria-checked:bg-secondary"
        @click="setMode(option)"
      >
        <span
          class="relative block aspect-[4/3] overflow-hidden rounded-lg border border-border-strong shadow-soft-sm"
          :class="swatch[option]"
          aria-hidden="true"
        >
          <span class="absolute inset-x-2 top-2 h-1.5 rounded-full bg-[oklch(66%_0.16_245)]" />
          <span
            class="absolute inset-x-2 top-5 h-1 rounded-full opacity-40"
            :class="option === 'light' ? 'bg-black' : 'bg-white'"
          />
          <span
            class="absolute inset-x-2 top-7.5 h-1 w-2/3 rounded-full opacity-40"
            :class="option === 'light' ? 'bg-black' : 'bg-white'"
          />
        </span>
        <span class="flex items-center gap-1.5 px-0.5 text-xs font-medium text-foreground">
          <Icon
            :name="icons[option]"
            size="14"
            class="text-muted"
            aria-hidden="true"
          />
          {{ t(`colorMode.${option}`) }}
          <Icon
            name="lucide:check"
            size="14"
            class="ml-auto text-accent opacity-0 transition-opacity group-aria-checked:opacity-100"
            aria-hidden="true"
          />
        </span>
      </button>
    </div>
  </SettingsSection>
</template>
