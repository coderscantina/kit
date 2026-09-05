import { isClient } from '~/lib/env'

/**
 * The server-injected `window.__APP_CONFIG__`, typed from the PHP payload
 * class by types:generate. Read once at module load; a missing payload (unit
 * tests without setup, a broken shell) degrades to realtime off.
 */
const appConfig: App.Support.RuntimeConfigPayload | undefined = isClient
  ? window.__APP_CONFIG__
  : undefined

export const runtimeConfig = {
  version: appConfig?.version ?? '',
  apiBaseUrl: appConfig?.apiBaseUrl ?? '',
  locale: appConfig?.locale ?? 'en',
  features: {
    realtime: appConfig?.features.realtime ?? false,
    registration: appConfig?.features.registration ?? false,
    impersonation: appConfig?.features.impersonation ?? false,
    ai: appConfig?.features.ai ?? false,
  },
  // An echo block without a key cannot connect; null keeps the client from
  // retry-looping against a socket that is not there.
  echo: appConfig?.echo?.key ? appConfig.echo : null,
  analytics: appConfig?.analytics ?? null,
}
