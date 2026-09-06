<script setup lang="ts">
import { computed, type Component } from 'vue'

import { TableCell, TableRow } from '~/components/ui/table'
import { useI18n } from '~/plugins/i18n'

/**
 * The "nothing here" row. It keeps the table's own edges rather than painting
 * a panel inside it, so an empty list still reads as the same list.
 */
const { t } = useI18n()

const props = withDefaults(
  defineProps<{
    colspan?: number
    // A component, never an icon name: `<Component :is>` would resolve a string
    // as a tag name and render an unknown element.
    icon?: Component
    label?: string
    description?: string
  }>(),
  {
    colspan: 3,
    icon: undefined,
    label: undefined,
    description: undefined,
  }
)

const iconComponent = computed(() => (typeof props.icon === 'string' ? undefined : props.icon))

const labelText = computed(() => props.label || t('labels.noResults'))
</script>

<template>
  <TableRow class="hover:bg-transparent">
    <TableCell
      :colspan="colspan"
      class="py-14 text-center select-none"
    >
      <div class="flex flex-col items-center justify-center gap-3">
        <Component
          :is="iconComponent"
          v-if="iconComponent"
          class="w-20 text-muted opacity-60"
        />
        <p class="font-medium text-primary">{{ labelText }}</p>
        <p
          v-if="description"
          class="max-w-sm text-sm text-balance text-muted"
        >
          {{ description }}
        </p>
        <slot name="actions" />
      </div>
    </TableCell>
  </TableRow>
</template>
