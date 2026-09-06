<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue'

import Icon from '~/components/Icon.vue'
import { SplitBadge } from '~/components/ui/badge'
import { Popover, PopoverContent, PopoverTrigger } from '~/components/ui/popover'
import { useI18n } from '~/plugins/i18n'

import FilterChip from './FilterChip.vue'
import {
  decodeFilter,
  encodeFilter,
  operatorsFor,
  VALUELESS_OPERATORS,
  type ActiveFilter,
  type FilterField,
  type FilterOperator,
  type FilterOption,
} from './types'

/**
 * One input for both halves of narrowing a list: type to search, or pick a
 * field and build a filter chip.
 *
 * The chip is built in stages — field, operator, value — because that is the
 * order the question is asked in, and each stage is a list you can arrow
 * through. Backspace walks a stage back, and on an empty input removes the
 * chip before the caret, so a filter can be undone without reaching for the
 * mouse.
 *
 * The model is the URL's own query bag (`field -> operator:value`), so what
 * this component shows and what the request sends cannot drift.
 *
 * Below the `sm` breakpoint the chips collapse into a count that opens a
 * popover. Two chips are already wider than a phone, and inline they squeeze
 * the input to nothing, which is the one part of this control that has to keep
 * working: you cannot add a filter in a field you cannot type in. The count is
 * a button and the popover holds the same chips, so editing and removing stay
 * reachable from the keyboard.
 */
const props = defineProps<{
  fields: FilterField[]
  /** Placeholder for the resting state, before a field is picked. */
  placeholder?: string
}>()

const filters = defineModel<Record<string, string>>({ required: true })
const search = defineModel<string>('search', { default: '' })

const { t } = useI18n()

const instanceId = useId()
const listboxId = `${instanceId}-listbox`

type Stage = 'field' | 'operator' | 'value'

const input = ref('')
const stage = ref<Stage>('field')
const open = ref(false)
const highlighted = ref(0)
const pendingField = ref<FilterField | null>(null)
const pendingOperator = ref<FilterOperator | null>(null)
const editing = ref<string | null>(null)
const announcement = ref('')

const chipsOpen = ref(false)
// Matches the `sm` breakpoint. The chips collapse below it.
const isCompact = useMediaQuery('(width < 40rem)')

const container = ref<HTMLElement | null>(null)
const inputEl = ref<HTMLInputElement | null>(null)
const listEl = ref<HTMLElement | null>(null)

const fieldById = computed(() => new Map(props.fields.map((field) => [field.id, field])))

/** The chips, derived from the model: the URL is the source of truth. */
const active = computed<ActiveFilter[]>(() =>
  Object.entries(filters.value)
    .filter(([field]) => fieldById.value.has(field))
    .map(([field, raw]) => decodeFilter(field, raw))
)

const operatorLabel = (operator: FilterOperator): string => t(`filters.operators.${operator}`)

const valueLabel = (filter: ActiveFilter): string => {
  const field = fieldById.value.get(filter.field)
  const option = field?.options?.find((item) => item.value === filter.value)

  return option?.label ?? filter.value
}

const chipLabel = (filter: ActiveFilter): string =>
  [operatorLabel(filter.operator), valueLabel(filter)].filter(Boolean).join(' ')

const matches = (label: string): boolean =>
  input.value === '' || label.toLowerCase().includes(input.value.toLowerCase())

interface DropdownItem {
  key: string
  label: string
  hint?: string
}

const items = computed<DropdownItem[]>(() => {
  if (stage.value === 'field') {
    // A field already carrying a chip is spent, unless it is the one being
    // edited: two chips on one field would overwrite each other in the URL.
    return props.fields
      .filter((field) => field.id === editing.value || !(field.id in filters.value))
      .filter((field) => matches(field.label))
      .map((field) => ({ key: field.id, label: field.label }))
  }

  if (stage.value === 'operator' && pendingField.value) {
    return operatorsFor(pendingField.value)
      .filter((operator) => matches(operatorLabel(operator)))
      .map((operator) => ({ key: operator, label: operatorLabel(operator) }))
  }

  if (stage.value === 'value' && pendingField.value?.options) {
    return pendingField.value.options
      .filter((option) => matches(option.label))
      .map((option) => ({ key: option.value, label: option.label }))
  }

  return []
})

