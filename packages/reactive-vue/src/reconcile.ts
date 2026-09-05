/**
 * Mutation-id reconciliation (§5.3), as pure functions over a plain state
 * object so the math is testable without Vue or TanStack.
 *
 * Per query key we remember the last server result and the mutationId it
 * carried. Pending mutations hold optimistic patches keyed by query key. The
 * cache value for a key is always: last server result, then every pending
 * patch on it whose mutation is not yet known to be included in that result.
 */

export type Updater = (current: unknown) => unknown

export interface PendingPatch {
  key: string
  updater: Updater
}

export interface PendingMutation {
  pendingId: number
  committedId?: number
  patches: PendingPatch[]
  startedAt: number
}

export interface ServerResult {
  result: unknown
  mutationId: number
}

export interface ReconcileState {
  pending: PendingMutation[]
  base: Map<string, ServerResult>
}

export const createReconcileState = (): ReconcileState => ({ pending: [], base: new Map() })

/** Whether a patch from this mutation still belongs on top of a result carrying `mutationId`. */
const stillPending = (mutation: PendingMutation, mutationId: number): boolean =>
  mutation.committedId === undefined || mutation.committedId > mutationId

const applyPatches = (
  state: ReconcileState,
  key: string,
  base: unknown,
  mutationId: number
): unknown =>
  state.pending
    .filter((mutation) => stillPending(mutation, mutationId))
    .flatMap((mutation) => mutation.patches.filter((patch) => patch.key === key))
    .reduce((value, patch) => patch.updater(value), base)

const dropEmpty = (state: ReconcileState): void => {
  state.pending = state.pending.filter((mutation) => mutation.patches.length > 0)
}

/** Step 1: a mutation starts. Returns the value to put in the cache. */
export const applyOptimistic = (
  state: ReconcileState,
  pendingId: number,
  key: string,
  updater: Updater,
  current: unknown,
  now: number
): unknown => {
  let mutation = state.pending.find((entry) => entry.pendingId === pendingId)

  if (!mutation) {
    mutation = { pendingId, patches: [], startedAt: now }
    state.pending.push(mutation)
  }

  mutation.patches.push({ key, updater })

  return updater(current)
}

/** Step 2: a push arrives. Returns the value to put in the cache. */
export const applyPush = (
  state: ReconcileState,
  key: string,
  result: unknown,
  mutationId: number
): unknown => {
  state.base.set(key, { result, mutationId })

  for (const mutation of state.pending) {
    if (!stillPending(mutation, mutationId)) {
      mutation.patches = mutation.patches.filter((patch) => patch.key !== key)
    }
  }
  dropEmpty(state)

  return applyPatches(state, key, result, mutationId)
}

/**
 * Step 3: the mutation resolved with `committedId`. Keys whose last result
 * already carries a mutationId >= committedId drop the patch now; the others
 * drop it when their next push arrives. Returns the keys to rewrite.
 */
export const markCommitted = (
  state: ReconcileState,
  pendingId: number,
  committedId: number
): string[] => {
  const mutation = state.pending.find((entry) => entry.pendingId === pendingId)

  if (!mutation) {
    return []
  }

  mutation.committedId = committedId

  const settled = mutation.patches
    .map((patch) => patch.key)
    .filter((key) => (state.base.get(key)?.mutationId ?? -1) >= committedId)

  mutation.patches = mutation.patches.filter((patch) => !settled.includes(key(patch)))
  dropEmpty(state)

  return [...new Set(settled)]
}

const key = (patch: PendingPatch): string => patch.key

/** Step 4: the mutation failed. Returns the keys whose patches were rolled back. */
export const markFailed = (state: ReconcileState, pendingId: number): string[] => {
  const mutation = state.pending.find((entry) => entry.pendingId === pendingId)

  if (!mutation) {
    return []
  }

  state.pending = state.pending.filter((entry) => entry !== mutation)

  return [...new Set(mutation.patches.map(key))]
}

/** Step 5: the safety valve. Drops entries older than `maxAge` and returns their keys. */
export const expire = (state: ReconcileState, now: number, maxAge: number): string[] => {
  const stale = state.pending.filter((mutation) => now - mutation.startedAt > maxAge)

  if (stale.length === 0) {
    return []
  }

  state.pending = state.pending.filter((mutation) => !stale.includes(mutation))

  return [...new Set(stale.flatMap((mutation) => mutation.patches.map(key)))]
}

/**
 * The value a key should show right now: its last server result with the
 * remaining patches on top. Undefined when no server result was seen yet,
 * which means the caller has to refetch rather than guess.
 */
export const currentValue = (
  state: ReconcileState,
  key: string
): { value: unknown } | undefined => {
  const base = state.base.get(key)

  if (!base) {
    return undefined
  }

  return { value: applyPatches(state, key, base.result, base.mutationId) }
}

export const hasPending = (state: ReconcileState): boolean => state.pending.length > 0
