<script setup lang="ts">
import { computed } from 'vue'

import PerPageSelect from '~/components/PerPageSelect.vue'
import LaravelPagination from '~/components/ui/pagination/LaravelPagination.vue'
import { useI18n } from '~/plugins/i18n'
import type { PaginationMeta } from '~/types/api'

/**
 * The row under a table: what is shown, which page, how many per page. It
 * draws no rule of its own — the table above it closes its own box — and it
 * sits on the page's content edge, so its counter starts where the table's
 * first column does.
 *
 * On a phone the counter and the page-size select drop away and the pager
 * keeps the full width: on that screen the only question is "next".
 */
const { t } = useI18n()

const props = defineProps<{
  meta: PaginationMeta
  currentPage: number
  perPage: number
  pageSizeOptions?: number[]
}>()

const emit = defineEmits<{
  (e: 'update:currentPage' | 'update:perPage', value: number): void
}>()

const currentPageProxy = computed({
  get: () => props.currentPage,
  set: (value: number) => emit('update:currentPage', value),
})

const perPageProxy = computed({
  get: () => props.perPage,
  set: (value: number) => emit('update:perPage', value),
})

// t's named-interpolation argument wants an index signature, which PaginationMeta
// (deliberately) doesn't have.
const metaParams = computed(() => ({ ...props.meta }) as Record<string, unknown>)
</script>

<template>
  <div class="flex items-center gap-3 text-sm text-muted">
    <p class="hidden shrink-0 sm:block">
      {{ meta.total ? t('labels.showingEntries', metaParams) : t('labels.nothingToShow') }}
    </p>

    <LaravelPagination
      v-model="currentPageProxy"
      class="mx-auto"
      :meta="meta"
    />

    <PerPageSelect
      v-model="perPageProxy"
      class="hidden sm:flex"
      :options="pageSizeOptions"
      :label="t('labels.datasets.perPage')"
    />
  </div>
</template>
