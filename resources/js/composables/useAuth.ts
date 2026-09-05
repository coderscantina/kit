import { computed, ref } from 'vue'

import { api } from '~/api'
import { queryKeys } from '~/lib/query-keys'
import { reactive } from '~/lib/reactive'
import { safeReturnPath } from '~/lib/safe-return-path'
import { queryClient } from '~/plugins/vue-query'
import { errorCode, isHttpError } from '~/types/api'

/** Endpoints expected to answer 401 while logged out; a 401 there is not a session teardown. */
const UNAUTHENTICATED_ENDPOINTS = [
  '/auth/csrf-cookie',
  '/auth/login',
  '/auth/register',
  '/auth/logout',
  '/auth/password/',
  '/auth/me',
]

const me = ref<App.Data.MeData | null>(null)
const ready = ref(false)
let initPromise: Promise<void> | null = null
let expiring: Promise<void> | null = null

/** Session state shared by the router guard, the layouts and the API's 401 handler. */
export function useAuth() {
  const isAuthenticated = computed(() => me.value !== null)
  const user = computed(() => me.value?.user ?? null)

  const load = async (): Promise<void> => {
    try {
      me.value = await api.auth.me()
    } catch (error) {
      me.value = null
      if (isHttpError(error) && error.status !== 401) throw error
    }
  }

  /** One /auth/me probe shared by concurrent callers; runs once per page load. */
  const init = async (): Promise<void> => {
    if (ready.value) return
    initPromise ??= load().finally(() => {
      ready.value = true
      initPromise = null
    })
    await initPromise
  }

  const refresh = async (): Promise<void> => {
    await load()
  }

  const clearSession = (): void => {
    me.value = null
    reactive.reset()
    queryClient.cancelQueries()
    queryClient.clear()
  }

  const login = async (
    email: string,
    password: string,
    totpCode?: string,
    remember = false
  ): Promise<void> => {
    await api.auth.login({ email, password, remember }, totpCode)
    await load()
  }

  const logout = async (): Promise<void> => {
    try {
      await api.auth.logout()
    } finally {
      clearSession()
    }
  }

  /**
   * Installed on the API client: a 401 anywhere means the session is gone.
   * Every in-flight request fails at once, so collapse them into one teardown.
   */
  const handleUnauthorized = async (endpoint: string): Promise<void> => {
    if (UNAUTHENTICATED_ENDPOINTS.some((path) => endpoint.includes(path))) return

    expiring ??= (async () => {
      const { router } = await import('~/router')
      clearSession()
      const current = router.currentRoute.value
      if (!current.meta.guest && !current.meta.public) {
        await router.push({ name: 'login', query: { return: current.fullPath } })
      }
    })().finally(() => {
      expiring = null
    })

    await expiring
  }

  const returnPath = (value: unknown): string => safeReturnPath(value)

  return {
    me,
    user,
    isAuthenticated,
    ready,
    init,
    refresh,
    login,
    logout,
    handleUnauthorized,
    returnPath,
    errorCode,
    queryKey: queryKeys.me(),
  }
}
