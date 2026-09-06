import { BaseResource } from '~/api/resources/base-resource'
import type { Paginated } from '~/types/api'

export interface UserListQuery {
  page?: number
  perPage?: number
  search?: string
  /** One of name, email, created_at or last_login_at; anything else falls back to name. */
  sort?: string
  direction?: 'asc' | 'desc'
}

export class UsersResource extends BaseResource {
  protected basePath = '/api/users'

  index(query: UserListQuery = {}): Promise<Paginated<App.Data.UserData>> {
    return this.client.get(this.basePath, {
      page: query.page ?? 1,
      per_page: query.perPage,
      search: query.search || undefined,
      sort: query.sort,
      direction: query.direction,
    })
  }

  roles(): Promise<App.Data.RoleData[]> {
    return this.client.get('/api/roles')
  }

  updateRole(id: string, role: string, totpCode?: string): Promise<App.Data.UserData> {
    return this.client.patch(
      this.idPath(id, '/role'),
      { role },
      { headers: totpCode ? { 'X-TOTP-Code': totpCode } : {} }
    )
  }

  destroy(id: string, totpCode?: string): Promise<void> {
    return this.client.delete(this.idPath(id), {
      headers: totpCode ? { 'X-TOTP-Code': totpCode } : {},
    })
  }
}
