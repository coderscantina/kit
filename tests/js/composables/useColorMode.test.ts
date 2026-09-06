import { describe, expect, it } from 'vitest'
import { nextTick } from 'vue'

import { COLOR_MODE_STORAGE_KEY, useColorMode } from '~/composables/useColorMode'

describe('useColorMode', () => {
  it('drives the html class and colour-scheme from the selected mode', async () => {
    const { setMode, isDark } = useColorMode()
    const root = document.documentElement

    setMode('dark')
    await nextTick()

    expect(isDark.value).toBe(true)
    expect(root.classList.contains('dark')).toBe(true)
    expect(root.style.colorScheme).toBe('dark')

    setMode('light')
    await nextTick()

    expect(isDark.value).toBe(false)
    expect(root.classList.contains('dark')).toBe(false)
    expect(root.style.colorScheme).toBe('light')
  })

  it('persists the choice under the key the pre-paint script reads', () => {
    const { setMode } = useColorMode()

    setMode('dark')

    expect(window.localStorage.getItem(COLOR_MODE_STORAGE_KEY)).toBe('dark')
    expect(COLOR_MODE_STORAGE_KEY).toBe('kit:color-mode')
  })
})
