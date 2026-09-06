<script setup lang="ts">
import Icon from '~/components/Icon.vue'
import { useI18n } from '~/plugins/i18n'

import Tooltip from './Tooltip.vue'
import TooltipContent from './TooltipContent.vue'
import TooltipProvider from './TooltipProvider.vue'
import TooltipTrigger from './TooltipTrigger.vue'

const { t } = useI18n()

withDefaults(
  defineProps<{
    icon?: string
    size?: string
    delayDuration?: number
  }>(),
  {
    icon: 'lucide:info',
    size: '1rem',
    delayDuration: 300,
  }
)
</script>

<template>
  <TooltipProvider :delay-duration="delayDuration">
    <Tooltip>
      <TooltipTrigger
        type="button"
        class="flex items-center"
        :aria-label="t('labels.moreInfo')"
      >
        <Icon
          :name="icon"
          :size="size"
        />
      </TooltipTrigger>
      <TooltipContent>
        <slot />
      </TooltipContent>
    </Tooltip>
  </TooltipProvider>
</template>
