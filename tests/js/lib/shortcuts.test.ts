import { afterEach, describe, expect, it, vi } from 'vitest'

import { formatKeys, isEditableTarget, parseKeys, registerShortcut } from '~/lib/shortcuts'

const press = (init: KeyboardEventInit) => {
  window.dispatchEvent(new KeyboardEvent('keydown', { bubbles: true, ...init }))
}

const disposers: Array<() => void> = []

afterEach(() => {
  disposers.splice(0).forEach((dispose) => dispose())
})

const bind = (...args: Parameters<typeof registerShortcut>) => {
  const dispose = registerShortcut(...args)
  disposers.push(dispose)
  return dispose
}

describe('parseKeys', () => {
  it('reads modifiers and aliases off the binding string', () => {
    expect(parseKeys('mod+b')).toEqual({ key: 'b', mod: true, alt: false, shift: false })
    expect(parseKeys('shift+mod+z')).toEqual({ key: 'z', mod: true, alt: false, shift: true })
    expect(parseKeys('esc')).toEqual({ key: 'escape', mod: false, alt: false, shift: false })
  })

  it('does not care about shift for a symbol key, which carries it implicitly', () => {
    expect(parseKeys('?').shift).toBeNull()
  })
})

describe('registerShortcut', () => {
  it('runs the handler and unregisters on dispose', () => {
    const handler = vi.fn()
    const dispose = bind({ keys: 'mod+b', description: () => 'toggle', handler })

    press({ key: 'b', ctrlKey: true, metaKey: true })
    expect(handler).toHaveBeenCalledTimes(1)

    dispose()
    press({ key: 'b', ctrlKey: true, metaKey: true })
    expect(handler).toHaveBeenCalledTimes(1)
  })

  it('holds off while `enabled` says no', () => {
    const handler = vi.fn()
    let enabled = false
    bind({ keys: 'mod+j', description: () => 'nope', enabled: () => enabled, handler })

    press({ key: 'j', ctrlKey: true, metaKey: true })
    expect(handler).not.toHaveBeenCalled()

    enabled = true
    press({ key: 'j', ctrlKey: true, metaKey: true })
    expect(handler).toHaveBeenCalledTimes(1)
  })

  it('lets a scoped binding shadow the global one on the same key', () => {
    const global = vi.fn()
    const scoped = vi.fn()

    bind({ keys: 'mod+e', description: () => 'global', handler: global })
    bind({ keys: 'mod+e', scope: 'dialog', description: () => 'dialog', handler: scoped })

    press({ key: 'e', ctrlKey: true, metaKey: true })

    expect(scoped).toHaveBeenCalledTimes(1)
    expect(global).not.toHaveBeenCalled()
  })
})

describe('isEditableTarget', () => {
  it('is true inside a text field, so a bare letter key does not fire', () => {
    const input = document.createElement('input')
    document.body.append(input)

    expect(isEditableTarget(input)).toBe(true)
    expect(isEditableTarget(document.body)).toBe(false)

    input.remove()
  })
})

describe('formatKeys', () => {
  it('renders display tokens for the platform', () => {
    expect(formatKeys('mod+b').at(-1)).toBe('B')
    expect(formatKeys('arrowup')).toEqual(['↑'])
  })
})
