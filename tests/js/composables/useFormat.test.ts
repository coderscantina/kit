import { afterEach, describe, expect, it } from 'vitest'

import { useFormat } from '~/composables/useFormat'
import { setLocale } from '~/plugins/i18n'

describe('useFormat', () => {
  afterEach(() => setLocale('en'))

  it('formats dates and numbers in the active locale', () => {
    const { date, number } = useFormat()
    const value = '2026-03-14T10:30:00Z'

    expect(date(value)).toBe('Mar 14, 2026')
    expect(number(1234.5)).toBe('1,234.5')

    setLocale('de')

    expect(date(value)).toBe('14.03.2026')
    expect(number(1234.5)).toBe('1.234,5')
  })

  it('names the largest byte unit that keeps the number above one', () => {
    const { bytes } = useFormat()

    expect(bytes(512)).toBe('512 byte')
    expect(bytes(1_500_000)).toBe('1.5 MB')
    expect(bytes(null)).toBe('')
  })

  it('picks the coarsest relative unit that still fits', () => {
    const { relative } = useFormat()
    const now = new Date('2026-03-14T12:00:00Z')

    expect(relative('2026-03-13T12:00:00Z', now)).toBe('yesterday')
    expect(relative('2026-03-14T09:00:00Z', now)).toBe('3 hours ago')
    expect(relative('2026-03-21T12:00:00Z', now)).toBe('next week')
    expect(relative(null, now)).toBe('')
  })
})