watch(items, () => {
  highlighted.value = 0
})

const placeholder = computed(() => {
  if (stage.value === 'operator') {
    return t('filters.pickOperator', { field: pendingField.value?.label ?? '' })
  }

  if (stage.value === 'value') {
    return pendingField.value?.options
      ? t('filters.pickValue', { field: pendingField.value.label })
      : t('filters.enterValue', { field: pendingField.value?.label ?? '' })
  }

  return props.placeholder ?? t('filters.placeholder')
})

const isDateStage = computed(
  () =>
    stage.value === 'value' && pendingField.value?.type === 'date' && !pendingField.value.options
)

const announce = (message: string) => {
  announcement.value = ''
  void nextTick(() => {
    announcement.value = message
  })
}

const focusInput = () => {
  void nextTick(() => inputEl.value?.focus())
}

const resetPending = () => {
  pendingField.value = null
  pendingOperator.value = null
  editing.value = null
  stage.value = 'field'
  input.value = ''
  highlighted.value = 0
}

const commit = (filter: ActiveFilter) => {
  const next = { ...filters.value }

  // Editing a chip may move it to a different field; drop the old key first.
  if (editing.value && editing.value !== filter.field) delete next[editing.value]

  next[filter.field] = encodeFilter(filter)
  filters.value = next

  announce(
    t('filters.applied', { field: fieldById.value.get(filter.field)?.label ?? filter.field })
  )
  resetPending()
  open.value = false
}

const removeFilter = (field: string) => {
  const next = { ...filters.value }
  delete next[field]
  filters.value = next

  announce(t('filters.removed', { field: fieldById.value.get(field)?.label ?? field }))
}

const chooseField = (field: FilterField) => {
  pendingField.value = field
  pendingOperator.value = null
  input.value = ''
  open.value = true

  const operators = operatorsFor(field)

  // One operator is not a choice; asking for it would be a keystroke that
  // never has a second answer.
  if (operators.length === 1) {
    pendingOperator.value = operators[0] ?? null
    stage.value = 'value'
  } else {
    stage.value = 'operator'
  }

  announce(t('filters.fieldPicked', { field: field.label }))
  focusInput()
}

const chooseOperator = (operator: FilterOperator) => {
  pendingOperator.value = operator
  input.value = ''

  if (VALUELESS_OPERATORS.includes(operator) && pendingField.value) {
    commit({ field: pendingField.value.id, operator, value: '' })
    return
  }

  stage.value = 'value'
  open.value = true
  focusInput()
}

const chooseValue = (value?: string) => {
  const field = pendingField.value
  if (!field) return

  const operator = pendingOperator.value ?? operatorsFor(field)[0] ?? 'eq'
  const resolved = value ?? input.value.trim()

  // Without a value there is nothing to commit; keep the pending selection
  // rather than throwing the field and operator choice away.
  if (resolved === '') return

  commit({ field: field.id, operator, value: resolved })
}

const editFilter = (filter: ActiveFilter) => {
  const field = fieldById.value.get(filter.field)
  if (!field) return

  editing.value = filter.field
  chipsOpen.value = false
  pendingField.value = field
  pendingOperator.value = filter.operator
  stage.value = 'value'
  input.value = field.options ? '' : filter.value
  open.value = true

  announce(t('filters.editing', { field: field.label }))
  focusInput()
}

const clearAll = () => {
  resetPending()
  open.value = false
  search.value = ''
  filters.value = {}
  announce(t('filters.cleared'))
  focusInput()
}

/** Commit what is typed, or abandon the half-built chip, when attention leaves. */
const settle = () => {
  if (stage.value === 'value' && input.value.trim() !== '') chooseValue()
  else resetPending()

  open.value = false
}

const scrollToHighlighted = () => {
  void nextTick(() => {
    listEl.value?.querySelector(`#${listboxId}-item-${highlighted.value}`)?.scrollIntoView({
      block: 'nearest',
    })
  })
}

