<script setup lang="ts">
import SettingsSection from '~/components/account/SettingsSection.vue'
import Icon from '~/components/Icon.vue'
import { useAccentColor } from '~/composables/useAccentColor'
import { type ColorMode, useColorMode } from '~/composables/useColorMode'
import { appConfig } from '~/lib/app-config'
import { useI18n } from '~/plugins/i18n'

/**
 * The theme, as three swatches rather than a dropdown: what each option looks
 * like is the whole question, so the option should show it. Applies on click
 * and stays in this browser, which the description says out loud.
 *
 * The accent below it works the same way, and the swatches are painted in the
 * colour they set rather than labelled with its name.
 */
const { t } = useI18n()
const { mode, setMode, colorModes } = useColorMode()
const { accent, setAccent, accentColors, accentSwatch } = useAccentColor()

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

    <div
      v-if="appConfig.features.accentPicker"
      class="grid gap-2 pt-2"
    >
      <p class="text-xs font-medium text-muted">{{ t('account.appearance.accent') }}</p>
      <div
        role="radiogroup"
        :aria-label="t('account.appearance.accent')"
        class="flex flex-wrap gap-2"
      >
        <button
          v-for="option in accentColors"
          :key="option"
          type="button"
          role="radio"
          :aria-checked="accent === option"
          :aria-label="t(`accent.${option}`)"
          class="grid size-8 place-items-center rounded-full ring-offset-2 ring-offset-background transition-shadow duration-200 ease-butter aria-checked:ring-2 aria-checked:ring-current"
          :class="accentSwatch[option]"
          @click="setAccent(option)"
        >
          <!-- One check for six swatches: the shadow is what keeps it legible
               on the light ones without a per-colour foreground. -->
          <Icon
            v-if="accent === option"
            name="lucide:check"
            size="15"
            class="text-white drop-shadow-[0_1px_2px_rgba(0,0,0,0.55)]"
            aria-hidden="true"
          />
        </button>
      </div>
    </div>
  </SettingsSection>
</template>
