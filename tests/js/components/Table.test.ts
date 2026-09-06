import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { h } from 'vue'

import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
  TableSortableHead,
} from '~/components/ui/table'

const rows = (props: Record<string, unknown> = {}) =>
  mount(Table, {
    props: { label: 'People', ...props },
    slots: {
      default: () => [
        h(TableHeader, () => h(TableRow, () => h(TableHead, () => 'Role'))),
        h(TableBody, () => h(TableRow, () => h(TableCell, { label: 'Role' }, () => 'Admin'))),
      ],
    },
  })

describe('Table primitives', () => {
  it('keeps the table semantics that the stacked phone layout would strip', () => {
    const wrapper = rows()
    const table = wrapper.get('table')

    // `display: block` at max-sm drops the native roles, so they are stated.
    expect(table.attributes('role')).toBe('table')
    expect(table.attributes('aria-label')).toBe('People')
    expect(wrapper.get('thead').attributes('role')).toBe('rowgroup')
    expect(wrapper.get('tr').attributes('role')).toBe('row')
    expect(wrapper.get('th').attributes('role')).toBe('columnheader')
    expect(wrapper.get('th').attributes('scope')).toBe('col')
    expect(wrapper.get('td').attributes('role')).toBe('cell')
  })

  it('stacks a page table on a phone and leaves a boxed one alone', () => {
    expect(rows().get('table').classes()).toContain('table-stacked')
    expect(rows({ variant: 'boxed' }).get('table').classes()).not.toContain('table-stacked')
  })

  it('draws its own edge and no bleed, so it lines up with the toolbar and the pager', () => {
    // The border is the table's, not a card's: nothing wraps a table.
    expect(rows().classes()).toEqual(
      expect.arrayContaining(['border', 'border-border', 'rounded-lg'])
    )
    // No horizontal bleed: the box lines up with the toolbar and the pager.
    expect(rows().classes().join(' ')).not.toMatch(/-mx-/)
    expect(rows({ variant: 'boxed' }).classes().join(' ')).not.toMatch(/-mx-/)
  })

  it('writes the column name to the cell, which is what the stacked row sorts by', () => {
    expect(rows().get('td').attributes('data-label')).toBe('Role')
  })

  it('repeats the column name in the cell, for the phone where the header is off screen', () => {
    const label = rows().get('td span')

    expect(label.text()).toBe('Role')
    // Announced but not drawn on a phone, so the record stays one line of
    // values wide; gone entirely on a wide screen, where the header is there.
    expect(label.classes()).toContain('sr-only')
    expect(label.classes()).toContain('sm:hidden')
  })

  it('announces the sorted column and flips it', async () => {
    const wrapper = mount(TableSortableHead, {
      props: { column: 'name', modelValue: { column: 'name', direction: 'asc' as const } },
      slots: { default: () => 'Name' },
    })

    expect(wrapper.get('th').attributes('aria-sort')).toBe('ascending')

    await wrapper.get('button').trigger('click')

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([
      { column: 'name', direction: 'desc' },
    ])
  })

  it('says nothing about a column it does not sort by', () => {
    const wrapper = mount(TableSortableHead, {
      props: { column: 'email', modelValue: { column: 'name', direction: 'asc' as const } },
      slots: { default: () => 'Email' },
    })

    expect(wrapper.get('th').attributes('aria-sort')).toBe('none')
  })
})
