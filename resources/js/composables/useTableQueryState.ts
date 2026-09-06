import { computed, ref, watch, type ComputedRef, type Ref } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'

export type SortDirection = 'asc' | 'desc'

export interface TableSort {
  column: string
  direction: SortDirection
}

/** Everything the request needs, settled. Also the query key. */
export interface TableQueryParams extends Record<string, string | number> {
  page: number
  per_page: number
  /** `+column` or `-column`, the form the backend filters read. */
  sort: string
}

export interface TableQueryStateOptions {
  /** Sort the list carries before the URL says otherwise. */
  defaultSort: TableSort
  defaultPerPage?: number
  /** Namespace for the query params, so two tables can share a page. */
  prefix?: string
  /**
   * The filter parameters this table understands. Only these are read from
   * and written to the URL, so an unrelated query param on the same page is
   * left alone and a stale one from another view never reaches the API.
   */
  filterKeys?: readonly string[]
  /** How long typing settles before the URL and the request follow. */
  searchDebounce?: number
}

export interface TableQueryState {
  page: Ref<number>
  perPage: Ref<number>
  sort: Ref<TableSort>
  /** Bound to the input; the URL and `params` follow after the debounce. */
  search: Ref<string>
  /** The filter chips, as `field -> operator:value`. Written straight to the URL. */
  filters: Ref<Record<string, string>>
  /** The sort as the backend reads it: `+name`, `-created_at`. */
  sortParam: ComputedRef<string>
  /** Everything the request needs, settled. Use it as the query key. */
  params: ComputedRef<TableQueryParams>
  /** Whether anything narrows the list right now. Drives the empty state's copy. */
  isFiltered: ComputedRef<boolean>
  /** The current state as a flat bag, which is what a saved view stores. */
  snapshot: ComputedRef<Record<string, string>>
  /** Put a saved view back. Replaces the table's params and leaves the rest of the URL alone. */
  apply: (snapshot: Record<string, string>) => void
  reset: () => void
}

const DEFAULT_PER_PAGE = 20
const DEFAULT_SEARCH_DEBOUNCE = 300

/** The free-text term. One name across every list, and the backend's too. */
const SEARCH_KEY = 'q'

/**
 * Keeps page, per-page, sort, search and filters in the URL rather than in
 * component refs, so a list view can be linked, reloaded and walked back
 * through with the browser's own buttons. The URL is the state: every getter
 * reads `route.query`, so a back navigation needs no extra wiring.
 *
 * Values that match their default are left out of the URL, which keeps the
 * common case a clean address and makes a saved view small.
 *
 * The sort travels as one `+column` / `-column` parameter, which is what
 * `App\Http\Filters` reads on the other end; there is no second `direction`
 * parameter to keep in step.
 */
