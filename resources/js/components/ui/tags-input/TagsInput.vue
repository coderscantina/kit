<script setup lang="ts">
import type { TagsInputRootEmits, TagsInputRootProps } from 'reka-ui'
import { TagsInputRoot, useForwardPropsEmits } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { computed } from 'vue'

import { cn } from '~/lib/utils'

const props = defineProps<TagsInputRootProps & { class?: HTMLAttributes['class'] }>()
const emits = defineEmits<TagsInputRootEmits>()

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props

  return delegated
})

const forwarded = useForwardPropsEmits(delegatedProps, emits)
</script>

<template>
  <TagsInputRoot
    v-bind="forwarded"
    :class="
      cn(
        'flex min-h-9 flex-wrap items-center gap-2 rounded-md border border-input-border bg-input px-3 py-1 text-sm text-primary shadow-sm transition-colors focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-ring',
        props.class
      )
    "
  >
    <slot />
  </TagsInputRoot>
</template>
