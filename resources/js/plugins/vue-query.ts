import { QueryClient, VueQueryPlugin, type VueQueryPluginOptions } from '@tanstack/vue-query'
import type { App } from 'vue'

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      refetchOnWindowFocus: true,
      // Retrying an auth or permission failure only delays the redirect to login.
      retry: (failureCount: number, error: unknown) => {
        const status = (error as { status?: number })?.status
        if (status && [401, 403, 404, 419, 422].includes(status)) return false
        return failureCount < 1
      },
      staleTime: 1000 * 60 * 5,
      gcTime: 1000 * 60 * 30,
    },
  },
})

const options: VueQueryPluginOptions = { queryClient }

export function installVueQuery(app: App): void {
  app.use(VueQueryPlugin, options)
}
