import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'
import { createMemoryHistory, createRouter, type Router } from 'vue-router'

import { useTableQueryState, type TableQueryState } from '~/composables/useTableQueryState'

const mountState = async (initial = '/users') => {
  const router: Router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/users', name: 'users', component: { template: '<div />' } }],
  })

  await router.push(initial)
  await router.isReady()

  let state: TableQueryState | undefined

  mount(
    defineComponent({
      setup() {
        state = useTableQueryState({ defaultSort: { column: 'name', direction: 'asc' } })
        return () => h('div')
      },
    }),
    { global: { plugins: [router] } }
  )

  return { state: state as TableQueryState, router }
}

describe('useTableQueryState', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  it('reads its state out of the URL', async () => {
    const { state } = await mountState(
      '/users?page=3&perPage=50&sort=email&direction=desc&search=ada'
    )

    expect(state.params.value).toEqual({
      page: 3,
      perPage: 50,
      sort: { column: 'email', direction: 'desc' },
      search: 'ada',
    })
  })

  it('writes changes to the URL and leaves defaults out of it', async () => {
    const { state, router } = await mountState()

    state.page.value = 2
    await flushPromises()
    expect(router.currentRoute.value.query.page).toBe('2')

    state.sort.value = { column: 'email', direction: 'desc' }
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({ sort: 'email', direction: 'desc' })

    // Back to the default sort, and the params disappear rather than pinning
    // the default into every shared link.
    state.sort.value = { column: 'name', direction: 'asc' }
    await flushPromises()
    expect(router.currentRoute.value.query).toEqual({})
  })

  it('debounces search into the URL and restores the input on a back navigation', async () => {
    const { state, router } = await mountState('/users?page=4')

    state.search.value = 'ada'
    expect(router.currentRoute.value.query.search).toBeUndefined()

    await vi.runAllTimersAsync()
    // Searching starts over on page one; the old page number would show an
    // empty result set for a narrower list.
    expect(router.currentRoute.value.query).toEqual({ search: 'ada' })

    await router.back()
    await flushPromises()

    expect(state.search.value).toBe('')
    expect(state.page.value).toBe(4)

    // The input following the URL must not write it back and drop the page.
    await vi.runAllTimersAsync()
    expect(router.currentRoute.value.query.page).toBe('4')
  })
})
