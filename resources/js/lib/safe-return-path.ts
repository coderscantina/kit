/**
 * A `?return=` target the login page may send the user to. Only same-origin
 * paths: anything with a scheme, a protocol-relative prefix, or a backslash
 * trick falls back to the root.
 */
export const safeReturnPath = (value: unknown, fallback = '/'): string => {
  if (typeof value !== 'string' || value === '') return fallback
  if (!value.startsWith('/') || value.startsWith('//') || value.startsWith('/\\')) return fallback
  return value
}
