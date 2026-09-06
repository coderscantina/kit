import { describe, expect, it } from 'vitest'
import { nextTick, ref } from 'vue'

import { useReactiveForm } from '../src/form'

interface Card {
  id: string
  version: number
  name: string
  points: number
  notes: string | null
}

const card = (over: Partial<Card> = {}): Card => ({
  id: 'c1',
  version: 1,
  name: 'first',
  points: 3,
  notes: null,
  ...over,
})

const FIELDS = ['name', 'points', 'notes'] as const

describe('useReactiveForm', () => {
  it('takes the first row into every field', () => {
    const form = useReactiveForm(card(), { fields: FIELDS })

    expect(form.fields.name).toBe('first')
    expect(form.base.value?.version).toBe(1)
    expect(form.isDirty.value).toBe(false)
  })

  it('lands a push in the untouched fields and leaves the edited one alone', async () => {
    const source = ref(card())
    const form = useReactiveForm(source, { fields: FIELDS })

    form.fields.name = 'mine'
    expect(form.dirty.value).toEqual(['name'])

    source.value = card({ version: 2, points: 9, notes: 'theirs' })
    await nextTick()

    expect(form.fields.name).toBe('mine')
    expect(form.fields.points).toBe(9)
    expect(form.fields.notes).toBe('theirs')
    expect(form.base.value?.version).toBe(2)
    // Their write left name alone, so there is nothing to resolve.
    expect(form.conflicts.value).toEqual([])
  })

  it('reports a conflict when the server moves a field the user is editing', async () => {
    const source = ref(card())
    const form = useReactiveForm(source, { fields: FIELDS })

    form.fields.name = 'mine'
    source.value = card({ version: 2, name: 'theirs' })
    await nextTick()

    expect(form.fields.name).toBe('mine')
    expect(form.conflicts.value).toEqual([{ field: 'name', theirs: 'theirs' }])

    form.accept('name')

    expect(form.fields.name).toBe('theirs')
    expect(form.conflicts.value).toEqual([])
    expect(form.isDirty.value).toBe(false)
  })

  it('is no conflict when they wrote what the user typed', async () => {
    const source = ref(card())
    const form = useReactiveForm(source, { fields: FIELDS })

    form.fields.name = 'same'
    source.value = card({ version: 2, name: 'same' })
    await nextTick()

    expect(form.conflicts.value).toEqual([])
    expect(form.isDirty.value).toBe(false)
  })

  it('applies a 409 payload the same way and moves the version forward', () => {
    const form = useReactiveForm(card(), { fields: FIELDS })

    form.fields.name = 'mine'
    form.fields.points = 5

    form.apply(card({ version: 4, name: 'theirs', notes: 'added' }))

    expect(form.base.value?.version).toBe(4)
    expect(form.values()).toEqual({ name: 'mine', points: 5, notes: 'added' })
    expect(form.conflicts.value.map((c) => c.field)).toEqual(['name'])
  })

  it('replaces everything, edits included, when the row identity changes', async () => {
    const source = ref(card())
    const form = useReactiveForm(source, { fields: FIELDS })

    form.fields.name = 'mine'
    source.value = card({ id: 'c2', name: 'other', points: 1 })
    await nextTick()

    expect(form.fields.name).toBe('other')
    expect(form.isDirty.value).toBe(false)
  })

  it('reads an input string as equal to the number it came from, and blank as null', () => {
    const form = useReactiveForm(card({ notes: null }), { fields: FIELDS })

    form.fields.points = '3' as unknown as number
    form.fields.notes = ''

    expect(form.isDirty.value).toBe(false)
  })

  it('reset drops every edit', () => {
    const form = useReactiveForm(card(), { fields: FIELDS })

    form.fields.name = 'mine'
    form.fields.notes = 'draft'
    form.reset()

    expect(form.values()).toEqual({ name: 'first', points: 3, notes: null })
  })
})
