import { ref } from 'vue'

/**
 * Open state of the single palette mounted in `app.vue`, shared so the header
 * trigger, the shortcut and any page-level "search everything" affordance all
 * drive the same dialog.
 */
const isOpen = ref(false)

export function useCommandPalette() {
  return {
    isOpen,
    open: (): void => {
      isOpen.value = true
    },
    close: (): void => {
      isOpen.value = false
    },
    toggle: (): void => {
      isOpen.value = !isOpen.value
    },
  }
}
