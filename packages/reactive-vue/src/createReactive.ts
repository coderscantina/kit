import {
  hashKey,
  keepPreviousData,
  useMutation,
  useQuery,
  useQueryClient,
  type QueryClient,
  type UseMutationReturnType,
  type UseQueryReturnType,
} from '@tanstack/vue-query'
import {
  computed,
  getCurrentScope,
  onScopeDispose,
  toValue,
  watch,
  type MaybeRefOrGetter,
  type Ref,
} from 'vue'

import { createConnectionMonitor, type ConnectionMonitor } from './connection'
import { ConflictError, ForbiddenError } from './errors'
import {
  applyOptimistic,
  applyPush,
  createReconcileState,
  currentValue,
  expire,
  hasPending,
  markCommitted,
  markFailed,
  type ReconcileState,
  type Updater,
} from './reconcile'
import { SubscriptionManager } from './subscriptions'
import {
  reactiveQueryKey,
  type ConnectionState,
  type EchoLike,
  type MutateResponse,
  type ReactiveMapLike,
  type ReactiveTransport,
  type ResultChangedPayload,
  type SubscriptionRevokedPayload,
} from './types'

/** Pending optimistic entries older than this are dropped and their keys refetched. */
const PENDING_MAX_AGE_MS = 10_000

const PENDING_SWEEP_MS = 2_000

/** Polling cadence for a query whose socket is down. */
const OFFLINE_REFETCH_MS = 30_000

/** No usable socket: either realtime is off (`none`) or the connection dropped. */
const isOffline = (state: ConnectionState): boolean =>
  state === 'none' || state === 'disconnected' || state === 'unavailable'

export interface ReactiveOptions {
  transport: ReactiveTransport
  echo: EchoLike | null
  /** Hooks for the app's toasts; the package knows nothing about i18n. */
  onMutationSuccess?: (name: string) => void
  onMutationError?: (name: string, error: Error) => void
}

export interface ReactiveQueryOptions {
  enabled?: MaybeRefOrGetter<boolean>
  /**
   * Keep showing the previous args' result while the next loads. On for
   * lists, off by default: a stale entity in an editor is dangerous.
   */
  list?: boolean
}

export interface ReactiveCache<M extends ReactiveMapLike> {
  patch<K extends keyof M & string>(
    target: [K, M[K]['args']],
    updater: (current: M[K]['result']) => M[K]['result']
  ): void
}

export interface ReactiveMutationOptions<M extends ReactiveMapLike, K extends keyof M & string> {
  optimistic?: (cache: ReactiveCache<M>, args: M[K]['args']) => void
  onSuccess?: (result: M[K]['result'], args: M[K]['args']) => void
  onError?: (error: Error, args: M[K]['args']) => void
  /**
   * A 409: the row moved and nothing was written. `error.current` is the
   * row now, typed as this mutation's result. When set, a conflict does
   * not reach `onError` or the app's error toast: the caller is resolving
   * it, not reporting it.
   */
  onConflict?: (error: ConflictError<M[K]['result']>, args: M[K]['args']) => void
}

export type ReactiveQueryReturn<TResult> = UseQueryReturnType<TResult, Error> & {
  connectionState: Readonly<Ref<ConnectionState>>
}

/** `data` is `{ result, mutationId }`: the id is what reconciliation keys on. */
export type ReactiveMutationReturn<TResult, TArgs> = UseMutationReturnType<
  MutateResponse<TResult>,
  Error,
  TArgs,
  number
>

export interface Reactive<M extends ReactiveMapLike> {
  useReactiveQuery<K extends keyof M & string>(
    name: K,
    args?: MaybeRefOrGetter<M[K]['args']>,
    options?: ReactiveQueryOptions
  ): ReactiveQueryReturn<M[K]['result']>
  useReactiveMutation<K extends keyof M & string>(
    name: K,
    options?: ReactiveMutationOptions<M, K>
  ): ReactiveMutationReturn<M[K]['result'], M[K]['args']>
  connection: ConnectionMonitor
  /** Drop every subscription (logout). */
  reset(): void
  /** @internal for tests */
  readonly state: ReconcileState
}

/**
 * Builds the typed composables once per app. `M` is the generated
 * ReactiveMap, so `name` is a literal union and args/result infer.
 */
