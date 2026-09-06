import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, nextTick, ref } from 'vue'

import { createReactive, type Reactive } from '../src/createReactive'
import { ConflictError, ForbiddenError } from '../src/errors'
import type {
  EchoLike,
  ReactiveTransport,
  ResultChangedPayload,
  SubscriptionRevokedPayload,
} from '../src/types'

type TestMap = {
  'notes.list': { args: { ownerId: string }; result: string[] }
  'notes.create': { args: { title: string }; result: { title: string } }
}

type Listener = (payload: never) => void

const createFakeEcho = () => {
  const channels = new Map<string, Map<string, Listener>>()
  const left: string[] = []
  const echo: EchoLike = {
    private: (name) => {
      const listeners = channels.get(name) ?? new Map<string, Listener>()
      channels.set(name, listeners)
      return {
        listen: (event, callback) => listeners.set(event, callback),
        stopListening: (event) => listeners.delete(event),
      }
    },
    leave: (name) => {
      left.push(name)
      channels.delete(name)
    },
    connector: { pusher: undefined },
  }
  const push = (
    subscriptionId: string,
    payload: ResultChangedPayload | SubscriptionRevokedPayload,
    event = '.ResultChanged'
  ) => {
    const listener = channels.get(`subscription.${subscriptionId}`)?.get(event)
    listener?.(payload as never)
  }
  return { echo, push, left, channels }
}

const createFakeTransport = () => {
  let ids = 0
  let mutationId = 10
  const results = ref<string[]>(['a'])
  const calls: string[] = []
  const transport: ReactiveTransport = {
    subscribe: vi.fn(async (query) => {
      calls.push(`subscribe:${query}`)
      return { subscriptionId: `s${++ids}`, result: [...results.value], mutationId }
    }),
    unsubscribe: vi.fn(async (id) => {
      calls.push(`unsubscribe:${id}`)
    }),
    mutate: vi.fn(async (_mutation, args) => {
      calls.push('mutate')
      mutationId += 1
      results.value = [...results.value, (args as { title: string }).title]
      return { result: args as { title: string }, mutationId }
    }),
    query: vi.fn(async () => {
      calls.push('query')
      return { result: [...results.value], mutationId }
    }),
  }
  return { transport, calls, results, nextMutationId: () => mutationId + 1 }
}

let wrapper: ReturnType<typeof mount> | undefined

afterEach(() => {
  wrapper?.unmount()
  wrapper = undefined
  vi.useRealTimers()
})

const mountWith = <T>(reactive: Reactive<TestMap>, setup: () => T) => {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false, gcTime: Infinity } },
  })
  let result: T | undefined
  wrapper = mount(
    defineComponent({
      setup() {
        result = setup()
        return () => h('div')
      },
    }),
    { global: { plugins: [[VueQueryPlugin, { queryClient }]] } }
  )
  return { result: result as T, queryClient }
}

describe('useReactiveQuery', () => {
  it('subscribes, joins the channel and applies an inline push', async () => {
    const { echo, push } = createFakeEcho()
    const { transport } = createFakeTransport()
    const reactive = createReactive<TestMap>({ transport, echo })

    const { result } = mountWith(reactive, () =>
      reactive.useReactiveQuery('notes.list', { ownerId: 'u' })
    )
    await flushPromises()

    expect(result.data.value).toEqual(['a'])
    expect(result.connectionState.value).toBe('none')

    push('s1', { subscriptionId: 's1', mutationId: 11, hash: 'h', result: ['a', 'b'] })
    await flushPromises()

    expect(result.data.value).toEqual(['a', 'b'])
  })

  it('fetches through /rq/query when a push carries no result', async () => {
    const { echo, push } = createFakeEcho()
    const { transport, results, calls } = createFakeTransport()
    const reactive = createReactive<TestMap>({ transport, echo })

    const { result } = mountWith(reactive, () =>
      reactive.useReactiveQuery('notes.list', { ownerId: 'u' })
    )
    await flushPromises()

    results.value = ['a', 'big']
    push('s1', { subscriptionId: 's1', mutationId: 11, hash: 'h' })
    await flushPromises()

    expect(calls).toContain('query')
    expect(result.data.value).toEqual(['a', 'big'])
  })

  it('discards a push older than what the key already has', async () => {
    const { echo, push } = createFakeEcho()
    const { transport } = createFakeTransport()
    const reactive = createReactive<TestMap>({ transport, echo })

    const { result } = mountWith(reactive, () =>
      reactive.useReactiveQuery('notes.list', { ownerId: 'u' })
    )
    await flushPromises()

    push('s1', { subscriptionId: 's1', mutationId: 12, hash: 'h', result: ['newer'] })
    push('s1', { subscriptionId: 's1', mutationId: 11, hash: 'h', result: ['older'] })
    await flushPromises()

    expect(result.data.value).toEqual(['newer'])
  })

  it('leaves the old channel by its own name when the args change', async () => {
    const { echo, left } = createFakeEcho()
    const { transport, calls } = createFakeTransport()
    const reactive = createReactive<TestMap>({ transport, echo })
    const owner = ref('u1')

    mountWith(reactive, () =>
      reactive.useReactiveQuery('notes.list', () => ({ ownerId: owner.value }))
    )
    await flushPromises()

    owner.value = 'u2'
    await nextTick()
    await flushPromises()

    expect(left).toEqual(['subscription.s1'])
    expect(calls).toContain('unsubscribe:s1')
    expect(calls.filter((call) => call.startsWith('subscribe'))).toHaveLength(2)
  })

  it('polls through /rq/query without subscribing when realtime is off', async () => {
    const { transport, calls } = createFakeTransport()
    const reactive = createReactive<TestMap>({ transport, echo: null })

    const { result } = mountWith(reactive, () =>
      reactive.useReactiveQuery('notes.list', { ownerId: 'u' })
    )
    await flushPromises()

    expect(result.data.value).toEqual(['a'])
    expect(transport.subscribe).not.toHaveBeenCalled()
    expect(calls).toEqual(['query'])
  })

  it('unsubscribes on unmount and puts a revoked subscription into a forbidden error state', async () => {
    const { echo, push, left } = createFakeEcho()
    const { transport, calls } = createFakeTransport()
    const reactive = createReactive<TestMap>({ transport, echo })

    const { result } = mountWith(reactive, () =>
      reactive.useReactiveQuery('notes.list', { ownerId: 'u' })
    )
    await flushPromises()

    push('s1', { subscriptionId: 's1' }, '.SubscriptionRevoked')
    await flushPromises()

    expect(result.error.value).toBeInstanceOf(ForbiddenError)
    expect(left).toEqual(['subscription.s1'])

    wrapper?.unmount()
    wrapper = undefined
    await flushPromises()

    // Already released by the revoke; no second unsubscribe.
    expect(calls.filter((call) => call === 'unsubscribe:s1')).toHaveLength(1)
  })
})

