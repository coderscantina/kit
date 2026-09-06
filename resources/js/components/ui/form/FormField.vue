<script setup lang="ts">
import type { HTMLAttributes } from 'vue'

import FieldPresence from '~/components/ui/form/FieldPresence.vue'
import Label from '~/components/ui/form/Label.vue'
import { TooltipIcon } from '~/components/ui/tooltip'
import { useFieldPresence } from '~/composables/useFieldPresence'
import { useI18n } from '~/plugins/i18n'

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

const { t } = useI18n()

// Inert, down to the listeners, unless a form called provideFieldPresence.
const presence = useFieldPresence(() => props.name)

/** Editors whose value came along: only on a form that opted in. */
const typing = computed(() =>
  presence.editors.value.filter((editor) => editor.value !== undefined && editor.value !== '')
)

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
      v-for="editor in typing"
      :key="editor.member.id"
      class="truncate text-xs text-text-muted"
      :title="t('presence.field.typing', { name: editor.member.name })"
    >
      <span
        class="font-medium"
        :style="{ color: editor.member.color }"
        >{{ editor.member.name }}</span
      >: {{ editor.value }}
    </p>
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
