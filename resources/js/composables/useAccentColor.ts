import { createSharedComposable } from '@vueuse/core'
import { ref, watchEffect } from 'vue'

import { appConfig } from '~/lib/app-config'
import { isClient } from '~/lib/env'

export const accentColors = ['blue', 'violet', 'emerald', 'amber', 'rose', 'cyan'] as const

export type AccentColor = (typeof accentColors)[number]

/**
 * Shared with the inline script in `resources/views/app.blade.php`, which
 * applies the attribute before first paint. Change one and change the other,
 * or every load flashes the default accent.
 */
export const ACCENT_STORAGE_KEY = 'kit:accent'

/**
 * The swatch each option shows. Written out rather than interpolated: Tailwind
 * only ships a colour it can find as a literal string in the source.
 */
export const accentSwatch: Record<AccentColor, string> = {
  blue: 'bg-blue-500',
  violet: 'bg-violet-500',
  emerald: 'bg-emerald-500',
  amber: 'bg-amber-500',
  rose: 'bg-rose-500',
  cyan: 'bg-cyan-500',
}

const isAccentColor = (value: unknown): value is AccentColor =>
  accentColors.includes(value as AccentColor)

const readStoredAccent = (): AccentColor => {
  const fallback = appConfig.defaultAccent

  if (!isClient) return fallback

  try {
    const stored = window.localStorage.getItem(ACCENT_STORAGE_KEY)
    return isAccentColor(stored) ? stored : fallback
  } catch {
    // Private-mode Safari throws on access rather than returning null.
    return fallback
  }
}

const useAccentColorBase = () => {
  const accent = ref<AccentColor>(readStoredAccent())

  if (isClient) {
    watchEffect(() => {
      // `blue` is the stylesheet's own default and has no block; leaving the
      // attribute off keeps the default the cheapest path.
      if (accent.value === 'blue') document.documentElement.removeAttribute('data-accent')
      else document.documentElement.setAttribute('data-accent', accent.value)
    })
  }

  const setAccent = (next: AccentColor): void => {
    accent.value = next

    if (!isClient) return

    try {
      window.localStorage.setItem(ACCENT_STORAGE_KEY, next)
    } catch {
      /* storage is unavailable; the accent still applies for this session */
    }
  }

  return { accent, setAccent, accentColors, accentSwatch }
}

/** One source of truth: the picker and anything else reading it stay in step. */
export const useAccentColor = createSharedComposable(useAccentColorBase)
