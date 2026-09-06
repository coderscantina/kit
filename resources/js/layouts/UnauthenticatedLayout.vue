<script setup lang="ts">
import ErrorBoundary from '~/components/ErrorBoundary.vue'
</script>

<template>
  <main
    class="flex min-h-full items-center justify-center overflow-x-clip bg-muted-background/40 p-6"
  >
    <div class="w-full max-w-sm">
      <ErrorBoundary>
        <slot />
      </ErrorBoundary>
    </div>
  </main>
</template>
