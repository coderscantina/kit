<script setup lang="ts">
import Icon from '~/components/Icon.vue'
import { Avatar } from '~/components/ui/avatar'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuSeparator,
  DropdownMenuShortcut,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
  DropdownMenuTrigger,
} from '~/components/ui/dropdown-menu'
import { useAuth } from '~/composables/useAuth'
import { type ColorMode, useColorMode } from '~/composables/useColorMode'
import { useShortcutHelp } from '~/composables/useShortcuts'
import { canAccessRouteByName } from '~/lib/access-control'
import { appConfig } from '~/lib/app-config'
import { type LocaleCode, useI18n } from '~/plugins/i18n'

const { t, locale, locales, setLocale } = useI18n()
const auth = useAuth()
const router = useRouter()
const { mode, isDark, setMode, colorModes } = useColorMode()
const shortcutHelp = useShortcutHelp()

const features = appConfig.features

const colorModeIcons: Record<ColorMode, string> = {
  light: 'lucide:sun',
  dark: 'lucide:moon',
  system: 'lucide:monitor',
}

const colorModeLabels = computed<Record<ColorMode, string>>(() => ({
  light: t('colorMode.light'),
  dark: t('colorMode.dark'),
  system: t('colorMode.system'),
}))

/** The account pages, shown only when the current user may actually open them. */
const accountEntries = [
  { name: 'account-profile', labelKey: 'nav.profile', icon: 'lucide:user' },
  { name: 'account-security', labelKey: 'nav.security', icon: 'lucide:shield' },
] as const

const accountRoutes = computed(() =>
  accountEntries.filter((entry) => canAccessRouteByName(entry.name, { me: auth.me.value }))
)

const logout = async () => {
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger
      class="flex cursor-pointer items-center gap-2 rounded-lg p-1 transition-colors duration-200 ease-butter hover:bg-secondary/80 data-[state=open]:bg-secondary/80"
      :aria-label="t('shell.userMenu')"
    >
      <Avatar
        size="sm"
        class="size-7 rounded-lg"
        :name="auth.user.value?.name ?? '?'"
      />
      <Icon
        name="lucide:chevron-down"
        size="14"
        class="text-muted"
        aria-hidden="true"
      />
    </DropdownMenuTrigger>

    <DropdownMenuContent
      align="end"
      class="min-w-56"
    >
      <div class="px-2 py-1.5">
        <p class="truncate text-sm font-semibold text-primary">{{ auth.user.value?.name }}</p>
        <p class="truncate text-xs text-muted">{{ auth.user.value?.email }}</p>
      </div>
      <DropdownMenuSeparator />

      <DropdownMenuGroup>
        <DropdownMenuItem
          v-for="entry in accountRoutes"
          :key="entry.name"
          as-child
        >
          <RouterLink :to="{ name: entry.name }">
            <Icon :name="entry.icon" />
            {{ t(entry.labelKey) }}
          </RouterLink>
        </DropdownMenuItem>
      </DropdownMenuGroup>

      <DropdownMenuSeparator v-if="accountRoutes.length > 0" />

      <DropdownMenuSub v-if="features.colorModeToggle">
        <DropdownMenuSubTrigger>
          <!-- The trigger shows what is on screen, not what was picked: on
               `system` the monitor icon says nothing about the current theme. -->
          <Icon :name="isDark ? colorModeIcons.dark : colorModeIcons.light" />
          {{ t('colorMode.label') }}
        </DropdownMenuSubTrigger>
        <DropdownMenuSubContent>
          <DropdownMenuRadioGroup
            :model-value="mode"
            @update:model-value="setMode($event as ColorMode)"
          >
            <DropdownMenuRadioItem
              v-for="option in colorModes"
              :key="option"
              :value="option"
            >
              <Icon :name="colorModeIcons[option]" />
              {{ colorModeLabels[option] }}
            </DropdownMenuRadioItem>
          </DropdownMenuRadioGroup>
        </DropdownMenuSubContent>
      </DropdownMenuSub>

      <DropdownMenuSub v-if="features.localeSwitch && locales.length > 1">
        <DropdownMenuSubTrigger>
          <Icon name="lucide:languages" />
          {{ t('shell.language') }}
        </DropdownMenuSubTrigger>
        <DropdownMenuSubContent>
          <DropdownMenuRadioGroup
            :model-value="locale"
            @update:model-value="setLocale($event as LocaleCode)"
          >
            <DropdownMenuRadioItem
              v-for="entry in locales"
              :key="entry.code"
              :value="entry.code"
            >
              {{ entry.name }}
            </DropdownMenuRadioItem>
          </DropdownMenuRadioGroup>
        </DropdownMenuSubContent>
      </DropdownMenuSub>

      <DropdownMenuItem
        v-if="features.shortcutsHelp"
        @select="shortcutHelp.show()"
      >
        <Icon name="lucide:keyboard" />
        {{ t('shortcuts.title') }}
        <DropdownMenuShortcut>?</DropdownMenuShortcut>
      </DropdownMenuItem>

      <DropdownMenuSeparator />
      <DropdownMenuItem @select="logout()">
        <Icon name="lucide:log-out" />
        {{ t('auth.logout') }}
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
