<script setup lang="ts">
import { cva, type VariantProps } from 'class-variance-authority'
import type { HTMLAttributes } from 'vue'

import { cn } from '~/lib/utils'

const alertVariants = cva('relative w-full rounded-lg border px-4 py-3 text-sm', {
  variants: {
    variant: {
      default: 'bg-background text-foreground',
      destructive: 'border-destructive/50 text-destructive',
    },
  },
  defaultVariants: { variant: 'default' },
})

defineProps<{
  variant?: VariantProps<typeof alertVariants>['variant']
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <div
    role="alert"
    :class="cn(alertVariants({ variant }), $props.class)"
  >
    <slot />
  </div>
</template>
