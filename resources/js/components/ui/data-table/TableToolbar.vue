<script setup lang="ts">
/**
 * The row above a list. The lead slot — segments, a title — holds the left;
 * the search field and the actions share the right and take about half the
 * row, which is what keeps the filter input wide enough to build a chip in.
 *
 * It wraps rather than scrolls, because a control that has scrolled out of a
 * toolbar is a control nobody finds. On a phone the two halves stack and each
 * takes the full width.
 */
</script>

<template>
  <div class="flex flex-wrap items-center justify-between gap-2">
    <div class="flex min-w-0 shrink-0 items-center gap-2">
      <slot name="lead" />
    </div>

    <div class="flex w-full min-w-0 flex-1 items-center gap-2 sm:w-auto sm:basis-1/2">
      <div class="min-w-0 flex-1">
        <slot />
      </div>

      <div class="flex shrink-0 items-center gap-1.5">
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>
