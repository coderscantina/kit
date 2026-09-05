import { onScopeDispose } from 'vue'

/**
 * Hover-intent prefetching: runs `prefetch(payload)` after the pointer (or
 * focus) has rested for `delay` ms, cancels if it leaves early, and dedupes
 * successful prefetches per payload.
 */
export function useHoverPrefetch<TPayload>(
  prefetch: (payload: TPayload) => void | Promise<unknown>,
  delay = 150
) {
  const timers = new Map<TPayload, ReturnType<typeof setTimeout>>()
  const done = new Set<TPayload>()

  const start = (payload: TPayload): void => {
    if (done.has(payload) || timers.has(payload)) return

    timers.set(
      payload,
      setTimeout(() => {
        timers.delete(payload)
        done.add(payload)
        // A failed prefetch never surfaces; the real query fetches on demand.
        try {
          void Promise.resolve(prefetch(payload)).catch(() => done.delete(payload))
        } catch {
          done.delete(payload)
        }
      }, delay)
    )
  }

  const cancel = (payload?: TPayload): void => {
    if (payload === undefined) {
      timers.forEach((timer) => clearTimeout(timer))
      timers.clear()
      return
    }

    const timer = timers.get(payload)
    if (timer !== undefined) {
      clearTimeout(timer)
      timers.delete(payload)
    }
  }

  onScopeDispose(() => {
    cancel()
    done.clear()
  })

  return { start, cancel }
}