export function createReactive<M extends ReactiveMapLike>(options: ReactiveOptions): Reactive<M> {
  const { transport, echo } = options
  const manager = new SubscriptionManager(transport, echo)
  const connection = createConnectionMonitor(echo)
  const state = createReconcileState()
  let pendingSequence = 0
  let sweeper: ReturnType<typeof setInterval> | undefined
  let client: QueryClient | undefined

  const keyOf = (name: string, args: unknown): string => hashKey(reactiveQueryKey(name, args))

  const writeCache = (key: string, value: unknown) => {
    const active = manager.get(key)
    if (active && client) {
      client.setQueryData(reactiveQueryKey(active.name, active.args), value)
    }
  }

  const refetchKey = (key: string) => {
    const active = manager.get(key)
    if (active && client) {
      void client.invalidateQueries({ queryKey: reactiveQueryKey(active.name, active.args) })
    }
  }

  const rewriteKeys = (keys: string[]) => {
    for (const key of keys) {
      const current = currentValue(state, key)
      if (current) {
        writeCache(key, current.value)
      } else {
        refetchKey(key)
      }
    }
  }

  const ensureSweeper = () => {
    if (sweeper !== undefined) {
      return
    }
    sweeper = setInterval(() => {
      rewriteKeys(expire(state, Date.now(), PENDING_MAX_AGE_MS))
      if (!hasPending(state) && sweeper !== undefined) {
        clearInterval(sweeper)
        sweeper = undefined
      }
    }, PENDING_SWEEP_MS)
  }

  const onPush = async (key: string, payload: ResultChangedPayload) => {
    const active = manager.get(key)

    if (!active || active.id !== payload.subscriptionId) {
      return
    }

    // Out of order: the server emits pushes in increasing id order, but a
    // fallback fetch can overtake an inline push that was still in flight.
    if (payload.mutationId < active.mutationId) {
      return
    }

    let result = payload.result

    if (!('result' in payload)) {
      const response = await transport.query(active.name, active.args)
      result = response.result
      if (manager.get(key)?.id !== active.id) {
        return
      }
    }

    active.mutationId = payload.mutationId
    writeCache(key, applyPush(state, key, result, payload.mutationId))
  }

  const onRevoked = (key: string, payload: SubscriptionRevokedPayload) => {
    const active = manager.get(key)

    if (!active || active.id !== payload.subscriptionId || !client) {
      return
    }

    manager.release(key)
    client
      .getQueryCache()
      .find({ queryKey: reactiveQueryKey(active.name, active.args) })
      ?.setState({ status: 'error', error: new ForbiddenError(), fetchStatus: 'idle' })
  }

  // Re-subscribe every mounted query after a recovered connection: pushes
  // during the gap are gone, and the server may have expired the ids.
  connection.onReconnected(() => {
    for (const key of manager.keys()) {
      refetchKey(key)
    }
  })

  const useReactiveQuery = <K extends keyof M & string>(
    name: K,
    args?: MaybeRefOrGetter<M[K]['args']>,
    queryOptions: ReactiveQueryOptions = {}
  ): ReactiveQueryReturn<M[K]['result']> => {
    client = useQueryClient()
    const resolvedArgs = computed(() => toValue(args) ?? {})
    const queryKey = computed(() => reactiveQueryKey(name, resolvedArgs.value))
    const keyHash = computed(() => hashKey(queryKey.value))

    manager.retain(keyHash.value)

    // Capture the previous hash: leaving by the reactive value after it
    // changed would leak the old subscription.
    watch(keyHash, (next, previous) => {
      manager.dispose(previous)
      manager.retain(next)
    })

    if (getCurrentScope()) {
      onScopeDispose(() => manager.dispose(keyHash.value))
    }

    const query = useQuery<M[K]['result'], Error>({
      queryKey,
      queryFn: async () => {
        const key = keyHash.value
        const argsValue = resolvedArgs.value

        // Without a socket a subscription can never be pushed to, and a poll
        // while one is down must not pile up server subscriptions either.
        if (echo === null || (isOffline(connection.state.value) && manager.has(key))) {
          const response = await transport.query(name, argsValue)
          return applyPush(state, key, response.result, response.mutationId) as M[K]['result']
        }

        const response = await transport.subscribe(name, argsValue)
        const active = manager.register(key, {
          id: response.subscriptionId,
          name,
          args: argsValue,
          mutationId: response.mutationId,
        })

        active.channel?.listen('.ResultChanged', (payload: ResultChangedPayload) => {
          void onPush(key, payload)
        })
        active.channel?.listen('.SubscriptionRevoked', (payload: SubscriptionRevokedPayload) =>
          onRevoked(key, payload)
        )

        return applyPush(state, key, response.result, response.mutationId) as M[K]['result']
      },
      enabled: computed(() => toValue(queryOptions.enabled) ?? true),
      placeholderData: queryOptions.list ? keepPreviousData : undefined,
      refetchInterval: () => (isOffline(connection.state.value) ? OFFLINE_REFETCH_MS : false),
    })

    return Object.assign(query, { connectionState: connection.state })
  }

  const useReactiveMutation = <K extends keyof M & string>(
    name: K,
    mutationOptions: ReactiveMutationOptions<M, K> = {}
  ): ReactiveMutationReturn<M[K]['result'], M[K]['args']> => {
    client = useQueryClient()

    return useMutation<MutateResponse<M[K]['result']>, Error, M[K]['args'], number>({
      mutationFn: (args) => transport.mutate(name, args) as Promise<MutateResponse<M[K]['result']>>,
      onMutate: (args) => {
        const pendingId = ++pendingSequence

        const cache: ReactiveCache<M> = {
          patch: (target, updater) => {
            const key = keyOf(target[0], target[1])
            const queryKey = reactiveQueryKey(target[0], target[1])
            const current = client?.getQueryData(queryKey)
            const next = applyOptimistic(
              state,
              pendingId,
              key,
              updater as Updater,
              current,
              Date.now()
            )
            client?.setQueryData(queryKey, next)
            ensureSweeper()
          },
        }

        mutationOptions.optimistic?.(cache, args)

        return pendingId
      },
      onSuccess: (response, args, pendingId) => {
        if (pendingId !== undefined) {
          rewriteKeys(markCommitted(state, pendingId, response.mutationId))
        }
        mutationOptions.onSuccess?.(response.result, args)
        options.onMutationSuccess?.(name)
      },
      onError: (error, args, pendingId) => {
        if (pendingId !== undefined) {
          for (const key of markFailed(state, pendingId)) {
            const current = currentValue(state, key)
            if (current) {
              writeCache(key, current.value)
            }
            refetchKey(key)
          }
        }
        if (error instanceof ConflictError && mutationOptions.onConflict) {
          mutationOptions.onConflict(error as ConflictError<M[K]['result']>, args)
          return
        }
        mutationOptions.onError?.(error, args)
        options.onMutationError?.(name, error)
      },
    })
  }

  return {
    useReactiveQuery,
    useReactiveMutation,
    connection,
    reset: () => manager.releaseAll(),
    state,
  }
}
