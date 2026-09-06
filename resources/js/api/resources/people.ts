import { BaseResource } from '~/api/resources/base-resource'
import type { Paginated } from '~/types/api'

/** All, active accounts only, or outstanding invitations only. */
export type PeopleSegment = 'all' | 'active' | 'pending'

export interface PeopleListQuery {
  page?: number
  perPage?: number
  search?: string
  /** Role key, e.g. `admin`. Empty means every role. */
  role?: string
  status?: PeopleSegment
  /** One of name, email or created_at; anything else falls back to name. */
  sort?: string
  direction?: 'asc' | 'desc'
}

/** The tab counts. They ignore the segment, so switching tabs does not move them. */
export interface PeopleCounts {
  active: number
  pending: number
  total: number
}

export type PeoplePage = Paginated<App.Data.PersonData> & { counts: PeopleCounts }

export class PeopleResource extends BaseResource {
  protected basePath = '/api/people'

  index(query: PeopleListQuery = {}): Promise<PeoplePage> {
    return this.client.get(this.basePath, {
      page: query.page ?? 1,
      per_page: query.perPage,
      search: query.search || undefined,
      role: query.role || undefined,
      status: query.status === 'all' ? undefined : query.status,
      sort: query.sort,
      direction: query.direction,
    })
  }
}
