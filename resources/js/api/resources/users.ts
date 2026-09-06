import { BaseResource } from '~/api/resources/base-resource'
import type { Paginated } from '~/types/api'

/**
 * The query bag `useTableQueryState` produces: `page`, `per_page`, `sort`
 * (`+column` / `-column`), the free-text `q`, and whatever filter chips are
 * on. It is passed through rather than re-mapped here.
 */
export type UserListQuery = Record<string, string | number>

export class UsersResource extends BaseResource {
  protected basePath = '/api/users'

  index(query: UserListQuery = {}): Promise<Paginated<App.Data.UserData>> {
    return this.client.get(this.basePath, query)
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
