<script setup lang="ts">
/** A placeholder row while a table loads. `columns` matches the real row. */
defineProps<{ columns: number }>()
</script>

<template>
  <tr aria-hidden="true">
    <td
      v-for="index in columns"
      :key="index"
      class="px-3 py-3"
    >
      <div class="h-4 w-full max-w-40 animate-pulse rounded bg-muted" />
    </td>
  </tr>
</template>
