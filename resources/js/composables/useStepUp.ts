import { ref } from 'vue'

import { errorCode } from '~/types/api'

/**
 * Runs a request; when the server answers 423 with
 * PASSWORD_CONFIRMATION_REQUIRED, opens the confirm dialog and retries once
 * the password was accepted.
 */
export function useStepUp() {
  const confirmOpen = ref(false)
  let retry: (() => Promise<void>) | null = null

  const run = async <T>(request: () => Promise<T>): Promise<T | undefined> => {
    try {
      return await request()
    } catch (error) {
      if (errorCode(error) !== 'PASSWORD_CONFIRMATION_REQUIRED') throw error

      return await new Promise<T | undefined>((resolve, reject) => {
        retry = async () => {
          try {
            resolve(await request())
          } catch (retryError) {
            reject(retryError)
          }
        }
        confirmOpen.value = true
      })
    }
  }

  const onConfirmed = async (): Promise<void> => {
    const pending = retry
    retry = null
    await pending?.()
  }

  return { confirmOpen, run, onConfirmed }
}
