import { computed, onScopeDispose, ref, toValue, watchEffect, type MaybeRefOrGetter } from 'vue'
import { useRoute, type RouteLocationRaw } from 'vue-router'

import { navigationItems } from '~/lib/access-control'
import { appConfig } from '~/lib/app-config'
import { isClient } from '~/lib/env'
import { useI18n } from '~/plugins/i18n'

export interface Breadcrumb {
  label: string
  /** Omit on the last crumb: the page you are on is not a link. */
  to?: RouteLocationRaw
}

export interface PageMeta {
  /** Header title and, through `appConfig.titleTemplate`, the document title. */
  title: string
  /** One line under the title. Skip it rather than restating the title. */
  description?: string
  /** Overrides the trail derived from the route's nav item. */
  breadcrumbs?: Breadcrumb[]
}

/**
 * One page owns the header at a time, so this is a single slot rather than a
 * stack. A late unmount cannot wipe the incoming page's title: the clear only
 * fires while the leaving page is still the owner.
 */
const current = ref<PageMeta | null>(null)
let owner: symbol | null = null

/**
 * Declares what the shell header shows for this page, and sets the document
 * title. Call it once per page component, with a getter when the title depends
 * on loaded data.
 */
export function usePageMeta(source: MaybeRefOrGetter<PageMeta>): void {
  const token = Symbol('page-meta')

  watchEffect(() => {
    const meta = toValue(source)
    current.value = meta
    owner = token

    // Set here, not in the header's read side: the auth pages have no header,
    // and a tab that says nothing but the brand name is a worse tab.
    if (isClient) document.title = appConfig.titleTemplate(meta.title)
  })

  onScopeDispose(() => {
    if (owner !== token) return
    current.value = null
    owner = null
    if (isClient) document.title = appConfig.brand.name
  })
}

/** The header's read side: the current title and the trail to show for it. */
export function useCurrentPageMeta() {
  const route = useRoute()
  const { t } = useI18n()

  const navLabel = computed(() => {
    const item = navigationItems.find((entry) => entry.routeName === route.name)
    return item ? t(item.labelKey) : null
  })

  const title = computed(() => current.value?.title ?? navLabel.value ?? '')

  const breadcrumbs = computed<Breadcrumb[]>(() => {
    if (current.value?.breadcrumbs) return current.value.breadcrumbs
    return title.value ? [{ label: title.value }] : []
  })

  return {
    meta: computed(() => current.value),
    title,
    description: computed(() => current.value?.description ?? null),
    breadcrumbs,
  }
}
