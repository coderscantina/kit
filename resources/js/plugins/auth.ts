import { api } from '~/api'
import { useAuth } from '~/composables/useAuth'

/**
 * Teaches the API client what a 401 means. Installed before the router
 * mounts: the first navigation guard already hits the API.
 */
export function installAuthHandler(): void {
  const auth = useAuth()

  api.client.setAuthHandler({
    handleUnauthorized: (endpoint) => auth.handleUnauthorized(endpoint),
  })
}
