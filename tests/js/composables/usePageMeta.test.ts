import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'

import { useCurrentPageMeta, usePageMeta } from '~/composables/usePageMeta'

const page = (title: string) =>
  defineComponent({
    setup() {
      usePageMeta(() => ({ title }))
      return () => h('div')
    },
  })

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/', name: 'dashboard', component: { template: '<div />' } }],
})

const readMeta = async () => {
  await router.push('/')
  await router.isReady()

  let meta: ReturnType<typeof useCurrentPageMeta> | undefined

  const wrapper = mount(
    defineComponent({
      setup() {
        meta = useCurrentPageMeta()
        return () => h('div')
      },
    }),
    { global: { plugins: [router] } }
  )

  return { meta: meta!, unmount: () => wrapper.unmount() }
}

describe('usePageMeta', () => {
  it('publishes the page title and sets the document title', async () => {
    const reader = await readMeta()
    const wrapper = mount(page('Reports'))

    expect(reader.meta.title.value).toBe('Reports')
    expect(document.title).toBe('Reports · Kit')

    wrapper.unmount()
    reader.unmount()
  })

  it('keeps the incoming title when the leaving page unmounts late', async () => {
    const reader = await readMeta()

    const leaving = mount(page('Old'))
    const arriving = mount(page('New'))

    // Vue tears the old page down after the new one is set up, which is
    // exactly the order that would blank the header without the ownership check.
    leaving.unmount()

    expect(reader.meta.title.value).toBe('New')

    arriving.unmount()
    reader.unmount()
  })

  it('falls back to a single crumb when the page declares none', async () => {
    const reader = await readMeta()
    const wrapper = mount(page('Reports'))

    expect(reader.meta.breadcrumbs.value).toEqual([{ label: 'Reports' }])

    wrapper.unmount()
    reader.unmount()
  })

  it('follows a reactive title', async () => {
    const reader = await readMeta()
    const title = ref('Loading…')

    const wrapper = mount(
      defineComponent({
        setup() {
          usePageMeta(() => ({ title: title.value }))
          return () => h('div')
        },
      })
    )

    title.value = 'Post 12'
    await wrapper.vm.$nextTick()

    expect(reader.meta.title.value).toBe('Post 12')

    wrapper.unmount()
    reader.unmount()
  })
})
