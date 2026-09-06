import type { QueryKey } from '@tanstack/vue-query'

/** One entry of the generated ReactiveMap: the args a query or mutation takes and what it returns. */
export interface ReactiveDefinition {
  args: unknown
  result: unknown
}

export type ReactiveMapLike = Record<string, ReactiveDefinition>

export interface SubscribeResponse<TResult = unknown> {
  subscriptionId: string
  result: TResult
  mutationId: number
}

export interface MutateResponse<TResult = unknown> {
  result: TResult
  mutationId: number
}

export interface QueryResponse<TResult = unknown> {
  result: TResult
  mutationId: number
}

/** The /rq/* endpoints (§4.5), behind an interface so tests can fake them. */
export interface ReactiveTransport {
  subscribe(query: string, args: unknown): Promise<SubscribeResponse>
  unsubscribe(subscriptionId: string): Promise<void>
  mutate(mutation: string, args: unknown): Promise<MutateResponse>
  query(query: string, args: unknown): Promise<QueryResponse>
}

/** `ResultChanged` (§4.6): `result` is absent when it did not fit under the inline threshold. */
export interface ResultChangedPayload {
  subscriptionId: string
  mutationId: number
  hash: string
  result?: unknown
}

export interface SubscriptionRevokedPayload {
  subscriptionId: string
}

/** The slice of a laravel-echo private channel the package uses. */
export interface EchoChannelLike {
  listen(event: string, callback: (payload: never) => void): unknown
  stopListening(event: string): unknown
}

export interface EchoConnectionLike {
  state: string
  bind(
    event: 'state_change',
    callback: (states: { previous: string; current: string }) => void
  ): void
  unbind(
    event: 'state_change',
    callback: (states: { previous: string; current: string }) => void
  ): void
}

/**
 * The slice of a laravel-echo presence channel the app uses. `here` fires
 * once with the roster, `joining` and `leaving` per change, and the whisper
 * pair carries client events between members without touching the server.
 */
export interface EchoPresenceChannelLike<TMember = unknown> {
  here(callback: (members: TMember[]) => void): this
  joining(callback: (member: TMember) => void): this
  leaving(callback: (member: TMember) => void): this
  error(callback: (error: unknown) => void): this
  whisper(event: string, payload: unknown): this
  listenForWhisper(event: string, callback: (payload: never) => void): this
  /** Without a callback laravel-echo drops every listener for the event. */
  stopListeningForWhisper(event: string, callback?: (payload: never) => void): this
}

/**
 * The slice of laravel-echo the package uses. Null when realtime is off.
 *
 * `join` is optional: the reactive layer itself never calls it, and the
 * fakes in the tests would all have to grow a presence channel they do not
 * use. A caller that wants presence checks for it.
 */
export interface EchoLike {
  private(name: string): EchoChannelLike
  join?(name: string): EchoPresenceChannelLike
  leave(name: string): void
  connector: { pusher?: { connection: EchoConnectionLike } }
}

export type ConnectionState = 'connected' | 'connecting' | 'disconnected' | 'unavailable' | 'none'

export type ReactiveQueryKey = readonly ['rq', string, unknown]

export const reactiveQueryKey = (name: string, args: unknown): ReactiveQueryKey => [
  'rq',
  name,
  args,
]

export type { QueryKey }
