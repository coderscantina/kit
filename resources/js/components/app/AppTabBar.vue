<script setup lang="ts">
import Icon from '~/components/Icon.vue'
import { useAuth } from '~/composables/useAuth'
import { useNavigation } from '~/composables/useNavigation'
import { useSidebar } from '~/composables/useSidebar'
import { navigationIcons } from '~/lib/access-control'
import { appConfig } from '~/lib/app-config'
import { useI18n } from '~/plugins/i18n'

/**
 * The phone's navigation: the first few nav items as thumb-reach tabs, and a
 * trailing "Menu" tab that opens the drawer with everything else, the
 * account pages included. Hidden from `lg` up, where the sidebar takes over.
 *
 * Sits above the home indicator through `pb-safe`, so on a notched phone the
 * tabs are never under the system gesture bar.
 */
const { t } = useI18n()
const auth = useAuth()
const sidebar = useSidebar()
const { items } = useNavigation()

const tabs = computed(() => items.value.slice(0, appConfig.mobile.tabBarItems))
</script>

<template>
  <nav
    v-if="auth.ready.value"
    :aria-label="t('shell.mobileNavigation')"
    class="fixed inset-x-0 bottom-0 z-30 border-t border-sidebar-border bg-sidebar/85 pb-safe backdrop-blur-md lg:hidden"
  >
    <ul class="grid h-shell-tabbar auto-cols-fr grid-flow-col">
      <li
        v-for="item in tabs"
        :key="item.routeName"
      >
        <RouterLink
          :to="{ name: item.routeName }"
          class="group/tab flex h-full flex-col items-center justify-center gap-1 text-2xs font-medium text-sidebar-muted transition-colors duration-200 ease-butter active:text-sidebar-accent-foreground"
          active-class="text-sidebar-accent-foreground"
        >
          <span
            class="grid h-7 w-12 place-items-center rounded-full transition-colors duration-200 ease-butter group-[.router-link-active]/tab:bg-sidebar-accent"
          >
            <Icon
              :name="navigationIcons[item.icon]"
              size="20"
              aria-hidden="true"
            />
          </span>
          <span class="truncate">{{ t(item.labelKey) }}</span>
        </RouterLink>
      </li>
      <li>
        <button
          type="button"
          class="flex h-full w-full flex-col items-center justify-center gap-1 text-2xs font-medium text-sidebar-muted transition-colors duration-200 ease-butter active:text-sidebar-accent-foreground"
          :aria-expanded="sidebar.mobileOpen.value"
          @click="sidebar.mobileOpen.value = true"
        >
          <span class="grid h-7 w-12 place-items-center rounded-full">
            <Icon
              name="lucide:menu"
              size="20"
              aria-hidden="true"
            />
          </span>
          <span>{{ t('shell.menu') }}</span>
        </button>
      </li>
    </ul>
  </nav>
</template>
