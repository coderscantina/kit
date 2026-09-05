import { computed, ref, shallowRef } from 'vue'

import { api } from '~/api'
import { getXsrfHeaders } from '~/lib/csrf'
import { consumeSseStream, parseStreamErrorResponse, type SseCallbacks } from '~/lib/sse'

const ENDPOINT = '/api/ai/stream'

/** Every action the server registered, from `Kit.AiMap`. A typo is a type error. */
export type AiActionName = keyof Kit.AiMap

export type AiActionArgs<TName extends AiActionName> = Kit.AiMap[TName]['args']

export interface AiStreamOptions extends SseCallbacks {
  /** Reset the answer when a new run starts. Off keeps the previous text visible until the first token. */
  clearOnRun?: boolean
}

/**
 * Streams one named AI action.
 *
 * The action is bound at creation, so `args` is typed to that action's Data
 * class and the result of renaming an action server-side is a failing
 * typecheck rather than a 404 in production.
 *
 * One stream per instance: calling `run()` again aborts the one in flight,
 * because two answers writing into one `content` is never what was meant.
 * Create a second instance for a second concurrent stream.
 *
 *     const assistant = useAiStream('assistant.ask')
 *     await assistant.run({ question })
 *
 * Never fetch `/api/ai/stream` directly; the CSRF handshake, the abort
 * handling and the terminal-event rules all live here.
 */
export function useAiStream<TName extends AiActionName>(
  action: TName,
  options: AiStreamOptions = {}
) {
  const content = ref('')
  const status = ref('')
  const error = ref<string | null>(null)
  const reason = ref<string | null>(null)
  const controller = shallowRef<AbortController | null>(null)

  const isStreaming = computed(() => controller.value !== null)

  const fail = (message: string, failureReason?: string): void => {
    error.value = message
    reason.value = failureReason ?? null
    status.value = ''
    options.onError?.(message, failureReason)
  }

  const run = async (args: AiActionArgs<TName>): Promise<string> => {
    cancel()

    if (options.clearOnRun !== false) content.value = ''
    status.value = ''
    error.value = null
    reason.value = null

    await api.client.ensureCsrfCookie()

    const xsrf = getXsrfHeaders()

    if (Object.keys(xsrf).length === 0) {
      fail('Your session could not be verified. Reload the page and try again.', 'csrf')
      return content.value
    }

    const abort = new AbortController()
    controller.value = abort

    try {
      const response = await fetch(api.client.resolveUrl(ENDPOINT), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'text/event-stream',
          'X-Requested-With': 'XMLHttpRequest',
          ...xsrf,
        },
        credentials: 'include',
        signal: abort.signal,
        body: JSON.stringify({ action, args }),
      })

      if (!response.ok) {
        const failure = await parseStreamErrorResponse(response)
        fail(failure.message, failure.reason)
        return content.value
      }

      const reader = response.body?.getReader()

      if (!reader) {
        fail('The server sent no stream to read.', 'no_body')
        return content.value
      }

      await consumeSseStream(reader, {
        onStatus: (message) => {
          status.value = message
          options.onStatus?.(message)
        },
        onDelta: (delta) => {
          content.value += delta
          options.onDelta?.(delta)
        },
        onDone: (full, data) => {
          // The server sends the whole answer again at the end, so a dropped
          // delta does not leave a hole in the text.
          if (full) content.value = full
          status.value = ''
          options.onDone?.(content.value, data)
        },
        onError: fail,
      })
    } catch (thrown: unknown) {
      // An abort is a decision, not a failure: `cancel()` already told the
      // caller, and the partial answer stays on screen.
      if (thrown instanceof DOMException && thrown.name === 'AbortError') return content.value

      fail(thrown instanceof Error ? thrown.message : 'The AI request failed.')
    } finally {
      if (controller.value === abort) controller.value = null
    }

    return content.value
  }

  const cancel = (): void => {
    controller.value?.abort()
    controller.value = null
    status.value = ''
  }

  return { content, status, error, reason, isStreaming, run, cancel }
}
