<script setup lang="ts">
import { SplitBadge } from '~/components/ui/badge'
import { useI18n } from '~/plugins/i18n'

/**
 * One built filter, as a two-part badge: the field it names, then how it
 * matches. Clicking it reopens the value step, so a chip is edited where it
 * is shown rather than removed and rebuilt.
 *
 * It lives in its own file because the bar shows chips inline on a wide screen
 * and inside a popover on a phone, and one chip that behaves the same in both
 * places is what keeps the two from drifting.
 */
defineProps<{
  /** The field's own label, already resolved. */
  field: string
  /** Operator and value, read out: "contains acme". */
  summary: string
}>()

const emit = defineEmits<{
  edit: []
  remove: []
}>()

const { t } = useI18n()
</script>

<template>
  <SplitBadge
    size="sm"
    variant="secondary"
    label-variant="surface"
    :label="field"
    removable
    :remove-label="t('filters.remove', { field })"
    role="button"
    tabindex="0"
    class="cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
    :aria-label="t('filters.edit', { field })"
    @remove="emit('remove')"
    @click="emit('edit')"
    @keydown.enter.prevent="emit('edit')"
    @keydown.space.prevent="emit('edit')"
  >
    {{ summary }}
  </SplitBadge>
</template>
