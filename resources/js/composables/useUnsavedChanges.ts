import { useEventListener } from '@vueuse/core'
import { toValue, type MaybeRefOrGetter } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'

import { useConfirm } from '~/composables/useConfirm'
import { useI18n } from '~/plugins/i18n'

/**
 * Asks before edits are thrown away: on a route change through the app's
 * own dialog, on a tab close or reload through the browser's (which shows
 * its own wording; no page may choose it). `confirmDiscard()` is the same
 * question for a close the component owns, a dialog's cancel button.
 *
 * Call it from a component under a routed page; `dirty` is read at the
 * moment of leaving, so a getter over form state is enough.
 */
export function useUnsavedChanges(dirty: MaybeRefOrGetter<boolean>) {
  const { t } = useI18n()
  const { confirm } = useConfirm()

  const confirmDiscard = async (): Promise<boolean> =>
    !toValue(dirty) ||
    confirm({
      title: t('unsaved.title'),
      message: t('unsaved.message'),
      confirmLabel: t('unsaved.discard'),
      cancelLabel: t('unsaved.keepEditing'),
      variant: 'destructive',
    })

  onBeforeRouteLeave(() => confirmDiscard())

  useEventListener(window, 'beforeunload', (event: BeforeUnloadEvent) => {
    if (toValue(dirty)) event.preventDefault()
  })

  return { confirmDiscard }
}
