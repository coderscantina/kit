import { toast } from 'vue-sonner'

import { runtimeConfig } from '~/lib/runtime-config'
import { useI18n } from '~/plugins/i18n'

let notified = false

/**
 * Compares the server's X-App-Version with the build this tab loaded and
 * offers a reload once. Call it from the API client's response path.
 */
export function useVersionCheck() {
  const { t } = useI18n()

  const check = (serverVersion: string | null): void => {
    if (
      notified ||
      !serverVersion ||
      !runtimeConfig.version ||
      serverVersion === runtimeConfig.version
    )
      return

    notified = true
    toast.info(t('app.newVersion'), {
      duration: Infinity,
      action: { label: t('app.reload'), onClick: () => window.location.reload() },
    })
  }

  return { check }
}
