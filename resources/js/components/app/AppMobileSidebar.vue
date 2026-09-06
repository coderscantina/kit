<script setup lang="ts">
import AppBrand from '~/components/app/AppBrand.vue'
import AppSidebarNav from '~/components/app/AppSidebarNav.vue'
import { Sheet, SheetContent, SheetTitle } from '~/components/ui/sheet'
import { useSidebar } from '~/composables/useSidebar'
import { useI18n } from '~/plugins/i18n'

const { t } = useI18n()
const sidebar = useSidebar()
const route = useRoute()

// A drawer that survives the navigation it triggered covers the page the user
// just asked for. Closing on the link click alone misses back/forward.
watch(
  () => route.fullPath,
  () => {
    sidebar.mobileOpen.value = false
  }
)
</script>

<template>
  <Sheet v-model:open="sidebar.mobileOpen.value">
    <SheetContent
      side="left"
      class="flex w-72 flex-col gap-0 border-sidebar-border bg-sidebar p-0 text-sidebar-foreground"
    >
      <SheetTitle class="sr-only">{{ t('nav.label') }}</SheetTitle>
      <div class="flex h-shell-header shrink-0 items-center px-2">
        <AppBrand />
      </div>
      <AppSidebarNav @navigate="sidebar.mobileOpen.value = false" />
    </SheetContent>
  </Sheet>
</template>
