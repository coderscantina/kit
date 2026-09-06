<script setup lang="ts">
import InstallDialog from '~/components/app/InstallDialog.vue'
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
import { useInstallPrompt } from '~/composables/useInstallPrompt'
import { useShortcutHelp } from '~/composables/useShortcuts'
import {
  accountNavigationItems,
  filterNavigationItems,
  navigationIcons,
} from '~/lib/access-control'
import { appConfig } from '~/lib/app-config'
import { type LocaleCode, useI18n } from '~/plugins/i18n'

const { t, locale, locales, setLocale } = useI18n()
const auth = useAuth()
const router = useRouter()
const { mode, isDark, setMode, colorModes } = useColorMode()
const shortcutHelp = useShortcutHelp()
const installPrompt = useInstallPrompt()

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

/** The account pages are reachable from here and from nowhere in the sidebar. */
const accountRoutes = computed(() =>
  filterNavigationItems(accountNavigationItems, { me: auth.me.value })
)

const installOpen = ref(false)

/** Prompt straight away where the browser allows it; show the steps where it does not. */
const install = async () => {
  if (installPrompt.canPrompt.value) {
    await installPrompt.install()
    return
  }
  installOpen.value = true
}

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
        :avatar="auth.user.value?.avatarUrl"
      />
      <Icon
        name="lucide:chevron-down"
        size="14"
        class="hidden text-muted sm:block"
        aria-hidden="true"
      />
    </DropdownMenuTrigger>

    <DropdownMenuContent
      align="end"
      class="min-w-60"
    >
      <div class="flex items-center gap-3 px-2 py-2">
        <Avatar
          :name="auth.user.value?.name ?? '?'"
          :avatar="auth.user.value?.avatarUrl"
          class="rounded-lg"
        />
        <div class="min-w-0">
          <p class="truncate text-sm font-semibold text-primary">{{ auth.user.value?.name }}</p>
          <p class="truncate text-xs text-muted">{{ auth.user.value?.email }}</p>
        </div>
      </div>
      <DropdownMenuSeparator />

      <DropdownMenuGroup>
        <DropdownMenuItem
          v-for="entry in accountRoutes"
          :key="entry.routeName"
          as-child
        >
          <RouterLink :to="{ name: entry.routeName }">
            <Icon :name="navigationIcons[entry.icon]" />
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
        class="max-lg:hidden"
        @select="shortcutHelp.show()"
      >
        <Icon name="lucide:keyboard" />
        {{ t('shortcuts.title') }}
        <DropdownMenuShortcut>?</DropdownMenuShortcut>
      </DropdownMenuItem>

      <DropdownMenuItem
        v-if="installPrompt.available.value"
        @select="install"
      >
        <Icon name="lucide:smartphone" />
        {{ t('pwa.install') }}
      </DropdownMenuItem>

      <DropdownMenuSeparator />
      <DropdownMenuItem @select="logout()">
        <Icon name="lucide:log-out" />
        {{ t('auth.logout') }}
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>

  <InstallDialog v-model:open="installOpen" />
</template>
