import type { App } from 'vue'
import { createI18n, type Composer } from 'vue-i18n'

import de from '~/i18n/de.json'
import en from '~/i18n/en.json'

export type MessageSchema = typeof en

/** Dot-separated paths to the string leaves of a message tree; nested objects are not keys. */
type Paths<T> = {
  [K in keyof T & string]: T[K] extends string ? K : `${K}.${Paths<T[K]>}`
}[keyof T & string]

/** Every key `en.json` actually defines, e.g. `'auth.login.title'`. */
export type MessageKey = Paths<MessageSchema>

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

/**
 * A literal key has to exist in the schema; a key the compiler only knows as
 * `string`, such as `mutations.${name}.error`, passes through unchecked.
 * A union of composed keys passes when any member exists, which is what
 * `te()` on an optional `mutations.<name>.success` key needs.
 */
type Checked<K extends string> = string extends K
  ? unknown
  : K extends MessageKey
    ? unknown
    : MessageKey

function t<K extends string>(key: K & Checked<K>, named?: Record<string, unknown>): string {
  return named ? composer.t(key, named) : composer.t(key)
}

function te<K extends string>(key: K & Checked<K>): boolean {
  return composer.te(key)
}

export function useI18n() {
  return {
    t,
    te,
    locale: composer.locale,
    locales,
    setLocale,
  }
}
