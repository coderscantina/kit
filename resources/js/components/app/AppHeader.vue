<script setup lang="ts">
import AppBrand from '~/components/app/AppBrand.vue'
import AppBreadcrumbs from '~/components/app/AppBreadcrumbs.vue'
import AppUserMenu from '~/components/app/AppUserMenu.vue'
import Icon from '~/components/Icon.vue'
import NotificationBell from '~/components/notifications/NotificationBell.vue'
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
       width as much as on the window, so it adapts to its own slot.
       `pt-safe` keeps it under a notch when the app runs from the home screen. -->
  <header
    class="@container sticky top-0 z-20 flex shrink-0 items-center gap-2 border-b border-sidebar-border bg-sidebar/85 px-3 pt-safe backdrop-blur-md"
  >
    <div class="flex h-shell-header min-w-0 flex-1 items-center gap-2">
      <!-- The phone has no sidebar to hold the brand, so the header does. The
           menu itself lives in the tab bar, within reach of a thumb. -->
      <AppBrand
        compact
        class="lg:hidden"
      />

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
          class="gap-2 text-muted max-@md:size-9 max-@md:px-0"
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

        <NotificationBell />

        <AppUserMenu />
      </div>
    </div>
  </header>
</template>
