import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import { nextTick } from 'vue'

import TableFilter from '~/components/ui/data-table/TableFilter.vue'
import type { FilterField } from '~/components/ui/data-table/types'

const fields: FilterField[] = [
  {
    id: 'role',
    label: 'Role',
    type: 'select',
    options: [
      { value: 'admin', label: 'Admin' },
      { value: 'member', label: 'Member' },
    ],
  },
  { id: 'email', label: 'Email', type: 'text' },
]

const filterBar = (filters: Record<string, string> = {}) =>
  mount(TableFilter, { props: { fields, modelValue: filters, search: '' } })

const optionLabels = (wrapper: ReturnType<typeof filterBar>) =>
  wrapper.findAll('[role="option"]').map((option) => option.text())

/** The nth option in the open listbox. Throws rather than silently no-op-ing. */
const pickOption = (wrapper: ReturnType<typeof filterBar>, index: number) => {
  const option = wrapper.findAll('[role="option"]')[index]
  if (!option) throw new Error(`No option at index ${index}`)

  return option.trigger('click')
}

describe('TableFilter', () => {
  it('builds a chip field, operator, value and writes it as a query parameter', async () => {
    const wrapper = filterBar()
    const input = wrapper.get('input[role="combobox"]')

    await input.trigger('focus')
    expect(optionLabels(wrapper)).toEqual(['Role', 'Email'])

    await pickOption(wrapper, 0)
    // A select field asks how to compare before it asks what to compare to.
    expect(optionLabels(wrapper)).toContain('is')

    await pickOption(wrapper, 0)
    expect(optionLabels(wrapper)).toEqual(['Admin', 'Member'])

    await pickOption(wrapper, 0)

    // `eq` is written bare, so the URL stays readable.
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([{ role: 'admin' }])
  })

  it('carries the operator when it is not a plain equals', async () => {
    const wrapper = filterBar()

    await wrapper.get('input[role="combobox"]').trigger('focus')
    await pickOption(wrapper, 1)

    // A text field leads with "contains": a typed fragment is what it means.
    expect(optionLabels(wrapper)[0]).toBe('contains')
    await pickOption(wrapper, 0)

    await wrapper.get('input[role="combobox"]').setValue('example.test')
    await wrapper.get('input[role="combobox"]').trigger('keydown', { key: 'Enter' })

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([{ email: 'like:example.test' }])
  })

  it('shows what the URL already carries and removes a chip on request', async () => {
    const wrapper = filterBar({ role: 'admin', email: 'like:example' })
    await nextTick()

    const chips = wrapper.findAll('[role="button"]')
    expect(chips.map((chip) => chip.text())).toEqual(['Roleis Admin', 'Emailcontains example'])

    await wrapper.get('button[aria-label="Remove the Role filter"]').trigger('click')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([{ email: 'like:example' }])
  })

  it('treats Enter at rest as the free-text search', async () => {
    const wrapper = filterBar()
    const input = wrapper.get('input[role="combobox"]')

    await input.setValue('ada')
    await input.trigger('keydown', { key: 'Enter' })

    expect(wrapper.emitted('update:search')?.at(-1)).toEqual(['ada'])
  })
})

/** Report every media query as matching, which is what a phone looks like here. */
const pretendPhone = () => {
  const original = window.matchMedia

  window.matchMedia = ((query: string) =>
    ({
      matches: true,
      media: query,
      addEventListener: () => {},
      removeEventListener: () => {},
    }) as unknown as MediaQueryList) as typeof window.matchMedia

  return () => {
    window.matchMedia = original
  }
}

describe('TableFilter on a phone', () => {
  let restore = () => {}

  afterEach(() => restore())

  it('collapses the chips to a count so the input keeps its width', async () => {
    restore = pretendPhone()

    const wrapper = filterBar({ role: 'admin', email: 'like:example' })
    await nextTick()

    // No chip in the bar itself; one button standing for both.
    expect(wrapper.findAll('[role="button"]')).toHaveLength(0)

    const count = wrapper.get('button[aria-label="2 filters applied. Open to edit them."]')
    expect(count.text()).toBe('2')

    // The one control that has to survive a narrow screen is still there.
    expect(wrapper.find('input[role="combobox"]').exists()).toBe(true)
  })
})
