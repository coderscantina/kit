<script setup lang="ts">
import { useVModel } from '@vueuse/core'
import type { HTMLAttributes } from 'vue'
import { computed } from 'vue'
import { toast } from 'vue-sonner'

import Icon from '~/components/Icon.vue'
import {
  InputGroup,
  InputGroupAddon,
  InputGroupButton,
  InputGroupInput,
} from '~/components/ui/input-group'
import { useI18n } from '~/plugins/i18n'

import FormField from './FormField.vue'

const { t } = useI18n()

type InputActionType = 'clear' | 'copy'

const props = defineProps<{
  // FormField props
  id?: string
  label?: string
  required?: boolean
  tooltip?: string
  description?: string
  error?: string | null
  autoFocus?: boolean
  class?: HTMLAttributes['class']
  inputClass?: HTMLAttributes['class']
  actions?: Array<InputActionType>
  actionTabindex?: number | string

  // Input props
  modelValue?: string | number | null
  defaultValue?: string | number | null
  placeholder?: unknown
  type?: string
  disabled?: boolean
  readonly?: boolean
  name: string
}>()

const icons = {
  clear: 'lucide:x-circle',
  copy: 'lucide:copy',
}

const emits = defineEmits<{
  (e: 'update:modelValue' | InputActionType, payload: string | number): void
}>()

const modelValue = useVModel(props, 'modelValue', emits, {
  passive: true,
  defaultValue: props.defaultValue,
})

const slots = defineSlots<{
  prepend?: () => unknown
  append?: () => unknown
}>()

const inputProps = computed(() => {
  const {
    //
    id,
    label,
    tooltip,
    description,
    error,
    actions,
    actionTabindex,
    class: _class,
    inputClass: _inputClass,
    modelValue: _modelValue,
    defaultValue: _defaultValue,
    ...rest
  } = props

  return rest
})

/** Whether anything sits after the control, so the addon can be left out. */
const hasTrailing = computed(() => !!props.actions?.length || !!slots.append)

const trigger = (action: InputActionType) => {
  if (action === 'clear') {
    modelValue.value = ''
  } else if (action === 'copy') {
    navigator.clipboard.writeText(modelValue.value as string)
    toast.info(t('notifications.inputField.copied'))
  }
}
</script>

<template>
  <FormField
    :id="id"
    :label="label"
    :name="name"
    :required="required"
    :tooltip="tooltip"
    :description="description"
    :error="error"
    :class="props.class"
  >
    <template #default="{ id, hasError }">
      <InputGroup :class="props.inputClass">
        <InputGroupAddon v-if="slots.prepend">
          <slot name="prepend" />
        </InputGroupAddon>
        <InputGroupInput
          :id="id"
          v-model="modelValue"
          :aria-invalid="hasError || undefined"
          v-bind="{ ...inputProps, ...$attrs }"
        />
        <InputGroupAddon
          v-if="hasTrailing"
          align="inline-end"
        >
          <slot name="append" />
          <InputGroupButton
            v-for="action in actions"
            :key="action"
            size="icon-xs"
            :aria-label="t(`actions.${action}`)"
            :tabindex="actionTabindex"
            @click="trigger(action)"
          >
            <Icon :name="icons[action]" />
          </InputGroupButton>
        </InputGroupAddon>
      </InputGroup>
    </template>
  </FormField>
</template>
