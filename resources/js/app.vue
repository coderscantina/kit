<script setup lang="ts">
// The bare Toaster on purpose: `assets/css/vue-sonner.css` is a fork of the
// vendor sheet restyled onto the design tokens, so the vendor stylesheet is
// not imported and no per-part class overrides are needed here.
import { Toaster } from 'vue-sonner'

import CommandPalette from '~/components/CommandPalette.vue'
import ConfirmDialog from '~/components/ConfirmDialog.vue'
import KeyboardShortcutsDialog from '~/components/KeyboardShortcutsDialog.vue'
import AppLayout from '~/layouts/AppLayout.vue'
import UnauthenticatedLayout from '~/layouts/UnauthenticatedLayout.vue'

const route = useRoute()

const layout = computed(() =>
  route.meta.layout === 'unauthenticated' ? UnauthenticatedLayout : AppLayout
)

/**
 * Keyed on the top-level route record, so a page re-enters when the section
 * changes and stays put when only its query does: a filtered list must not
 * remount on every keystroke. Nested areas key their own `RouterView`.
 */
const pageKey = computed(() => route.matched[0]?.path ?? route.path)
</script>

<template>
  <component :is="layout">
    <RouterView v-slot="{ Component }">
      <!-- An enter-only animation, not a cross-fade: the old page leaves at
           once and the new one rises in, so nothing overlaps and the focus
           move in AppLayout lands on a page that is already there. -->
      <div
        :key="pageKey"
        class="page-enter"
      >
        <component :is="Component" />
      </div>
    </RouterView>
  </component>
  <CommandPalette />
  <ConfirmDialog />
  <KeyboardShortcutsDialog />
  <!-- Three at a time, four seconds, dismissible. A toast is a receipt, not a
       log: anything that has to be read twice belongs on the page. On a phone
       the stack sits above the tab bar and the home indicator. -->
  <Toaster
    position="bottom-right"
    :duration="4000"
    :visible-toasts="3"
    :mobile-offset="{
      bottom: 'calc(var(--shell-tabbar-height) + env(safe-area-inset-bottom) + 12px)',
    }"
    close-button
    rich-colors
  />
</template>