export function useTableQueryState(options: TableQueryStateOptions): TableQueryState {
  const route = useRoute()
  const router = useRouter()

  const defaultPerPage = options.defaultPerPage ?? DEFAULT_PER_PAGE
  const filterKeys = options.filterKeys ?? []
  const key = (name: string) => (options.prefix ? `${options.prefix}_${name}` : name)

  const readParam = (name: string): string | null => {
    const value = route.query[key(name)]
    const first = Array.isArray(value) ? value[0] : value

    return typeof first === 'string' && first.length > 0 ? first : null
  }

  const readNumber = (name: string, fallback: number): number => {
    const parsed = Number(readParam(name))

    return Number.isInteger(parsed) && parsed > 0 ? parsed : fallback
  }

  /**
   * `push`, not `replace`: paging, sorting and filtering are navigations the
   * user expects Back to undo. Search is debounced before it gets here, so
   * typing leaves one history entry, not one per keystroke.
   */
  const write = (patch: Record<string, string | number | null>) => {
    const query: LocationQueryRaw = { ...route.query }

    for (const [name, value] of Object.entries(patch)) {
      if (value === null || value === '') delete query[key(name)]
      else query[key(name)] = String(value)
    }

    void router.push({ query })
  }

  const page = computed<number>({
    get: () => readNumber('page', 1),
    set: (value) => write({ page: value > 1 ? value : null }),
  })

  const perPage = computed<number>({
    get: () => readNumber('perPage', defaultPerPage),
    // A bigger page can put the current page past the end, so it starts over.
    set: (value) => write({ perPage: value === defaultPerPage ? null : value, page: null }),
  })

  const encodeSort = (sort: TableSort) => `${sort.direction === 'desc' ? '-' : '+'}${sort.column}`

  const sort = computed<TableSort>({
    get: () => {
      const raw = readParam('sort')
      if (!raw) return options.defaultSort

      const direction: SortDirection = raw.startsWith('-') ? 'desc' : 'asc'

      return { column: raw.replace(/^[+-]/, ''), direction }
    },
    set: (value) =>
      write({
        sort: encodeSort(value) === encodeSort(options.defaultSort) ? null : encodeSort(value),
        page: null,
      }),
  })

  const sortParam = computed(() => encodeSort(sort.value))

  const filters = computed<Record<string, string>>({
    get: () => {
      const current: Record<string, string> = {}

      for (const name of filterKeys) {
        const value = readParam(name)
        if (value !== null) current[name] = value
      }

      return current
    },
    set: (value) => {
      const patch: Record<string, string | null> = { page: null }
      // Every known key is written, not just the ones present: a filter that
      // was removed has to leave the URL, and only naming it does that.
      for (const name of filterKeys) patch[name] = value[name] ?? null

      write(patch)
    },
  })

  const search = ref(readParam(SEARCH_KEY) ?? '')

  let debounce: ReturnType<typeof setTimeout> | null = null
  // A Back navigation writes the input from the URL. Without this the input's
  // own watcher would then write that value straight back and reset the page
  // the user just navigated to.
  let syncingFromUrl = false

  watch(search, (value) => {
    if (syncingFromUrl) {
      syncingFromUrl = false
      return
    }

    if (debounce) clearTimeout(debounce)

    debounce = setTimeout(
      () => write({ [SEARCH_KEY]: value || null, page: null }),
      options.searchDebounce ?? DEFAULT_SEARCH_DEBOUNCE
    )
  })

  // Back and forward move the URL without touching the input, so the input
  // has to follow it back.
  watch(
    () => readParam(SEARCH_KEY),
    (value) => {
      if ((value ?? '') === search.value) return

      syncingFromUrl = true
      search.value = value ?? ''
    }
  )

  const settledSearch = computed(() => readParam(SEARCH_KEY) ?? '')

  const params = computed<TableQueryParams>(() => {
    const query: TableQueryParams = {
      page: page.value,
      per_page: perPage.value,
      sort: sortParam.value,
    }

    if (settledSearch.value) query[SEARCH_KEY] = settledSearch.value
    for (const [name, value] of Object.entries(filters.value)) query[name] = value

    return query
  })

  const isFiltered = computed(
    () => settledSearch.value !== '' || Object.keys(filters.value).length > 0
  )

  const snapshot = computed<Record<string, string>>(() => {
    const state: Record<string, string> = { ...filters.value }

    if (settledSearch.value) state[SEARCH_KEY] = settledSearch.value
    if (sortParam.value !== encodeSort(options.defaultSort)) state.sort = sortParam.value
    if (perPage.value !== defaultPerPage) state.perPage = String(perPage.value)

    return state
  })

  const apply = (state: Record<string, string>) => {
    const patch: Record<string, string | null> = {
      page: null,
      sort: state.sort ?? null,
      perPage: state.perPage ?? null,
      [SEARCH_KEY]: state[SEARCH_KEY] ?? null,
    }

    for (const name of filterKeys) patch[name] = state[name] ?? null

    syncingFromUrl = search.value !== (state[SEARCH_KEY] ?? '')
    search.value = state[SEARCH_KEY] ?? ''

    write(patch)
  }

  const reset = () => apply({})

  return {
    page,
    perPage,
    sort,
    search,
    filters,
    sortParam,
    params,
    isFiltered,
    snapshot,
    apply,
    reset,
  }
}
