import { createSharedComposable } from '@vueuse/core'
import { computed, ref, watchEffect } from 'vue'

import { isClient } from '~/lib/env'

export const colorModes = ['light', 'dark', 'system'] as const

export type ColorMode = (typeof colorModes)[number]

/**
 * Shared with the inline script in `resources/views/app.blade.php`, which
 * applies the class before first paint. Change one and change the other, or
 * every load flashes the light theme.
 */
export const COLOR_MODE_STORAGE_KEY = 'kit:color-mode'

const isColorMode = (value: unknown): value is ColorMode => colorModes.includes(value as ColorMode)

const readStoredMode = (): ColorMode => {
  if (!isClient) return 'system'

  try {
    const stored = window.localStorage.getItem(COLOR_MODE_STORAGE_KEY)
    return isColorMode(stored) ? stored : 'system'
  } catch {
    // Private-mode Safari throws on access rather than returning null.
    return 'system'
  }
}

const useColorModeBase = () => {
  const mode = ref<ColorMode>(readStoredMode())
  const prefersDark = ref(
    isClient ? window.matchMedia('(prefers-color-scheme: dark)').matches : false
  )

  if (isClient) {
    const media = window.matchMedia('(prefers-color-scheme: dark)')
    media.addEventListener('change', (event) => {
      prefersDark.value = event.matches
    })
  }

  const resolved = computed<Exclude<ColorMode, 'system'>>(() =>
    mode.value === 'system' ? (prefersDark.value ? 'dark' : 'light') : mode.value
  )

  const isDark = computed(() => resolved.value === 'dark')

  if (isClient) {
    watchEffect(() => {
      const root = document.documentElement
      root.classList.toggle('dark', isDark.value)
      // Native widgets — scrollbars, date pickers, form controls — read this,
      // not the class, and stay light without it.
      root.style.colorScheme = resolved.value
    })
  }

  const setMode = (next: ColorMode): void => {
    mode.value = next

    if (!isClient) return

    try {
      window.localStorage.setItem(COLOR_MODE_STORAGE_KEY, next)
    } catch {
      /* storage is unavailable; the mode still applies for this session */
    }
  }

  return { mode, resolved, isDark, setMode, colorModes }
}

/** One source of truth: several toggles on screen stay in step. */
export const useColorMode = createSharedComposable(useColorModeBase)
