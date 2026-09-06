<script setup lang="ts" generic="T extends string">
import { computed } from 'vue'

import Icon from '~/components/Icon.vue'
import { Badge } from '~/components/ui/badge'
import { Button } from '~/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuTrigger,
} from '~/components/ui/dropdown-menu'
import { Tabs, TabsList, TabsTrigger } from '~/components/ui/tabs'
import { useI18n } from '~/plugins/i18n'

import type { TableSegment } from './types'

/**
 * The list's coarse cut — everything, or one slice of it. On a wide screen it
 * is a row of tabs, each carrying its count, because the whole choice is worth
 * the width when there is width. Below `40rem` — the same breakpoint the rows
 * stack and the filter chips collapse at — it becomes one button that says
 * which slice is showing and how many are in it, with the rest in a menu.
 *
 * Both forms are rendered and the other is `display: none`, so the swap costs
 * no media query in JavaScript and no wrong control on the first frame. A
 * hidden form is out of the accessibility tree and out of the tab order.
 *
 * It writes whatever the caller binds, which on every list so far is the same
 * `status` filter the chips write, so the URL and a saved view are unaffected.
 */
const props = defineProps<{
  segments: readonly TableSegment<T>[]
}>()

const current = defineModel<T>({ required: true })

const { t } = useI18n()

const active = computed(
  () => props.segments.find((segment) => segment.value === current.value) ?? props.segments[0]
)

// Both controls are typed to reka's `AcceptableValue`. What they can emit is
// one of the values this component was given, which is a `T`.
const pick = (value: unknown) => {
  current.value = String(value) as T
}
</script>

<template>
  <Tabs
    class="max-sm:hidden"
    :model-value="current"
    @update:model-value="pick"
  >
    <TabsList :aria-label="t('labels.segment')">
      <TabsTrigger
        v-for="segment in segments"
        :key="segment.value"
        :value="segment.value"
      >
        {{ segment.label }}
        <Badge
          v-if="segment.count !== undefined"
          variant="secondary"
          size="xs"
          >{{ segment.count }}</Badge
        >
      </TabsTrigger>
    </TabsList>
  </Tabs>

  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button
        variant="outline"
        class="h-10 sm:hidden"
      >
        <span class="sr-only">{{ t('labels.segment') }}</span>
        {{ active?.label }}
        <Badge
          v-if="active?.count !== undefined"
          variant="secondary"
          size="xs"
          >{{ active.count }}</Badge
        >
        <Icon
          name="lucide:chevron-down"
          class="text-muted"
          aria-hidden="true"
        />
      </Button>
    </DropdownMenuTrigger>

    <DropdownMenuContent
      align="start"
      class="w-52"
    >
      <DropdownMenuRadioGroup
        :model-value="current"
        @update:model-value="pick"
      >
        <DropdownMenuRadioItem
          v-for="segment in segments"
          :key="segment.value"
          :value="segment.value"
          class="justify-between gap-3"
        >
          <span class="truncate">{{ segment.label }}</span>
          <Badge
            v-if="segment.count !== undefined"
            variant="secondary"
            size="xs"
            >{{ segment.count }}</Badge
          >
        </DropdownMenuRadioItem>
      </DropdownMenuRadioGroup>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
