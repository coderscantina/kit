import type { App } from 'vue'
import { createI18n, type Composer } from 'vue-i18n'

import de from '~/i18n/de.json'
import en from '~/i18n/en.json'

export type MessageSchema = typeof en

export const locales = [
  { code: 'en', name: 'English' },
  { code: 'de', name: 'Deutsch' },
] as const

export type LocaleCode = (typeof locales)[number]['code']

const FALLBACK_LOCALE: LocaleCode = 'en'

export const isSupportedLocale = (code: string): code is LocaleCode =>
  locales.some((locale) => locale.code === code)

/** First supported language the browser asks for, region ignored. Only the initial locale; a user preference outranks it. */
export function detectBrowserLocale(): LocaleCode {
  if (typeof navigator === 'undefined') return FALLBACK_LOCALE

  const preferred = navigator.languages?.length ? navigator.languages : [navigator.language]

  for (const tag of preferred) {
    const code = tag?.toLowerCase().split('-')[0]
    if (code && isSupportedLocale(code)) return code
  }

  return FALLBACK_LOCALE
}

export const i18n = createI18n<[MessageSchema], LocaleCode>({
  legacy: false,
  locale: detectBrowserLocale(),
  fallbackLocale: FALLBACK_LOCALE,
  messages: { en, de },
})

/**
 * A standalone Composer that works without an app, so composables translate
 * for real in tests and tests can assert on real copy.
 */
export const composer = i18n.global as unknown as Composer<{ en: MessageSchema; de: MessageSchema }>

/** Blade renders `<html lang>` from the server; every locale change carries the document along. */
export function setLocale(locale: LocaleCode): void {
  composer.locale.value = locale

  if (typeof document !== 'undefined') {
    document.documentElement.setAttribute('lang', locale)
  }
}

export function installI18n(app: App): void {
  app.use(i18n)
  setLocale(composer.locale.value as LocaleCode)
}

export function useI18n() {
  const t = composer.t.bind(composer) as (key: string, named?: Record<string, unknown>) => string
  return {
    t,
    te: (key: string) => composer.te(key),
    locale: composer.locale,
    locales,
    setLocale,
  }
}
