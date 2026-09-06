import { computed } from 'vue'

import { useAuth } from '~/composables/useAuth'
import { filterNavigationItems, navigationItems, type NavigationItem } from '~/lib/access-control'
import { appConfig } from '~/lib/app-config'

export interface NavigationSection {
  /** i18n key for the group heading, or `null` for an unlabelled group. */
  labelKey: string | null
  items: NavigationItem[]
}

/**
 * The nav the current user may actually use, grouped the way
 * `appConfig.sidebar.sections` asks for.
 *
 * Grouping is by route name, not by copying the items, so an item added at
 * `// kit:nav` in `access-control.ts` appears on its own: unlisted names land
 * in `overflowSection`. Empty groups are dropped, which is what keeps a
 * heading from hanging over nothing when access filtering removes its items.
 */
export function useNavigation() {
  const auth = useAuth()

  const items = computed(() => filterNavigationItems(navigationItems, { me: auth.me.value }))

  const sections = computed<NavigationSection[]>(() => {
    const config = appConfig.sidebar.sections
    if (config.length === 0) return [{ labelKey: null, items: items.value }]

    const buckets = config.map<NavigationSection>((section) => ({
      labelKey: section.labelKey ?? null,
      items: [],
    }))

    const overflow = Math.min(Math.max(appConfig.sidebar.overflowSection, 0), buckets.length - 1)
    const placement = new Map<string, number>()

    config.forEach((section, index) => {
      for (const routeName of section.items) placement.set(routeName, index)
    })

    // Config order inside a section, declaration order for what the config
    // never mentions: a generated feature lands at the end, not at random.
    const ordered = [...items.value].sort((a, b) => {
      const sectionA = placement.get(a.routeName) ?? overflow
      const sectionB = placement.get(b.routeName) ?? overflow
      if (sectionA !== sectionB) return sectionA - sectionB

      const rankA = config[sectionA]?.items.indexOf(a.routeName) ?? -1
      const rankB = config[sectionB]?.items.indexOf(b.routeName) ?? -1
      if (rankA === rankB) return 0
      if (rankA === -1) return 1
      if (rankB === -1) return -1
      return rankA - rankB
    })

    for (const item of ordered) {
      const bucket = buckets[placement.get(item.routeName) ?? overflow]
      bucket?.items.push(item)
    }

    return buckets.filter((section) => section.items.length > 0)
  })

  return { items, sections }
}
