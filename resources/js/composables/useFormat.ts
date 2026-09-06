import dayjs from 'dayjs'

import { useI18n } from '~/plugins/i18n'

export type DateInput = string | number | Date | null | undefined

/**
 * Decimal, not binary: these are the unit names `Intl.NumberFormat` knows, and
 * a localized "1,5 MB" beats a hand-built "1.4 MiB".
 */
const BYTE_UNITS = ['byte', 'kilobyte', 'megabyte', 'gigabyte', 'terabyte', 'petabyte'] as const

const RELATIVE_UNITS = [
  ['year', 60 * 60 * 24 * 365],
  ['month', 60 * 60 * 24 * 30],
  ['week', 60 * 60 * 24 * 7],
  ['day', 60 * 60 * 24],
  ['hour', 60 * 60],
  ['minute', 60],
  ['second', 1],
] as const satisfies ReadonlyArray<readonly [Intl.RelativeTimeFormatUnit, number]>

/**
 * Constructing an Intl formatter is the expensive part, and a table renders
 * one per cell. Keyed by locale plus options, so a locale switch builds new
 * ones instead of reusing the old locale's.
 */
const formatterCache = new Map<string, Intl.DateTimeFormat | Intl.NumberFormat>()

function cached<T extends Intl.DateTimeFormat | Intl.NumberFormat>(key: string, build: () => T): T {
  const hit = formatterCache.get(key)
  if (hit) return hit as T

  const formatter = build()
  formatterCache.set(key, formatter)

  return formatter
}

const toDate = (value: DateInput): Date | null => {
  if (value === null || value === undefined || value === '') return null

  const parsed = dayjs(value)

  return parsed.isValid() ? parsed.toDate() : null
}

/**
 * Intl formatters bound to the active i18n locale. Every helper reads
 * `locale.value`, so a render that formats also re-runs when the locale
 * changes; nothing has to be invalidated by hand.
 */
export function useFormat() {
  const { locale } = useI18n()

  const dateTimeFormatter = (options: Intl.DateTimeFormatOptions) =>
    cached(
      `dt:${locale.value}:${JSON.stringify(options)}`,
      () => new Intl.DateTimeFormat(locale.value, options)
    )

  const numberFormatter = (options: Intl.NumberFormatOptions) =>
    cached(
      `n:${locale.value}:${JSON.stringify(options)}`,
      () => new Intl.NumberFormat(locale.value, options)
    )

  /** Day precision, e.g. `6 Sept 2026`. */
  const date = (value: DateInput, options: Intl.DateTimeFormatOptions = {}): string => {
    const parsed = toDate(value)
    if (!parsed) return ''

    return dateTimeFormatter({ dateStyle: 'medium', ...options }).format(parsed)
  }

  /** Day and clock time, e.g. `6 Sept 2026, 14:03`. */
  const dateTime = (value: DateInput, options: Intl.DateTimeFormatOptions = {}): string => {
    const parsed = toDate(value)
    if (!parsed) return ''

    return dateTimeFormatter({ dateStyle: 'medium', timeStyle: 'short', ...options }).format(parsed)
  }

  const time = (value: DateInput, options: Intl.DateTimeFormatOptions = {}): string => {
    const parsed = toDate(value)
    if (!parsed) return ''

    return dateTimeFormatter({ timeStyle: 'short', ...options }).format(parsed)
  }

  const number = (
    value: number | null | undefined,
    options: Intl.NumberFormatOptions = {}
  ): string => (value === null || value === undefined ? '' : numberFormatter(options).format(value))

  /** Size in the largest unit that keeps the number above one. */
  const bytes = (value: number | null | undefined, fractionDigits = 1): string => {
    if (value === null || value === undefined) return ''

    let size = Math.abs(value)
    let unitIndex = 0

    while (size >= 1000 && unitIndex < BYTE_UNITS.length - 1) {
      size /= 1000
      unitIndex += 1
    }

    return numberFormatter({
      style: 'unit',
      unit: BYTE_UNITS[unitIndex],
      unitDisplay: 'short',
      // Whole bytes never want a decimal place.
      maximumFractionDigits: unitIndex === 0 ? 0 : fractionDigits,
    }).format(Math.sign(value) * size)
  }

  /**
   * `3 days ago`, `in 2 hours`. `numeric: 'auto'` is what turns "1 day ago"
   * into "yesterday" where the language has a word for it.
   */
  const relative = (value: DateInput, from: DateInput = new Date()): string => {
    const parsed = toDate(value)
    const base = toDate(from)
    if (!parsed || !base) return ''

    const seconds = (parsed.getTime() - base.getTime()) / 1000
    const formatter = new Intl.RelativeTimeFormat(locale.value, { numeric: 'auto' })

    for (const [unit, unitSeconds] of RELATIVE_UNITS) {
      if (Math.abs(seconds) >= unitSeconds || unit === 'second') {
        return formatter.format(Math.round(seconds / unitSeconds), unit)
      }
    }

    return ''
  }

  return { date, dateTime, time, number, bytes, relative }
}
