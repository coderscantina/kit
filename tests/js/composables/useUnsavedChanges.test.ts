import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, nextTick, ref } from 'vue'
import { createMemoryHistory, createRouter, RouterView } from 'vue-router'

import { useConfirm } from '~/composables/useConfirm'
import { useUnsavedChanges } from '~/composables/useUnsavedChanges'

const dirty = ref(false)

const Form = defineComponent(() => {
  useUnsavedChanges(dirty)
  return () => h('form')
})

const setup = async () => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/form', component: Form },
      { path: '/elsewhere', component: defineComponent(() => () => h('p')) },
    ],
  })
  await router.push('/form')
  const wrapper = mount(RouterView, { global: { plugins: [router] } })
  await router.isReady()

  return { router, wrapper }
}

const settle = async () => {
  await nextTick()
  await vi.runAllTimersAsync()
}

describe('useUnsavedChanges', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    dirty.value = false
  })
  afterEach(() => vi.useRealTimers())

  it('lets a clean form go without asking', async () => {
    const { router, wrapper } = await setup()

    await router.push('/elsewhere')

    expect(router.currentRoute.value.path).toBe('/elsewhere')
    expect(useConfirm().current.value).toBeNull()
    wrapper.unmount()
  })

  it('keeps a dirty form on screen unless the user discards', async () => {
    const { router, wrapper } = await setup()
    const { current, answer } = useConfirm()
    dirty.value = true

    const stay = router.push('/elsewhere')
    await nextTick()
    expect(current.value?.options.title).toBe('Discard changes?')
    answer(false)
    await stay
    expect(router.currentRoute.value.path).toBe('/form')
    await settle()

    const leave = router.push('/elsewhere')
    await nextTick()
    answer(true)
    await leave
    expect(router.currentRoute.value.path).toBe('/elsewhere')
    await settle()
    wrapper.unmount()
  })

  it('asks the browser before a dirty tab closes', async () => {
    const { wrapper } = await setup()
    dirty.value = true

    const event = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(event)

    expect(event.defaultPrevented).toBe(true)
    wrapper.unmount()
  })
})
