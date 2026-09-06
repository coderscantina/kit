<script setup lang="ts">
import { PaginationFirst, type PaginationFirstProps } from 'reka-ui'
import { computed, type HTMLAttributes } from 'vue'

import Icon from '~/components/Icon.vue'
import { Button } from '~/components/ui/button'
import { cn } from '~/lib/utils'

const props = withDefaults(
  defineProps<PaginationFirstProps & { class?: HTMLAttributes['class'] }>(),
  {
    asChild: true,
  }
)

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props

  return delegated
})
</script>

<template>
  <PaginationFirst v-bind="delegatedProps">
    <Button :class="cn('h-9 w-9 !p-0', props.class)">
      <slot>
        <Icon name="lucide:chevron-first" />
      </slot>
    </Button>
  </PaginationFirst>
</template>
