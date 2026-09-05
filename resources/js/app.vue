<script setup lang="ts">
import { Toaster } from 'vue-sonner'

import AppLayout from '~/layouts/AppLayout.vue'
import UnauthenticatedLayout from '~/layouts/UnauthenticatedLayout.vue'

import 'vue-sonner/style.css'

const route = useRoute()

const layout = computed(() =>
  route.meta.layout === 'unauthenticated' ? UnauthenticatedLayout : AppLayout
)
</script>

<template>
  <component :is="layout">
    <RouterView />
  </component>
  <Toaster
    position="bottom-right"
    rich-colors
  />
</template>
