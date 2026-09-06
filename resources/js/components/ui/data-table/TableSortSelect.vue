<script setup lang="ts">
import { computed } from 'vue'

import Icon from '~/components/Icon.vue'
import { Button } from '~/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger } from '~/components/ui/select'
import type { TableSort } from '~/composables/useTableQueryState'
import { useI18n } from '~/plugins/i18n'

/**
 * Column and direction as one control, for the screens where the table's own
 * header is off-screen or scrolled away. The button flips the direction; the
 * select picks the column.
 */
export interface SortOption {
  value: string
  label: string
}

const props = defineProps<{
  options: SortOption[]
}>()

const sort = defineModel<TableSort>({ required: true })

const { t } = useI18n()

const label = computed(
  () =>
    props.options.find((option) => option.value === sort.value.column)?.label ?? sort.value.column
)

const isAscending = computed(() => sort.value.direction === 'asc')

const flip = () => {
  sort.value = { column: sort.value.column, direction: isAscending.value ? 'desc' : 'asc' }
}
</script>

<template>
  <div class="inline-flex items-center">
    <Select
      :model-value="sort.column"
      @update:model-value="sort = { column: String($event), direction: sort.direction }"
    >
      <SelectTrigger
        class="h-8 rounded-r-none text-sm"
        :aria-label="t('sort.column')"
      >
        <span class="truncate">{{ label }}</span>
      </SelectTrigger>
      <SelectContent>
        <SelectItem
          v-for="option in options"
          :key="option.value"
          :value="option.value"
        >
          {{ option.label }}
        </SelectItem>
      </SelectContent>
    </Select>

    <Button
      variant="outline"
      size="sm"
      class="-ml-px h-8 rounded-l-none px-2"
      :aria-label="isAscending ? t('sort.toDescending') : t('sort.toAscending')"
      @click="flip"
    >
      <Icon :name="isAscending ? 'lucide:arrow-up-narrow-wide' : 'lucide:arrow-down-wide-narrow'" />
    </Button>
  </div>
</template>
