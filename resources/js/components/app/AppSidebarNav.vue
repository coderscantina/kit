<script setup lang="ts">
import AppNavLink from '~/components/app/AppNavLink.vue'
import { Skeleton } from '~/components/ui/skeleton'
import { useAuth } from '~/composables/useAuth'
import { useNavigation } from '~/composables/useNavigation'
import { useI18n } from '~/plugins/i18n'

defineProps<{ compact?: boolean }>()

const emit = defineEmits<{ navigate: [] }>()

const { t } = useI18n()
const auth = useAuth()
const { sections } = useNavigation()

// Abilities decide which items exist, so the nav has no honest shape until
// the session has loaded. Hold the space rather than popping items in.
const loading = computed(() => !auth.ready.value)
</script>

<template>
  <nav
    class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto py-2"
    :aria-label="t('nav.label')"
  >
    <template v-if="loading">
      <div class="flex flex-col gap-shell-gap px-2">
        <Skeleton
          v-for="n in 4"
          :key="n"
          class="h-shell-row w-full"
        />
      </div>
    </template>

    <div
      v-for="(section, index) in sections"
      v-else
      :key="section.labelKey ?? `section-${index}`"
      class="flex flex-col gap-shell-gap px-2"
    >
      <h2
        v-if="section.labelKey && !compact"
        class="px-2 pt-1 pb-1.5 text-2xs font-semibold tracking-wide text-sidebar-muted uppercase"
      >
        {{ t(section.labelKey) }}
      </h2>
      <!-- The rail has no room for a heading, so the group reads as a rule
           instead. Skipped on the first group, which needs no divider. -->
      <hr
        v-else-if="section.labelKey && index > 0"
        class="mx-2 mb-1 border-sidebar-border"
      />
      <AppNavLink
        v-for="item in section.items"
        :key="item.routeName"
        :item="item"
        :compact="compact"
        @navigate="emit('navigate')"
      />
    </div>
  </nav>
</template>
