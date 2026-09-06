import {
  computed,
  shallowReactive,
  shallowRef,
  toValue,
  watch,
  type ComputedRef,
  type MaybeRefOrGetter,
  type Ref,
  type ShallowRef,
} from 'vue'

export interface ReactiveFormOptions<T extends object, K extends keyof T> {
  /** The properties the form edits. Everything else on the row is context. */
  fields: readonly K[]
  /**
   * What tells one row from another. A row with a different identity
   * replaces the form outright, edits included; the same identity is an
   * update and merges. Default: `row.id`.
   */
  identity?: (row: T) => unknown
  /**
   * Whether two values count as the same, which is what decides dirty.
   * The default treats `7` and `"7"` alike, and null, undefined and the
   * empty string alike, because that is what an input hands back.
   */
  equals?: (a: unknown, b: unknown) => boolean
}

/** A field the user edited whose server value moved since. */
export interface FieldConflict<T, K extends keyof T> {
  field: K
  /** What the server holds now. The user's own value is still in `fields`. */
  theirs: T[K]
}

export interface ReactiveForm<T extends object, K extends keyof T> {
  /** The editable values; bind them with `v-model="form.fields.name"`. */
  fields: Pick<T, K>
  /** The last row the server gave, `version` included. Null before the first. */
  base: Readonly<Ref<T | null>>
  /** Fields whose value differs from `base`. */
  dirty: ComputedRef<K[]>
  isDirty: ComputedRef<boolean>
  /** Dirty fields the server moved under the user. Empty means a save cannot overwrite anyone. */
  conflicts: ComputedRef<Array<FieldConflict<T, K>>>
  /**
   * Take a row in. A push, a mutation result and a 409's `current` all go
   * through here: clean fields take the server's value, dirty fields keep
   * the user's and note what the server has.
   */
  apply(row: T): void
  /** Give up the user's value for the server's. */
  accept(field: K): void
  /** Drop every edit. */
  reset(): void
  /** The editable values as a plain object, for a mutation payload. */
  values(): Pick<T, K>
}

const blank = (value: unknown): boolean => value === null || value === undefined || value === ''

const scalar = (value: unknown): value is string | number =>
  typeof value === 'string' || typeof value === 'number'

const sameValue = (a: unknown, b: unknown): boolean => {
  if (a === b) return true
  if (blank(a) && blank(b)) return true
  if (scalar(a) && scalar(b)) return String(a) === String(b)
  return false
}

const byId = (row: object): unknown => (row as { id?: unknown }).id

/**
 * A form bound to a live row.
 *
 * `source` is whatever holds the row: a `useReactiveQuery` result, a row
 * picked out of a list, a prop. Every time it changes the form takes the
 * new values into the fields the user has not touched and leaves the ones
 * he has, so a colleague's save lands in an open form without erasing
 * anything typed into it. `conflicts` says where the two met.
 *
 * ```ts
 * const form = useReactiveForm(() => card.value, { fields: ['name', 'notes'] })
 * update.mutate({ id: form.base.value.id, version: form.base.value.version, ...form.values() }, {
 *   onConflict: (error) => form.apply(error.current),
 * })
 * ```
 *
 * The same `apply()` serves a 409: the payload's `current` is a row like
 * any other, so after it the form holds the merge and the next save carries
 * the current version.
 */
export function useReactiveForm<T extends object, K extends keyof T>(
  source: MaybeRefOrGetter<T | null | undefined>,
  options: ReactiveFormOptions<T, K>
): ReactiveForm<T, K> {
  const { fields: keys, identity = byId, equals = sameValue } = options
  const base = shallowRef(null) as ShallowRef<T | null>
  // Shallow on purpose: a field holds a value, and v-model replaces it.
  const fields = shallowReactive({} as Pick<T, K>)
  /** Server values that arrived while the field was dirty. */
  const moved = shallowReactive(new Map<K, T[K]>())

  const isDirty = (key: K): boolean => base.value !== null && !equals(fields[key], base.value[key])

  const apply = (row: T): void => {
    const previous = base.value
    const fresh = previous === null || identity(previous) !== identity(row)

    for (const key of keys) {
      if (fresh || !isDirty(key)) {
        fields[key] = row[key]
        moved.delete(key)
        continue
      }

      // Dirty under the user. Theirs unchanged means the edit simply stands.
      if (equals(row[key], previous[key])) continue

      if (equals(row[key], fields[key])) moved.delete(key)
      else moved.set(key, row[key])
    }

    base.value = row
  }

  watch(
    () => toValue(source),
    (row) => row && apply(row),
    { immediate: true, deep: true }
  )

  const dirty = computed(() => keys.filter((key) => isDirty(key)))

  return {
    fields,
    base,
    dirty,
    isDirty: computed(() => dirty.value.length > 0),
    conflicts: computed(() =>
      keys
        .filter((key) => moved.has(key) && isDirty(key))
        .map((key) => ({ field: key, theirs: moved.get(key) as T[K] }))
    ),
    apply,
    accept: (key) => {
      if (base.value) fields[key] = base.value[key]
      moved.delete(key)
    },
    reset: () => {
      if (base.value) for (const key of keys) fields[key] = base.value[key]
      moved.clear()
    },
    values: () => Object.fromEntries(keys.map((key) => [key, fields[key]])) as Pick<T, K>,
  }
}
