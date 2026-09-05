import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { Button } from '~/components/ui/button'

beforeEach(() => vi.useFakeTimers())
afterEach(() => vi.useRealTimers())

describe('Button', () => {
  it('disables and marks busy at once but shows the spinner only after 250 ms', async () => {
    const wrapper = mount(Button, { props: { loading: false }, slots: { default: 'Save' } })

    await wrapper.setProps({ loading: true })

    expect(wrapper.attributes('disabled')).toBeDefined()
    expect(wrapper.attributes('aria-busy')).toBe('true')
    expect(wrapper.find('svg').exists()).toBe(false)

    vi.advanceTimersByTime(249)
    await wrapper.vm.$nextTick()
    expect(wrapper.find('svg').exists()).toBe(false)

    vi.advanceTimersByTime(1)
    await wrapper.vm.$nextTick()
    expect(wrapper.find('svg').exists()).toBe(true)

    await wrapper.setProps({ loading: false })
    expect(wrapper.find('svg').exists()).toBe(false)
    expect(wrapper.attributes('disabled')).toBeUndefined()
    wrapper.unmount()
  })

  it('merges conflicting utilities through cn', () => {
    const wrapper = mount(Button, { props: { class: 'px-8' }, slots: { default: 'x' } })

    expect(wrapper.classes()).toContain('px-8')
    expect(wrapper.classes()).not.toContain('px-4')
    wrapper.unmount()
  })
})
