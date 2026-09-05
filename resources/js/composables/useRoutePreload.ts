import { useRouter, type RouteLocationRaw } from 'vue-router'

import { useHoverPrefetch } from '~/composables/useHoverPrefetch'

/**
 * Preloads a route's lazy chunks on hover or focus, so the JS is in the
 * module cache before the click.
 */
export function useRoutePreload(delay = 150) {
  const router = useRouter()

  const loadRouteChunks = (fullPath: string) => {
    const loaders: Promise<unknown>[] = []

    for (const record of router.resolve(fullPath).matched) {
      for (const component of Object.values(record.components ?? {})) {
        // Only lazy route components are functions; eager ones are loaded.
        if (typeof component === 'function') {
          loaders.push((component as () => Promise<unknown>)())
        }
      }
    }

    return Promise.all(loaders)
  }

  const { start, cancel } = useHoverPrefetch(loadRouteChunks, delay)
  const toKey = (to: RouteLocationRaw) => router.resolve(to).fullPath

  return {
    preloadRoute: (to: RouteLocationRaw) => start(toKey(to)),
    cancelPreload: (to?: RouteLocationRaw) => cancel(to === undefined ? undefined : toKey(to)),
  }
}
