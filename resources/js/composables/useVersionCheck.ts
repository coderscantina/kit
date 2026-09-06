import { toast } from 'vue-sonner'

import { runtimeConfig } from '~/lib/runtime-config'
import { useI18n } from '~/plugins/i18n'

let notified = false

/**
 * Compares the server's X-App-Version with the build this tab loaded and
 * offers a reload once. Call it from the API client's response path. The
 * service worker reports a waiting build through `offerReload` as well, so
 * the two never stack two toasts for one deploy.
 */
export function useVersionCheck() {
  const { t } = useI18n()

  const offerReload = (reload: () => void = () => window.location.reload()): void => {
    if (notified) return

    notified = true
    toast.info(t('app.newVersion'), {
      duration: Infinity,
      action: { label: t('app.reload'), onClick: reload },
    })
  }

  const check = (serverVersion: string | null): void => {
    if (!serverVersion || !runtimeConfig.version || serverVersion === runtimeConfig.version) return

    offerReload()
  }

  return { check, offerReload }
}