const pick = (index: number) => {
  const item = items.value[index]
  if (!item) return

  if (stage.value === 'field') {
    const field = fieldById.value.get(item.key)
    if (field) chooseField(field)
    return
  }

  if (stage.value === 'operator') {
    chooseOperator(item.key as FilterOperator)
    return
  }

  chooseValue(item.key)
}

const stepBack = () => {
  if (stage.value === 'value') {
    const operators = pendingField.value ? operatorsFor(pendingField.value) : []
    stage.value = operators.length > 1 ? 'operator' : 'field'
    pendingOperator.value = null
    if (stage.value === 'field') pendingField.value = null
    return
  }

  stage.value = 'field'
  pendingField.value = null
}

const onKeydown = (event: KeyboardEvent) => {
  if (open.value && items.value.length > 0) {
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      highlighted.value = (highlighted.value + 1) % items.value.length
      scrollToHighlighted()
      return
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault()
      highlighted.value = highlighted.value <= 0 ? items.value.length - 1 : highlighted.value - 1
      scrollToHighlighted()
      return
    }
  }

  if (event.key === 'Enter') {
    event.preventDefault()

    if (open.value && items.value.length > 0) pick(highlighted.value)
    else if (stage.value === 'value') chooseValue()
    // At rest, Enter is the search: the typed text is the term, not a chip.
    else if (stage.value === 'field') search.value = input.value.trim()

    return
  }

  if (event.key === 'Backspace' && input.value === '') {
    if (stage.value !== 'field') {
      event.preventDefault()
      stepBack()
      open.value = true
      announce(t('filters.wentBack'))
      return
    }

    const last = active.value.at(-1)
    if (last) {
      event.preventDefault()
      removeFilter(last.field)
    }

    return
  }

  if (event.key === 'Escape') {
    event.preventDefault()

    if (stage.value !== 'field' || editing.value) resetPending()
    else if (open.value) open.value = false
    else if (input.value !== '') input.value = ''
    else clearAll()

    return
  }

  if (event.key === 'Tab') settle()
}

const onClickOutside = (event: MouseEvent) => {
  // The chip popover is portalled out of the container, so a click inside it
  // must not read as attention leaving the control.
  if (chipsOpen.value) return
  if (container.value && !container.value.contains(event.target as Node)) settle()
}

onMounted(() => document.addEventListener('mousedown', onClickOutside))
onBeforeUnmount(() => document.removeEventListener('mousedown', onClickOutside))

// The term can be cleared from outside (a reset, a saved view); the input
// mirrors it only while it is not busy building a chip.
watch(search, (value) => {
  if (stage.value === 'field' && !editing.value) input.value = value
})

const hasAnything = computed(
  () => active.value.length > 0 || input.value !== '' || search.value !== ''
)

const dateValue = ref('')

watch(isDateStage, (active) => {
  if (active) dateValue.value = input.value
})
</script>

