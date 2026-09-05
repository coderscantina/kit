const XSRF_COOKIE_NAME = 'XSRF-TOKEN'

/** `[name, value]` per cookie; the name is everything before the first `=`. */
const parseCookies = (): Array<[string, string]> =>
  (document.cookie ? document.cookie.split('; ') : []).map((cookie) => {
    const separator = cookie.indexOf('=')
    return separator === -1
      ? [cookie, '']
      : [cookie.slice(0, separator), cookie.slice(separator + 1)]
  })

export const getXsrfToken = (): string | null => {
  if (typeof document === 'undefined') return null

  const entry = parseCookies().find(([name]) => name === XSRF_COOKIE_NAME)
  if (!entry?.[1]) return null

  try {
    return decodeURIComponent(entry[1])
  } catch {
    return entry[1]
  }
}

export const getXsrfHeaders = (): Record<string, string> => {
  const token = getXsrfToken()
  return token ? { 'X-XSRF-TOKEN': token } : {}
}

export const hasXsrfToken = (): boolean => getXsrfToken() !== null
