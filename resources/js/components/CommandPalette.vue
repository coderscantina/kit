<script setup lang="ts">
import Icon from '~/components/Icon.vue'
import {
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandShortcut,
} from '~/components/ui/command'
import { useAuth } from '~/composables/useAuth'
import { useColorMode } from '~/composables/useColorMode'
import { useCommandPalette } from '~/composables/useCommandPalette'
import { useNavigation } from '~/composables/useNavigation'
import { useShortcut, useShortcutHelp } from '~/composables/useShortcuts'
import { useSidebar } from '~/composables/useSidebar'
import { navigationIcons } from '~/lib/access-control'
import { appConfig } from '~/lib/app-config'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const auth = useAuth()
const router = useRouter()
const palette = useCommandPalette()
const sidebar = useSidebar()
const { isDark, setMode } = useColorMode()
const shortcutHelp = useShortcutHelp()

// The same filter the sidebar uses, so the palette never offers a page the
// user would only be bounced off.
const { items } = useNavigation()

interface Command {
  id: string
  label: string
  icon: string
  run: () => void
}

const commands = computed<Command[]>(() => {
  const list: Command[] = [
    {
      id: 'theme',
      label: isDark.value ? t('commandPalette.actions.light') : t('commandPalette.actions.dark'),
      icon: isDark.value ? 'lucide:sun' : 'lucide:moon',
      run: () => setMode(isDark.value ? 'light' : 'dark'),
    },
  ]

  if (sidebar.collapsible) {
    list.push({
      id: 'sidebar',
      label: t('shortcuts.toggleSidebar'),
      icon: 'lucide:panel-left',
      run: () => sidebar.toggle(),
    })
  }

  if (appConfig.features.shortcutsHelp) {
    list.push({
      id: 'shortcuts',
      label: t('shortcuts.title'),
      icon: 'lucide:keyboard',
      run: () => shortcutHelp.show(),
    })
  }

  list.push({
    id: 'logout',
    label: t('auth.logout'),
    icon: 'lucide:log-out',
    run: () => {
      void auth.logout().then(() => router.push({ name: 'login' }))
    },
  })

  return list
})

// allowInInput/allowInOverlay: the palette is a text field over a dialog, and
// the same key has to close it again from inside.
useShortcut({
  keys: 'mod+k',
  description: () => t('commandPalette.open'),
  allowInInput: true,
  allowInOverlay: true,
  enabled: () => appConfig.features.commandPalette && auth.isAuthenticated.value,
  handler: () => palette.toggle(),
})

const run = (action: () => void) => {
  palette.close()
  action()
}
</script>

<template>
  <CommandDialog
    v-if="appConfig.features.commandPalette"
    v-model:open="palette.isOpen.value"
  >
    <CommandInput :placeholder="t('commandPalette.placeholder')" />
    <CommandList>
      <CommandEmpty>{{ t('commandPalette.empty') }}</CommandEmpty>
      <CommandGroup :heading="t('commandPalette.navigation')">
        <CommandItem
          v-for="item in items"
          :key="item.routeName"
          :value="`${t(item.labelKey)} ${item.routeName}`"
          @select="run(() => router.push({ name: item.routeName }))"
        >
          <Icon :name="navigationIcons[item.icon]" />
          {{ t(item.labelKey) }}
        </CommandItem>
      </CommandGroup>
      <CommandGroup :heading="t('commandPalette.actions.heading')">
        <CommandItem
          v-for="command in commands"
          :key="command.id"
          :value="command.label"
          @select="run(command.run)"
        >
          <Icon :name="command.icon" />
          {{ command.label }}
          <CommandShortcut v-if="command.id === 'sidebar'">⌘B</CommandShortcut>
        </CommandItem>
      </CommandGroup>
    </CommandList>
  </CommandDialog>
</template>
