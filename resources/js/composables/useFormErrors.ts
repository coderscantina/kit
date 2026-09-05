import { ref } from 'vue'

import { errorMessage } from '~/lib/toast-error'
import { isHttpError } from '~/types/api'

/** Field errors from a 422 plus a general message for everything else. */
export function useFormErrors() {
  const fields = ref<Record<string, string>>({})
  const message = ref<string | null>(null)

  const capture = (error: unknown): void => {
    fields.value = {}
    message.value = null

    if (isHttpError(error) && error.status === 422 && error.data?.errors) {
      for (const [field, messages] of Object.entries(error.data.errors)) {
        fields.value[field] = messages[0] ?? ''
      }
      return
    }

    message.value = errorMessage(error)
  }

  const clear = (): void => {
    fields.value = {}
    message.value = null
  }

  return { fields, message, capture, clear }
}
