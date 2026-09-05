import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'

export interface HarnessOptions {
  /** Query cache entries to seed, as [queryKey, data] pairs. */
  seed?: Array<[readonly unknown[], unknown]>
}

export interface Harness<T> {
  result: T
  queryClient: QueryClient
  unmount: () => void
}

/**
 * Run a composable inside a real component with VueQueryPlugin installed.
 *
 * Composables that call useQuery/useQueryClient and register lifecycle hooks
 * cannot be invoked bare. Seeding the cache instead of stubbing the
 * composable keeps the query keys under test: a key that drifts stops
 * resolving and the test fails.
 *
 * Declare the harness type explicitly: `Harness<ReturnType<typeof useThing>>`,
 * never `ReturnType<typeof setup>`, which is circular and widens to `any`.
 */
export function withSetup<T>(composable: () => T, options: HarnessOptions = {}): Harness<T> {
  const queryClient = new QueryClient({
    defaultOptions: {
      // Never hit the network: an unseeded key fails loudly and fast.
      queries: { retry: false, gcTime: Infinity, staleTime: Infinity },
      mutations: { retry: false },
    },
  })

  for (const [key, data] of options.seed ?? []) {
    queryClient.setQueryData(key, data)
  }

  let result: T | undefined

  const wrapper = mount(
    defineComponent({
      setup() {
        result = composable()
        return () => h('div')
      },
    }),
    { global: { plugins: [[VueQueryPlugin, { queryClient }]] } }
  )

  return {
    result: result as T,
    queryClient,
    unmount: () => wrapper.unmount(),
  }
}
