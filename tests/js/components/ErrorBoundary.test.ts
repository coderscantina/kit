import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { defineComponent, h, nextTick, ref } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'

import ErrorBoundary from '~/components/ErrorBoundary.vue'
import { composer } from '~/plugins/i18n'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [{ path: '/', component: { template: '<div />' } }],
})

const failing = ref(true)

const Exploding = defineComponent({
  setup() {
    return () => {
      if (failing.value) throw new Error('render exploded')
      return h('p', 'recovered')
    }
  },
})

describe('ErrorBoundary', () => {
  it('renders a recoverable fallback instead of blanking the page', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {})
    failing.value = true
    await router.push('/')
    await router.isReady()

    const wrapper = mount(ErrorBoundary, {
      slots: { default: () => h(Exploding) },
      global: { plugins: [router] },
    })

    // The boundary catches during the child's render pass, so the fallback
    // only exists after the parent re-renders.
    await nextTick()

    expect(wrapper.text()).toContain(composer.t('errorBoundary.title'))

    failing.value = false
    await wrapper.find('button').trigger('click')

    expect(wrapper.text()).toContain('recovered')
  })
})
