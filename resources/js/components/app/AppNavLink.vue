<script setup lang="ts">
import Icon from '~/components/Icon.vue'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '~/components/ui/tooltip'
import { useRoutePreload } from '~/composables/useRoutePreload'
import { navigationIcons, type NavigationItem } from '~/lib/access-control'
import { useI18n } from '~/plugins/i18n'

const props = defineProps<{
  item: NavigationItem
  /** Rail mode: icon only, label moved into a tooltip. */
  compact?: boolean
}>()

const emit = defineEmits<{ navigate: [] }>()

const { t } = useI18n()
const { preloadRoute, cancelPreload } = useRoutePreload()

const to = computed(() => ({ name: props.item.routeName }))
const label = computed(() => t(props.item.labelKey))
</script>

<template>
  <!-- as-child, so the trigger stays the <a>: a tooltip must not wrap a link
       in a button, which is what kills both the semantics and middle-click. -->
  <TooltipProvider :delay-duration="300">
    <Tooltip>
      <TooltipTrigger as-child>
        <RouterLink
          :to="to"
          class="group/nav flex h-shell-row w-full items-center gap-2.5 rounded-lg px-2 text-sm text-sidebar-muted transition-colors duration-200 ease-butter hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
          :class="compact && 'justify-center px-0'"
          active-class="bg-sidebar-accent font-medium text-sidebar-accent-foreground"
          @mouseenter="preloadRoute(to)"
          @focusin="preloadRoute(to)"
          @mouseleave="cancelPreload(to)"
          @focusout="cancelPreload(to)"
          @click="emit('navigate')"
        >
          <Icon
            :name="navigationIcons[item.icon]"
            size="16"
            class="shrink-0 opacity-80 group-hover/nav:opacity-100"
            aria-hidden="true"
          />
          <span
            class="truncate"
            :class="compact && 'sr-only'"
          >
            {{ label }}
          </span>
        </RouterLink>
      </TooltipTrigger>
      <TooltipContent
        v-if="compact"
        side="right"
      >
        {{ label }}
      </TooltipContent>
    </Tooltip>
  </TooltipProvider>
</template>
