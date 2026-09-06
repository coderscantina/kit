<script setup lang="ts">
// The bare Toaster on purpose: `assets/css/vue-sonner.css` is a fork of the
// vendor sheet restyled onto the design tokens, so the vendor stylesheet is
// not imported and no per-part class overrides are needed here.
import { Toaster } from 'vue-sonner'

import CommandPalette from '~/components/CommandPalette.vue'
import ConfirmDialog from '~/components/ConfirmDialog.vue'
import AppLayout from '~/layouts/AppLayout.vue'
import UnauthenticatedLayout from '~/layouts/UnauthenticatedLayout.vue'

const route = useRoute()

const layout = computed(() =>
  route.meta.layout === 'unauthenticated' ? UnauthenticatedLayout : AppLayout
)
</script>

<template>
  <component :is="layout">
    <RouterView />
  </component>
  <CommandPalette />
  <ConfirmDialog />
  <Toaster
    position="bottom-right"
    rich-colors
  />
</template>
