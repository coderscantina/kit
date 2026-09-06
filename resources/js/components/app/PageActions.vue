<script setup lang="ts">
/**
 * Puts a page's primary actions in the app header, next to the breadcrumbs,
 * without the page having to know the shell exists. Teleport rather than a
 * layout slot: a page is rendered by `<RouterView>`, so it has no slot to
 * fill.
 *
 *   <PageActions><Button>New post</Button></PageActions>
 */
const TARGET = '#app-header-actions'
</script>

<template>
  <!-- `defer`: the header is patched in the same pass as the page, so the
       target does not exist yet when this teleport is first evaluated.
       Deferring resolves it after the render cycle instead. -->
  <Teleport
    defer
    :to="TARGET"
  >
    <slot />
  </Teleport>
</template>
