<script setup lang="ts">
import Icon from '~/components/Icon.vue'
import { Button } from '~/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuTrigger,
} from '~/components/ui/dropdown-menu'
import { type ColorMode, useColorMode } from '~/composables/useColorMode'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const { mode, isDark, setMode, colorModes } = useColorMode()

const icons: Record<ColorMode, string> = {
  light: 'lucide:sun',
  dark: 'lucide:moon',
  system: 'lucide:monitor',
}

const labels = computed<Record<ColorMode, string>>(() => ({
  light: t('colorMode.light'),
  dark: t('colorMode.dark'),
  system: t('colorMode.system'),
}))

// The trigger shows what is on screen, not what was picked: on `system` the
// monitor icon says nothing about which theme the visitor is looking at.
const triggerIcon = computed(() => (isDark.value ? icons.dark : icons.light))
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button
        variant="ghost"
        size="sm"
        class="w-full justify-start"
        :aria-label="t('colorMode.label')"
      >
        <Icon :name="triggerIcon" />
        {{ labels[mode] }}
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent
      align="start"
      side="top"
    >
      <DropdownMenuRadioGroup
        :model-value="mode"
        @update:model-value="setMode($event as ColorMode)"
      >
        <DropdownMenuRadioItem
          v-for="option in colorModes"
          :key="option"
          :value="option"
        >
          <Icon
            :name="icons[option]"
            class="mr-2"
          />
          {{ labels[option] }}
        </DropdownMenuRadioItem>
      </DropdownMenuRadioGroup>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
