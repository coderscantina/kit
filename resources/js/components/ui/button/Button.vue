<script setup lang="ts">
import { Primitive, type PrimitiveProps } from 'reka-ui'
import type { HTMLAttributes } from 'vue'

import { Spinner } from '~/components/ui/spinner'
import { cn } from '~/lib/utils'

import { buttonVariants, type ButtonVariants } from './variants'

interface Props extends PrimitiveProps {
  variant?: ButtonVariants['variant']
  size?: ButtonVariants['size']
  class?: HTMLAttributes['class']
  /**
   * Disables and marks busy immediately, locks the current width, and shows
   * the spinner only after 250 ms so a fast request never flashes one.
   */
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), { as: 'button' })

const root = ref<InstanceType<typeof Primitive>>()
const showSpinner = ref(false)
const lockedWidth = ref<string>()
let spinnerTimer: ReturnType<typeof setTimeout> | undefined

watch(
  () => props.loading,
  (loading) => {
    clearTimeout(spinnerTimer)

    if (loading) {
      const el = root.value?.$el as HTMLElement | undefined
      lockedWidth.value = el?.offsetWidth ? `${el.offsetWidth}px` : undefined
      spinnerTimer = setTimeout(() => {
        showSpinner.value = true
      }, 250)
    } else {
      showSpinner.value = false
      lockedWidth.value = undefined
    }
  }
)

onUnmounted(() => clearTimeout(spinnerTimer))
</script>

<template>
  <Primitive
    ref="root"
    :as="as"
    :as-child="asChild"
    :disabled="loading || undefined"
    :aria-busy="loading || undefined"
    :class="cn(buttonVariants({ variant, size }), props.class)"
    :style="lockedWidth ? { minWidth: lockedWidth } : undefined"
  >
    <Spinner v-if="showSpinner" />
    <slot />
  </Primitive>
</template>
