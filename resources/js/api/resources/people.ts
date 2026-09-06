import type { DownloadedFile } from '~/api/client'
import { BaseResource } from '~/api/resources/base-resource'
import type { ExportFormat } from '~/components/ui/data-table'
import type { Paginated } from '~/types/api'

/** All, active accounts only, or outstanding invitations only. */
export type PeopleSegment = 'all' | 'active' | 'pending'

/**
 * The query bag `useTableQueryState` produces: `page`, `per_page`, `sort`
 * (`+column` / `-column`), the free-text `q`, and whatever filter chips are
 * on. It is passed through rather than re-mapped here, so adding a filter to
 * the server needs no edit in this file.
 */
export type PeopleListQuery = Record<string, string | number>

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
    return this.client.get(this.basePath, query)
  }

  /**
   * The whole filtered list as a file. `page` and `per_page` are dropped: an
   * export is of the result set, not of the page someone happens to be on.
   */
  export(query: PeopleListQuery, format: ExportFormat): Promise<DownloadedFile> {
    const { page: _page, per_page: _perPage, ...rest } = query

    return this.client.download(`${this.basePath}/export`, { ...rest, format }, `people.${format}`)
  }
}
