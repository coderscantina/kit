import { computed, ref, watch, type Ref } from 'vue'
import { useRoute, useRouter, type LocationQueryRaw } from 'vue-router'

export type SortDirection = 'asc' | 'desc'

export interface TableSort {
  column: string
  direction: SortDirection
}

export interface TableQueryStateOptions {
  /** Sort the list carries before the URL says otherwise. */
  defaultSort: TableSort
  defaultPerPage?: number
  /** Namespace for the query params, so two tables can share a page. */
  prefix?: string
  /** How long typing settles before the URL and the request follow. */
  searchDebounce?: number
}

const DEFAULT_PER_PAGE = 20
const DEFAULT_SEARCH_DEBOUNCE = 300

export interface TableQueryState {
  page: Ref<number>
  perPage: Ref<number>
  sort: Ref<TableSort>
  /** Bound to the input; the URL and `params` follow after the debounce. */
  search: Ref<string>
  /** Everything the request needs, settled. Use it as the query key. */
  params: Ref<{ page: number; perPage: number; sort: TableSort; search: string }>
  reset: () => void
}

/**
 * Keeps page, per-page, sort and search in the URL rather than in component
 * refs, so a list view can be linked, reloaded and walked back through with
 * the browser's own buttons. The URL is the state: every getter reads
 * `route.query`, so a back navigation needs no extra wiring.
 *
 * Values that match their default are left out of the URL, which keeps the
 * common case a clean address.
 */
export function useTableQueryState(options: TableQueryStateOptions): TableQueryState {
  const route = useRoute()
  const router = useRouter()

  const defaultPerPage = options.defaultPerPage ?? DEFAULT_PER_PAGE
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
   * `push`, not `replace`: paging and sorting are navigations the user expects
   * Back to undo. Search is debounced before it gets here, so typing leaves
   * one history entry, not one per keystroke.
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

  const sort = computed<TableSort>({
    get: () => ({
      column: readParam('sort') ?? options.defaultSort.column,
      direction: (readParam('direction') as SortDirection | null) ?? options.defaultSort.direction,
    }),
    set: (value) =>
      write({
        sort: value.column === options.defaultSort.column ? null : value.column,
        direction: value.direction === options.defaultSort.direction ? null : value.direction,
        page: null,
      }),
  })

  const search = ref(readParam('search') ?? '')

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
      () => write({ search: value || null, page: null }),
      options.searchDebounce ?? DEFAULT_SEARCH_DEBOUNCE
    )
  })

  // Back and forward move the URL without touching the input, so the input
  // has to follow it back.
  watch(
    () => readParam('search'),
    (value) => {
      if ((value ?? '') === search.value) return

      syncingFromUrl = true
      search.value = value ?? ''
    }
  )

  const params = computed(() => ({
    page: page.value,
    perPage: perPage.value,
    sort: sort.value,
    search: readParam('search') ?? '',
  }))

  const reset = () => {
    search.value = ''
    write({ page: null, perPage: null, sort: null, direction: null, search: null })
  }

  return { page, perPage, sort, search, params, reset }
}
