<script setup lang="ts">
import { Box, Home, LogOut, Shield, User, Users } from 'lucide-vue-next'

import { Button } from '~/components/ui/button'
import { useAuth } from '~/composables/useAuth'
import { useRoutePreload } from '~/composables/useRoutePreload'
import { filterNavigationItems, navigationItems } from '~/lib/access-control'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const auth = useAuth()
const router = useRouter()
const { preloadRoute, cancelPreload } = useRoutePreload()

// `box` is what make:feature gives a generated page; swap it for something
// that says what the feature is.
const icons = { home: Home, users: Users, user: User, shield: Shield, box: Box } as const

// The nav never shows a page the user cannot open.
const items = computed(() => filterNavigationItems(navigationItems, { me: auth.me.value }))

const logout = async () => {
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <div class="flex min-h-full">
    <aside class="flex w-56 shrink-0 flex-col border-r bg-card">
      <div class="px-4 py-5 text-lg font-semibold">Kit</div>
      <nav class="flex-1 space-y-1 px-2">
        <RouterLink
          v-for="item in items"
          :key="item.routeName"
          :to="{ name: item.routeName }"
          class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-muted-foreground hover:bg-accent hover:text-accent-foreground"
          active-class="bg-accent text-accent-foreground"
          @mouseenter="preloadRoute({ name: item.routeName })"
          @focusin="preloadRoute({ name: item.routeName })"
          @mouseleave="cancelPreload({ name: item.routeName })"
          @focusout="cancelPreload({ name: item.routeName })"
        >
          <component
            :is="icons[item.icon as keyof typeof icons]"
            class="size-4"
          />
          {{ t(item.labelKey) }}
        </RouterLink>
      </nav>
      <div class="border-t p-3">
        <p class="truncate px-2 text-xs text-muted-foreground">{{ auth.user.value?.email }}</p>
        <p
          v-if="auth.me.value?.impersonating"
          class="px-2 text-xs text-destructive"
        >
          {{ t('auth.impersonating') }}
        </p>
        <Button
          variant="ghost"
          size="sm"
          class="mt-1 w-full justify-start"
          @click="logout"
        >
          <LogOut />
          {{ t('auth.logout') }}
        </Button>
      </div>
    </aside>
    <!-- overflow-x-clip, not overflow-hidden: hidden breaks every sticky element inside. -->
    <main class="min-w-0 flex-1 overflow-x-clip p-6">
      <slot />
    </main>
  </div>
</template>
