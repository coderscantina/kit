<script setup lang="ts">
import Icon from '~/components/Icon.vue'
import { Avatar } from '~/components/ui/avatar'
import { useAuth } from '~/composables/useAuth'
import { useRoutePreload } from '~/composables/useRoutePreload'
import {
  accountNavigationItems,
  filterNavigationItems,
  navigationIcons,
} from '~/lib/access-control'
import { useI18n } from '~/plugins/i18n'

/**
 * The account area: who you are on the left, the page on the right. Wide
 * screens get a sticky column; a phone gets the same links as a scrollable
 * row under the identity, so the current page is always one tap away.
 */
const { t } = useI18n()
const auth = useAuth()
const route = useRoute()
const { preloadRoute, cancelPreload } = useRoutePreload()

const items = computed(() => filterNavigationItems(accountNavigationItems, { me: auth.me.value }))
</script>

<template>
  <div class="mx-auto grid w-full max-w-6xl gap-6 lg:grid-cols-[14rem_minmax(0,1fr)] lg:gap-12">
    <aside
      class="grid content-start gap-4 self-start lg:sticky lg:top-[calc(var(--shell-header-height)+var(--shell-gutter))]"
    >
      <div class="flex min-w-0 items-center gap-3">
        <Avatar
          :name="auth.user.value?.name ?? '?'"
          :avatar="auth.user.value?.avatarUrl"
          size="lg"
        />
        <div class="min-w-0">
          <p class="truncate text-sm font-semibold text-primary">{{ auth.user.value?.name }}</p>
          <p class="truncate text-xs text-muted">{{ auth.user.value?.email }}</p>
        </div>
      </div>

      <nav
        :aria-label="t('account.nav.label')"
        class="scroll-fade-x -mx-shell-gutter-x flex snap-x gap-1 px-shell-gutter-x lg:mx-0 lg:grid lg:px-0"
      >
        <RouterLink
          v-for="item in items"
          :key="item.routeName"
          :to="{ name: item.routeName }"
          class="flex h-shell-row shrink-0 snap-start items-center gap-2.5 rounded-lg px-3 text-sm text-muted transition-colors duration-200 ease-butter hover:bg-secondary/70 hover:text-primary"
          active-class="bg-secondary font-medium text-primary"
          @mouseenter="preloadRoute({ name: item.routeName })"
          @focusin="preloadRoute({ name: item.routeName })"
          @mouseleave="cancelPreload({ name: item.routeName })"
          @focusout="cancelPreload({ name: item.routeName })"
        >
          <Icon
            :name="navigationIcons[item.icon]"
            size="16"
            class="shrink-0"
            aria-hidden="true"
          />
          {{ t(item.labelKey) }}
        </RouterLink>
      </nav>
    </aside>

    <div class="min-w-0 max-w-3xl">
      <RouterView v-slot="{ Component }">
        <div
          :key="route.path"
          class="page-enter"
        >
          <component :is="Component" />
        </div>
      </RouterView>
    </div>
  </div>
</template>
