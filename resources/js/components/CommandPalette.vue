<script setup lang="ts">
import { useEventListener } from '@vueuse/core'

import Icon from '~/components/Icon.vue'
import {
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '~/components/ui/command'
import { useAuth } from '~/composables/useAuth'
import { filterNavigationItems, navigationIcons, navigationItems } from '~/lib/access-control'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const auth = useAuth()
const router = useRouter()

const open = ref(false)

// The same filter the sidebar uses, so the palette never offers a page the
// user would only be bounced off.
const items = computed(() => filterNavigationItems(navigationItems, { me: auth.me.value }))

useEventListener(window, 'keydown', (event: KeyboardEvent) => {
  if (event.key !== 'k' || !(event.metaKey || event.ctrlKey)) return
  // Chrome focuses the address bar on Ctrl+K otherwise.
  event.preventDefault()

  if (!auth.isAuthenticated.value) return

  open.value = !open.value
})

const go = async (routeName: string) => {
  open.value = false
  await router.push({ name: routeName })
}
</script>

<template>
  <CommandDialog v-model:open="open">
    <CommandInput :placeholder="t('commandPalette.placeholder')" />
    <CommandList>
      <CommandEmpty>{{ t('commandPalette.empty') }}</CommandEmpty>
      <CommandGroup :heading="t('commandPalette.navigation')">
        <CommandItem
          v-for="item in items"
          :key="item.routeName"
          :value="item.routeName"
          @select="go(item.routeName)"
        >
          <Icon :name="navigationIcons[item.icon]" />
          {{ t(item.labelKey) }}
        </CommandItem>
      </CommandGroup>
    </CommandList>
  </CommandDialog>
</template>
