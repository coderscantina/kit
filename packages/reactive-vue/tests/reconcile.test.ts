import { describe, expect, it } from 'vitest'

import {
  applyOptimistic,
  applyPush,
  createReconcileState,
  currentValue,
  expire,
  hasPending,
  markCommitted,
  markFailed,
} from '../src/reconcile'

const K = 'key:list'

const append = (item: string) => (current: unknown) => [...((current as string[]) ?? []), item]

describe('reconcile', () => {
  it('re-applies pending patches on top of a push that does not include them', () => {
    const state = createReconcileState()
    applyPush(state, K, ['a'], 5)

    const optimistic = applyOptimistic(state, 1, K, append('temp'), ['a'], 0)
    expect(optimistic).toEqual(['a', 'temp'])

    // A push for an unrelated commit: the temp item must survive.
    expect(applyPush(state, K, ['a', 'x'], 6)).toEqual(['a', 'x', 'temp'])
  })

  it('drops the patch once a push carries a mutation id at or beyond the commit', () => {
    const state = createReconcileState()
    applyPush(state, K, ['a'], 5)
    applyOptimistic(state, 1, K, append('temp'), ['a'], 0)

    expect(markCommitted(state, 1, 7)).toEqual([])
    // Push with id 6 predates commit 7: still pending.
    expect(applyPush(state, K, ['a', 'b'], 6)).toEqual(['a', 'b', 'temp'])
    // Push with id 7 includes the commit: the real row replaces the temp one.
    expect(applyPush(state, K, ['a', 'b', 'real'], 7)).toEqual(['a', 'b', 'real'])
    expect(hasPending(state)).toBe(false)
  })

  it('settles immediately when the key already saw a newer push than the commit', () => {
    const state = createReconcileState()
    applyPush(state, K, [], 1)
    applyOptimistic(state, 1, K, append('temp'), [], 0)

    // The push overtook the HTTP response (both carry the same commit).
    expect(applyPush(state, K, ['real'], 9)).toEqual(['real', 'temp'])

    expect(markCommitted(state, 1, 9)).toEqual([K])
    expect(currentValue(state, K)).toEqual({ value: ['real'] })
  })

  it('rolls back a failed mutation and leaves other pending mutations alone', () => {
    const state = createReconcileState()
    applyPush(state, K, [], 1)
    applyOptimistic(state, 1, K, append('one'), [], 0)
    applyOptimistic(state, 2, K, append('two'), ['one'], 0)

    expect(markFailed(state, 1)).toEqual([K])
    expect(currentValue(state, K)).toEqual({ value: ['two'] })
    expect(markFailed(state, 99)).toEqual([])
  })

  it('expires entries older than the safety valve and reports their keys', () => {
    const state = createReconcileState()
    applyOptimistic(state, 1, K, append('stale'), [], 0)
    applyOptimistic(state, 2, 'other', append('fresh'), [], 9_000)

    expect(expire(state, 10_500, 10_000)).toEqual([K])
    expect(expire(state, 10_500, 10_000)).toEqual([])
    expect(hasPending(state)).toBe(true)
  })

  it('has no current value for a key that never saw a server result', () => {
    const state = createReconcileState()
    applyOptimistic(state, 1, K, append('x'), undefined, 0)

    expect(currentValue(state, K)).toBeUndefined()
  })
})
