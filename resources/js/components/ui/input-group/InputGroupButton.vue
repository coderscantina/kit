<script setup lang="ts">
import { Button } from '~/components/ui/button'
import { cn } from '~/lib/utils'

import { inputGroupButtonVariants, type InputGroupButtonProps } from './variants'

const props = withDefaults(defineProps<InputGroupButtonProps>(), {
  size: 'xs',
  variant: 'ghost',
  // An addon button clears, copies or reveals. Submitting is opt-in, because a
  // bare <button> inside a form submits it.
  type: 'button',
})
</script>

<template>
  <Button
    :type="props.type"
    :data-size="props.size"
    :variant="props.variant"
    :class="cn(inputGroupButtonVariants({ size: props.size }), props.class)"
  >
    <slot />
  </Button>
</template>
