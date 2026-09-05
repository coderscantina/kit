import { describe, expect, it, vi } from 'vitest'

vi.mock('vue-sonner', () => ({ toast: { error: vi.fn() } }))

import { toast } from 'vue-sonner'

import { errorMessage, toastError } from '~/lib/toast-error'
import { useI18n } from '~/plugins/i18n'

describe('errorMessage', () => {
  it('reads strings, errors and objects, with a fallback', () => {
    expect(errorMessage('boom')).toBe('boom')
    expect(errorMessage(new Error('bad'))).toBe('bad')
    expect(errorMessage({ message: '' })).toBe('Unknown error')
    expect(errorMessage(null)).toBe('Unknown error')
  })
})

describe('toastError', () => {
  it('interpolates the error into the translated message', () => {
    const { t } = useI18n()

    toastError(t, 'users.error', new Error('nope'))

    expect(toast.error).toHaveBeenCalledWith('Something went wrong: nope')
  })
})
