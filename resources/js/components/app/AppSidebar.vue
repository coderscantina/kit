<script setup lang="ts">
import AppBrand from '~/components/app/AppBrand.vue'
import AppSidebarNav from '~/components/app/AppSidebarNav.vue'
import AppSidebarResizer from '~/components/app/AppSidebarResizer.vue'
import { useSidebar } from '~/composables/useSidebar'
import { runtimeConfig } from '~/lib/runtime-config'

const sidebar = useSidebar()
const dragging = ref(false)
</script>

<template>
  <aside
    id="app-sidebar"
    class="relative hidden shrink-0 flex-col border-r border-sidebar-border bg-sidebar text-sidebar-foreground select-none lg:flex"
    :class="!dragging && 'transition-[width] duration-200 ease-butter'"
    :style="{ width: `${sidebar.currentWidth.value}px` }"
  >
    <div class="flex h-shell-header shrink-0 items-center px-2">
      <AppBrand :compact="sidebar.collapsed.value" />
    </div>

    <AppSidebarNav :compact="sidebar.collapsed.value" />

    <p
      v-if="runtimeConfig.version && !sidebar.collapsed.value"
      class="shrink-0 truncate px-4 pb-3 font-mono text-2xs text-sidebar-muted"
    >
      {{ runtimeConfig.version }}
    </p>

    <AppSidebarResizer
      v-if="sidebar.resizable && !sidebar.collapsed.value"
      v-model:dragging="dragging"
    />
  </aside>
</template>
