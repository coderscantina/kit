import { enableAutoUnmount, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import { nextTick } from 'vue'

import TableSegments from '~/components/ui/data-table/TableSegments.vue'

const segments = [
  { value: 'all', label: 'All', count: 12 },
  { value: 'active', label: 'Members', count: 9 },
  { value: 'pending', label: 'Invited', count: 3 },
] as const

const picker = (current: 'all' | 'active' | 'pending' = 'all') =>
  mount(TableSegments, {
    attachTo: document.body,
    props: { segments, modelValue: current },
  })

// The menu is portalled onto the document, so a wrapper left mounted would
// leave its items behind for the next test to find.
enableAutoUnmount(afterEach)

const tabs = () => [...document.querySelectorAll<HTMLElement>('[role="tab"]')]

const text = (element: HTMLElement) => element.textContent?.replace(/\s+/g, ' ').trim()
const items = () => [...document.querySelectorAll<HTMLElement>('[role="menuitemradio"]')]

type Picker = ReturnType<typeof picker>

/** The compact form's trigger, told apart from the tabs by what it opens. */
const trigger = (wrapper: Picker) => wrapper.get('button[aria-haspopup="menu"]')

describe('TableSegments', () => {
  it('is a row of tabs on a wide screen and a menu button on a phone', () => {
    const wrapper = picker()

    expect(wrapper.get('[role="tablist"]').classes()).not.toContain('sm:hidden')
    // Each form is display:none on the other's screen, which takes it out of
    // the accessibility tree and out of the tab order.
    expect(wrapper.get('[role="tablist"]').element.parentElement?.className).toContain(
      'max-sm:hidden'
    )
    expect(trigger(wrapper).classes()).toContain('sm:hidden')
  })

  it('carries every segment and its count into the tabs, and selects the current one', () => {
    picker('active')

    expect(tabs().map(text)).toEqual(['All 12', 'Members 9', 'Invited 3'])
    expect(tabs().map((tab) => tab.getAttribute('aria-selected'))).toEqual([
      'false',
      'true',
      'false',
    ])
  })

  it('writes the picked tab, which is what reaches the URL', async () => {
    const wrapper = picker('all')

    // Reka selects a tab on pointer down, not on the click that follows.
    tabs()[2]?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }))
    await nextTick()

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['pending'])
  })

  it('says which slice is showing and how many are in it', () => {
    const wrapper = picker('active')

    expect(trigger(wrapper).text()).toContain('Members')
    expect(trigger(wrapper).text()).toContain('9')
  })

  it('carries every segment and its count into the menu, and ticks the current one', async () => {
    const wrapper = picker('pending')

    await trigger(wrapper).trigger('click')
    await nextTick()

    expect(items().map((item) => item.textContent?.trim())).toEqual([
      'All12',
      'Members9',
      'Invited3',
    ])
    // Shape, not colour: the checked row is announced as checked.
    expect(items().map((item) => item.getAttribute('aria-checked'))).toEqual([
      'false',
      'false',
      'true',
    ])
  })

  it('writes the picked segment, which is what reaches the URL', async () => {
    const wrapper = picker('all')

    await trigger(wrapper).trigger('click')
    await nextTick()

    items()[2]?.click()
    await nextTick()

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual(['pending'])
  })
})
