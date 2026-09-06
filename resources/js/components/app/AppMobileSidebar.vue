<script setup lang="ts">
import AppBrand from '~/components/app/AppBrand.vue'
import AppSidebarNav from '~/components/app/AppSidebarNav.vue'
import Icon from '~/components/Icon.vue'
import { Avatar } from '~/components/ui/avatar'
import { Sheet, SheetContent, SheetTitle } from '~/components/ui/sheet'
import { useAuth } from '~/composables/useAuth'
import { useSidebar } from '~/composables/useSidebar'
import {
  accountNavigationItems,
  filterNavigationItems,
  navigationIcons,
} from '~/lib/access-control'
import { useI18n } from '~/plugins/i18n'

/**
 * The phone's full menu: the nav the sidebar would show, then the account
 * block the user menu would show. On a phone the two are one sheet, opened
 * from the tab bar's Menu tab.
 */
const { t } = useI18n()
const auth = useAuth()
const sidebar = useSidebar()
const route = useRoute()
const router = useRouter()

const accountItems = computed(() =>
  filterNavigationItems(accountNavigationItems, { me: auth.me.value })
)

// A drawer that survives the navigation it triggered covers the page the user
// just asked for. Closing on the link click alone misses back/forward.
watch(
  () => route.fullPath,
  () => {
    sidebar.mobileOpen.value = false
  }
)

const close = () => {
  sidebar.mobileOpen.value = false
}

const logout = async () => {
  close()
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <Sheet v-model:open="sidebar.mobileOpen.value">
    <SheetContent
      side="left"
      class="flex w-[min(20rem,85vw)] flex-col gap-0 border-sidebar-border bg-sidebar p-0 pt-safe pb-safe text-sidebar-foreground"
    >
      <SheetTitle class="sr-only">{{ t('nav.label') }}</SheetTitle>
      <div class="flex h-shell-header shrink-0 items-center px-2">
        <AppBrand />
      </div>

      <AppSidebarNav @navigate="close" />

      <div class="shrink-0 border-t border-sidebar-border p-2">
        <div class="flex items-center gap-3 px-2 py-2">
          <Avatar
            :name="auth.user.value?.name ?? '?'"
            :avatar="auth.user.value?.avatarUrl"
            class="rounded-lg"
          />
          <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-sidebar-accent-foreground">
              {{ auth.user.value?.name }}
            </p>
            <p class="truncate text-xs text-sidebar-muted">{{ auth.user.value?.email }}</p>
          </div>
        </div>

        <RouterLink
          v-for="item in accountItems"
          :key="item.routeName"
          :to="{ name: item.routeName }"
          class="flex h-shell-row items-center gap-2.5 rounded-lg px-2 text-sm text-sidebar-muted transition-colors duration-200 ease-butter hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
          active-class="bg-sidebar-accent font-medium text-sidebar-accent-foreground"
          @click="close"
        >
          <Icon
            :name="navigationIcons[item.icon]"
            size="16"
            class="shrink-0 opacity-80"
            aria-hidden="true"
          />
          {{ t(item.labelKey) }}
        </RouterLink>

        <button
          type="button"
          class="flex h-shell-row w-full items-center gap-2.5 rounded-lg px-2 text-sm text-sidebar-muted transition-colors duration-200 ease-butter hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
          @click="logout"
        >
          <Icon
            name="lucide:log-out"
            size="16"
            class="shrink-0 opacity-80"
            aria-hidden="true"
          />
          {{ t('auth.logout') }}
        </button>
      </div>
    </SheetContent>
  </Sheet>
</template>
