import type { ApiClient } from '~/api/client'

export abstract class BaseResource {
  protected abstract basePath: string

  constructor(protected readonly client: ApiClient) {}

  /**
   * Ids reach these methods straight from user data, so a slash must not be
   * able to address a different route.
   */
  protected idPath(id: string, suffix = ''): string {
    return `${this.basePath}/${encodeURIComponent(id)}${suffix}`
  }
}
