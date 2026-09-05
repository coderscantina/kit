import { describe, expect, it } from 'vitest'

import de from '~/i18n/de.json'
import en from '~/i18n/en.json'

const flatten = (tree: Record<string, unknown>, prefix = ''): string[] =>
  Object.entries(tree).flatMap(([key, value]) =>
    value !== null && typeof value === 'object'
      ? flatten(value as Record<string, unknown>, `${prefix}${key}.`)
      : [`${prefix}${key}`]
  )

describe('i18n messages', () => {
  it('de has every key en has, and nothing extra', () => {
    const enKeys = flatten(en).sort()
    const deKeys = flatten(de).sort()

    expect(deKeys.filter((key) => !enKeys.includes(key))).toEqual([])
    expect(enKeys.filter((key) => !deKeys.includes(key))).toEqual([])
  })

  it('no message is empty', () => {
    for (const messages of [en, de]) {
      const empty = flatten(messages).filter((key) => {
        const value = key
          .split('.')
          .reduce<unknown>((node, part) => (node as Record<string, unknown>)[part], messages)
        return value === ''
      })
      expect(empty).toEqual([])
    }
  })
})
