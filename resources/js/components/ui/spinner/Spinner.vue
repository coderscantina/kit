<script setup lang="ts">
import { LoaderCircle } from 'lucide-vue-next'

import { cn } from '~/lib/utils'

defineProps<{ class?: string }>()
</script>

<template>
  <LoaderCircle
    :class="cn('size-4 animate-spin', $props.class)"
    aria-hidden="true"
  />
</template>
