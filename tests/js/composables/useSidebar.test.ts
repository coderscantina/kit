import { beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'

/**
 * `useSidebar` is a shared composable that reads storage once, at creation.
 * Resetting the module registry per test is what lets a stored value be seen
 * at all; reusing the instance would only ever test the first run.
 */
const load = async () => {
  vi.resetModules()
  const { useSidebar } = await import('~/composables/useSidebar')
  return useSidebar()
}

beforeEach(() => {
  window.localStorage.clear()
})

describe('useSidebar', () => {
  it('starts from the config default and remembers the collapse', async () => {
    const sidebar = await load()
    expect(sidebar.collapsed.value).toBe(false)

    sidebar.toggle()
    await nextTick()

    expect(window.localStorage.getItem('kit:sidebar-collapsed')).toBe('1')

    const reloaded = await load()
    expect(reloaded.collapsed.value).toBe(true)
  })

  it('clamps a width to the configured bounds', async () => {
    const sidebar = await load()

    sidebar.setWidth(10_000)
    expect(sidebar.width.value).toBe(sidebar.bounds.max)

    sidebar.setWidth(0)
    expect(sidebar.width.value).toBe(sidebar.bounds.min)
  })

  it('reports the rail width while collapsed, not the stored one', async () => {
    const sidebar = await load()
    sidebar.setWidth(300)

    expect(sidebar.currentWidth.value).toBe(300)

    sidebar.toggle()
    expect(sidebar.currentWidth.value).toBe(56)
    // The expanded width survives the collapse, so expanding restores it.
    expect(sidebar.width.value).toBe(300)
  })

  it('ignores a stored width outside the bounds', async () => {
    window.localStorage.setItem('kit:sidebar-width', '99999')

    const sidebar = await load()
    expect(sidebar.width.value).toBe(sidebar.bounds.max)
  })
})
