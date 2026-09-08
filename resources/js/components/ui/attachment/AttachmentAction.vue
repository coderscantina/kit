<script setup lang="ts">
import type { HTMLAttributes } from 'vue'

import type { ButtonVariants } from '~/components/ui/button'
import { Button } from '~/components/ui/button'
import { cn } from '~/lib/utils'

const props = withDefaults(
  defineProps<{
    class?: HTMLAttributes['class']
    variant?: ButtonVariants['variant']
  }>(),
  {
    variant: 'ghost',
  }
)
</script>

<template>
  <Button
    data-slot="attachment-action"
    :variant="variant"
    :class="cn('size-6 rounded-md p-0 [&_svg]:size-3.5', props.class)"
  >
    <slot />
  </Button>
</template>
