import { createSharedComposable } from '@vueuse/core'
import { computed, ref, watch } from 'vue'

import { appConfig } from '~/lib/app-config'
import { isClient } from '~/lib/env'

const COLLAPSED_KEY = 'kit:sidebar-collapsed'
const WIDTH_KEY = 'kit:sidebar-width'

const clampWidth = (value: number): number => {
  const { min, max } = appConfig.sidebar.width
  return Math.min(max, Math.max(min, Math.round(value)))
}

const read = (key: string): string | null => {
  if (!isClient) return null
  try {
    return window.localStorage.getItem(key)
  } catch {
    // Private-mode Safari throws on access rather than returning null.
    return null
  }
}

const write = (key: string, value: string): void => {
  if (!isClient) return
  try {
    window.localStorage.setItem(key, value)
  } catch {
    /* storage is unavailable; the choice still applies for this session */
  }
}

const useSidebarBase = () => {
  const config = appConfig.sidebar

  const stored = read(COLLAPSED_KEY)
  const collapsed = ref(
    config.collapsible ? (stored === null ? config.defaultCollapsed : stored === '1') : false
  )

  const storedWidth = Number(read(WIDTH_KEY))
  const width = ref(
    Number.isFinite(storedWidth) && storedWidth > 0 ? clampWidth(storedWidth) : config.width.default
  )

  /** The mobile drawer is deliberately not persisted: it opens closed, always. */
  const mobileOpen = ref(false)

  watch(collapsed, (value) => write(COLLAPSED_KEY, value ? '1' : '0'))
  watch(width, (value) => write(WIDTH_KEY, String(value)))

  /** Rail width matches the header's icon button column, so the two line up. */
  const railWidth = 56

  const currentWidth = computed(() => (collapsed.value ? railWidth : width.value))

  return {
    collapsed,
    width,
    currentWidth,
    mobileOpen,
    collapsible: config.collapsible,
    resizable: config.resizable,
    bounds: config.width,
    toggle: (): void => {
      if (!config.collapsible) return
      collapsed.value = !collapsed.value
    },
    setWidth: (value: number): void => {
      width.value = clampWidth(value)
    },
    resetWidth: (): void => {
      width.value = config.width.default
    },
  }
}

/** One source of truth: the header toggle, the rail and `mod+b` stay in step. */
export const useSidebar = createSharedComposable(useSidebarBase)
