/**
 * Reading a Server-Sent-Events body. Kept apart from the AI composable so
 * the parsing is testable without a network, a component or a model.
 */

export interface SseEvent {
  type: string
  message?: string
  content?: string
  reason?: string
  data?: Record<string, unknown>
}

export interface SseCallbacks {
  /** Progress the user may see and should not keep: a tool running, a model thinking. */
  onStatus?: (message: string) => void
  onDelta?: (content: string) => void
  onDone?: (content: string, data?: Record<string, unknown>) => void
  onError?: (message: string, reason?: string) => void
}

/**
 * Turn a failed response into a message plus the server's machine `reason`.
 * These are the failures that happen before the stream opens: 403, 422, 419,
 * or an unconfigured provider, all of which arrive as ordinary JSON.
 */
export async function parseStreamErrorResponse(
  response: Response
): Promise<{ message: string; reason?: string }> {
  if (response.status === 419) {
    return { message: 'Your session expired. Reload the page and try again.', reason: 'csrf' }
  }

  const body = await response.text().catch(() => '')
  let message = `HTTP ${response.status}`
  let reason: string | undefined

  try {
    const parsed = JSON.parse(body) as { message?: unknown; reason?: unknown }
    if (typeof parsed.message === 'string' && parsed.message) message = parsed.message
    if (typeof parsed.reason === 'string') reason = parsed.reason
  } catch {
    if (body) message = body.slice(0, 500)
  }

  return { message, reason }
}

/** `data: {...}` → the event. Anything else on the line is not ours. */
export function parseSseEvent(line: string): SseEvent | null {
  if (!line.startsWith('data:')) return null

  try {
    const parsed: unknown = JSON.parse(line.slice(5).trimStart())
    // An object with a string `type`, or there is nothing to dispatch on: a
    // bare array or a number parses fine and routes nowhere.
    if (!parsed || typeof parsed !== 'object') return null
    const event = parsed as SseEvent
    return typeof event.type === 'string' ? event : null
  } catch {
    return null
  }
}

/** Returns true when the event ends the stream. */
export function dispatchSseEvent(event: SseEvent, callbacks: SseCallbacks): boolean {
  switch (event.type) {
    case 'status':
      callbacks.onStatus?.(event.message ?? '')
      return false
    case 'delta':
      callbacks.onDelta?.(event.content ?? '')
      return false
    case 'done':
      callbacks.onDone?.(event.content ?? '', event.data)
      return true
    case 'error':
      callbacks.onError?.(event.message ?? 'Unknown error', event.reason)
      return true
    default:
      return false
  }
}

const dispatchLines = (lines: string[], callbacks: SseCallbacks): boolean =>
  lines.some((line) => {
    const event = parseSseEvent(line)
    return event ? dispatchSseEvent(event, callbacks) : false
  })

/**
 * Drain a response body into callbacks.
 *
 * A chunk ends wherever the network put it, so the tail of one is held back
 * until the next arrives. Reading stops at the first terminal event: a
 * second `done` must not re-apply its result, and nothing written after one
 * is ours.
 */
export async function consumeSseStream(
  reader: ReadableStreamDefaultReader<Uint8Array>,
  callbacks: SseCallbacks
): Promise<void> {
  const decoder = new TextDecoder()
  let buffer = ''
  let finished = false

  try {
    while (!finished) {
      const { done, value } = await reader.read()

      if (done) {
        if (buffer.trim()) finished = dispatchLines(buffer.split('\n'), callbacks)
        break
      }

      buffer += decoder.decode(value, { stream: true })
      const lines = buffer.split('\n')
      buffer = lines.pop() ?? ''

      finished = dispatchLines(lines, callbacks)
    }
  } finally {
    // Release on every path, including an abort: a locked body is not
    // collected until the reader is.
    void reader.cancel().catch(() => {})
    reader.releaseLock()
  }

  if (!finished) {
    callbacks.onError?.('The stream ended before the answer did.', 'incomplete')
  }
}
