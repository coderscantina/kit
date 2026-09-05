import { toast } from 'vue-sonner'

/** `t()` narrowed to the interpolating overload the toasts use. */
export type Translate = (key: string, named?: Record<string, unknown>) => string

/** The message an error toast should show; callers hand in whatever a rejected promise carried. */
export const errorMessage = (error: unknown): string => {
  if (typeof error === 'string') return error || 'Unknown error'
  const message = (error as { message?: unknown } | null | undefined)?.message
  return typeof message === 'string' && message ? message : 'Unknown error'
}

/** One place for `toast.error(t(key, { error }))`, so every error toast reads the same. */
export const toastError = (t: Translate, key: string, error: unknown): void => {
  toast.error(t(key, { error: errorMessage(error) }))
}
