import { describe, expect, it } from 'vitest'

import { safeReturnPath } from '~/lib/safe-return-path'

describe('safeReturnPath', () => {
  it('keeps same-origin paths and rejects everything that could leave the site', () => {
    expect(safeReturnPath('/users?page=2')).toBe('/users?page=2')
    expect(safeReturnPath('https://evil.test')).toBe('/')
    expect(safeReturnPath('//evil.test')).toBe('/')
    expect(safeReturnPath('/\\evil.test')).toBe('/')
    expect(safeReturnPath(undefined, '/x')).toBe('/x')
    expect(safeReturnPath('')).toBe('/')
  })
})