<template>
  <div
    ref="container"
    class="relative w-full min-w-0"
  >
    <div
      class="flex min-h-9 w-full flex-wrap items-center gap-1.5 rounded-lg border border-input-border bg-input py-1 pr-1.5 pl-2.5 text-sm transition-colors focus-within:border-accent focus-within:ring-1 focus-within:ring-accent max-sm:min-h-10"
    >
      <Icon
        name="lucide:search"
        size="15"
        class="shrink-0 text-muted"
        aria-hidden="true"
      />

      <Popover
        v-if="isCompact && active.length > 0"
        v-model:open="chipsOpen"
      >
        <PopoverTrigger
          class="flex h-7 shrink-0 cursor-pointer items-center gap-1 rounded-md bg-secondary px-2 text-xs font-semibold text-secondary-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
          :aria-label="t('filters.chipCount', { count: active.length })"
        >
          <Icon
            name="lucide:list-filter"
            size="13"
            aria-hidden="true"
          />
          {{ active.length }}
        </PopoverTrigger>
        <PopoverContent
          align="start"
          class="w-[min(20rem,calc(100vw-2*var(--shell-gutter-x)))] p-2"
        >
          <div class="flex flex-wrap gap-1.5">
            <FilterChip
              v-for="filter in active"
              :key="filter.field"
              :field="fieldById.get(filter.field)?.label ?? filter.field"
              :summary="chipLabel(filter)"
              @edit="editFilter(filter)"
              @remove="removeFilter(filter.field)"
            />
          </div>
        </PopoverContent>
      </Popover>

      <template v-else>
        <template
          v-for="filter in active"
          :key="filter.field"
        >
          <FilterChip
            v-if="filter.field !== editing"
            :field="fieldById.get(filter.field)?.label ?? filter.field"
            :summary="chipLabel(filter)"
            @edit="editFilter(filter)"
            @remove="removeFilter(filter.field)"
          />
        </template>
      </template>

      <SplitBadge
        v-if="pendingField"
        size="sm"
        variant="secondary"
        label-variant="surface"
        :label="pendingField.label"
      >
        {{ pendingOperator ? operatorLabel(pendingOperator) : '…' }}
      </SplitBadge>

      <input
        ref="inputEl"
        v-model="input"
        type="text"
        role="combobox"
        data-shortcut-search
        class="min-w-24 flex-1 border-none bg-transparent py-1 text-sm text-foreground placeholder:text-muted focus:outline-none"
        :placeholder="placeholder"
        :aria-label="placeholder"
        :aria-expanded="open"
        aria-haspopup="listbox"
        :aria-controls="open ? listboxId : undefined"
        :aria-activedescendant="
          open && items.length > 0 ? `${listboxId}-item-${highlighted}` : undefined
        "
        @focus="open = true"
        @input="open = true"
        @keydown="onKeydown"
      />

      <button
        v-if="hasAnything"
        type="button"
        class="flex size-5 shrink-0 items-center justify-center rounded-full text-muted transition-colors hover:bg-secondary hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring max-sm:size-8"
        :aria-label="t('filters.clearAll')"
        @click="clearAll"
      >
        <Icon
          name="lucide:x"
          size="14"
        />
      </button>
    </div>

    <div
      v-if="open"
      :id="listboxId"
      ref="listEl"
      role="listbox"
      :aria-label="t(`filters.stages.${stage}`)"
      class="absolute z-50 mt-1.5 max-h-[min(16rem,50vh)] w-full overflow-y-auto rounded-lg border border-popover-border bg-popover p-1 shadow-soft-lg"
    >
      <template v-if="items.length > 0">
        <div
          v-for="(item, index) in items"
          :id="`${listboxId}-item-${index}`"
          :key="item.key"
          role="option"
          :aria-selected="index === highlighted"
          class="flex cursor-pointer items-center justify-between gap-2 rounded-md px-2 py-1.5 text-sm text-popover-foreground select-none aria-selected:bg-accent aria-selected:text-accent-foreground max-sm:min-h-10"
          @mousemove="highlighted = index"
          @click="pick(index)"
        >
          {{ item.label }}
          <span
            v-if="item.hint"
            class="text-xs opacity-70"
            >{{ item.hint }}</span
          >
        </div>
      </template>

      <div
        v-else-if="isDateStage"
        class="p-2"
      >
        <label
          :for="`${listboxId}-date`"
          class="sr-only"
          >{{ placeholder }}</label
        >
        <input
          :id="`${listboxId}-date`"
          v-model="dateValue"
          type="date"
          class="w-full rounded-md border border-input-border bg-input px-2 py-1.5 text-sm text-foreground"
          @change="chooseValue(dateValue)"
        />
      </div>

      <p
        v-else-if="stage === 'value'"
        class="px-2 py-2 text-sm text-muted select-none"
        role="status"
      >
        {{ t('filters.typeValue') }}
      </p>

      <p
        v-else
        class="px-2 py-2 text-sm text-muted select-none"
        role="status"
      >
        {{ t('filters.noMatch') }}
      </p>
    </div>

    <span
      class="sr-only"
      role="status"
      aria-live="polite"
      aria-atomic="true"
    >
      {{ announcement }}
    </span>
  </div>
</template>
