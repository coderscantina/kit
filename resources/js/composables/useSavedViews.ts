import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { computed, type ComputedRef, type Ref } from 'vue'

import { api } from '~/api'
import type { TableQueryState } from '~/composables/useTableQueryState'
import { queryKeys } from '~/lib/query-keys'

export interface UseSavedViewsOptions {
  /** The list this view belongs to, e.g. `people`. Views never cross a scope. */
  scope: string
  table: TableQueryState
}

export interface UseSavedViews {
  views: ComputedRef<App.Data.SavedViewData[]>
  isLoading: Ref<boolean>
  /** The view whose parameters match what is on screen right now, if any. */
  current: ComputedRef<App.Data.SavedViewData | null>
  save: (name: string, isDefault?: boolean) => Promise<App.Data.SavedViewData>
  remove: (id: string) => Promise<void>
  setDefault: (id: string, isDefault: boolean) => Promise<App.Data.SavedViewData>
  apply: (view: App.Data.SavedViewData) => void
  isSaving: Ref<boolean>
}

/** Same bag, same order-independent comparison: what makes a view "current". */
const sameParams = (a: Record<string, string>, b: Record<string, string>): boolean => {
  const keys = Object.keys(a)

  return keys.length === Object.keys(b).length && keys.every((key) => a[key] === b[key])
}

/**
 * Named list states for one table: save what is on screen, put it back later.
 *
 * The stored parameters are the table's own URL bag, so applying a view is a
 * navigation — the list reloads through the normal query path and the address
 * bar still describes what is shown.
 */
export function useSavedViews({ scope, table }: UseSavedViewsOptions): UseSavedViews {
  const queryClient = useQueryClient()
  const key = queryKeys.savedViews(scope)

  const query = useQuery({
    queryKey: key,
    queryFn: () => api.views.index(scope),
    staleTime: 60_000,
  })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: key })

  const saveMutation = useMutation({
    mutationFn: (variables: { name: string; isDefault: boolean }) =>
      api.views.store({
        scope,
        name: variables.name,
        params: table.snapshot.value,
        isDefault: variables.isDefault,
      }),
    onSuccess: invalidate,
  })

  const removeMutation = useMutation({
    mutationFn: (id: string) => api.views.destroy(id),
    onSuccess: invalidate,
  })

  const defaultMutation = useMutation({
    mutationFn: (variables: { id: string; isDefault: boolean }) =>
      api.views.update(variables.id, { isDefault: variables.isDefault }),
    onSuccess: invalidate,
  })

  const views = computed(() => query.data.value ?? [])

  const current = computed(
    () => views.value.find((view) => sameParams(view.params, table.snapshot.value)) ?? null
  )

  return {
    views,
    isLoading: query.isPending,
    current,
    isSaving: saveMutation.isPending,
    save: (name, isDefault = false) => saveMutation.mutateAsync({ name, isDefault }),
    remove: (id) => removeMutation.mutateAsync(id),
    setDefault: (id, isDefault) => defaultMutation.mutateAsync({ id, isDefault }),
    apply: (view) => table.apply(view.params),
  }
}
