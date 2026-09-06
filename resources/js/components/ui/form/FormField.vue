<script setup lang="ts">
import type { HTMLAttributes } from 'vue'

import FieldPresence from '~/components/ui/form/FieldPresence.vue'
import Label from '~/components/ui/form/Label.vue'
import { TooltipIcon } from '~/components/ui/tooltip'
import { useFieldPresence } from '~/composables/useFieldPresence'

const props = defineProps<{
  id?: string
  label?: string
  required?: boolean
  hideLabel?: boolean
  tooltip?: string
  description?: string
  name: string
  error?: string | null
  class?: HTMLAttributes['class']
  /**
   * Only for a control that changes without a DOM event, a select or a
   * combobox. Native inputs are read off their own events.
   */
  dirty?: boolean
}>()

const uniqueId = computed(
  () => props.id || `${props.name}-${Math.random().toString(36).substring(2, 9)}`
)
const hasError = computed(() => !!props.error)

// Inert, down to the listeners, unless a form called provideFieldPresence.
const presence = useFieldPresence(() => props.name)

watch(
  () => props.dirty,
  (dirty) => presence.setDirty(dirty === true)
)
</script>

<template>
  <div
    :class="['grid w-full items-center gap-2', props.class]"
    v-on="presence.handlers"
  >
    <div
      v-if="label || presence.editors.value.length > 0"
      class="flex items-center gap-2"
    >
      <Label
        v-if="label"
        :label="label"
        :hide-label="hideLabel"
        :required="required"
        :for="uniqueId"
        :has-error="hasError"
      />
      <TooltipIcon v-if="tooltip">{{ tooltip }}</TooltipIcon>
      <FieldPresence
        :editors="presence.editors.value"
        class="ml-auto"
      />
    </div>
    <slot
      v-bind="{
        id: uniqueId,
        name: props.name,
      }"
      :has-error="hasError"
    />
    <p
      v-if="hasError"
      class="mt-1 text-sm leading-tight whitespace-pre-line text-destructive"
    >
      {{ error }}
    </p>
    <p
      v-if="description"
      class="text-sm text-text-muted"
    >
      {{ description }}
    </p>
  </div>
</template>
