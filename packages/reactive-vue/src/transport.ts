import { normalizeError } from './errors'
import type { MutateResponse, QueryResponse, ReactiveTransport, SubscribeResponse } from './types'

/** The one method the package needs from the app's ApiClient. */
export interface HttpPoster {
  post<T>(endpoint: string, body?: unknown): Promise<T>
}

/**
 * Wires the /rq/* endpoints onto the app's fetch wrapper, so CSRF, the
 * 419 retry, the socket id header and the 401 handler all apply.
 */
export const createHttpTransport = (http: HttpPoster, prefix = '/rq'): ReactiveTransport => {
  const post = async <T>(path: string, body: unknown): Promise<T> => {
    try {
      return await http.post<T>(`${prefix}${path}`, body)
    } catch (error) {
      throw normalizeError(error)
    }
  }

  return {
    subscribe: (query, args) => post<SubscribeResponse>('/subscribe', { query, args }),
    unsubscribe: async (subscriptionId) => {
      await post<void>('/unsubscribe', { subscriptionId })
    },
    mutate: (mutation, args) => post<MutateResponse>('/mutate', { mutation, args }),
    query: (query, args) => post<QueryResponse>('/query', { query, args }),
  }
}