describe('useReactiveMutation', () => {
  it('applies the optimistic patch, keeps it across an unrelated push, and settles on commit', async () => {
    const { echo, push } = createFakeEcho()
    const { transport, nextMutationId } = createFakeTransport()
    const reactive = createReactive<TestMap>({ transport, echo })

    const { result } = mountWith(reactive, () => ({
      list: reactive.useReactiveQuery('notes.list', { ownerId: 'u' }),
      create: reactive.useReactiveMutation('notes.create', {
        optimistic: (cache, args) =>
          cache.patch(['notes.list', { ownerId: 'u' }], (list) => [...list, args.title]),
      }),
    }))
    await flushPromises()

    let resolve: ((value: unknown) => void) | undefined
    const gate = new Promise((r) => (resolve = r))
    const expectedId = nextMutationId()
    ;(transport.mutate as ReturnType<typeof vi.fn>).mockImplementationOnce(async () => {
      await gate
      return { result: { title: 'new' }, mutationId: expectedId }
    })

    const promise = result.create.mutateAsync({ title: 'new' })
    await flushPromises()
    expect(result.list.data.value).toEqual(['a', 'new'])

    // A push from someone else's commit, before ours resolves: no flicker.
    push('s1', {
      subscriptionId: 's1',
      mutationId: expectedId - 1,
      hash: 'h',
      result: ['a', 'other'],
    })
    await flushPromises()
    expect(result.list.data.value).toEqual(['a', 'other', 'new'])

    resolve?.(undefined)
    const response = await promise
    expect(response.mutationId).toBe(expectedId)

    // Our own push arrives: the temp item is replaced by the real row.
    push('s1', {
      subscriptionId: 's1',
      mutationId: expectedId,
      hash: 'h',
      result: ['a', 'other', 'new'],
    })
    await flushPromises()
    expect(result.list.data.value).toEqual(['a', 'other', 'new'])
  })

  it('rolls back on failure and reports the error to the app hook', async () => {
    const { echo } = createFakeEcho()
    const { transport } = createFakeTransport()
    const onMutationError = vi.fn()
    const reactive = createReactive<TestMap>({ transport, echo, onMutationError })

    const { result } = mountWith(reactive, () => ({
      list: reactive.useReactiveQuery('notes.list', { ownerId: 'u' }),
      create: reactive.useReactiveMutation('notes.create', {
        optimistic: (cache, args) =>
          cache.patch(['notes.list', { ownerId: 'u' }], (list) => [...list, args.title]),
      }),
    }))
    await flushPromises()
    ;(transport.mutate as ReturnType<typeof vi.fn>).mockImplementationOnce(async () => {
      throw new Error('nope')
    })

    await result.create.mutateAsync({ title: 'doomed' }).catch(() => undefined)
    await flushPromises()

    expect(result.list.data.value).toEqual(['a'])
    expect(onMutationError).toHaveBeenCalledWith('notes.create', expect.any(Error))
  })

  it('hands a 409 to onConflict with the current row, and not to the error toast', async () => {
    const { echo } = createFakeEcho()
    const { transport } = createFakeTransport()
    const onMutationError = vi.fn()
    const onConflict = vi.fn()
    const onError = vi.fn()
    const reactive = createReactive<TestMap>({ transport, echo, onMutationError })

    const { result } = mountWith(reactive, () => ({
      list: reactive.useReactiveQuery('notes.list', { ownerId: 'u' }),
      create: reactive.useReactiveMutation('notes.create', {
        optimistic: (cache, args) =>
          cache.patch(['notes.list', { ownerId: 'u' }], (list) => [...list, args.title]),
        onConflict,
        onError,
      }),
    }))
    await flushPromises()
    ;(transport.mutate as ReturnType<typeof vi.fn>).mockImplementationOnce(async () => {
      throw new ConflictError('moved', 1, 2, { title: 'theirs' })
    })

    await result.create.mutateAsync({ title: 'mine' }).catch(() => undefined)
    await flushPromises()

    // The optimistic patch is rolled back: nothing was written.
    expect(result.list.data.value).toEqual(['a'])
    expect(onConflict).toHaveBeenCalledWith(
      expect.objectContaining({ current: { title: 'theirs' }, actual: 2 }),
      { title: 'mine' }
    )
    expect(onError).not.toHaveBeenCalled()
    expect(onMutationError).not.toHaveBeenCalled()
  })
})
