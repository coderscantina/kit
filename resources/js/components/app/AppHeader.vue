<script setup lang="ts">
import AppBreadcrumbs from '~/components/app/AppBreadcrumbs.vue'
import AppUserMenu from '~/components/app/AppUserMenu.vue'
import Icon from '~/components/Icon.vue'
import { Button } from '~/components/ui/button'
import { useCommandPalette } from '~/composables/useCommandPalette'
import { useSidebar } from '~/composables/useSidebar'
import { appConfig } from '~/lib/app-config'
import { IS_MAC } from '~/lib/shortcuts'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const sidebar = useSidebar()
const palette = useCommandPalette()

const features = appConfig.features
</script>

<template>
  <!-- A container, not the viewport: the header's room depends on the sidebar
       width as much as on the window, so it adapts to its own slot. -->
  <header
    class="@container sticky top-0 z-20 flex h-shell-header shrink-0 items-center gap-2 border-b border-border bg-background/85 px-3 backdrop-blur-md"
  >
    <Button
      variant="ghost"
      size="icon"
      class="lg:hidden"
      :aria-label="t('shell.openNavigation')"
      @click="sidebar.mobileOpen.value = true"
    >
      <Icon name="lucide:menu" />
    </Button>

    <Button
      v-if="sidebar.collapsible"
      variant="ghost"
      size="icon"
      class="hidden lg:inline-flex"
      :aria-label="t('shell.toggleSidebar')"
      :aria-expanded="!sidebar.collapsed.value"
      aria-controls="app-sidebar"
      @click="sidebar.toggle()"
    >
      <Icon
        :name="sidebar.collapsed.value ? 'lucide:panel-left-open' : 'lucide:panel-left-close'"
      />
    </Button>

    <!-- Hidden below ~28rem of header, where the crumbs would out-shout the
         page's own title bar and the actions have nowhere left to go. -->
    <AppBreadcrumbs
      v-if="features.breadcrumbs"
      class="hidden min-w-0 @sm:block"
    />

    <div class="ml-auto flex items-center gap-1.5">
      <Button
        v-if="features.commandPalette"
        variant="outline"
        size="sm"
        class="gap-2 text-muted"
        :aria-label="t('commandPalette.open')"
        @click="palette.open()"
      >
        <Icon name="lucide:search" />
        <span class="hidden @md:inline">{{ t('commandPalette.trigger') }}</span>
        <kbd class="hidden rounded border border-border px-1 font-mono text-2xs @md:inline">
          {{ IS_MAC ? '⌘' : 'Ctrl' }}K
        </kbd>
      </Button>

      <!-- Filled by `<PageActions>` from whichever page is mounted. -->
      <div
        id="app-header-actions"
        class="flex items-center gap-1.5 empty:hidden"
      />

      <AppUserMenu />
    </div>
  </header>
</template>
